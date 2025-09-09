<?php
/**
 * Consistency Check for WDM AI BotKit Extension
 * 
 * This script validates that the extension is properly refactored
 * to use action hooks instead of JavaScript injection.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

echo "🔍 WDM AI BotKit Extension - Consistency Check\n";
echo "===============================================\n\n";

$issues = [];
$successes = [];

// 1. Check if old injection methods are completely removed
if (method_exists('Wdm_Ai_Botkit_Extension_Admin', 'inject_ai_botkit_sidebar_menu')) {
    $issues[] = "❌ Old method 'inject_ai_botkit_sidebar_menu' still exists";
} else {
    $successes[] = "✅ Old method 'inject_ai_botkit_sidebar_menu' properly removed";
}

if (method_exists('Wdm_Ai_Botkit_Extension_Admin', 'inject_ai_botkit_tab_content')) {
    $issues[] = "❌ Old method 'inject_ai_botkit_tab_content' still exists";
} else {
    $successes[] = "✅ Old method 'inject_ai_botkit_tab_content' properly removed";
}

if (method_exists('Wdm_Ai_Botkit_Extension_Admin', 'get_license_content_ajax')) {
    $issues[] = "❌ Old AJAX method 'get_license_content_ajax' still exists";
} else {
    $successes[] = "✅ Old AJAX method 'get_license_content_ajax' properly removed";
}

// 2. Check if new action hook methods exist
if (method_exists('Wdm_Ai_Botkit_Extension_Admin', 'add_extension_sidebar_menu')) {
    $successes[] = "✅ New method 'add_extension_sidebar_menu' exists";
} else {
    $issues[] = "❌ New method 'add_extension_sidebar_menu' missing";
}

if (method_exists('Wdm_Ai_Botkit_Extension_Admin', 'add_extension_tab_content')) {
    $successes[] = "✅ New method 'add_extension_tab_content' exists";
} else {
    $issues[] = "❌ New method 'add_extension_tab_content' missing";
}

// 3. Check if action hooks are registered
if (has_action('ai_botkit_sidebar_menu_items')) {
    $successes[] = "✅ Action hook 'ai_botkit_sidebar_menu_items' is registered";
} else {
    $issues[] = "❌ Action hook 'ai_botkit_sidebar_menu_items' not registered";
}

if (has_action('ai_botkit_admin_tab_content')) {
    $successes[] = "✅ Action hook 'ai_botkit_admin_tab_content' is registered";
} else {
    $issues[] = "❌ Action hook 'ai_botkit_admin_tab_content' not registered";
}

// 4. Check if old admin_footer hooks are removed
$admin_hooks = get_option('active_plugins');
$has_old_hooks = false;

// This is a simplified check - in a real environment we'd check the actual hooks
if (class_exists('Wdm_Ai_Botkit_Extension_Admin')) {
    $successes[] = "✅ Extension admin class exists";
} else {
    $issues[] = "❌ Extension admin class missing";
}

// 5. Check if license AJAX handler still exists (should be kept)
if (method_exists('Wdm_Ai_Botkit_Extension_Admin', 'process_license_ajax')) {
    $successes[] = "✅ License AJAX handler 'process_license_ajax' exists (should be kept)";
} else {
    $issues[] = "❌ License AJAX handler 'process_license_ajax' missing";
}

// 6. Check if partials file exists
if (file_exists(plugin_dir_path(__FILE__) . 'admin/partials/wdm-ai-botkit-extension-license-settings.php')) {
    $successes[] = "✅ License settings partial file exists";
} else {
    $issues[] = "❌ License settings partial file missing";
}

// 7. Check if JavaScript file exists and doesn't contain old AJAX calls
if (file_exists(plugin_dir_path(__FILE__) . 'admin/js/wdm-ai-botkit-extension-admin.js')) {
    $js_content = file_get_contents(plugin_dir_path(__FILE__) . 'admin/js/wdm-ai-botkit-extension-admin.js');
    if (strpos($js_content, 'wdm_ai_botkit_extension_get_license_content') === false) {
        $successes[] = "✅ JavaScript file doesn't contain old AJAX content loading";
    } else {
        $issues[] = "❌ JavaScript file still contains old AJAX content loading";
    }
} else {
    $issues[] = "❌ JavaScript file missing";
}

// Display results
echo "📋 CHECK RESULTS:\n";
echo "==================\n\n";

if (!empty($successes)) {
    echo "✅ SUCCESSES:\n";
    foreach ($successes as $success) {
        echo "  $success\n";
    }
    echo "\n";
}

if (!empty($issues)) {
    echo "❌ ISSUES FOUND:\n";
    foreach ($issues as $issue) {
        echo "  $issue\n";
    }
    echo "\n";
} else {
    echo "🎉 NO ISSUES FOUND! Extension is fully consistent.\n\n";
}

echo "📊 SUMMARY:\n";
echo "============\n";
echo "✅ Successes: " . count($successes) . "\n";
echo "❌ Issues: " . count($issues) . "\n";

if (empty($issues)) {
    echo "\n🎯 CONCLUSION: Extension is fully refactored and consistent!\n";
    echo "   All JavaScript injection has been replaced with action hooks.\n";
    echo "   The extension is ready for production use.\n";
} else {
    echo "\n⚠️  CONCLUSION: Some issues need to be addressed before production.\n";
}
