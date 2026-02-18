# AI BotKit Chatbot - Architecture Documentation

> **Version:** 2.0 (Phase 1 + Phase 2)
> **Last Updated:** 2026-01-28
> **Status:** Extended for Phase 2

---

## Document Status

| Phase | Aspect | Status | Confidence |
|-------|--------|--------|------------|
| Phase 1 | System Overview | RECOVERED | 90% |
| Phase 1 | Component Architecture | RECOVERED | 88% |
| Phase 1 | Data Flow | RECOVERED | 85% |
| Phase 1 | Database Schema | RECOVERED | 95% |
| Phase 1 | API Contracts | RECOVERED | 92% |
| Phase 2 | New Components | DESIGNED | 95% |
| Phase 2 | Extended APIs | DESIGNED | 95% |
| Phase 2 | Hook Architecture | DESIGNED | 95% |

---

## 1. System Overview

### 1.1 Product Description

AI BotKit Chatbot (also branded as KnowVault) is a **production-grade WordPress plugin** implementing a **Retrieval Augmented Generation (RAG)** chatbot system. The plugin enables AI-powered conversational interfaces that answer questions based on:

- WordPress site content (posts, pages, custom post types)
- Uploaded documents (PDF, text files)
- External URLs (web pages)
- LearnDash LMS content (courses, lessons, topics, quizzes)
- WooCommerce product catalogs

### 1.2 Technical Stack

| Layer | Technology |
|-------|------------|
| **Platform** | WordPress 5.8+ |
| **Language** | PHP 7.4+ (typed properties) |
| **Frontend** | JavaScript (vanilla), CSS |
| **Database** | MySQL via WordPress $wpdb |
| **Vector Storage** | Local DB or Pinecone |
| **LLM Providers** | OpenAI, Anthropic, Google AI, Together AI, VoyageAI |
| **Dependencies** | Guzzle HTTP, smalot/pdfparser, fivefilters/readability.php, dompdf (Phase 2) |

### 1.3 High-Level Architecture Diagram (Phase 1 + Phase 2)

```
+-----------------------------------------------------------------------------------+
|                     AI BOTKIT CHATBOT PLUGIN (Phase 1 + Phase 2)                  |
+-----------------------------------------------------------------------------------+
|                                                                                    |
|  PRESENTATION LAYER                                                                |
|  +----------------+  +----------------+  +----------------+  +----------------+   |
|  |  Admin Panel   |  |  REST API      |  |  Shortcodes    |  |  AJAX          |   |
|  |  (Admin class) |  |  (REST_API)    |  |  (Shortcode_   |  |  Handlers      |   |
|  |                |  |                |  |   Handler)     |  |  (Public/Admin)|   |
|  +-------+--------+  +-------+--------+  +-------+--------+  +-------+--------+   |
|          |                   |                   |                   |            |
|          +-------------------+-------------------+-------------------+            |
|                                      |                                            |
|  ORCHESTRATION LAYER                 v                                            |
|  +-----------------------------------------------------------------------------------+
|  |                            RAG_Engine                                          |
|  |                     (Central Orchestrator)                                     |
|  |  - Document processing pipeline                                                |
|  |  - Query processing and response generation                                    |
|  |  - Streaming response support                                                  |
|  |  - Conversation management                                                     |
|  +-----------------------------------------------------------------------------------+
|          |                   |                   |                   |            |
|  CORE SERVICES LAYER        |                   |                   |            |
|  +----------------+  +----------------+  +----------------+  +----------------+   |
|  |Document_Loader |  |Text_Chunker    |  |Embeddings_     |  |Retriever       |   |
|  | - PDF          |  | - Paragraph    |  | Generator      |  | - Vector search|   |
|  | - URL          |  | - Sentence     |  | - OpenAI API   |  | - Re-ranking   |   |
|  | - WP Posts     |  | - Overlap      |  | - Batch process|  | - Deduplication|   |
|  +-------+--------+  +-------+--------+  +-------+--------+  +-------+--------+   |
|          |                   |                   |                   |            |
|  +---------------------------------------------------------------------------------------+
|  |                        PHASE 2 FEATURE LAYER (NEW)                             |
|  +---------------------------------------------------------------------------------------+
|  |  +------------------+  +------------------+  +------------------+               |
|  |  | Chat_History_    |  | Search_Handler   |  | Media_Handler    |              |
|  |  | Handler          |  | - Fulltext search|  | - Image upload   |              |
|  |  | - List history   |  | - Date filters   |  | - Video embed    |              |
|  |  | - Resume convo   |  | - User scoping   |  | - File downloads |              |
|  |  | - Pagination     |  | - Highlighting   |  | - Link previews  |              |
|  |  +------------------+  +------------------+  +------------------+               |
|  |                                                                                 |
|  |  +------------------+  +------------------+  +------------------+               |
|  |  | Template_Manager |  | Export_Handler   |  | Recommendation_  |              |
|  |  | - Template CRUD  |  | - PDF generation |  | Engine           |              |
|  |  | - Apply template |  | - Branding       |  | - Context signals|              |
|  |  | - Pre-built      |  | - User export    |  | - User history   |              |
|  |  |   templates      |  | - Admin export   |  | - Suggestions UI |              |
|  |  +------------------+  +------------------+  +------------------+               |
|  +---------------------------------------------------------------------------------------+
|                                                                                    |
|  DATA ACCESS LAYER                                                                 |
|  +-------------------------------------------+  +--------------------------------+|
|  |           Vector Storage                   |  |        LLM_Client             ||
|  |  +------------------+  +----------------+  |  | - OpenAI (GPT-4, GPT-3.5)     ||
|  |  | Vector_Database  |  |Pinecone_Database| |  | - Anthropic (Claude 3)       ||
|  |  | (Local MySQL)    |  |(Cloud Service) | |  | - Google (Gemini)             ||
|  |  +------------------+  +----------------+  |  | - Together AI                 ||
|  +-------------------------------------------+  | - VoyageAI (embeddings)       ||
|                                                  +--------------------------------+|
|                                                                                    |
|  INTEGRATION LAYER                                                                 |
|  +----------------+  +----------------+  +----------------+  +----------------+   |
|  |WordPress_      |  |LearnDash       |  |WooCommerce     |  |WooCommerce_    |   |
|  | Content        |  | Integration    |  | Integration    |  | Assistant      |   |
|  +----------------+  +----------------+  +----------------+  +----------------+   |
|                                                                                    |
|  INFRASTRUCTURE LAYER                                                              |
|  +----------------+  +----------------+  +----------------+  +----------------+   |
|  |Unified_Cache_  |  |Rate_Limiter    |  |Analytics       |  |Health_Checks   |   |
|  | Manager        |  |                |  |                |  |                |   |
|  +----------------+  +----------------+  +----------------+  +----------------+   |
|                                                                                    |
|  DATA MODELS                                                                       |
|  +----------------+  +----------------+  +----------------+  +----------------+   |
|  |Chatbot         |  |Conversation    |  |Template        |  |Media           |   |
|  | (Entity/CRUD)  |  | (Entity/CRUD)  |  | (Phase 2)      |  | (Phase 2)      |   |
|  +----------------+  +----------------+  +----------------+  +----------------+   |
|                                                                                    |
+-----------------------------------------------------------------------------------+
```

