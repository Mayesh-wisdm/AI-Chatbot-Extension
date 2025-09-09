<?php
/**
 * Test file for WDM AI BotKit Extension
 * 
 * This file tests if the extension is properly integrated with AI BotKit
 * using the new action hook system instead of JavaScript injection.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Test if the extension is loaded
if (!class_exists('Wdm_Ai_Botkit_Extension')) {
    echo "❌ Extension class not found\n";
    exit;
}

// Test if AI BotKit is active
if (!class_exists('AI_BotKit\Admin\Admin')) {
    echo "❌ AI BotKit not active\n";
    exit;
}

// Test if action hooks are registered
if (!has_action('ai_botkit_sidebar_menu_items')) {
    echo "❌ Sidebar menu action hook not registered\n";
    exit;
}

if (!has_action('ai_botkit_admin_tab_content')) {
    echo "❌ Tab content action hook not registered\n";
    exit;
}

echo "✅ Extension is properly integrated with AI BotKit using action hooks!\n";
echo "✅ Sidebar menu hook: ai_botkit_sidebar_menu_items\n";
echo "✅ Tab content hook: ai_botkit_admin_tab_content\n";
echo "✅ No JavaScript injection required\n";
