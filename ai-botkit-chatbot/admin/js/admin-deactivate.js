/**
 * AI BotKit Deactivate Modal Scripts
 * 
 * This file contains all the JavaScript functionality for the deactivate modal
 */
jQuery(document).ready(function($) {
    'use strict';

    const deactivateButton = $('#the-list').find('[data-plugin="knowVault/knowVault.php"] span.deactivate a');
    deactivateButton.on('click', function(e) {
        e.preventDefault();
        $('#ai-botkit-deactivation-modal').fadeIn();
    });

    window.addEventListener('message', (e) => {
        if (e.origin === 'https://tally.so' && e.data.includes('Tally.FormSubmitted')) {
            $('#ai-botkit-deactivation-modal').fadeOut();
            window.location.href = deactivateButton.attr('href');
        }
    });

    // cancel deactivate
    $('#ai-botkit-cancel-deactivation').on('click', function() {
        $('#ai-botkit-deactivation-modal').fadeOut();
    });

    // confirm deactivate
    $('#ai-botkit-confirm-deactivation-submit').on('click', function() {
        const reason = $('input[name="ai-botkit-deactivation-reason"]:checked').val();

        // if no reason is selected, show error
        if (!reason) {
            $('.ai-botkit-deactivation-reason-error').show();
            return;
        }

        const other_reason = $('textarea[name="ai-botkit-deactivation-reason-other"]').val();
        $.ajax({
            url: ai_botkitAdminDeactivate.ajaxUrl,
            type: 'POST',
            data: {
                action: 'ai_botkit_deactivate',
                reason: reason,
                other_reason: other_reason,
                site_url: window.location.href
            },
            success: function(response) {
                $('#ai-botkit-deactivation-modal').fadeOut();
                window.location.href = deactivateButton.attr('href');
            },
            complete: function() {
                $('#ai-botkit-deactivation-modal').fadeOut();
                window.location.href = deactivateButton.attr('href');
            }
        });
    });

    // skip deactivate
    $('#ai-botkit-skip-deactivation').on('click', function() {
        $('#ai-botkit-deactivation-modal').fadeOut();
        window.location.href = deactivateButton.attr('href');
    });
});
