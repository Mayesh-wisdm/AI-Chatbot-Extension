/**
 * AI BotKit Chat Suggestions
 *
 * Handles product and course recommendation cards in the chat interface,
 * including page view tracking, suggestion card rendering, and action handlers.
 *
 * @package AI_BotKit
 * @since   2.0.0
 *
 * Implements: FR-250 (Recommendation Engine Core)
 *             FR-252 (Browsing History Tracking)
 *             FR-255 (Suggestion UI Cards)
 *             FR-256 (Add to Cart Action)
 *             FR-257 (Enroll Now Action)
 */

(function ($) {
    'use strict';

    /**
     * AI BotKit Suggestions Module
     */
    window.AIBotKitSuggestions = {

        /**
         * Configuration
         */
        config: {
            ajaxUrl: (typeof ai_botkitChat !== 'undefined' && ai_botkitChat.ajaxUrl) ? ai_botkitChat.ajaxUrl : '/wp-admin/admin-ajax.php',
            nonce: (typeof ai_botkitChat !== 'undefined' && ai_botkitChat.nonce) ? ai_botkitChat.nonce : '',
            trackNonce: (typeof aiBotKitSuggestions !== 'undefined' && aiBotKitSuggestions.trackNonce) ? aiBotKitSuggestions.trackNonce : '',
            maxSuggestions: 5,
            carouselAutoplay: false,
            carouselInterval: 5000
        },

        /**
         * State
         */
        state: {
            currentRecommendations: [],
            isLoading: false,
            lastConversationText: ''
        },

        /**
         * Initialize the suggestions module
         */
        init: function () {
            this.bindEvents();
            this.trackCurrentPage();
        },

        /**
         * Bind event listeners
         */
        bindEvents: function () {
            var self = this;

            // Track page views on product/course pages
            $(document).ready(function () {
                self.trackCurrentPage();
            });

            // Handle suggestion card clicks
            $(document).on('click', '.ai-botkit-suggestion-card', function (e) {
                // Don't trigger if clicking on a button
                if ($(e.target).closest('.ai-botkit-suggestion-action').length) {
                    return;
                }

                var url = $(this).data('url');
                if (url) {
                    window.open(url, '_blank');
                }
            });

            // Handle Add to Cart button (stopPropagation so widget "click outside" does not close chat)
            $(document).on('click', '.ai-botkit-add-to-cart', function (e) {
                e.preventDefault();
                e.stopPropagation();

                var $btn = $(this);
                var productId = $btn.data('product-id');

                self.addToCart(productId, $btn);
            });

            $(document).on('mousedown', '.ai-botkit-add-to-cart', function (e) {
                e.stopPropagation();
            });

            // Handle View Cart button (after add to cart success) - open in new window
            $(document).on('click', '.ai-botkit-view-cart', function (e) {
                e.preventDefault();
                e.stopPropagation();

                var url = $(this).data('url');
                if (url) {
                    window.open(url, '_blank', 'noopener,noreferrer');
                }
            });

            $(document).on('mousedown', '.ai-botkit-view-cart', function (e) {
                e.stopPropagation();
            });

            // Handle Enroll Now button
            $(document).on('click', '.ai-botkit-enroll-course', function (e) {
                e.preventDefault();
                e.stopPropagation();

                var $btn = $(this);
                var courseId = $btn.data('course-id');

                self.enrollCourse(courseId, $btn);
            });

            // Handle View button (product/course links - open in new window)
            $(document).on('click', '.ai-botkit-view-item', function (e) {
                e.preventDefault();
                e.stopPropagation();

                var url = $(this).data('url');
                if (url) {
                    window.open(url, '_blank', 'noopener,noreferrer');
                }
            });

            // Handle Continue Learning button (open in new window)
            $(document).on('click', '.ai-botkit-continue-learning', function (e) {
                e.preventDefault();
                e.stopPropagation();

                var url = $(this).data('url');
                if (url) {
                    window.open(url, '_blank', 'noopener,noreferrer');
                }
            });

            // Carousel navigation
            $(document).on('click', '.ai-botkit-carousel-prev', function (e) {
                e.preventDefault();
                var $carousel = $(this).closest('.ai-botkit-suggestions-carousel');
                self.navigateCarousel($carousel, 'prev');
            });

            $(document).on('click', '.ai-botkit-carousel-next', function (e) {
                e.preventDefault();
                var $carousel = $(this).closest('.ai-botkit-suggestions-carousel');
                self.navigateCarousel($carousel, 'next');
            });
        },

        /**
         * Track current page view
         */
        trackCurrentPage: function () {
            // Check if we have tracking data from the server
            if (typeof aiBotKitTracker !== 'undefined' && aiBotKitTracker.itemType && aiBotKitTracker.itemId) {
                this.trackPageView(aiBotKitTracker.itemType, aiBotKitTracker.itemId);
            }
        },

        /**
         * Track a page view
         *
         * @param {string} itemType - Type of item (product, course)
         * @param {number} itemId - ID of the item
         * @param {object} metadata - Additional metadata
         */
        trackPageView: function (itemType, itemId, metadata) {
            var self = this;

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_botkit_track_page_view',
                    nonce: this.config.trackNonce || this.config.nonce,
                    item_type: itemType,
                    item_id: itemId,
                    metadata: JSON.stringify(metadata || {})
                },
                success: function () {
                    // Page view tracked successfully
                },
                error: function () {
                    // Silently fail - non-critical tracking
                }
            });
        },

        /**
         * Get recommendations
         *
         * @param {string} conversationText - Recent conversation text
         * @param {function} callback - Callback function with recommendations
         */
        getRecommendations: function (conversationText, callback) {
            var self = this;

            if (this.state.isLoading) {
                console.log('[AI BotKit Suggestions] Already loading recommendations, skipping');
                return;
            }

            this.state.isLoading = true;

            // Get chatbot_id from global chat config (localized as ai_botkitChat with botID)
            var chatbotId = 0;
            var sessionId = '';
            if (typeof ai_botkitChat !== 'undefined') {
                chatbotId = ai_botkitChat.botID || ai_botkitChat.chatbotId || 0;
                sessionId = ai_botkitChat.sessionId || '';
            }

            console.log('[AI BotKit Suggestions] Requesting recommendations:', {
                conversationText: conversationText.substring(0, 100),
                chatbotId: chatbotId,
                limit: this.config.maxSuggestions
            });

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_botkit_get_recommendations',
                    nonce: this.config.nonce,
                    conversation_text: conversationText,
                    chatbot_id: chatbotId,
                    session_id: sessionId,
                    limit: this.config.maxSuggestions
                },
                success: function (response) {
                    self.state.isLoading = false;

                    console.log('[AI BotKit Suggestions] Recommendations response:', response);

                    if (response.success && response.data && response.data.recommendations) {
                        self.state.currentRecommendations = response.data.recommendations;
                        self.state.lastConversationText = conversationText;

                        console.log('[AI BotKit Suggestions] Found', response.data.recommendations.length, 'recommendations');

                        if (typeof callback === 'function') {
                            callback(response.data.recommendations);
                        }
                    } else {
                        console.log('[AI BotKit Suggestions] No recommendations in response');
                        // No recommendations available
                        if (typeof callback === 'function') {
                            callback([]);
                        }
                    }
                },
                error: function (xhr, status, error) {
                    self.state.isLoading = false;
                    console.error('[AI BotKit Suggestions] Error getting recommendations:', {
                        status: status,
                        error: error,
                        responseText: xhr.responseText
                    });
                    // Failed to get recommendations - callback with empty array

                    if (typeof callback === 'function') {
                        callback([]);
                    }
                }
            });
        },

        /**
         * Render suggestion cards
         *
         * @param {array} recommendations - Array of recommendation objects
         * @param {jQuery} $container - Container element to render into
         */
        renderSuggestionCards: function (recommendations, $container) {
            console.log('[AI BotKit Suggestions] renderSuggestionCards called:', {
                recommendationsCount: recommendations ? recommendations.length : 0,
                containerExists: $container && $container.length > 0,
                containerSelector: $container ? $container.selector || 'N/A' : 'N/A'
            });

            if (!recommendations || recommendations.length === 0) {
                console.log('[AI BotKit Suggestions] No recommendations to render');
                return;
            }

            if (!$container || $container.length === 0) {
                console.error('[AI BotKit Suggestions] Container not found');
                return;
            }

            var self = this;
            var html = '<div class="ai-botkit-suggestions-container">';
            html += '<div class="ai-botkit-suggestions-header">';
            html += '<span class="ai-botkit-suggestions-title">' + this.translate('Recommendations for you') + '</span>';
            html += '</div>';

            if (recommendations.length > 3) {
                // Use carousel for more than 3 items
                html += '<div class="ai-botkit-suggestions-carousel">';
                html += '<button class="ai-botkit-carousel-nav ai-botkit-carousel-prev" aria-label="Previous">';
                html += '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"></polyline></svg>';
                html += '</button>';
                html += '<div class="ai-botkit-suggestions-track">';
            } else {
                html += '<div class="ai-botkit-suggestions-grid">';
            }

            recommendations.forEach(function (item, index) {
                html += self.renderSingleCard(item, index);
            });

            if (recommendations.length > 3) {
                html += '</div>'; // track
                html += '<button class="ai-botkit-carousel-nav ai-botkit-carousel-next" aria-label="Next">';
                html += '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"></polyline></svg>';
                html += '</button>';
                html += '</div>'; // carousel
            } else {
                html += '</div>'; // grid
            }

            html += '</div>'; // container

            $container.append(html);
        },

        /**
         * Render a single suggestion card
         *
         * @param {object} item - Recommendation item
         * @param {number} index - Item index
         * @return {string} HTML string
         */
        renderSingleCard: function (item, index) {
            var html = '<div class="ai-botkit-suggestion-card" data-id="' + item.id + '" data-type="' + item.type + '" data-url="' + this.escapeHtml(item.url) + '">';

            // Image
            if (item.image) {
                html += '<div class="ai-botkit-suggestion-image">';
                html += '<img src="' + this.escapeHtml(item.image) + '" alt="' + this.escapeHtml(item.title) + '" loading="lazy">';
                html += '</div>';
            } else {
                html += '<div class="ai-botkit-suggestion-image ai-botkit-suggestion-no-image">';
                html += '<span class="ai-botkit-suggestion-type-badge">' + (item.type === 'product' ? 'Product' : 'Course') + '</span>';
                html += '</div>';
            }

            // Content
            html += '<div class="ai-botkit-suggestion-content">';

            // Title
            html += '<h4 class="ai-botkit-suggestion-title">' + this.escapeHtml(item.title) + '</h4>';

            // Description
            if (item.description) {
                html += '<p class="ai-botkit-suggestion-description">' + this.escapeHtml(item.description) + '</p>';
            }

            // Meta info
            html += '<div class="ai-botkit-suggestion-meta">';

            // Price
            if (item.price) {
                html += '<span class="ai-botkit-suggestion-price">' + item.price + '</span>';
            }

            // Rating
            if (item.rating && parseFloat(item.rating) > 0) {
                html += '<span class="ai-botkit-suggestion-rating">';
                html += this.renderStars(item.rating);
                if (item.review_count) {
                    html += ' <span class="ai-botkit-suggestion-reviews">(' + item.review_count + ')</span>';
                }
                html += '</span>';
            }

            // Progress (for courses)
            if (item.type === 'course' && typeof item.progress !== 'undefined') {
                html += '<span class="ai-botkit-suggestion-progress">' + item.progress + '% complete</span>';
            }

            // Lesson count (for courses)
            if (item.type === 'course' && item.lesson_count) {
                html += '<span class="ai-botkit-suggestion-lessons">' + item.lesson_count + ' lessons</span>';
            }

            // Stock status (for products)
            if (item.type === 'product' && item.stock_status && item.stock_status !== 'instock') {
                html += '<span class="ai-botkit-suggestion-stock ai-botkit-suggestion-stock-' + item.stock_status + '">';
                html += item.stock_status === 'outofstock' ? 'Out of Stock' : 'Low Stock';
                html += '</span>';
            }

            html += '</div>'; // meta

            // Action button
            html += '<div class="ai-botkit-suggestion-action">';
            html += this.renderActionButton(item);
            html += '</div>';

            html += '</div>'; // content
            html += '</div>'; // card

            return html;
        },

        /**
         * Render action button based on item type and state
         *
         * @param {object} item - Recommendation item
         * @return {string} HTML string
         */
        renderActionButton: function (item) {
            var action = item.action || {};
            var type = action.type || 'view';
            var label = action.label || this.translate('View');

            switch (type) {
                case 'add_to_cart':
                    return '<button class="ai-botkit-suggestion-btn ai-botkit-add-to-cart" data-product-id="' + item.id + '">' +
                        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>' +
                        '<span>' + this.escapeHtml(label) + '</span></button>';

                case 'enroll':
                    return '<button class="ai-botkit-suggestion-btn ai-botkit-enroll-course" data-course-id="' + item.id + '">' +
                        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>' +
                        '<span>' + this.escapeHtml(label) + '</span></button>';

                case 'continue':
                    return '<button class="ai-botkit-suggestion-btn ai-botkit-continue-learning" data-url="' + this.escapeHtml(item.url) + '">' +
                        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg>' +
                        '<span>' + this.escapeHtml(label) + '</span></button>';

                case 'view_cart':
                    var cartUrl = action.url || (typeof wc_cart_fragments_params !== 'undefined' ? wc_cart_fragments_params.cart_url : '/cart/');
                    return '<button class="ai-botkit-suggestion-btn ai-botkit-view-cart" data-url="' + this.escapeHtml(cartUrl) + '">' +
                        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>' +
                        '<span>' + this.escapeHtml(label) + '</span></button>';

                case 'view':
                default:
                    return '<button class="ai-botkit-suggestion-btn ai-botkit-view-item" data-url="' + this.escapeHtml(item.url) + '">' +
                        '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>' +
                        '<span>' + this.escapeHtml(label) + '</span></button>';
            }
        },

        /**
         * Render star rating
         *
         * @param {number} rating - Rating value (0-5)
         * @return {string} HTML string
         */
        renderStars: function (rating) {
            var html = '';
            var fullStars = Math.floor(rating);
            var hasHalfStar = rating % 1 >= 0.5;

            for (var i = 0; i < 5; i++) {
                if (i < fullStars) {
                    html += '<svg class="ai-botkit-star ai-botkit-star-full" width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>';
                } else if (i === fullStars && hasHalfStar) {
                    html += '<svg class="ai-botkit-star ai-botkit-star-half" width="12" height="12" viewBox="0 0 24 24"><defs><linearGradient id="half"><stop offset="50%" stop-color="currentColor"/><stop offset="50%" stop-color="transparent"/></linearGradient></defs><polygon fill="url(#half)" stroke="currentColor" stroke-width="1" points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>';
                } else {
                    html += '<svg class="ai-botkit-star ai-botkit-star-empty" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>';
                }
            }

            return html;
        },

        /**
         * Add product to cart
         *
         * @param {number} productId - Product ID
         * @param {jQuery} $btn - Button element
         */
        addToCart: function (productId, $btn) {
            var self = this;
            var originalText = $btn.html();

            // Show loading state
            $btn.prop('disabled', true);
            $btn.html('<span class="ai-botkit-loading"></span>' + this.translate('Adding...'));

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_botkit_add_to_cart',
                    nonce: this.config.nonce,
                    product_id: productId,
                    quantity: 1
                },
                success: function (response) {
                    if (response.success) {
                        if (response.data.redirect) {
                            // Variable product - redirect
                            window.location.href = response.data.url;
                            return;
                        }

                        // Show success
                        $btn.html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg><span>' + self.translate('Added!') + '</span>');
                        $btn.addClass('ai-botkit-btn-success');

                        // Update cart widget if exists
                        if (typeof wc_cart_fragments_params !== 'undefined') {
                            $(document.body).trigger('wc_fragment_refresh');
                        }

                        // Trigger custom event
                        $(document).trigger('ai_botkit_added_to_cart', [productId, response.data]);

                        // Reset button after delay
                        setTimeout(function () {
                            $btn.html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg><span>' + self.translate('View Cart') + '</span>');
                            $btn.removeClass('ai-botkit-add-to-cart ai-botkit-btn-success');
                            $btn.addClass('ai-botkit-view-cart');
                            $btn.attr('data-url', response.data.cart_url);
                            $btn.prop('disabled', false);
                        }, 2000);

                    } else {
                        // Show error
                        self.showButtonError($btn, response.data.message, originalText);
                    }
                },
                error: function (xhr, status, error) {
                    self.showButtonError($btn, self.translate('Failed to add to cart'), originalText);
                }
            });
        },

        /**
         * Enroll in course
         *
         * @param {number} courseId - Course ID
         * @param {jQuery} $btn - Button element
         */
        enrollCourse: function (courseId, $btn) {
            var self = this;
            var originalText = $btn.html();

            // Show loading state
            $btn.prop('disabled', true);
            $btn.html('<span class="ai-botkit-loading"></span>' + this.translate('Enrolling...'));

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_botkit_enroll_course',
                    nonce: this.config.nonce,
                    course_id: courseId
                },
                success: function (response) {
                    if (response.success) {
                        if (response.data.redirect) {
                            // Paid course or needs action - redirect
                            window.location.href = response.data.url;
                            return;
                        }

                        // Show success
                        $btn.html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg><span>' + self.translate('Enrolled!') + '</span>');
                        $btn.addClass('ai-botkit-btn-success');

                        // Trigger custom event
                        $(document).trigger('ai_botkit_enrolled_course', [courseId, response.data]);

                        // Change to Continue Learning after delay
                        setTimeout(function () {
                            $btn.html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"></polygon></svg><span>' + self.translate('Start Learning') + '</span>');
                            $btn.removeClass('ai-botkit-enroll-course ai-botkit-btn-success');
                            $btn.addClass('ai-botkit-continue-learning');
                            $btn.attr('data-url', response.data.course_url);
                            $btn.prop('disabled', false);
                        }, 2000);

                    } else {
                        // Show error
                        self.showButtonError($btn, response.data.message, originalText);
                    }
                },
                error: function (xhr, status, error) {
                    self.showButtonError($btn, self.translate('Failed to enroll'), originalText);
                }
            });
        },

        /**
         * Show error on button and reset
         *
         * @param {jQuery} $btn - Button element
         * @param {string} message - Error message
         * @param {string} originalText - Original button HTML
         */
        showButtonError: function ($btn, message, originalText) {
            $btn.html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg><span>' + this.escapeHtml(message) + '</span>');
            $btn.addClass('ai-botkit-btn-error');

            var self = this;
            setTimeout(function () {
                $btn.html(originalText);
                $btn.removeClass('ai-botkit-btn-error');
                $btn.prop('disabled', false);
            }, 3000);
        },

        /**
         * Navigate carousel
         *
         * @param {jQuery} $carousel - Carousel element
         * @param {string} direction - 'prev' or 'next'
         */
        navigateCarousel: function ($carousel, direction) {
            var $track = $carousel.find('.ai-botkit-suggestions-track');
            var $cards = $track.find('.ai-botkit-suggestion-card');
            var cardWidth = $cards.first().outerWidth(true);
            var visibleCards = Math.floor($carousel.width() / cardWidth) || 1;
            var maxScroll = Math.max(0, $cards.length - visibleCards);
            var currentIndex = parseInt($track.data('index') || 0, 10);

            if (direction === 'prev') {
                currentIndex = Math.max(0, currentIndex - 1);
            } else {
                currentIndex = Math.min(maxScroll, currentIndex + 1);
            }

            $track.data('index', currentIndex);
            $track.css('transform', 'translateX(-' + (currentIndex * cardWidth) + 'px)');

            // Update button states
            $carousel.find('.ai-botkit-carousel-prev').prop('disabled', currentIndex === 0);
            $carousel.find('.ai-botkit-carousel-next').prop('disabled', currentIndex >= maxScroll);
        },

        /**
         * Translate a string
         *
         * @param {string} text - Text to translate
         * @return {string} Translated text
         */
        translate: function (text) {
            // Use WordPress i18n if available (aiBotKitSuggestions is optional localized object)
            if (typeof aiBotKitSuggestions !== 'undefined' && aiBotKitSuggestions.i18n && aiBotKitSuggestions.i18n[text]) {
                return aiBotKitSuggestions.i18n[text];
            }
            return text;
        },

        /**
         * Escape HTML entities
         *
         * @param {string} str - String to escape
         * @return {string} Escaped string
         */
        escapeHtml: function (str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }
    };

    // Initialize on document ready
    $(document).ready(function () {
        AIBotKitSuggestions.init();
    });

    // Also initialize when chat widget opens
    $(document).on('ai_botkit_widget_opened', function () {
        // Re-initialize if needed
    });

})(jQuery);