---

## 2. Component Architecture

### 2.1 Phase 1 Core Components

*(Existing components from Phase 1 - see RECOVERED_ARCHITECTURE.md for full details)*

| Component | File | Purpose |
|-----------|------|---------|
| RAG_Engine | `includes/core/class-rag-engine.php` | Central orchestrator |
| LLM_Client | `includes/core/class-llm-client.php` | Multi-provider AI interface |
| Vector_Database | `includes/core/class-vector-database.php` | Local vector storage |
| Pinecone_Database | `includes/core/class-pinecone-database.php` | Cloud vector storage |
| Document_Loader | `includes/core/class-document-loader.php` | Document ingestion |
| Text_Chunker | `includes/core/class-text-chunker.php` | Text segmentation |
| Retriever | `includes/core/class-retriever.php` | Context retrieval |
| Chatbot | `includes/models/class-chatbot.php` | Chatbot configuration |
| Conversation | `includes/models/class-conversation.php` | Chat sessions |

---

### 2.2 Phase 2 New Components

#### 2.2.1 Chat_History_Handler

**File:** `includes/core/class-chat-history-handler.php`
**Namespace:** `AI_BotKit\Core`
**Status:** NEW (Phase 2)

**Responsibilities:**
- Retrieve paginated conversation history for logged-in users
- Provide conversation list with previews
- Enable conversation resumption and switching
- Support conversation archiving

**Key Dependencies:**
```php
namespace AI_BotKit\Core;

class Chat_History_Handler {
    private $conversation_model;  // Conversation
    private $cache_manager;       // Unified_Cache_Manager
    private $table_helper;        // Table_Helper

    /**
     * Get paginated conversation list for a user
     *
     * @param int   $user_id    WordPress user ID
     * @param int   $chatbot_id Optional chatbot filter
     * @param int   $page       Page number (1-indexed)
     * @param int   $per_page   Items per page (default 10)
     * @return array {
     *     @type array  $conversations  List of conversation summaries
     *     @type int    $total          Total conversation count
     *     @type int    $pages          Total page count
     *     @type int    $current_page   Current page number
     * }
     */
    public function get_user_history(
        int $user_id,
        ?int $chatbot_id = null,
        int $page = 1,
        int $per_page = 10
    ): array;

    /**
     * Get conversation preview (first message + metadata)
     *
     * @param int $conversation_id
     * @return array {
     *     @type string $preview       First 100 chars of first message
     *     @type int    $message_count Total messages in conversation
     *     @type string $last_activity Last message timestamp
     *     @type string $chatbot_name  Associated chatbot name
     * }
     */
    public function get_conversation_preview(int $conversation_id): array;

    /**
     * Resume an existing conversation
     *
     * @param int $conversation_id
     * @param int $user_id         For ownership verification
     * @return array|WP_Error Conversation data or error if not found/unauthorized
     */
    public function resume_conversation(int $conversation_id, int $user_id);

    /**
     * Archive a conversation (soft delete)
     *
     * @param int $conversation_id
     * @param int $user_id
     * @return bool
     */
    public function archive_conversation(int $conversation_id, int $user_id): bool;
}
```

**Integration with Phase 1:**
- Extends `Conversation::get_by_user()` with pagination
- Uses `Conversation::get_messages()` for preview generation
- Integrates with existing session management

---

#### 2.2.2 Search_Handler

**File:** `includes/core/class-search-handler.php`
**Namespace:** `AI_BotKit\Core`
**Status:** NEW (Phase 2)

**Responsibilities:**
- Fulltext search across chat messages
- Admin: search all conversations
- Users: search own conversations only
- Apply filters (date range, chatbot, user)
- Result highlighting and ranking

**Key Dependencies:**
```php
namespace AI_BotKit\Core;

class Search_Handler {
    private $cache_manager;       // Unified_Cache_Manager
    private $table_helper;        // Table_Helper

    /**
     * Search messages with filters
     *
     * @param string $query       Search query
     * @param array  $filters {
     *     @type int      $user_id      Limit to user (required for non-admins)
     *     @type int      $chatbot_id   Filter by chatbot
     *     @type string   $start_date   Filter by date range start (Y-m-d)
     *     @type string   $end_date     Filter by date range end (Y-m-d)
     *     @type string   $role         Filter by role (user|assistant)
     * }
     * @param int    $page        Page number
     * @param int    $per_page    Results per page
     * @return array {
     *     @type array  $results      Search results with highlights
     *     @type int    $total        Total matching results
     *     @type int    $pages        Total pages
     *     @type float  $search_time  Query execution time
     * }
     */
    public function search(
        string $query,
        array $filters = [],
        int $page = 1,
        int $per_page = 20
    ): array;

    /**
     * Check if user can search all conversations
     *
     * @param int $user_id
     * @return bool
     */
    public function can_search_all(int $user_id): bool;

    /**
     * Highlight search terms in content
     *
     * @param string $content
     * @param string $query
     * @return string HTML with <mark> tags
     */
    public function highlight_matches(string $content, string $query): string;

    /**
     * Get search suggestions based on query
     *
     * @param string $partial_query
     * @param int    $user_id
     * @return array List of suggested completions
     */
    public function get_suggestions(string $partial_query, int $user_id): array;
}
```

**Database Requirements:**
```sql
-- Add fulltext index to messages table
ALTER TABLE {prefix}ai_botkit_messages
ADD FULLTEXT INDEX ft_content (content);
```

**Integration with Phase 1:**
- Uses existing `ai_botkit_messages` table
- Extends `Database_Optimizer` for new indexes
- Uses `Unified_Cache_Manager` for result caching

---

#### 2.2.3 Media_Handler

**File:** `includes/core/class-media-handler.php`
**Namespace:** `AI_BotKit\Core`
**Status:** NEW (Phase 2)

**Responsibilities:**
- Handle media file uploads for chat messages
- Process and validate images, videos, documents
- Generate and fetch link previews (OpenGraph)
- Manage media storage and cleanup

**Key Dependencies:**
```php
namespace AI_BotKit\Core;

class Media_Handler {
    private const UPLOAD_DIR = 'ai-botkit/chat-media';
    private const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const ALLOWED_VIDEO_TYPES = ['video/mp4', 'video/webm'];
    private const ALLOWED_DOC_TYPES = ['application/pdf', 'text/plain'];
    private const MAX_FILE_SIZE = 10485760; // 10MB

    /**
     * Upload a media file for chat attachment
     *
     * @param array $file       $_FILES array element
     * @param int   $message_id Associated message ID (optional, for linking)
     * @return array|WP_Error {
     *     @type int    $id        Media record ID
     *     @type string $url       Public URL to media
     *     @type string $type      Media type (image|video|document)
     *     @type string $filename  Original filename
     *     @type int    $size      File size in bytes
     *     @type array  $metadata  Additional metadata (dimensions, duration, etc.)
     * }
     */
    public function upload_media(array $file, ?int $message_id = null);

    /**
     * Fetch link preview data (OpenGraph)
     *
     * @param string $url
     * @return array {
     *     @type string $title       Page title
     *     @type string $description Meta description
     *     @type string $image       og:image URL
     *     @type string $site_name   og:site_name
     *     @type string $url         Canonical URL
     * }
     */
    public function get_link_preview(string $url): array;

    /**
     * Process video embed URL (YouTube, Vimeo)
     *
     * @param string $url
     * @return array|false {
     *     @type string $provider    Video provider
     *     @type string $video_id    Provider video ID
     *     @type string $embed_url   Embeddable iframe URL
     *     @type string $thumbnail   Video thumbnail URL
     * }
     */
    public function process_video_embed(string $url);

    /**
     * Get media by ID
     *
     * @param int $media_id
     * @return array|null Media data or null if not found
     */
    public function get_media(int $media_id): ?array;

    /**
     * Delete media file and record
     *
     * @param int $media_id
     * @return bool
     */
    public function delete_media(int $media_id): bool;

    /**
     * Cleanup orphaned media files older than specified days
     *
     * @param int $days_old
     * @return int Number of files cleaned up
     */
    public function cleanup_orphaned_media(int $days_old = 30): int;

    /**
     * Validate file for upload
     *
     * @param array $file $_FILES array element
     * @return true|WP_Error
     */
    private function validate_file(array $file);
}
```

