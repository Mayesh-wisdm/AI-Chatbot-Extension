/**
 * AI BotKit Browsing Tracker
 *
 * Lightweight script to track page views on product and course pages
 * for the recommendation engine.
 *
 * @package AI_BotKit
 * @since   2.0.0
 *
 * Implements: FR-252 (Browsing History Tracking)
 */

(function() {
    'use strict';

    // Only run if tracking data is available
    if (typeof aiBotKitTracker === 'undefined') {
        return;
    }

    var tracker = aiBotKitTracker;

    // Validate required data
    if (!tracker.ajaxUrl || !tracker.nonce || !tracker.itemType || !tracker.itemId) {
        return;
    }

    /**
     * Track the page view
     */
    function trackPageView() {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', tracker.ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

        var data = 'action=ai_botkit_track_page_view' +
                   '&nonce=' + encodeURIComponent(tracker.nonce) +
                   '&item_type=' + encodeURIComponent(tracker.itemType) +
                   '&item_id=' + encodeURIComponent(tracker.itemId);

        // Add session ID if available
        if (tracker.sessionId) {
            data += '&session_id=' + encodeURIComponent(tracker.sessionId);
        }

        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    // Response parsed successfully, tracking complete
                    JSON.parse(xhr.responseText);
                } catch (e) {
                    // Silently fail on parse error
                }
            }
        };

        xhr.send(data);
    }

    // Track after DOM is loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', trackPageView);
    } else {
        // DOM already loaded
        trackPageView();
    }

})();
