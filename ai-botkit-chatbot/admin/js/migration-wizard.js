/**
 * AI BotKit Migration Wizard JavaScript
 * 
 * Handles the migration wizard modal and AJAX operations
 */

(function($) {
    'use strict';

    class MigrationWizard {
        constructor() {
            this.currentStep = 1;
            this.totalSteps = 5;
            this.migrationOptions = {};
            this.init();
        }

        init() {
            this.bindEvents();
            this.loadMigrationStatus();
        }

        bindEvents() {
            // Migration button click
            $('#ai-botkit-migration-btn').on('click', () => {
                this.openWizard();
            });

            // Refresh status button
            $('#ai-botkit-refresh-status-btn').on('click', () => {
                this.loadMigrationStatus();
            });

            // Clear database buttons
            $('#ai-botkit-clear-local-btn').on('click', () => {
                this.clearDatabase('local');
            });

            $('#ai-botkit-clear-pinecone-btn').on('click', () => {
                this.clearDatabase('pinecone');
            });

            // Modal close
            $('.ai-botkit-modal-close, #ai-botkit-migration-close').on('click', () => {
                this.closeWizard();
            });

            // Modal backdrop click
            $('#ai-botkit-migration-modal').on('click', (e) => {
                if (e.target === e.currentTarget) {
                    this.closeWizard();
                }
            });

            // Navigation buttons
            $('#ai-botkit-migration-next').on('click', () => {
                this.nextStep();
            });

            $('#ai-botkit-migration-prev').on('click', () => {
                this.prevStep();
            });

            $('#ai-botkit-migration-start').on('click', () => {
                this.startMigration();
            });

            // Scope change handler
            $('input[name="migration_scope"]').on('change', () => {
                this.handleScopeChange();
            });

            // Direction change handler
            $('input[name="migration_direction"]').on('change', () => {
                this.handleDirectionChange();
            });
        }

        openWizard() {
            this.currentStep = 1;
            this.migrationOptions = {};
            this.showStep(1);
            $('#ai-botkit-migration-modal').show();
            this.updateNavigationButtons();
        }

        closeWizard() {
            $('#ai-botkit-migration-modal').hide();
            this.resetWizard();
        }

        resetWizard() {
            this.currentStep = 1;
            this.migrationOptions = {};
            $('.ai-botkit-migration-step').hide();
            $('input[type="radio"]').prop('checked', false);
            $('input[type="checkbox"]').prop('checked', false);
            $('input[type="date"]').val('');
            $('#migration-summary-list').empty();
            $('#migration-log').empty();
            $('#migration-progress-fill').css('width', '0%');
            $('#migration-progress-text').text('Starting migration...');
        }

        nextStep() {
            if (this.validateCurrentStep()) {
                this.collectStepData();
                this.currentStep++;
                
                // Skip steps based on scope
                if (this.currentStep === 3 && this.migrationOptions.scope !== 'by_type') {
                    this.currentStep++; // Skip to step 4
                }
                if (this.currentStep === 4 && this.migrationOptions.scope !== 'by_date') {
                    this.currentStep++; // Skip to step 5
                }
                
                this.showStep(this.currentStep);
                this.updateNavigationButtons();
            }
        }

        prevStep() {
            this.currentStep--;
            
            // Skip steps based on scope (in reverse)
            if (this.currentStep === 4 && this.migrationOptions.scope !== 'by_date') {
                this.currentStep--; // Skip to step 3
            }
            if (this.currentStep === 3 && this.migrationOptions.scope !== 'by_type') {
                this.currentStep--; // Skip to step 2
            }
            
            this.showStep(this.currentStep);
            this.updateNavigationButtons();
        }

        showStep(step) {
            $('.ai-botkit-migration-step').hide();
            $(`.ai-botkit-migration-step[data-step="${step}"]`).show();
            
            // Load content types when reaching step 3
            if (step === 3) {
                this.loadContentTypes();
            }
        }

        updateNavigationButtons() {
            const prevBtn = $('#ai-botkit-migration-prev');
            const nextBtn = $('#ai-botkit-migration-next');
            const startBtn = $('#ai-botkit-migration-start');
            const closeBtn = $('#ai-botkit-migration-close');

            // Show/hide previous button
            if (this.currentStep > 1) {
                prevBtn.show();
            } else {
                prevBtn.hide();
            }

            // Show/hide next/start buttons
            if (this.currentStep < this.totalSteps) {
                nextBtn.show();
                startBtn.hide();
                closeBtn.hide();
            } else {
                nextBtn.hide();
                startBtn.show();
                closeBtn.hide();
            }

            // Update next button text
            if (this.currentStep === this.totalSteps - 1) {
                nextBtn.text('Review & Start');
            } else {
                nextBtn.text('Next');
            }
        }

        validateCurrentStep() {
            switch (this.currentStep) {
                case 1:
                    return $('input[name="migration_direction"]:checked').length > 0;
                case 2:
                    return $('input[name="migration_scope"]:checked').length > 0;
                case 3:
                    const scope = $('input[name="migration_scope"]:checked').val();
                    if (scope === 'by_type') {
                        return $('input[name="content_types[]"]:checked').length > 0;
                    }
                    return true;
                case 4:
                    const scope2 = $('input[name="migration_scope"]:checked').val();
                    if (scope2 === 'by_date') {
                        const startDate = $('#migration_date_start').val();
                        const endDate = $('#migration_date_end').val();
                        return startDate && endDate && new Date(startDate) <= new Date(endDate);
                    }
                    return true;
                case 5:
                    return $('#migration_confirm').is(':checked');
                default:
                    return true;
            }
        }

        collectStepData() {
            switch (this.currentStep) {
                case 1:
                    this.migrationOptions.direction = $('input[name="migration_direction"]:checked').val();
                    break;
                case 2:
                    this.migrationOptions.scope = $('input[name="migration_scope"]:checked').val();
                    break;
                case 3:
                    if (this.migrationOptions.scope === 'by_type') {
                        this.migrationOptions.content_types = $('input[name="content_types[]"]:checked').map(function() {
                            return this.value;
                        }).get();
                    }
                    break;
                case 4:
                    if (this.migrationOptions.scope === 'by_date') {
                        this.migrationOptions.date_range = {
                            start: $('#migration_date_start').val(),
                            end: $('#migration_date_end').val()
                        };
                    }
                    break;
            }
        }

        handleScopeChange() {
            const scope = $('input[name="migration_scope"]:checked').val();
            
            if (scope === 'by_type') {
                this.loadContentTypes();
            }
        }

        handleDirectionChange() {
            // Update UI based on direction
            const direction = $('input[name="migration_direction"]:checked').val();
            // Could add direction-specific UI updates here
        }

        loadContentTypes() {
            $.ajax({
                url: aiBotKitMigration.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_botkit_get_content_types',
                    nonce: aiBotKitMigration.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.displayContentTypes(response.data);
                    } else {
                        this.showError('Failed to load content types: ' + response.data);
                    }
                },
                error: () => {
                    this.showError('Failed to load content types');
                }
            });
        }

        displayContentTypes(contentTypes) {
            const container = $('#content-types-selection');
            let html = '<div class="ai-botkit-content-types-list">';
            
            if (Object.keys(contentTypes).length === 0) {
                html += '<p>No content types available for migration.</p>';
            } else {
                for (const [type, info] of Object.entries(contentTypes)) {
                    html += `
                        <div class="ai-botkit-form-group">
                            <label class="ai-botkit-checkbox-label">
                                <input type="checkbox" name="content_types[]" value="${type}">
                                <span class="ai-botkit-checkbox-text">
                                    <strong>${info.name}</strong>
                                    <span class="ai-botkit-count">(${info.count} items)</span>
                                </span>
                            </label>
                        </div>
                    `;
                }
            }
            
            html += '</div>';
            container.html(html);
        }

        startMigration() {
            this.showStep('progress');
            this.updateNavigationButtons();
            
            // Start the migration process
            $.ajax({
                url: aiBotKitMigration.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_botkit_start_migration',
                    nonce: aiBotKitMigration.nonce,
                    options: this.migrationOptions
                },
                success: (response) => {
                    if (response.success) {
                        this.updateProgress(100, 'Migration completed successfully!');
                        this.showMigrationResult(response.data);
                    } else {
                        this.showError('Migration failed: ' + response.data);
                    }
                },
                error: () => {
                    this.showError('Migration failed due to a server error');
                }
            });
        }

        updateProgress(percentage, message) {
            $('#migration-progress-fill').css('width', percentage + '%');
            $('#migration-progress-text').text(message);
        }

        showMigrationResult(result) {
            const log = $('#migration-log');
            log.html(`
                <div class="ai-botkit-migration-result">
                    <h5>Migration Results:</h5>
                    <p><strong>Status:</strong> ${result.success ? 'Success' : 'Failed'}</p>
                    <p><strong>Message:</strong> ${result.message}</p>
                    ${result.migrated_count ? `<p><strong>Items Migrated:</strong> ${result.migrated_count}</p>` : ''}
                    ${result.error_count ? `<p><strong>Errors:</strong> ${result.error_count}</p>` : ''}
                </div>
            `);
            
            // Show close button
            $('#ai-botkit-migration-close').show();
        }

        showError(message) {
            const log = $('#migration-log');
            log.html(`<div class="ai-botkit-error">${message}</div>`);
        }

        loadMigrationStatus() {
            // Show loading state and disable interactions
            this.showLoadingState();
            
            $.ajax({
                url: aiBotKitMigration.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_botkit_get_migration_status',
                    nonce: aiBotKitMigration.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.updateStatusDisplay(response.data);
                    }
                    this.hideLoadingState();
                },
                error: () => {
                    $('#local-db-status').text('Error loading status');
                    $('#pinecone-db-status').text('Error loading status');
                    $('#migration-status').text('Error loading status');
                    $('#last-migration').text('Error loading status');
                    this.hideLoadingState();
                }
            });
        }

        updateStatusDisplay(status) {
            // Update local database status
            const localStatus = status.local_database;
            $('#local-db-status').html(`
                <span class="ai-botkit-status-badge ai-botkit-status-${localStatus.status}">
                    ${localStatus.chunk_count} chunks
                </span>
            `);

            // Update Pinecone database status
            const pineconeStatus = status.pinecone_database;
            let pineconeText = 'Not configured';
            if (pineconeStatus.enabled && pineconeStatus.configured) {
                pineconeText = `${pineconeStatus.chunk_count} chunks`;
            } else if (pineconeStatus.enabled) {
                pineconeText = 'Not configured';
            } else {
                pineconeText = 'Disabled';
            }
            
            $('#pinecone-db-status').html(`
                <span class="ai-botkit-status-badge ai-botkit-status-${pineconeStatus.status}">
                    ${pineconeText}
                </span>
            `);

            // Update migration status
            const migrationInProgress = status.migration_in_progress;
            const migrationStatusText = migrationInProgress ? 'In Progress' : 'Ready';
            const migrationStatusClass = migrationInProgress ? 'warning' : 'success';
            
            $('#migration-status').html(`
                <span class="ai-botkit-status-badge ai-botkit-status-${migrationStatusClass}">
                    ${migrationStatusText}
                </span>
            `);

            // Update last migration time
            const lastMigration = status.last_migration;
            const lastMigrationText = lastMigration ? new Date(lastMigration).toLocaleString() : 'Never';
            
            $('#last-migration').html(`
                <span class="ai-botkit-status-badge ai-botkit-status-info">
                    ${lastMigrationText}
                </span>
            `);

            // Update migration button state
            const migrationBtn = $('#ai-botkit-migration-btn');
            if (status.migration_available) {
                migrationBtn.prop('disabled', false).text('Start Migration');
            } else {
                migrationBtn.prop('disabled', true).text('Migration Not Available');
            }
        }

        showLoadingState() {
            // Add loading overlay to migration section
            const migrationSection = $('.ai-botkit-migration-section');
            migrationSection.addClass('ai-botkit-loading');
            
            // Disable all interactive elements
            migrationSection.find('button').prop('disabled', true);
            
            // Show loading spinner on status display
            $('#migration-status-display').addClass('ai-botkit-loading-content');
            
            // Update status text to show loading
            $('#local-db-status').html('<span class="ai-botkit-loading-spinner"></span> Loading...');
            $('#pinecone-db-status').html('<span class="ai-botkit-loading-spinner"></span> Loading...');
            $('#migration-status').html('<span class="ai-botkit-loading-spinner"></span> Loading...');
            $('#last-migration').html('<span class="ai-botkit-loading-spinner"></span> Loading...');
        }

        hideLoadingState() {
            // Remove loading overlay from migration section
            const migrationSection = $('.ai-botkit-migration-section');
            migrationSection.removeClass('ai-botkit-loading');
            
            // Re-enable all interactive elements
            migrationSection.find('button').prop('disabled', false);
            
            // Remove loading spinner from status display
            $('#migration-status-display').removeClass('ai-botkit-loading-content');
        }

        clearDatabase(database) {
            const databaseName = database === 'local' ? 'Local Database' : 'Pinecone Database';
            
            if (!confirm(`Are you sure you want to clear the ${databaseName}? This action cannot be undone.`)) {
                return;
            }

            // Show loading state
            this.showLoadingState();

            $.ajax({
                url: aiBotKitMigration.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'ai_botkit_clear_database',
                    database: database,
                    nonce: aiBotKitMigration.nonce
                },
                success: (response) => {
                    this.hideLoadingState();
                    if (response.success) {
                        alert(`Successfully cleared ${databaseName}`);
                        this.loadMigrationStatus(); // Refresh status
                    } else {
                        alert(`Failed to clear ${databaseName}: ${response.data.message || 'Unknown error'}`);
                    }
                },
                error: () => {
                    this.hideLoadingState();
                    alert(`Failed to clear ${databaseName}`);
                }
            });
        }
    }

    // Initialize when document is ready
    $(document).ready(() => {
        new MigrationWizard();
    });

})(jQuery);