**Storage Structure:**
```
wp-content/uploads/ai-botkit/
├── documents/               # Existing - knowledge base documents
└── chat-media/              # NEW - chat attachments
    ├── images/
    │   └── {year}/{month}/
    ├── videos/
    │   └── {year}/{month}/
    └── files/
        └── {year}/{month}/
```

**Integration with Phase 1:**
- Extends existing upload patterns from `Ajax_Handler::handle_upload_file()`
- Uses `messages.metadata` JSON column for attachment references
- Leverages `fivefilters/readability.php` for link preview extraction

---

#### 2.2.4 Template_Manager

**File:** `includes/core/class-template-manager.php`
**Namespace:** `AI_BotKit\Core`
**Status:** NEW (Phase 2)

**Responsibilities:**
- CRUD operations for conversation templates
- Apply templates to chatbot configurations
- Manage pre-built template library
- Template import/export functionality

**Key Dependencies:**
```php
namespace AI_BotKit\Core;

class Template_Manager {
    private $table_helper;    // Table_Helper
    private $chatbot_model;   // Chatbot

    /**
     * Get all templates with optional filtering
     *
     * @param array $filters {
     *     @type string $category   Template category
     *     @type bool   $is_system  System templates only
     *     @type bool   $is_active  Active templates only
     * }
     * @return array List of template records
     */
    public function get_templates(array $filters = []): array;

    /**
     * Get a single template by ID
     *
     * @param int $template_id
     * @return array|null
     */
    public function get_template(int $template_id): ?array;

    /**
     * Create a new template
     *
     * @param array $data {
     *     @type string $name           Template name
     *     @type string $description    Template description
     *     @type string $category       Template category
     *     @type array  $style          Style configuration (JSON)
     *     @type array  $messages       Message templates (JSON)
     *     @type array  $model_config   Model configuration (JSON)
     *     @type array  $conversation_starters  Initial prompts
     *     @type string $thumbnail      Template preview image URL
     * }
     * @return int|WP_Error Template ID or error
     */
    public function create_template(array $data);

    /**
     * Update an existing template
     *
     * @param int   $template_id
     * @param array $data
     * @return bool|WP_Error
     */
    public function update_template(int $template_id, array $data);

    /**
     * Delete a template
     *
     * @param int $template_id
     * @return bool
     */
    public function delete_template(int $template_id): bool;

    /**
     * Apply a template to a chatbot
     *
     * @param int  $template_id
     * @param int  $chatbot_id
     * @param bool $merge       Merge with existing config (true) or replace (false)
     * @return bool|WP_Error
     */
    public function apply_to_chatbot(int $template_id, int $chatbot_id, bool $merge = true);

    /**
     * Create a template from an existing chatbot
     *
     * @param int    $chatbot_id
     * @param string $template_name
     * @return int|WP_Error Template ID
     */
    public function create_from_chatbot(int $chatbot_id, string $template_name);

    /**
     * Export template as JSON
     *
     * @param int $template_id
     * @return string JSON string
     */
    public function export_template(int $template_id): string;

    /**
     * Import template from JSON
     *
     * @param string $json_data
     * @return int|WP_Error Template ID
     */
    public function import_template(string $json_data);

    /**
     * Install pre-built system templates
     *
     * @return int Number of templates installed
     */
    public function install_system_templates(): int;

    /**
     * Get pre-built template definitions
     *
     * @return array
     */
    private function get_prebuilt_templates(): array;
}
```

**Pre-built Templates (Phase 2):**

| Template | Category | Key Features |
|----------|----------|--------------|
| FAQ Bot | support | Direct answers, source citations, helpfulness feedback |
| Customer Support | support | Ticket references, escalation flow, human handoff prompts |
| Product Advisor | sales | Needs assessment, product matching, comparison tables |
| Lead Capture | marketing | Multi-step forms, field validation, CRM integration hooks |

**Integration with Phase 1:**
- Extends `Chatbot` model structure
- Uses existing `style`, `messages_template`, `model_config` patterns
- Builds on `Chatbot::save()` for template application

---

#### 2.2.5 Export_Handler

**File:** `includes/core/class-export-handler.php`
**Namespace:** `AI_BotKit\Core`
**Status:** NEW (Phase 2)

**Responsibilities:**
- Generate PDF transcripts of conversations
- Apply branding (site logo, colors)
- Admin bulk export capabilities
- User self-service export

**Key Dependencies:**
```php
namespace AI_BotKit\Core;

use Dompdf\Dompdf;
use Dompdf\Options;

class Export_Handler {
    private $conversation_model;  // Conversation
    private $table_helper;        // Table_Helper

    /**
     * Export a single conversation to PDF
     *
     * @param int   $conversation_id
     * @param array $options {
     *     @type bool   $include_metadata  Include timestamps, user info
     *     @type bool   $include_branding  Include site logo/colors
     *     @type string $paper_size        'letter' or 'a4'
     * }
     * @return string|WP_Error PDF file path or error
     */
    public function export_to_pdf(int $conversation_id, array $options = []);

    /**
     * Generate PDF content for a conversation
     *
     * @param int   $conversation_id
     * @param array $options
     * @return string HTML content for PDF
     */
    private function generate_pdf_html(int $conversation_id, array $options): string;

    /**
     * Get PDF template
     *
     * @return string HTML template with placeholders
     */
    private function get_pdf_template(): string;

    /**
     * Stream PDF download to browser
     *
     * @param int    $conversation_id
     * @param string $filename
     * @param array  $options
     */
    public function stream_pdf(int $conversation_id, string $filename, array $options = []): void;

    /**
     * Check if user can export a conversation
     *
     * @param int $conversation_id
     * @param int $user_id
     * @return bool
     */
    public function can_export(int $conversation_id, int $user_id): bool;

    /**
     * Get site branding for PDF
     *
     * @return array {
     *     @type string $logo_url    Site logo URL
     *     @type string $site_name   Site name
     *     @type string $primary_color  Brand color
     * }
     */
    private function get_branding(): array;
}
```

