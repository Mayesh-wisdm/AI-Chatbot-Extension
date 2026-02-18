# KnowVault Developer Documentation

> **Version:** 2.0.0 (Phase 2)
> **Last Updated:** 2026-01-29
> **Status:** Complete

This document provides technical documentation for developers extending or integrating with KnowVault (AI BotKit) Phase 2 features.

---

## Table of Contents

1. [Architecture Overview](#1-architecture-overview)
2. [Extending Phase 2 Features](#2-extending-phase-2-features)
3. [Available Hooks and Filters](#3-available-hooks-and-filters)
4. [REST API Endpoints](#4-rest-api-endpoints)
5. [AJAX Handlers](#5-ajax-handlers)
6. [Database Schema](#6-database-schema)
7. [Code Examples](#7-code-examples)

---

## 1. Architecture Overview

### Phase 2 Component Structure

```
includes/core/
├── class-chat-history-handler.php   # History management
├── class-search-handler.php         # Fulltext search
├── class-media-handler.php          # Media uploads
├── class-template-manager.php       # Template CRUD
├── class-export-handler.php         # PDF generation
└── class-recommendation-engine.php  # Suggestions

includes/models/
├── class-template.php               # Template entity
└── class-media.php                  # Media entity
```

### Namespace

All Phase 2 classes use the `AI_BotKit\Core` namespace:

```php
namespace AI_BotKit\Core;
```

### Class Dependencies

```
Chat_History_Handler
├── Conversation (model)
├── Table_Helper
└── Unified_Cache_Manager

Search_Handler
├── Table_Helper
└── Unified_Cache_Manager

Media_Handler
├── Table_Helper
└── fivefilters/readability.php (link previews)

Template_Manager
├── Chatbot (model)
└── Table_Helper

Export_Handler
├── Conversation (model)
├── Table_Helper
└── Dompdf\Dompdf

Recommendation_Engine
├── WooCommerce_Assistant
├── LearnDash integration
├── Unified_Cache_Manager
└── Table_Helper
```

---

## 2. Extending Phase 2 Features

### 2.1 Extending Chat History

**Add custom data to history items:**

```php
add_filter('ai_botkit_history_item', function($item, $conversation) {
    // Add custom fields to each history item
    $item['custom_label'] = get_post_meta($conversation->id, 'custom_label', true);
    $item['priority'] = get_post_meta($conversation->id, 'priority', true);
    return $item;
}, 10, 2);
```

**Modify history query parameters:**

```php
add_filter('ai_botkit_history_query', function($query_args, $user_id) {
    // Only show conversations from the last 90 days
    $query_args['date_after'] = date('Y-m-d', strtotime('-90 days'));
    return $query_args;
}, 10, 2);
```

**React to conversation events:**

```php
add_action('ai_botkit_conversation_resumed', function($conversation_id, $user_id) {
    // Log resume event to analytics
    do_action('custom_analytics', 'conversation_resumed', [
        'conversation_id' => $conversation_id,
        'user_id' => $user_id
    ]);
}, 10, 2);

add_action('ai_botkit_conversation_archived', function($conversation_id, $user_id) {
    // Send notification or trigger workflow
    wp_schedule_single_event(time() + 86400, 'cleanup_archived_conversation', [$conversation_id]);
}, 10, 2);
```

### 2.2 Extending Search

**Add custom search filters:**

```php
add_filter('ai_botkit_search_query', function($query, $filters) {
    // Add custom WHERE clause
    if (!empty($filters['custom_tag'])) {
        $query['where'][] = "m.metadata LIKE '%" . esc_sql($filters['custom_tag']) . "%'";
    }
    return $query;
}, 10, 2);
```

**Modify search results:**

```php
add_filter('ai_botkit_search_result', function($result, $raw_data) {
    // Add sentiment analysis
    $result['sentiment'] = analyze_sentiment($raw_data['content']);
    return $result;
}, 10, 2);
```

**Override search permissions:**

```php
add_filter('ai_botkit_can_search_all', function($can_search, $user_id) {
    // Allow support agents to search all
    if (user_can($user_id, 'support_agent')) {
        return true;
    }
    return $can_search;
}, 10, 2);
```

### 2.3 Extending Media Support

**Add custom media types:**

```php
add_filter('ai_botkit_allowed_media_types', function($types) {
    // Allow audio files
    $types[] = 'audio/mpeg';
    $types[] = 'audio/wav';
    $types[] = 'audio/ogg';
    return $types;
});
```

**Customize upload path:**

```php
add_filter('ai_botkit_media_upload_path', function($path, $file_type) {
    // Organize by chatbot ID
    $chatbot_id = isset($_POST['chatbot_id']) ? intval($_POST['chatbot_id']) : 0;
    return $path . '/chatbot-' . $chatbot_id;
}, 10, 2);
```

**Process uploaded media:**

```php
add_action('ai_botkit_media_uploaded', function($media_id, $file_data, $user_id) {
    // Generate thumbnail for images
    if (strpos($file_data['type'], 'image') === 0) {
        generate_custom_thumbnail($media_id, $file_data['path']);
    }

    // Scan document content
    if ($file_data['type'] === 'application/pdf') {
        extract_pdf_text($media_id, $file_data['path']);
    }
}, 10, 3);
```

**Modify link previews:**

```php
add_filter('ai_botkit_link_preview_data', function($preview_data, $url) {
    // Add custom metadata
    $preview_data['affiliate_link'] = add_affiliate_code($url);

    // Filter out certain domains
    if (strpos($url, 'competitor.com') !== false) {
        return null; // Block preview
    }

    return $preview_data;
}, 10, 2);
```

### 2.4 Extending Templates

**Modify template data on save:**

```php
add_filter('ai_botkit_template_data', function($template_data) {
    // Add required fields
    $template_data['version'] = '1.0.0';
    $template_data['author'] = get_current_user_id();

    // Validate required settings
    if (empty($template_data['model_config']['temperature'])) {
        $template_data['model_config']['temperature'] = 0.7;
    }

    return $template_data;
});
```

**Customize template application:**

```php
add_filter('ai_botkit_apply_template', function($config, $template, $chatbot) {
    // Preserve certain chatbot settings
    $config['knowledge_base'] = $chatbot->get_knowledge_base();
    $config['rate_limits'] = $chatbot->get_rate_limits();

    return $config;
}, 10, 3);
```

**React to template changes:**

```php
add_action('ai_botkit_template_applied', function($template_id, $chatbot_id) {
    // Clear chatbot cache
    wp_cache_delete('chatbot_' . $chatbot_id, 'ai_botkit');

    // Log for audit
    error_log("Template $template_id applied to chatbot $chatbot_id");
}, 10, 2);
```

### 2.5 Extending PDF Export

**Customize PDF HTML template:**

```php
add_filter('ai_botkit_pdf_template', function($html, $conversation) {
    // Add custom header
    $html = str_replace(
        '<div class="header">',
        '<div class="header"><div class="custom-banner">CONFIDENTIAL</div>',
        $html
    );

    return $html;
}, 10, 2);
```

**Modify PDF styles:**

```php
add_filter('ai_botkit_pdf_styles', function($css) {
    return $css . '
        .custom-banner {
            background: #ff0000;
            color: #fff;
            padding: 5px;
            text-align: center;
            font-weight: bold;
        }
        .message.user {
            background-color: #e3f2fd;
        }
        .message.assistant {
            background-color: #f5f5f5;
        }
    ';
});
```

**Customize export filename:**

```php
add_filter('ai_botkit_export_filename', function($filename, $conversation_id) {
    $conversation = Conversation::get($conversation_id);
    $date = date('Y-m-d', strtotime($conversation->created_at));
    return "transcript-{$date}-{$conversation_id}.pdf";
}, 10, 2);
```

**Override export permissions:**

```php
add_filter('ai_botkit_can_export', function($can_export, $conversation_id, $user_id) {
    // Allow export for premium members only
    if (!is_premium_member($user_id)) {
        return false;
    }
    return $can_export;
}, 10, 3);
```

**Track exports:**

```php
add_action('ai_botkit_pdf_exported', function($conversation_id, $file_path, $user_id) {
    // Log for compliance
    global $wpdb;
    $wpdb->insert(
        $wpdb->prefix . 'export_audit_log',
        [
            'user_id' => $user_id,
            'conversation_id' => $conversation_id,
            'exported_at' => current_time('mysql'),
            'ip_address' => $_SERVER['REMOTE_ADDR']
        ]
    );
}, 10, 3);
```

### 2.6 Extending Recommendations

**Modify recommendation signals:**

```php
add_filter('ai_botkit_recommendation_signals', function($signals, $user_id) {
    // Add loyalty tier signal
    $loyalty_tier = get_user_meta($user_id, 'loyalty_tier', true);
    if ($loyalty_tier === 'gold') {
        $signals['premium_products'] = [
            'weight' => 0.15,
            'filter' => 'premium_only'
        ];
    }

    // Add category affinity
    $favorite_category = get_user_meta($user_id, 'favorite_category', true);
    if ($favorite_category) {
        $signals['category_affinity'] = [
            'weight' => 0.1,
            'category_id' => $favorite_category
        ];
    }

    return $signals;
}, 10, 2);
```

**Filter recommendations:**

```php
add_filter('ai_botkit_recommendations', function($recommendations, $context) {
    // Remove out-of-stock items
    return array_filter($recommendations, function($item) {
        if ($item['type'] === 'product') {
            $product = wc_get_product($item['id']);
            return $product && $product->is_in_stock();
        }
        return true;
    });
}, 10, 2);
```

**Modify individual recommendation items:**

```php
add_filter('ai_botkit_recommendation_item', function($item, $raw_data) {
    // Add urgency messaging
    if ($item['type'] === 'product') {
        $stock = wc_get_product($item['id'])->get_stock_quantity();
        if ($stock < 5) {
            $item['urgency_message'] = "Only $stock left!";
        }
    }

    // Add discount badge
    $product = wc_get_product($item['id']);
    if ($product && $product->is_on_sale()) {
        $item['badge'] = 'SALE';
    }

    return $item;
}, 10, 2);
```

**Track recommendation interactions:**

```php
add_action('ai_botkit_interaction_tracked', function($user_id, $type, $data) {
    // Send to external analytics
    if ($type === 'recommendation_click') {
        wp_remote_post('https://analytics.example.com/track', [
            'body' => json_encode([
                'event' => 'recommendation_click',
                'user_id' => $user_id,
                'item_id' => $data['item_id'],
                'item_type' => $data['item_type']
            ])
        ]);
    }
}, 10, 3);
```

---

## 3. Available Hooks and Filters

### 3.1 Filters

| Filter | Description | Parameters | Return |
|--------|-------------|------------|--------|
| `ai_botkit_history_query` | Modify history query | `$query_args`, `$user_id` | `array` |
| `ai_botkit_history_item` | Modify history list item | `$item`, `$conversation` | `array` |
| `ai_botkit_search_query` | Modify search query | `$query`, `$filters` | `array` |
| `ai_botkit_search_result` | Modify search result item | `$result`, `$raw_data` | `array` |
| `ai_botkit_allowed_media_types` | Modify allowed uploads | `$mime_types` | `array` |
| `ai_botkit_media_upload_path` | Modify upload path | `$path`, `$file_type` | `string` |
| `ai_botkit_link_preview_data` | Modify link preview | `$preview_data`, `$url` | `array|null` |
| `ai_botkit_template_data` | Modify template on save | `$template_data` | `array` |
| `ai_botkit_apply_template` | Modify template application | `$config`, `$template`, `$chatbot` | `array` |
| `ai_botkit_pdf_template` | Modify PDF HTML template | `$html`, `$conversation` | `string` |
| `ai_botkit_pdf_styles` | Modify PDF CSS | `$css` | `string` |
| `ai_botkit_export_filename` | Modify export filename | `$filename`, `$conversation_id` | `string` |
| `ai_botkit_recommendation_signals` | Modify recommendation weights | `$signals`, `$user_id` | `array` |
| `ai_botkit_recommendations` | Modify recommendation list | `$recommendations`, `$context` | `array` |
| `ai_botkit_recommendation_item` | Modify single recommendation | `$item`, `$raw_data` | `array` |
| `ai_botkit_can_search_all` | Override search permissions | `$can_search`, `$user_id` | `bool` |
| `ai_botkit_can_export` | Override export permissions | `$can_export`, `$conversation_id`, `$user_id` | `bool` |

### 3.2 Actions

| Action | Description | Parameters |
|--------|-------------|------------|
| `ai_botkit_conversation_resumed` | After conversation resumed | `$conversation_id`, `$user_id` |
| `ai_botkit_conversation_archived` | After conversation archived | `$conversation_id`, `$user_id` |
| `ai_botkit_search_performed` | After search executed | `$query`, `$results_count`, `$user_id` |
| `ai_botkit_media_uploaded` | After media upload | `$media_id`, `$file_data`, `$user_id` |
| `ai_botkit_media_deleted` | After media deleted | `$media_id` |
| `ai_botkit_template_created` | After template created | `$template_id`, `$template_data` |
| `ai_botkit_template_updated` | After template updated | `$template_id`, `$changes` |
| `ai_botkit_template_deleted` | After template deleted | `$template_id` |
| `ai_botkit_template_applied` | After template applied | `$template_id`, `$chatbot_id` |
| `ai_botkit_pdf_exported` | After PDF generated | `$conversation_id`, `$file_path`, `$user_id` |
| `ai_botkit_interaction_tracked` | After interaction tracked | `$user_id`, `$type`, `$data` |
| `ai_botkit_recommendations_generated` | After recommendations generated | `$user_id`, `$recommendations` |

---

## 4. REST API Endpoints

### Base URL
```
/wp-json/ai-botkit/v1/
```

### Authentication
All endpoints require WordPress authentication. Use cookie authentication for browser requests or application passwords for API access.

### 4.1 History Endpoints

**List User History**
```
GET /history
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `chatbot_id` | int | null | Filter by chatbot |
| `page` | int | 1 | Page number |
| `per_page` | int | 10 | Items per page |

Response:
```json
{
  "conversations": [
    {
      "id": 123,
      "chatbot_id": 1,
      "chatbot_name": "Support Bot",
      "preview": "How do I reset my password...",
      "message_count": 5,
      "last_activity": "2026-01-29T10:30:00Z",
      "created_at": "2026-01-28T15:00:00Z"
    }
  ],
  "total": 45,
  "pages": 5,
  "current_page": 1
}
```

**Resume Conversation**
```
POST /history/{id}/resume
```

Response:
```json
{
  "conversation_id": 123,
  "messages": [...],
  "session_id": "abc123"
}
```

**Archive Conversation**
```
DELETE /history/{id}/archive
```

Response: `204 No Content`

### 4.2 Search Endpoints

**Search Messages**
```
GET /search
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `q` | string | Search query (required) |
| `chatbot_id` | int | Filter by chatbot |
| `user_id` | int | Filter by user (admin only) |
| `start_date` | string | Y-m-d format |
| `end_date` | string | Y-m-d format |
| `role` | string | user or assistant |
| `page` | int | Page number |
| `per_page` | int | Results per page |

Response:
```json
{
  "results": [
    {
      "message_id": 456,
      "conversation_id": 123,
      "chatbot_name": "Support Bot",
      "role": "user",
      "content": "How do I reset my password?",
      "content_highlighted": "How do I <mark>reset</mark> my <mark>password</mark>?",
      "created_at": "2026-01-29T10:30:00Z",
      "relevance_score": 0.95
    }
  ],
  "total": 15,
  "pages": 1,
  "search_time": 0.042
}
```

**Search Suggestions**
```
GET /search/suggestions?q=res
```

Response:
```json
{
  "suggestions": ["reset password", "reset account", "restart subscription"]
}
```

### 4.3 Media Endpoints

**Upload Media**
```
POST /media/upload
Content-Type: multipart/form-data
```

| Field | Type | Description |
|-------|------|-------------|
| `file` | file | File to upload (required) |
| `conversation_id` | int | Associated conversation |

Response:
```json
{
  "id": 789,
  "url": "https://example.com/wp-content/uploads/ai-botkit/chat-media/images/2026/01/image.jpg",
  "type": "image",
  "filename": "screenshot.jpg",
  "size": 245678,
  "metadata": {
    "width": 1920,
    "height": 1080,
    "mime_type": "image/jpeg"
  }
}
```

**Get Link Preview**
```
GET /media/link-preview?url=https://example.com/page
```

Response:
```json
{
  "title": "Example Page Title",
  "description": "A description of the page content...",
  "image": "https://example.com/og-image.jpg",
  "site_name": "Example Site",
  "url": "https://example.com/page"
}
```

### 4.4 Template Endpoints

**List Templates**
```
GET /templates?category=support
```

**Get Template**
```
GET /templates/{id}
```

**Create Template**
```
POST /templates
Content-Type: application/json
```

Body:
```json
{
  "name": "Custom Support Bot",
  "description": "Optimized for technical support",
  "category": "support",
  "style": {
    "primary_color": "#1E3A8A",
    "position": "bottom-right"
  },
  "messages_template": {
    "greeting": "Hello! How can I help today?",
    "fallback": "I apologize, let me connect you with a human."
  },
  "model_config": {
    "temperature": 0.5,
    "max_tokens": 500
  },
  "conversation_starters": [
    "I need help with my account",
    "How do I track my order?"
  ]
}
```

**Update Template**
```
PUT /templates/{id}
```

**Delete Template**
```
DELETE /templates/{id}
```

**Apply Template**
```
POST /templates/{id}/apply
```

Body:
```json
{
  "chatbot_id": 1,
  "merge": true
}
```

### 4.5 Export Endpoints

**Export PDF**
```
GET /export/{conversation_id}/pdf
```

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `include_metadata` | bool | true | Include timestamps |
| `include_branding` | bool | true | Include logo/colors |
| `paper_size` | string | letter | letter or a4 |

Response: PDF file stream

### 4.6 Recommendation Endpoints

**Get Recommendations**
```
GET /recommendations
```

| Parameter | Type | Description |
|-----------|------|-------------|
| `chatbot_id` | int | Current chatbot |
| `conversation_id` | int | Current conversation |
| `limit` | int | Max results (default 5) |

Response:
```json
{
  "recommendations": [
    {
      "type": "product",
      "id": 123,
      "title": "Premium Widget",
      "description": "Our best-selling widget...",
      "image": "https://example.com/widget.jpg",
      "price": "$49.99",
      "url": "https://example.com/product/premium-widget",
      "action_text": "Add to Cart",
      "action_url": "?add-to-cart=123",
      "score": 0.87
    }
  ]
}
```

**Track Interaction**
```
POST /recommendations/track
```

Body:
```json
{
  "interaction_type": "product_view",
  "item_id": 123,
  "item_type": "product",
  "metadata": {
    "source": "recommendation_card"
  }
}
```

---

## 5. AJAX Handlers

### 5.1 Public AJAX Actions

All require `check_ajax_referer('ai_botkit_nonce', 'nonce')`.

| Action | Parameters | Response |
|--------|------------|----------|
| `ai_botkit_get_history_list` | page, per_page, chatbot_id | Conversation list |
| `ai_botkit_resume_conversation` | conversation_id | Conversation data |
| `ai_botkit_search_messages` | q, filters, page | Search results |
| `ai_botkit_upload_chat_media` | file (multipart) | Media data |
| `ai_botkit_get_link_preview` | url | Preview data |
| `ai_botkit_get_recommendations` | chatbot_id, conversation_id | Recommendations |
| `ai_botkit_track_interaction` | type, item_id, item_type | Success status |
| `ai_botkit_export_pdf` | conversation_id, options | PDF download |

### 5.2 Admin AJAX Actions

| Action | Parameters | Response |
|--------|------------|----------|
| `ai_botkit_get_templates` | category | Template list |
| `ai_botkit_get_template` | template_id | Template data |
| `ai_botkit_save_template` | template_data | Template ID |
| `ai_botkit_delete_template` | template_id | Success status |
| `ai_botkit_apply_template` | template_id, chatbot_id, merge | Success status |
| `ai_botkit_create_template_from_chatbot` | chatbot_id, name | Template ID |
| `ai_botkit_export_template` | template_id | JSON string |
| `ai_botkit_import_template` | json_data | Template ID |
| `ai_botkit_admin_search` | q, filters | Search results |
| `ai_botkit_bulk_export` | conversation_ids, format | Download |

---

## 6. Database Schema

### 6.1 New Tables (Phase 2)

**ai_botkit_templates**
```sql
CREATE TABLE {prefix}ai_botkit_templates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(50),
    style JSON,
    messages_template JSON,
    model_config JSON,
    conversation_starters JSON,
    thumbnail VARCHAR(255),
    is_system TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_category (category),
    INDEX idx_is_system (is_system)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**ai_botkit_media**
```sql
CREATE TABLE {prefix}ai_botkit_media (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    message_id BIGINT UNSIGNED,
    user_id BIGINT UNSIGNED NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255),
    file_path VARCHAR(500) NOT NULL,
    file_url VARCHAR(500) NOT NULL,
    media_type ENUM('image', 'video', 'document') NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL,
    metadata JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_message (message_id),
    INDEX idx_user (user_id),
    INDEX idx_type (media_type),
    INDEX idx_message_type (message_id, media_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**ai_botkit_user_interactions**
```sql
CREATE TABLE {prefix}ai_botkit_user_interactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    interaction_type VARCHAR(50) NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    item_type VARCHAR(50) NOT NULL,
    metadata JSON,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_user_time (user_id, created_at DESC),
    INDEX idx_item (item_type, item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 6.2 Schema Modifications

**messages table - FULLTEXT index**
```sql
ALTER TABLE {prefix}ai_botkit_messages
ADD FULLTEXT INDEX ft_content (content);
```

**conversations table - composite index**
```sql
ALTER TABLE {prefix}ai_botkit_conversations
ADD INDEX idx_user_updated (user_id, updated_at DESC);
```

**conversations table - new columns**
```sql
ALTER TABLE {prefix}ai_botkit_conversations
ADD COLUMN is_archived TINYINT(1) DEFAULT 0,
ADD COLUMN is_favorite TINYINT(1) DEFAULT 0;
```

---

## 7. Code Examples

### 7.1 Custom History Panel Widget

```php
<?php
// Register a custom widget for history display
class Custom_Chat_History_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'custom_chat_history',
            'Chat History',
            ['description' => 'Display user chat history']
        );
    }

    public function widget($args, $instance) {
        if (!is_user_logged_in()) {
            return;
        }

        $handler = new \AI_BotKit\Core\Chat_History_Handler();
        $history = $handler->get_user_history(
            get_current_user_id(),
            null,
            1,
            5
        );

        echo $args['before_widget'];
        echo '<h3>Recent Conversations</h3>';
        echo '<ul class="chat-history-list">';

        foreach ($history['conversations'] as $convo) {
            printf(
                '<li><a href="#" data-conversation="%d">%s</a><br><small>%s</small></li>',
                $convo['id'],
                esc_html($convo['preview']),
                esc_html($convo['last_activity'])
            );
        }

        echo '</ul>';
        echo $args['after_widget'];
    }
}

add_action('widgets_init', function() {
    register_widget('Custom_Chat_History_Widget');
});
```

### 7.2 Custom Search Integration

```php
<?php
// Add chat search to site-wide search
add_filter('the_posts', function($posts, $query) {
    if (!$query->is_search() || !is_user_logged_in()) {
        return $posts;
    }

    $search_handler = new \AI_BotKit\Core\Search_Handler();
    $results = $search_handler->search(
        $query->query['s'],
        ['user_id' => get_current_user_id()],
        1,
        5
    );

    if (!empty($results['results'])) {
        // Store for display
        set_query_var('chat_search_results', $results['results']);
    }

    return $posts;
}, 10, 2);
```

### 7.3 Custom Recommendation Provider

```php
<?php
// Add custom recommendation source
add_filter('ai_botkit_recommendations', function($recommendations, $context) {
    // Add featured articles from blog
    $articles = get_posts([
        'post_type' => 'post',
        'meta_key' => 'featured',
        'meta_value' => '1',
        'posts_per_page' => 3
    ]);

    foreach ($articles as $article) {
        $recommendations[] = [
            'type' => 'article',
            'id' => $article->ID,
            'title' => $article->post_title,
            'description' => get_the_excerpt($article),
            'image' => get_the_post_thumbnail_url($article, 'medium'),
            'url' => get_permalink($article),
            'action_text' => 'Read More',
            'action_url' => get_permalink($article),
            'score' => 0.5
        ];
    }

    // Re-sort by score
    usort($recommendations, function($a, $b) {
        return $b['score'] <=> $a['score'];
    });

    return $recommendations;
}, 10, 2);
```

### 7.4 Custom Export Template

```php
<?php
// Fully custom PDF template
add_filter('ai_botkit_pdf_template', function($html, $conversation) {
    // Load custom template
    ob_start();
    include get_template_directory() . '/templates/chat-transcript.php';
    return ob_get_clean();
}, 10, 2);

// Custom CSS
add_filter('ai_botkit_pdf_styles', function($css) {
    return file_get_contents(
        get_template_directory() . '/assets/css/transcript.css'
    );
});
```

### 7.5 WooCommerce Order Context

```php
<?php
// Add recent orders to recommendation context
add_filter('ai_botkit_recommendation_signals', function($signals, $user_id) {
    $orders = wc_get_orders([
        'customer_id' => $user_id,
        'limit' => 5,
        'orderby' => 'date',
        'order' => 'DESC'
    ]);

    $purchased_categories = [];
    foreach ($orders as $order) {
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            $cats = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'ids']);
            $purchased_categories = array_merge($purchased_categories, $cats);
        }
    }

    if (!empty($purchased_categories)) {
        $signals['purchase_categories'] = [
            'weight' => 0.2,
            'category_ids' => array_unique($purchased_categories)
        ];
    }

    return $signals;
}, 10, 2);
```

---

## Further Resources

- **Architecture**: See `docs/ARCHITECTURE.md` for system design
- **UI Specification**: See `docs/PHASE2_UI_DESIGN_SPEC.md` for frontend details
- **Specification**: See `specs/PHASE2_SPECIFICATION.md` for full requirements
- **User Guide**: See `docs/PHASE2_USER_GUIDE.md` for end-user documentation

---

*Developer Documentation - Phase 2*
*Last Updated: 2026-01-29*
