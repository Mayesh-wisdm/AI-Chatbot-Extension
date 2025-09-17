<?php
defined('ABSPATH') || exit;

$nonce = wp_create_nonce('ai_botkit_chatbots');

?>
<aside class="ai-botkit-sidebar">
  <div class="ai-botkit-sidebar-header">
    <div class="ai-botkit-sidebar-logo">
      <img src="<?php echo esc_url(AI_BOTKIT_PLUGIN_URL . 'admin/logo.png'); ?>" alt="AI BotKit Logo" />
    </div>
  </div>

  <nav class="ai-botkit-sidebar-nav">
    <ul>
     <!-- <li>
        <?php 
        $is_active = $current_tab === 'dashboard';
        ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-botkit&tab=dashboard&nonce=' . $nonce)); ?>" class="ai-botkit-sidebar-link <?php echo $is_active ? esc_attr('active') : ''; ?>">
          <i class="ti ti-home"></i>
          <?php esc_html_e('Home', 'ai-botkit-for-lead-generation'); ?>
        </a>
      </li> -->
      <li>
        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-botkit&tab=chatbots&nonce=' . $nonce)); ?>" class="ai-botkit-sidebar-link <?php echo $current_tab === 'chatbots' ? esc_attr('active') : ''; ?>">
        <svg width="24" height="25" viewBox="0 0 24 25" xmlns="http://www.w3.org/2000/svg">
          <path d="M5.81607 5.26354C6.9879 4.4222 10.1033 2.91832 13.6237 3.35826C14.1717 3.42677 14.5604 3.92646 14.4919 4.47447C14.4274 4.98781 13.9845 5.36194 13.4782 5.35045L13.3756 5.34264L13.1061 5.31334C10.3341 5.06107 7.84986 6.26622 6.98306 6.88854L6.97818 6.89147C3.86497 9.10089 3.12939 12.9338 5.1276 15.8895C5.30168 16.147 5.34699 16.4716 5.24869 16.7665L4.50064 19.0077L7.49185 18.3719L7.65592 18.3514C7.82089 18.3442 7.98626 18.3772 8.13638 18.4501C9.83388 19.2742 12.6231 19.419 15.1159 18.5067C17.5584 17.6126 19.5729 15.7636 20.0094 12.7088C20.0875 12.1622 20.5946 11.7823 21.1413 11.8602C21.6879 11.9384 22.068 12.4454 21.9899 12.9921C21.4262 16.9364 18.7705 19.2984 15.8034 20.3846C13.022 21.4026 9.83703 21.3535 7.58658 20.3954L3.20767 21.328C2.85703 21.4024 2.49247 21.2837 2.25357 21.0165C2.01484 20.7492 1.93829 20.3741 2.05142 20.034L3.20084 16.5819C0.904495 12.7331 2.02375 7.95808 5.81607 5.26354Z"/>
          <path d="M19.5005 3.34851C19.7208 3.34867 19.9161 3.48997 19.9858 3.6991L20.6519 5.69812L22.6509 6.36414C22.86 6.43394 23.0005 6.62997 23.0005 6.85046C23.0004 7.07092 22.86 7.26704 22.6509 7.33679L20.6519 8.00281L19.9858 10.0018C19.916 10.2107 19.7207 10.3513 19.5005 10.3514C19.2801 10.3514 19.084 10.2109 19.0142 10.0018L18.3481 8.00281L16.3491 7.33679C16.1401 7.26699 15.9996 7.07085 15.9995 6.85046C15.9995 6.63004 16.1401 6.434 16.3491 6.36414L18.3481 5.69812L19.0142 3.6991C19.0839 3.48985 19.28 3.34851 19.5005 3.34851Z"/>
        </svg>
          <?php esc_html_e('My Bots', 'ai-botkit-for-lead-generation'); ?>
        </a>
      </li>
      <!-- <li><a href="/templates" class="ai-botkit-sidebar-link">💬 Templates</a></li> -->
      <li>
        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-botkit&tab=knowledge&nonce=' . $nonce)); ?>" class="ai-botkit-sidebar-link <?php echo $current_tab === 'knowledge' ? esc_attr('active') : ''; ?>">
          <i class="ti ti-database"></i>
          <?php esc_html_e('Knowledge Base', 'ai-botkit-for-lead-generation'); ?>
        </a>
      </li>
      <li>
        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-botkit&tab=analytics&nonce=' . $nonce)); ?>" class="ai-botkit-sidebar-link <?php echo $current_tab === 'analytics' ? esc_attr('active') : ''; ?>">
          <i class="ti ti-chart-bar"></i>
          <?php esc_html_e('Analytics', 'ai-botkit-for-lead-generation'); ?>
        </a>
      </li>
      <li>
        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-botkit&tab=security&nonce=' . $nonce)); ?>" class="ai-botkit-sidebar-link <?php echo $current_tab === 'security' ? esc_attr('active') : ''; ?>">
          <i class="ti ti-shield"></i>
          <?php esc_html_e('Security', 'ai-botkit-for-lead-generation'); ?>
        </a>
      </li>
      <li>
        <a href="<?php echo esc_url(admin_url('admin.php?page=ai-botkit&tab=settings&nonce=' . $nonce)); ?>" class="ai-botkit-sidebar-link <?php echo $current_tab === 'settings' ? esc_attr('active') : ''; ?>">
          <i class="ti ti-settings"></i>
          <?php esc_html_e('Settings', 'ai-botkit-for-lead-generation'); ?>
        </a>
      </li>
      <?php do_action( 'ai_botkit_sidebar_menu_items' ); ?>
    </ul>
  </nav>
</aside>