**PDF Template Structure:**
```html
<!DOCTYPE html>
<html>
<head>
    <style>
        .header { /* Site branding */ }
        .message { /* Message bubble */ }
        .message.user { /* User message styling */ }
        .message.assistant { /* Bot message styling */ }
        .timestamp { /* Message timestamp */ }
        .footer { /* Page footer with page numbers */ }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{logo_url}}" alt="{{site_name}}">
        <h1>Chat Transcript</h1>
        <p>Conversation with {{chatbot_name}}</p>
        <p>Date: {{date}}</p>
    </div>
    <div class="messages">
        {{#messages}}
        <div class="message {{role}}">
            <div class="content">{{content}}</div>
            <div class="timestamp">{{timestamp}}</div>
        </div>
        {{/messages}}
    </div>
    <div class="footer">
        Generated by AI BotKit - Page {{page_number}}
    </div>
</body>
</html>
```

**Integration with Phase 1:**
- Uses `Conversation::get_messages()` for data retrieval
- Extends patterns from `handle_download_migration_log()`
- Requires Dompdf library addition to composer.json

---

#### 2.2.6 Recommendation_Engine

**File:** `includes/core/class-recommendation-engine.php`
**Namespace:** `AI_BotKit\Core`
**Status:** NEW (Phase 2)

**Responsibilities:**
- Generate product/course recommendations based on multiple signals
- Track user browsing behavior for personalization
- Integrate with WooCommerce and LearnDash
- Provide recommendation data for chat UI

**Key Dependencies:**
```php
namespace AI_BotKit\Core;

class Recommendation_Engine {
    private $wc_assistant;        // WooCommerce_Assistant
    private $learndash_int;       // LearnDash integration
    private $cache_manager;       // Unified_Cache_Manager
    private $table_helper;        // Table_Helper

    /**
     * Get recommendations for a user based on context
     *
     * @param int   $user_id
     * @param array $context {
     *     @type string $conversation_text  Recent conversation content
     *     @type int    $chatbot_id         Current chatbot
     *     @type string $intent             Detected intent (optional)
     * }
     * @param int   $limit   Maximum recommendations to return
     * @return array List of recommendation objects
     */
    public function get_recommendations(int $user_id, array $context, int $limit = 5): array;

    /**
     * Track user interaction for future recommendations
     *
     * @param int    $user_id
     * @param string $interaction_type  page_view|product_view|course_view|search|click
     * @param array  $data {
     *     @type int    $item_id    Product/Course/Post ID
     *     @type string $item_type  product|course|post
     *     @type array  $metadata   Additional context
     * }
     * @return bool
     */
    public function track_interaction(int $user_id, string $interaction_type, array $data): bool;

    /**
     * Get user's interaction history
     *
     * @param int $user_id
     * @param int $days_back  How many days of history to retrieve
     * @return array
     */
    public function get_user_interactions(int $user_id, int $days_back = 30): array;

    /**
     * Analyze conversation for recommendation signals
     *
     * @param string $conversation_text
     * @return array {
     *     @type array  $keywords     Extracted keywords
     *     @type array  $categories   Detected categories
     *     @type string $intent       Primary intent
     *     @type float  $confidence   Intent confidence score
     * }
     */
    public function analyze_conversation(string $conversation_text): array;

    /**
     * Get similar products based on viewing history
     *
     * @param int   $user_id
     * @param int   $limit
     * @return array WooCommerce product data
     */
    public function get_similar_products(int $user_id, int $limit = 5): array;

    /**
     * Get recommended courses based on interests
     *
     * @param int   $user_id
     * @param int   $limit
     * @return array LearnDash course data
     */
    public function get_recommended_courses(int $user_id, int $limit = 5): array;

    /**
     * Get complementary items based on purchases/enrollments
     *
     * @param int   $user_id
     * @param int   $limit
     * @return array Mixed product/course recommendations
     */
    public function get_complementary_items(int $user_id, int $limit = 5): array;

    /**
     * Score and rank recommendations
     *
     * @param array $items       Raw recommendation items
     * @param array $signals     User signals (interactions, purchases, etc.)
     * @return array Scored and sorted recommendations
     */
    private function score_recommendations(array $items, array $signals): array;

    /**
     * Format recommendations for chat UI
     *
     * @param array $recommendations
     * @return array {
     *     @type string $type          product|course
     *     @type string $title         Item title
     *     @type string $description   Short description
     *     @type string $image         Thumbnail URL
     *     @type string $price         Formatted price
     *     @type string $url           Item URL
     *     @type string $action_text   CTA text (Add to Cart, Enroll Now)
     *     @type string $action_url    Action URL
     * }
     */
    public function format_for_chat(array $recommendations): array;
}
```

**Recommendation Signals:**

| Signal | Weight | Source |
|--------|--------|--------|
| Conversation Context | 0.35 | Current chat analysis |
| Browsing History | 0.25 | User interactions table |
| Purchase/Enrollment History | 0.25 | WooCommerce/LearnDash |
| Explicit Request | 0.15 | Intent detection |

**Integration with Phase 1:**
- Extends `WooCommerce_Assistant::detect_shopping_intent()`
- Uses `ai_botkit_pre_response` filter for suggestion injection
- Integrates with LearnDash enrollment-aware context
- Stores interactions in new `ai_botkit_user_interactions` table

---

## 3. Integration Points (Phase 2)

### 3.1 Phase 2 Integration with Phase 1 Classes

```
Phase 2 Class Dependencies on Phase 1:

Chat_History_Handler
├── uses → Conversation (model)
├── uses → Table_Helper
├── uses → Unified_Cache_Manager
└── extends → conversation retrieval patterns

Search_Handler
├── uses → Table_Helper
├── uses → Unified_Cache_Manager
├── references → Retriever (search patterns)
└── extends → Database_Optimizer (new indexes)

Media_Handler
├── extends → Ajax_Handler::handle_upload_file()
├── uses → Document_Loader (URL processing)
├── uses → messages.metadata column
└── extends → upload directory structure

Template_Manager
├── uses → Chatbot (model)
├── uses → Table_Helper
├── extends → chatbot.style patterns
└── extends → chatbot.messages_template patterns

Export_Handler
├── uses → Conversation::get_messages()
├── references → handle_download_migration_log()
├── uses → Table_Helper
└── NEW dependency → Dompdf library

Recommendation_Engine
├── extends → WooCommerce_Assistant
├── extends → LearnDash integration
├── uses → ai_botkit_pre_response filter
├── uses → Unified_Cache_Manager
└── uses → Table_Helper
```

### 3.2 Component Interaction Diagram

