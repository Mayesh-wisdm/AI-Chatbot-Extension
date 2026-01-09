<div id="ai-botkit-deactivation-modal" class="ai-botkit-modal-overlay">
	<div class="ai-botkit-kb-modal">
		<div class="ai-botkit-modal-header">
			<h3><?php esc_html_e('Deactivate KnowVault Plugin', 'knowvault'); ?></h3>
			<p><?php esc_html_e('Are you sure you want to deactivate KnowVault Plugin? You will no longer be able to use the plugin and all your data will be lost.', 'knowvault'); ?></p>
		</div>
		<div class="ai-botkit-modal-body">
            <!-- <div class="ai-botkit-form-group">
                <label for="ai-botkit-deactivation-reason" class="ai-botkit-label"><?php esc_html_e('Reason for deactivation', 'knowvault'); ?></label>
                <div class="ai-botkit-radio-group">
                    <label>
                        <input type="radio" name="ai-botkit-deactivation-reason" value="no_longer_needed">
                        <?php esc_html_e('I no longer need the plugin', 'knowvault'); ?>
                    </label>
                    <label>
                        <input type="radio" name="ai-botkit-deactivation-reason" value="found_better_plugin">
                        <?php esc_html_e('I found a better plugin', 'knowvault'); ?>
                    </label>
                    <label>
                        <input type="radio" name="ai-botkit-deactivation-reason" value="couldn_t_get_it_to_work">
                        <?php esc_html_e('I couldn\'t get it to work', 'knowvault'); ?>
                    </label>
                    <label>
                        <input type="radio" name="ai-botkit-deactivation-reason" value="temporary_deactivation">
                        <?php esc_html_e('It is a temporary deactivation', 'knowvault'); ?>
                    </label>
                    <label>
                        <input type="radio" name="ai-botkit-deactivation-reason" class="ai-botkit-deactivation-reason-other" value="functionality_not_what_expected">
                        <?php esc_html_e('The functionality is not what I expected', 'knowvault'); ?>
                    </label>
                    <label>
                        <input type="radio" name="ai-botkit-deactivation-reason" class="ai-botkit-deactivation-reason-other" value="other">
                        <?php esc_html_e('Other', 'knowvault'); ?>
                    </label>
                </div>
            </div>
            <div class="ai-botkit-form-group ai-botkit-deactivation-reason-textarea">
                <label for="ai-botkit-deactivation-reason" class="ai-botkit-label"><?php esc_html_e('Reason for deactivation', 'knowvault'); ?></label>
				<textarea id="ai-botkit-deactivation-reason" name="ai-botkit-deactivation-reason-other" placeholder="Reason for deactivation"></textarea>
			</div>
            <div class="ai-botkit-deactivation-reason-error">
                <?php esc_html_e('Please select a reason for deactivation', 'knowvault'); ?>
            </div> -->
            <iframe data-tally-src="https://tally.so/embed/3xbBkG?alignLeft=1&hideTitle=1&transparentBackground=1&dynamicHeight=1&site=<?php echo esc_url(get_site_url()); ?>" loading="lazy" width="100%" height="200" frameborder="0" marginheight="0" marginwidth="0" title="Deactivation Feedback"></iframe>
            <script>var d=document,w="https://tally.so/widgets/embed.js",v=function(){"undefined"!=typeof Tally?Tally.loadEmbeds():d.querySelectorAll("iframe[data-tally-src]:not([src])").forEach((function(e){e.src=e.dataset.tallySrc}))};if("undefined"!=typeof Tally)v();else if(d.querySelector('script[src="'+w+'"]')==null){var s=d.createElement("script");s.src=w,s.onload=v,s.onerror=v,d.body.appendChild(s);}</script>
		</div>
		<div class="ai-botkit-modal-footer">
            <button id="ai-botkit-cancel-deactivation" class="ai-botkit-btn-outline"><?php esc_html_e('Cancel', 'knowvault'); ?></button>
            <div class="ai-botkit-deactivation-buttons">
                <button id="ai-botkit-skip-deactivation" class="ai-botkit-btn-outline"><?php esc_html_e('Skip and deactivate', 'knowvault'); ?></button>
                <!-- <button id="ai-botkit-confirm-deactivation-submit" class="ai-botkit-btn ai-botkit-btn-primary"><?php esc_html_e('Submit and deactivate', 'knowvault'); ?></button> -->
            </div>
		</div>
	</div>
</div>