```
                           ┌─────────────────────────────────────────────┐
                           │              FRONTEND (chat.js)             │
                           └──────────────────┬──────────────────────────┘
                                              │
        ┌─────────────┬───────────────┬──────┴──────┬───────────────┬─────────────┐
        │             │               │             │               │             │
        ▼             ▼               ▼             ▼               ▼             ▼
┌───────────┐ ┌───────────────┐ ┌──────────┐ ┌───────────┐ ┌───────────┐ ┌────────────┐
│ History   │ │ Search        │ │ Media    │ │ Templates │ │ Export    │ │ Recommend  │
│ Panel     │ │ Interface     │ │ Upload   │ │ Selector  │ │ Button    │ │ Cards      │
└─────┬─────┘ └───────┬───────┘ └────┬─────┘ └─────┬─────┘ └─────┬─────┘ └──────┬─────┘
      │               │              │             │             │              │
      │  AJAX/REST    │              │             │             │              │
      ▼               ▼              ▼             ▼             ▼              ▼
┌───────────────────────────────────────────────────────────────────────────────────────┐
│                              Ajax_Handler (Extended)                                   │
│  ┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐     │
│  │get_history_list │ │search_messages  │ │upload_media     │ │get_templates    │ ... │
│  └────────┬────────┘ └────────┬────────┘ └────────┬────────┘ └────────┬────────┘     │
└───────────┼───────────────────┼───────────────────┼───────────────────┼──────────────┘
            │                   │                   │                   │
            ▼                   ▼                   ▼                   ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│Chat_History_    │ │Search_Handler   │ │Media_Handler    │ │Template_Manager │
│Handler          │ │                 │ │                 │ │                 │
└────────┬────────┘ └────────┬────────┘ └────────┬────────┘ └────────┬────────┘
         │                   │                   │                   │
         ▼                   ▼                   ▼                   ▼
┌───────────────────────────────────────────────────────────────────────────────────────┐
│                                  DATA LAYER                                            │
│  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐ ┌──────────────┐│
│  │conversations │ │messages      │ │media         │ │templates     │ │user_         ││
│  │              │ │(+fulltext)   │ │(NEW)         │ │(NEW)         │ │interactions  ││
│  │              │ │              │ │              │ │              │ │(NEW)         ││
│  └──────────────┘ └──────────────┘ └──────────────┘ └──────────────┘ └──────────────┘│
└───────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 4. API Contracts (Phase 2)

### 4.1 New REST API Endpoints

**Namespace:** `ai-botkit/v1`

#### 4.1.1 Chat History Endpoints

```
GET /wp-json/ai-botkit/v1/history
Query Parameters:
  - chatbot_id: integer (optional) - Filter by chatbot
  - page: integer (default: 1) - Page number
  - per_page: integer (default: 10) - Items per page

Response (200):
{
  "conversations": [
    {
      "id": integer,
      "chatbot_id": integer,
      "chatbot_name": string,
      "preview": string,
      "message_count": integer,
      "last_activity": datetime,
      "created_at": datetime
    }
  ],
  "total": integer,
  "pages": integer,
  "current_page": integer
}

Permission: Logged-in users only (returns own history)
```

```
POST /wp-json/ai-botkit/v1/history/{id}/resume

Response (200):
{
  "conversation_id": integer,
  "messages": [...],
  "session_id": string
}

Response (403):
{
  "error": "You do not have permission to access this conversation"
}

Permission: Own conversations only
```

```
DELETE /wp-json/ai-botkit/v1/history/{id}/archive

Response (204): No content

Permission: Own conversations only
```

#### 4.1.2 Search Endpoints

```
GET /wp-json/ai-botkit/v1/search
Query Parameters:
  - q: string (required) - Search query
  - chatbot_id: integer (optional) - Filter by chatbot
  - user_id: integer (optional, admin only) - Filter by user
  - start_date: string (optional) - Y-m-d format
  - end_date: string (optional) - Y-m-d format
  - role: string (optional) - user|assistant
  - page: integer (default: 1)
  - per_page: integer (default: 20)

Response (200):
{
  "results": [
    {
      "message_id": integer,
      "conversation_id": integer,
      "chatbot_name": string,
      "role": string,
      "content": string,
      "content_highlighted": string,
      "created_at": datetime,
      "relevance_score": float
    }
  ],
  "total": integer,
  "pages": integer,
  "search_time": float
}

Permission:
  - Admin: can search all with user_id filter
  - User: automatically filtered to own messages
```

```
GET /wp-json/ai-botkit/v1/search/suggestions
Query Parameters:
  - q: string (required) - Partial query

Response (200):
{
  "suggestions": [string]
}

Permission: Logged-in users
```

#### 4.1.3 Media Endpoints

```
POST /wp-json/ai-botkit/v1/media/upload
Content-Type: multipart/form-data

Request:
  - file: File (required)
  - conversation_id: integer (optional)

Response (201):
{
  "id": integer,
  "url": string,
  "type": "image"|"video"|"document",
  "filename": string,
  "size": integer,
  "metadata": {
    "width": integer,
    "height": integer,
    "mime_type": string
  }
}

Response (400):
{
  "error": "File type not allowed" | "File too large"
}

Permission: Logged-in users
```

```
GET /wp-json/ai-botkit/v1/media/link-preview
Query Parameters:
  - url: string (required)

Response (200):
{
  "title": string,
  "description": string,
  "image": string,
  "site_name": string,
  "url": string
}

Permission: Logged-in users
```

#### 4.1.4 Template Endpoints

```
GET /wp-json/ai-botkit/v1/templates
Query Parameters:
  - category: string (optional)

Response (200):
[
  {
    "id": integer,
    "name": string,
    "description": string,
    "category": string,
    "thumbnail": string,
    "is_system": boolean,
    "created_at": datetime
  }
]

Permission: manage_ai_botkit capability
```

```
GET /wp-json/ai-botkit/v1/templates/{id}

Response (200):
{
  "id": integer,
  "name": string,
  "description": string,
  "category": string,
  "style": {...},
  "messages_template": {...},
  "model_config": {...},
  "conversation_starters": [...],
  "thumbnail": string,
  "is_system": boolean,
  "created_at": datetime,
  "updated_at": datetime
}

Permission: manage_ai_botkit capability
```

```
POST /wp-json/ai-botkit/v1/templates

Request:
{
  "name": string (required),
  "description": string,
  "category": string,
  "style": {...},
  "messages_template": {...},
  "model_config": {...},
  "conversation_starters": [...]
}

Response (201):
{
  "id": integer,
  "message": "Template created successfully"
}

Permission: manage_ai_botkit capability
```

```
PUT /wp-json/ai-botkit/v1/templates/{id}

Request: (same as POST)

Response (200):
{
  "id": integer,
  "message": "Template updated successfully"
}

Permission: manage_ai_botkit capability (cannot edit system templates)
```

```
DELETE /wp-json/ai-botkit/v1/templates/{id}

Response (204): No content

Permission: manage_ai_botkit capability (cannot delete system templates)
```

```
POST /wp-json/ai-botkit/v1/templates/{id}/apply

Request:
{
  "chatbot_id": integer (required),
  "merge": boolean (default: true)
}

Response (200):
{
  "message": "Template applied successfully",
  "chatbot_id": integer
}

Permission: manage_ai_botkit capability
```

#### 4.1.5 Export Endpoints

```
GET /wp-json/ai-botkit/v1/export/{conversation_id}/pdf
Query Parameters:
  - include_metadata: boolean (default: true)
  - include_branding: boolean (default: true)
  - paper_size: string (default: "letter")

Response: PDF file stream (Content-Type: application/pdf)

Permission:
  - Admin: any conversation
  - User: own conversations only
```

#### 4.1.6 Recommendations Endpoints

```
GET /wp-json/ai-botkit/v1/recommendations
Query Parameters:
  - chatbot_id: integer (optional)
  - conversation_id: integer (optional)
  - limit: integer (default: 5)

Response (200):
{
  "recommendations": [
    {
      "type": "product"|"course",
      "id": integer,
      "title": string,
      "description": string,
      "image": string,
      "price": string,
      "url": string,
      "action_text": string,
      "action_url": string,
      "score": float
    }
  ]
}

Permission: Logged-in users
```

```
POST /wp-json/ai-botkit/v1/recommendations/track

Request:
{
  "interaction_type": "page_view"|"product_view"|"course_view"|"click",
  "item_id": integer,
  "item_type": "product"|"course"|"post",
  "metadata": {...}
}

Response (201):
{
  "success": true
}

Permission: Logged-in users
```

### 4.2 New AJAX Endpoints

**Requirement:** All require `check_ajax_referer('ai_botkit_nonce', 'nonce')`

#### 4.2.1 Public AJAX Actions (Phase 2)

| Action | Purpose | Parameters |
|--------|---------|------------|
| `ai_botkit_get_history_list` | Get conversation list | page, per_page, chatbot_id |
| `ai_botkit_resume_conversation` | Resume a conversation | conversation_id |
| `ai_botkit_search_messages` | Search chat history | q, filters, page |
| `ai_botkit_upload_chat_media` | Upload chat attachment | file (multipart) |
| `ai_botkit_get_link_preview` | Get URL preview | url |
| `ai_botkit_get_recommendations` | Get suggestions | chatbot_id, conversation_id |
| `ai_botkit_track_interaction` | Track user action | type, item_id, item_type |
| `ai_botkit_export_pdf` | Export conversation PDF | conversation_id, options |

#### 4.2.2 Admin AJAX Actions (Phase 2)

| Action | Purpose | Parameters |
|--------|---------|------------|
| `ai_botkit_get_templates` | List all templates | category |
| `ai_botkit_get_template` | Get single template | template_id |
| `ai_botkit_save_template` | Create/update template | template_data |
| `ai_botkit_delete_template` | Delete template | template_id |
| `ai_botkit_apply_template` | Apply to chatbot | template_id, chatbot_id, merge |
| `ai_botkit_create_template_from_chatbot` | Create from chatbot | chatbot_id, name |
| `ai_botkit_export_template` | Export template JSON | template_id |
| `ai_botkit_import_template` | Import template JSON | json_data |
| `ai_botkit_admin_search` | Search all messages | q, filters |
| `ai_botkit_bulk_export` | Bulk export conversations | conversation_ids, format |

---

## 5. Hook Architecture (Extensibility)

### 5.1 Phase 2 WordPress Filters

| Filter | Purpose | Parameters | Return |
|--------|---------|------------|--------|
| `ai_botkit_history_query` | Modify history query | $query_args, $user_id | $query_args |
| `ai_botkit_history_item` | Modify history list item | $item, $conversation | $item |
| `ai_botkit_search_query` | Modify search query | $query, $filters | $query |
| `ai_botkit_search_result` | Modify search result item | $result, $raw_data | $result |
| `ai_botkit_allowed_media_types` | Modify allowed uploads | $mime_types | $mime_types |
| `ai_botkit_media_upload_path` | Modify upload path | $path, $file_type | $path |
| `ai_botkit_link_preview_data` | Modify link preview | $preview_data, $url | $preview_data |
| `ai_botkit_template_data` | Modify template on save | $template_data | $template_data |
| `ai_botkit_apply_template` | Modify template application | $config, $template, $chatbot | $config |
| `ai_botkit_pdf_template` | Modify PDF HTML template | $html, $conversation | $html |
| `ai_botkit_pdf_styles` | Modify PDF CSS | $css | $css |
| `ai_botkit_export_filename` | Modify export filename | $filename, $conversation_id | $filename |
| `ai_botkit_recommendation_signals` | Modify recommendation weights | $signals, $user_id | $signals |
| `ai_botkit_recommendations` | Modify recommendation list | $recommendations, $context | $recommendations |
| `ai_botkit_recommendation_item` | Modify single recommendation | $item, $raw_data | $item |
| `ai_botkit_can_search_all` | Override search permissions | $can_search, $user_id | $can_search |
| `ai_botkit_can_export` | Override export permissions | $can_export, $conversation_id, $user_id | $can_export |

### 5.2 Phase 2 WordPress Actions

| Action | Purpose | Parameters |
|--------|---------|------------|
| `ai_botkit_conversation_resumed` | After conversation resumed | $conversation_id, $user_id |
| `ai_botkit_conversation_archived` | After conversation archived | $conversation_id, $user_id |
| `ai_botkit_search_performed` | After search executed | $query, $results_count, $user_id |
| `ai_botkit_media_uploaded` | After media upload | $media_id, $file_data, $user_id |
| `ai_botkit_media_deleted` | After media deleted | $media_id |
| `ai_botkit_template_created` | After template created | $template_id, $template_data |
| `ai_botkit_template_updated` | After template updated | $template_id, $changes |
| `ai_botkit_template_deleted` | After template deleted | $template_id |
| `ai_botkit_template_applied` | After template applied | $template_id, $chatbot_id |
| `ai_botkit_pdf_exported` | After PDF generated | $conversation_id, $file_path, $user_id |
| `ai_botkit_interaction_tracked` | After interaction tracked | $user_id, $type, $data |
| `ai_botkit_recommendations_generated` | After recommendations generated | $user_id, $recommendations |

### 5.3 Hook Usage Examples

**Example 1: Add custom media type support**
```php
add_filter('ai_botkit_allowed_media_types', function($types) {
    $types['audio/mpeg'] = true;  // Allow MP3 uploads
    $types['audio/wav'] = true;   // Allow WAV uploads
    return $types;
});
```

**Example 2: Custom recommendation scoring**
```php
add_filter('ai_botkit_recommendation_signals', function($signals, $user_id) {
    // Boost products in user's favorite category
    $favorite_category = get_user_meta($user_id, 'favorite_product_category', true);
    if ($favorite_category) {
        $signals['category_affinity'] = [
            'weight' => 0.2,
            'category_id' => $favorite_category
        ];
    }
    return $signals;
}, 10, 2);
```

**Example 3: Custom PDF branding**
```php
add_filter('ai_botkit_pdf_styles', function($css) {
    return $css . '
        .header { background-color: #1a365d; }
        .message.assistant { background-color: #e6f2ff; }
    ';
});
```

**Example 4: Track custom analytics on export**
```php
add_action('ai_botkit_pdf_exported', function($conversation_id, $file_path, $user_id) {
    // Log export for compliance
    error_log(sprintf(
        '[AI BotKit] User %d exported conversation %d',
        $user_id,
        $conversation_id
    ));

    // Track in analytics
    do_action('custom_analytics_event', 'chat_export', [
        'conversation_id' => $conversation_id,
        'user_id' => $user_id
    ]);
}, 10, 3);
```

---

## 6. Data Flow Diagrams (Phase 2)

### 6.1 Chat History Flow

```
[User Opens History Panel]
        |
        v
[Ajax: ai_botkit_get_history_list]
        |
        v
+------------------+
|Chat_History_     |
|Handler::         |
|get_user_history()|
+--------+---------+
         |
         v
+------------------+     +------------------+
|Check Cache       |---->|Return cached     |
|(Cache_Manager)   |yes  |results           |
+--------+---------+     +------------------+
         |no
         v
+------------------+
|Query conversations|
|JOIN messages     |
|(preview data)    |
+--------+---------+
         |
         v
+------------------+
|Format results    |
|Add metadata      |
+--------+---------+
         |
         v
+------------------+
|Cache results     |
|TTL: 2 min        |
+--------+---------+
         |
         v
[JSON Response to Frontend]
        |
        v
[Render History Panel]
```

### 6.2 Search Flow

```
[User Enters Search Query]
        |
        v
[Ajax: ai_botkit_search_messages]
        |
        v
+------------------+
|Search_Handler::  |
|search()          |
+--------+---------+
         |
         v
+------------------+
|Check permissions |
|can_search_all()  |
+--------+---------+
         |
    +----+----+
    |         |
   Admin     User
    |         |
    v         v
[No filter]  [Add user_id filter]
    |         |
    +----+----+
         |
         v
+------------------+
|Build FULLTEXT    |
|query with filters|
+--------+---------+
         |
         v
+------------------+
|Execute search    |
|(MATCH...AGAINST) |
+--------+---------+
         |
         v
+------------------+
|Score & rank      |
|results           |
+--------+---------+
         |
         v
+------------------+
|Highlight matches |
|in content        |
+--------+---------+
         |
         v
[Return paginated results]
```

### 6.3 Media Upload Flow

```
[User Selects File in Chat]
        |
        v
[Ajax: ai_botkit_upload_chat_media]
        |
        v
+------------------+
|Media_Handler::   |
|upload_media()    |
+--------+---------+
         |
         v
+------------------+
|validate_file()   |
|- Check MIME type |
|- Check file size |
+--------+---------+
         |
    +----+----+
    |         |
  Valid     Invalid
    |         |
    |         v
    |    [Return WP_Error]
    |
    v
+------------------+
|Generate unique   |
|filename          |
+--------+---------+
         |
         v
+------------------+
|Move to uploads   |
|/ai-botkit/       |
|chat-media/{type}/|
+--------+---------+
         |
         v
+------------------+
|Create media      |
|record in DB      |
+--------+---------+
         |
         v
+------------------+
|Generate metadata |
|- Dimensions      |
|- Duration        |
|- Thumbnails      |
+--------+---------+
         |
         v
[Return media data to frontend]
        |
        v
[Display in chat message]
```

### 6.4 Recommendation Flow

```
[Chat Message Received]
        |
        v
+------------------+
|ai_botkit_pre_    |
|response filter   |
+--------+---------+
         |
         v
+------------------+
|Recommendation_   |
|Engine::get_      |
|recommendations() |
+--------+---------+
         |
         v
+------------------+         +------------------+
|Gather Signals    |-------->|1. Analyze        |
|                  |         |   conversation   |
+------------------+         +------------------+
         |                            |
         |                   +------------------+
         |------------------>|2. Get user       |
         |                   |   interactions   |
         |                   +------------------+
         |                            |
         |                   +------------------+
         |------------------>|3. Get purchase/  |
         |                   |   enrollment hx  |
         |                   +------------------+
         |                            |
         v                            v
+------------------+
|score_            |
|recommendations() |
+--------+---------+
         |
         v
+------------------+
|format_for_chat() |
+--------+---------+
         |
         v
+------------------+
|Filter: ai_botkit_|
|recommendations   |
+--------+---------+
         |
         v
[Inject into response]
        |
        v
[Display suggestion cards]
```

---

## 7. Security Architecture (Phase 2 Extensions)

### 7.1 New Permission Checks

| Capability | Default Roles | Purpose |
|------------|---------------|---------|
| `view_ai_botkit_history` | subscriber+ | View own chat history |
| `search_ai_botkit_all` | administrator | Search all user conversations |
| `export_ai_botkit_all` | administrator | Export any conversation |
| `manage_ai_botkit_templates` | administrator | Create/edit/delete templates |
| `upload_ai_botkit_media` | subscriber+ | Upload chat media |

### 7.2 Media Security

```php
// File validation in Media_Handler
private function validate_file(array $file): bool|WP_Error {
    // 1. Check file size
    if ($file['size'] > self::MAX_FILE_SIZE) {
        return new WP_Error('file_too_large', 'File exceeds maximum size');
    }

    // 2. Verify MIME type (not just extension)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);

    // 3. Check against whitelist
    $allowed = apply_filters('ai_botkit_allowed_media_types', [
        ...self::ALLOWED_IMAGE_TYPES,
        ...self::ALLOWED_VIDEO_TYPES,
        ...self::ALLOWED_DOC_TYPES
    ]);

    if (!in_array($mime_type, $allowed)) {
        return new WP_Error('invalid_type', 'File type not allowed');
    }

    // 4. Additional security scans
    if ($this->contains_php_code($file['tmp_name'])) {
        return new WP_Error('security_threat', 'File contains executable code');
    }

    return true;
}
```

### 7.3 Search Security

```php
// User scoping in Search_Handler
public function search(string $query, array $filters = [], ...): array {
    $user_id = get_current_user_id();

    // Non-admins can only search their own messages
    if (!$this->can_search_all($user_id)) {
        $filters['user_id'] = $user_id;
    }

    // Sanitize search query
    $query = sanitize_text_field($query);

    // Prevent SQL injection in fulltext search
    $query = $this->escape_fulltext_query($query);

    // ...
}
```

---

## 8. Performance Architecture (Phase 2 Extensions)

### 8.1 New Cache Groups

| Cache Group | TTL | Purpose |
|-------------|-----|---------|
| `history` | 2 min | User history lists |
| `search` | 5 min | Search results |
| `link_preview` | 24 hours | URL preview data |
| `recommendations` | 10 min | User recommendations |
| `templates` | 30 min | Template listings |

### 8.2 New Database Indexes

```sql
-- Search performance
ALTER TABLE {prefix}ai_botkit_messages
ADD FULLTEXT INDEX ft_content (content);

-- History queries
ALTER TABLE {prefix}ai_botkit_conversations
ADD INDEX idx_user_updated (user_id, updated_at DESC);

-- Media lookups
ALTER TABLE {prefix}ai_botkit_media
ADD INDEX idx_message_type (message_id, media_type);

-- Recommendation queries
ALTER TABLE {prefix}ai_botkit_user_interactions
ADD INDEX idx_user_time (user_id, created_at DESC);
ADD INDEX idx_item (item_type, item_id);
```

### 8.3 Optimization Strategies

| Feature | Strategy |
|---------|----------|
| History | Cache per-user, invalidate on new message |
| Search | Cache by query hash, pagination included |
| Media | Lazy load thumbnails, CDN integration ready |
| Templates | Cache globally, invalidate on change |
| Recommendations | Cache per-user, 10-min TTL |
| PDF Export | Generate on-demand, no caching |

---

## 9. File Structure (Phase 1 + Phase 2)

```
ai-botkit-chatbot/
├── knowVault.php                           # Main plugin entry point
├── includes/
│   ├── class-ai-botkit.php                # Bootstrap class
│   ├── class-ai-botkit-activator.php      # Activation handler (EXTENDED)
│   ├── class-ai-botkit-deactivator.php    # Deactivation handler
│   ├── core/
│   │   ├── class-rag-engine.php           # Main orchestrator
│   │   ├── class-llm-client.php           # Multi-provider LLM
│   │   ├── class-vector-database.php      # Local vector storage
│   │   ├── class-pinecone-database.php    # Pinecone integration
│   │   ├── class-retriever.php            # Context retrieval
│   │   ├── class-document-loader.php      # Document loading
│   │   ├── class-text-chunker.php         # Text splitting
│   │   ├── class-embeddings-generator.php # Embeddings
│   │   ├── class-rate-limiter.php         # Rate limiting
│   │   ├── class-unified-cache-manager.php # Caching
│   │   │
│   │   │   # PHASE 2 NEW FILES
│   │   ├── class-chat-history-handler.php # History management
│   │   ├── class-search-handler.php       # Message search
│   │   ├── class-media-handler.php        # Media uploads
│   │   ├── class-template-manager.php     # Template CRUD
│   │   ├── class-export-handler.php       # PDF export
│   │   └── class-recommendation-engine.php # Suggestions
│   │
│   ├── admin/
│   │   ├── class-admin.php                # Admin controller
│   │   ├── class-ajax-handler.php         # Admin AJAX (EXTENDED)
│   │   └── views/
│   │       └── templates/                 # NEW: Template builder UI
│   │
│   ├── public/
│   │   ├── class-public.php               # Frontend controller
│   │   └── class-ajax-handler.php         # Public AJAX (EXTENDED)
│   │
│   ├── models/
│   │   ├── class-chatbot.php              # Chatbot entity
│   │   ├── class-conversation.php         # Conversation entity
│   │   ├── class-template.php             # NEW: Template entity
│   │   └── class-media.php                # NEW: Media entity
│   │
│   ├── integration/
│   │   ├── class-rest-api.php             # REST endpoints (EXTENDED)
│   │   ├── class-wordpress-content.php    # WP content sync
│   │   ├── class-learndash.php            # LearnDash integration
│   │   ├── class-woocommerce.php          # WooCommerce sync
│   │   └── class-woocommerce-assistant.php # Shopping assistant (EXTENDED)
│   │
│   ├── monitoring/
│   │   ├── class-health-checks.php        # Health monitoring
│   │   └── class-analytics.php            # Analytics tracking
│   │
│   ├── utils/
│   │   └── class-table-helper.php         # Database utilities
│   │
│   └── vendor/                            # Composer dependencies
│       └── dompdf/                        # NEW: PDF library
│
├── admin/
│   ├── css/
│   │   └── template-builder.css           # NEW: Template UI styles
│   └── js/
│       └── template-builder.js            # NEW: Template UI scripts
│
├── public/
│   ├── css/
│   │   ├── chat.css                       # Chat widget styles (EXTENDED)
│   │   ├── history-panel.css              # NEW: History panel styles
│   │   └── recommendation-cards.css       # NEW: Suggestion card styles
│   ├── js/
│   │   ├── chat.js                        # Chat widget (EXTENDED)
│   │   ├── history.js                     # NEW: History panel logic
│   │   ├── media-upload.js                # NEW: Media upload handling
│   │   └── recommendations.js             # NEW: Suggestion display
│   └── templates/
│       ├── pdf/
│       │   └── transcript.php             # NEW: PDF template
│       └── email/                         # Future: email templates
│
└── data/
    └── templates/                         # NEW: Pre-built template JSON files
        ├── faq-bot.json
        ├── customer-support.json
        ├── product-advisor.json
        └── lead-capture.json
```

---

## 10. Migration and Backward Compatibility

### 10.1 Database Migration (Phase 2)

```php
// In class-ai-botkit-activator.php
public static function activate_phase_2(): void {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $prefix = $wpdb->prefix;

    // 1. Add fulltext index to messages (if not exists)
    $wpdb->query("
        ALTER TABLE {$prefix}ai_botkit_messages
        ADD FULLTEXT INDEX ft_content (content)
    ");

    // 2. Create templates table
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}ai_botkit_templates ...");

    // 3. Create media table
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}ai_botkit_media ...");

    // 4. Create user_interactions table
    $wpdb->query("CREATE TABLE IF NOT EXISTS {$prefix}ai_botkit_user_interactions ...");

    // 5. Add new indexes to conversations
    $wpdb->query("
        ALTER TABLE {$prefix}ai_botkit_conversations
        ADD INDEX idx_user_updated (user_id, updated_at DESC)
    ");

    // 6. Install system templates
    $template_manager = new Template_Manager();
    $template_manager->install_system_templates();

    // 7. Update version
    update_option('ai_botkit_version', '2.0.0');
}
```

### 10.2 Backward Compatibility

| Phase 1 Component | Phase 2 Impact | Compatibility |
|-------------------|----------------|---------------|
| Conversation model | Extended with archive flag | Full backward compat |
| messages table | Added fulltext index | No schema change |
| Ajax endpoints | New endpoints added | Existing unchanged |
| REST API | New endpoints added | Existing unchanged |
| Chatbot model | Template relationship added | Full backward compat |
| WooCommerce_Assistant | Extended methods | Full backward compat |

### 10.3 Version Detection

```php
// Check phase version
$version = get_option('ai_botkit_version', '1.0.0');
$is_phase_2 = version_compare($version, '2.0.0', '>=');

// Feature flags
$features = [
    'history_panel' => $is_phase_2,
    'search' => $is_phase_2,
    'media_upload' => $is_phase_2,
    'templates' => $is_phase_2,
    'pdf_export' => $is_phase_2,
    'recommendations' => $is_phase_2,
];
```

---

## 11. Appendix: Quick Reference

### 11.1 Phase 2 Classes Summary

| Class | File | Purpose |
|-------|------|---------|
| `Chat_History_Handler` | `core/class-chat-history-handler.php` | History management |
| `Search_Handler` | `core/class-search-handler.php` | Fulltext search |
| `Media_Handler` | `core/class-media-handler.php` | Media uploads |
| `Template_Manager` | `core/class-template-manager.php` | Template CRUD |
| `Export_Handler` | `core/class-export-handler.php` | PDF generation |
| `Recommendation_Engine` | `core/class-recommendation-engine.php` | Suggestions |
| `Template` | `models/class-template.php` | Template entity |
| `Media` | `models/class-media.php` | Media entity |

### 11.2 New Database Tables

| Table | Purpose |
|-------|---------|
| `ai_botkit_templates` | Template configurations |
| `ai_botkit_media` | Chat media attachments |
| `ai_botkit_user_interactions` | Recommendation tracking |

### 11.3 New WordPress Options

| Option | Type | Purpose |
|--------|------|---------|
| `ai_botkit_history_per_page` | int | Default history pagination |
| `ai_botkit_search_per_page` | int | Default search pagination |
| `ai_botkit_max_media_size` | int | Max upload size (bytes) |
| `ai_botkit_allowed_media_types` | array | Allowed MIME types |
| `ai_botkit_recommendation_enabled` | bool | Enable recommendations |
| `ai_botkit_pdf_paper_size` | string | Default PDF size |

---

*Architecture Document - Phase 1 + Phase 2*
*Last Updated: 2026-01-28*
