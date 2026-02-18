# AI BotKit Chatbot - Data Model Documentation

> **Version:** 2.0 (Phase 1 + Phase 2)
> **Last Updated:** 2026-01-28
> **Status:** Extended for Phase 2

---

## Document Status

| Section | Status | Confidence |
|---------|--------|------------|
| Phase 1 Entities | RECOVERED | 95% |
| Phase 1 Relationships | RECOVERED | 90% |
| Phase 2 New Tables | DESIGNED | 95% |
| Phase 2 Schema Modifications | DESIGNED | 95% |
| Phase 2 Indexes | DESIGNED | 95% |
| Data Lifecycle | DESIGNED | 90% |

---

## 1. Data Model Overview

### 1.1 Complete Entity Summary (Phase 1 + Phase 2)

| Entity | Table Name | Purpose | Phase |
|--------|------------|---------|-------|
| Document | `ai_botkit_documents` | Source document metadata | 1 |
| Document Metadata | `ai_botkit_document_metadata` | Extended document properties | 1 |
| Chunk | `ai_botkit_chunks` | Text segments for embedding | 1 |
| Embedding | `ai_botkit_embeddings` | Vector representations | 1 |
| Chatbot | `ai_botkit_chatbots` | Chatbot configuration | 1 |
| Conversation | `ai_botkit_conversations` | Chat session data | 1 |
| Message | `ai_botkit_messages` | Individual chat messages | 1 (Extended) |
| Content Relationship | `ai_botkit_content_relationships` | Entity associations | 1 |
| Analytics | `ai_botkit_analytics` | Usage events | 1 |
| WP Content | `ai_botkit_wp_content` | WordPress content sync queue | 1 |
| **Template** | `ai_botkit_templates` | Conversation templates | **2 (NEW)** |
| **Media** | `ai_botkit_media` | Chat media attachments | **2 (NEW)** |
| **User Interaction** | `ai_botkit_user_interactions` | Recommendation tracking | **2 (NEW)** |

### 1.2 Table Prefix

**Standard Prefix:** `ai_botkit_`
**Migration Support:** Tables support migration to `knowvault_` prefix via `Table_Helper` class.

---

## 2. Phase 1 Entity Descriptions

*(Existing entities from Phase 1 - see RECOVERED_DATA_MODEL.md for complete details)*

### 2.1 Quick Reference

| Table | Key Columns | Primary Use |
|-------|-------------|-------------|
| `ai_botkit_documents` | id, title, source_type, status | Document tracking |
| `ai_botkit_document_metadata` | document_id, meta_key, meta_value | Extensible metadata |
| `ai_botkit_chunks` | document_id, content, metadata | Text segments |
| `ai_botkit_embeddings` | chunk_id, embedding, model | Vector storage |
| `ai_botkit_chatbots` | name, style, model_config | Bot configuration |
| `ai_botkit_conversations` | chatbot_id, user_id, session_id | Chat sessions |
| `ai_botkit_messages` | conversation_id, role, content | Chat messages |
| `ai_botkit_content_relationships` | source_type/id, target_type/id | Many-to-many links |
| `ai_botkit_analytics` | chatbot_id, event_type, event_data | Event tracking |
| `ai_botkit_wp_content` | post_id, post_type, status | Sync queue |

---

## 3. Phase 2 New Tables

### 3.1 Template Entity

**Table:** `{prefix}ai_botkit_templates`
**Status:** NEW (Phase 2)

**Purpose:** Stores reusable chatbot configuration templates that can be applied to new or existing chatbots.

#### Schema Definition

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | BIGINT(20) UNSIGNED | NO | AUTO_INCREMENT | Primary key |
| `name` | VARCHAR(255) | NO | - | Template name |
| `description` | TEXT | YES | NULL | Template description |
| `category` | VARCHAR(50) | NO | 'general' | Template category |
| `style` | JSON | YES | NULL | UI styling configuration |
| `messages_template` | JSON | YES | NULL | Message templates (greeting, fallback, etc.) |
| `model_config` | JSON | YES | NULL | LLM configuration |
| `conversation_starters` | JSON | YES | NULL | Suggested opening prompts |
| `thumbnail` | VARCHAR(255) | YES | NULL | Preview image URL |
| `is_system` | TINYINT(1) | NO | 0 | System template flag (non-deletable) |
| `is_active` | TINYINT(1) | NO | 1 | Template visibility |
| `usage_count` | INT | NO | 0 | Times applied to chatbots |
| `created_by` | BIGINT(20) UNSIGNED | YES | NULL | Creator user ID |
| `created_at` | DATETIME | NO | CURRENT_TIMESTAMP | Creation timestamp |
| `updated_at` | DATETIME | NO | CURRENT_TIMESTAMP | Last update timestamp |

#### SQL Creation

```sql
CREATE TABLE IF NOT EXISTS {prefix}ai_botkit_templates (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(50) NOT NULL DEFAULT 'general',
    style JSON,
    messages_template JSON,
    model_config JSON,
    conversation_starters JSON,
    thumbnail VARCHAR(255),
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    usage_count INT NOT NULL DEFAULT 0,
    created_by BIGINT(20) UNSIGNED,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_category (category),
    INDEX idx_is_system (is_system),
    INDEX idx_is_active (is_active),
    INDEX idx_created_by (created_by)
) {charset_collate};
```

#### JSON Structure: `style`

```json
{
  "primaryColor": "#0066cc",
  "secondaryColor": "#f0f4f8",
  "backgroundColor": "#ffffff",
  "textColor": "#333333",
  "fontFamily": "system-ui, -apple-system, sans-serif",
  "fontSize": "14px",
  "borderRadius": "8px",
  "position": "bottom-right",
  "widgetWidth": "380px",
  "headerStyle": {
    "backgroundColor": "#0066cc",
    "textColor": "#ffffff",
    "showAvatar": true
  },
  "messageStyle": {
    "userBubbleColor": "#0066cc",
    "userTextColor": "#ffffff",
    "botBubbleColor": "#f0f4f8",
    "botTextColor": "#333333"
  }
}
```

#### JSON Structure: `messages_template`

```json
{
  "greeting": "Hello! How can I help you today?",
  "fallback": "I'm sorry, I don't have information about that. Would you like to speak with a human?",
  "error": "Something went wrong. Please try again.",
  "thinking": "Thinking...",
  "offline": "I'm currently offline. Please leave a message.",
  "rateLimit": "You've reached your message limit. Please try again later.",
  "handoff": "Let me connect you with a human agent.",
  "goodbye": "Thank you for chatting! Have a great day."
}
```

#### JSON Structure: `model_config`

```json
{
  "model": "gpt-4o-mini",
  "temperature": 0.7,
  "max_tokens": 1000,
  "context_length": 5,
  "min_chunk_relevance": 0.2,
  "personality": "You are a helpful and friendly assistant.",
  "tone": "professional",
  "instructions": [
    "Always be polite and helpful",
    "Cite sources when providing information",
    "Ask clarifying questions when needed"
  ],
  "banned_topics": [],
  "required_keywords": []
}
```

#### JSON Structure: `conversation_starters`

```json
[
  {
    "text": "What can you help me with?",
    "icon": "help-circle"
  },
  {
    "text": "Tell me about your products",
    "icon": "shopping-bag"
  },
  {
    "text": "I need support",
    "icon": "headphones"
  }
]
```

#### Categories

| Category | Description | Pre-built Templates |
|----------|-------------|---------------------|
| `support` | Customer support use cases | FAQ Bot, Customer Support |
| `sales` | Sales and product guidance | Product Advisor |
| `marketing` | Lead generation and engagement | Lead Capture |
| `education` | Learning and onboarding | Course Advisor |
| `general` | General purpose | Default |

#### Indexes

| Index Name | Columns | Type | Purpose |
|------------|---------|------|---------|
| PRIMARY | id | Primary Key | Record lookup |
| idx_category | category | Non-unique | Filter by category |
| idx_is_system | is_system | Non-unique | Filter system templates |
| idx_is_active | is_active | Non-unique | Filter active templates |
| idx_created_by | created_by | Non-unique | Filter by creator |

---

### 3.2 Media Entity

**Table:** `{prefix}ai_botkit_media`
**Status:** NEW (Phase 2)

**Purpose:** Stores metadata for media files uploaded as chat attachments (images, videos, documents).

#### Schema Definition

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | BIGINT(20) UNSIGNED | NO | AUTO_INCREMENT | Primary key |
| `message_id` | BIGINT(20) UNSIGNED | YES | NULL | Associated message ID |
| `conversation_id` | BIGINT(20) UNSIGNED | YES | NULL | Associated conversation ID |
| `user_id` | BIGINT(20) UNSIGNED | NO | - | Uploader user ID |
| `media_type` | VARCHAR(20) | NO | - | Type: image, video, document, link |
| `file_name` | VARCHAR(255) | NO | - | Original filename |
| `file_path` | VARCHAR(500) | NO | - | Server file path |
| `file_url` | VARCHAR(500) | NO | - | Public URL |
| `mime_type` | VARCHAR(100) | NO | - | MIME type |
| `file_size` | BIGINT(20) | NO | 0 | File size in bytes |
| `metadata` | JSON | YES | NULL | Type-specific metadata |
| `status` | VARCHAR(20) | NO | 'active' | Status: active, deleted, orphaned |
| `created_at` | DATETIME | NO | CURRENT_TIMESTAMP | Upload timestamp |
| `updated_at` | DATETIME | NO | CURRENT_TIMESTAMP | Last update timestamp |

#### SQL Creation

```sql
CREATE TABLE IF NOT EXISTS {prefix}ai_botkit_media (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    message_id BIGINT(20) UNSIGNED,
    conversation_id BIGINT(20) UNSIGNED,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    media_type VARCHAR(20) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_url VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size BIGINT(20) NOT NULL DEFAULT 0,
    metadata JSON,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_message (message_id),
    INDEX idx_conversation (conversation_id),
    INDEX idx_user (user_id),
    INDEX idx_type (media_type),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) {charset_collate};
```

#### Media Types

| Type | MIME Types | Metadata Fields |
|------|------------|-----------------|
| `image` | image/jpeg, image/png, image/gif, image/webp | width, height, thumbnail_url |
| `video` | video/mp4, video/webm | width, height, duration, thumbnail_url |
| `document` | application/pdf, text/plain | page_count (PDF), word_count |
| `link` | (external URLs) | title, description, image, site_name |

#### JSON Structure: `metadata` (Image)

```json
{
  "width": 1920,
  "height": 1080,
  "thumbnail_url": "/wp-content/uploads/ai-botkit/chat-media/images/2026/01/thumb_abc123.jpg",
  "alt_text": "User uploaded image",
  "orientation": "landscape"
}
```

#### JSON Structure: `metadata` (Video)

```json
{
  "width": 1920,
  "height": 1080,
  "duration": 125.5,
  "thumbnail_url": "/wp-content/uploads/ai-botkit/chat-media/videos/2026/01/thumb_xyz789.jpg",
  "provider": null
}
```

#### JSON Structure: `metadata` (Video Embed)

```json
{
  "provider": "youtube",
  "video_id": "dQw4w9WgXcQ",
  "embed_url": "https://www.youtube.com/embed/dQw4w9WgXcQ",
  "thumbnail_url": "https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg",
  "title": "Video Title",
  "duration": 212
}
```

#### JSON Structure: `metadata` (Link Preview)

```json
{
  "title": "Page Title",
  "description": "Page description from meta tags",
  "image": "https://example.com/og-image.jpg",
  "site_name": "Example Site",
  "url": "https://example.com/page",
  "favicon": "https://example.com/favicon.ico"
}
```

#### Status Values

| Status | Description |
|--------|-------------|
| `active` | Normal, accessible media |
| `deleted` | Soft-deleted, pending cleanup |
| `orphaned` | No associated message (cleanup candidate) |

#### Indexes

| Index Name | Columns | Type | Purpose |
|------------|---------|------|---------|
| PRIMARY | id | Primary Key | Record lookup |
| idx_message | message_id | Non-unique | Find media by message |
| idx_conversation | conversation_id | Non-unique | Find media by conversation |
| idx_user | user_id | Non-unique | Find media by uploader |
| idx_type | media_type | Non-unique | Filter by type |
| idx_status | status | Non-unique | Filter active/orphaned |
| idx_created | created_at | Non-unique | Cleanup queries |

---

### 3.3 User Interaction Entity

**Table:** `{prefix}ai_botkit_user_interactions`
**Status:** NEW (Phase 2)

**Purpose:** Tracks user behavior for generating personalized recommendations.

#### Schema Definition

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | BIGINT(20) UNSIGNED | NO | AUTO_INCREMENT | Primary key |
| `user_id` | BIGINT(20) UNSIGNED | NO | - | WordPress user ID |
| `session_id` | VARCHAR(100) | YES | NULL | Browser session (for guests) |
| `interaction_type` | VARCHAR(50) | NO | - | Type of interaction |
| `item_type` | VARCHAR(50) | NO | - | Type of item interacted with |
| `item_id` | BIGINT(20) UNSIGNED | NO | - | ID of the item |
| `chatbot_id` | BIGINT(20) UNSIGNED | YES | NULL | Context chatbot (if any) |
| `metadata` | JSON | YES | NULL | Additional interaction data |
| `created_at` | DATETIME | NO | CURRENT_TIMESTAMP | Interaction timestamp |

#### SQL Creation

```sql
CREATE TABLE IF NOT EXISTS {prefix}ai_botkit_user_interactions (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    session_id VARCHAR(100),
    interaction_type VARCHAR(50) NOT NULL,
    item_type VARCHAR(50) NOT NULL,
    item_id BIGINT(20) UNSIGNED NOT NULL,
    chatbot_id BIGINT(20) UNSIGNED,
    metadata JSON,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_user_time (user_id, created_at DESC),
    INDEX idx_item (item_type, item_id),
    INDEX idx_type (interaction_type),
    INDEX idx_chatbot (chatbot_id),
    INDEX idx_session (session_id),
    INDEX idx_created (created_at)
) {charset_collate};
```

#### Interaction Types

| Type | Description | Typical Item Types |
|------|-------------|-------------------|
| `page_view` | User viewed a page | post, page, product, course |
| `product_view` | User viewed product details | product |
| `course_view` | User viewed course details | course |
| `search` | User searched for something | search_query |
| `add_to_cart` | User added item to cart | product |
| `purchase` | User completed purchase | product |
| `enrollment` | User enrolled in course | course |
| `recommendation_click` | User clicked recommendation | product, course |
| `recommendation_dismiss` | User dismissed recommendation | product, course |
| `chat_inquiry` | User asked about item in chat | product, course |

#### Item Types

| Type | Description | Source |
|------|-------------|--------|
| `product` | WooCommerce product | WooCommerce |
| `course` | LearnDash course | LearnDash |
| `lesson` | LearnDash lesson | LearnDash |
| `post` | WordPress post | WordPress |
| `page` | WordPress page | WordPress |
| `search_query` | Search term (item_id = hash) | Search Handler |

#### JSON Structure: `metadata`

```json
{
  "referrer": "https://example.com/previous-page",
  "source": "chat_suggestion",
  "conversation_id": 12345,
  "duration_seconds": 45,
  "scroll_depth": 0.75,
  "categories": [15, 23],
  "tags": ["featured", "sale"],
  "price": 49.99,
  "quantity": 1
}
```

#### Data Retention

- **Active data:** 90 days (configurable)
- **Aggregated data:** Summarized and stored in analytics
- **Cleanup:** Scheduled daily via `ai_botkit_cleanup_interactions` cron

#### Indexes

| Index Name | Columns | Type | Purpose |
|------------|---------|------|---------|
| PRIMARY | id | Primary Key | Record lookup |
| idx_user_time | user_id, created_at DESC | Composite | Get recent user activity |
| idx_item | item_type, item_id | Composite | Find interactions for item |
| idx_type | interaction_type | Non-unique | Filter by type |
| idx_chatbot | chatbot_id | Non-unique | Filter by chatbot context |
| idx_session | session_id | Non-unique | Guest session lookup |
| idx_created | created_at | Non-unique | Cleanup queries |

---

## 4. Phase 2 Schema Modifications

### 4.1 Messages Table Extension

**Table:** `{prefix}ai_botkit_messages`
**Modification:** Extended `metadata` JSON structure

#### Original Schema (Phase 1)

```sql
CREATE TABLE {prefix}ai_botkit_messages (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    conversation_id BIGINT(20) UNSIGNED NOT NULL,
    role VARCHAR(20) NOT NULL,
    content TEXT NOT NULL,
    metadata JSON,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX conversation_id (conversation_id)
);
```

#### Phase 2 Additions

```sql
-- Add fulltext index for search functionality
ALTER TABLE {prefix}ai_botkit_messages
ADD FULLTEXT INDEX ft_content (content);

-- No schema changes needed - attachments stored in metadata JSON
```

#### Extended `metadata` JSON Structure

**Phase 1 Structure:**
```json
{
  "tokens": 150,
  "model": "gpt-4o-mini"
}
```

**Phase 2 Extended Structure:**
```json
{
  "tokens": 150,
  "model": "gpt-4o-mini",
  "processing_time_ms": 2500,
  "context_chunks": 3,

  "attachments": [
    {
      "media_id": 123,
      "type": "image",
      "url": "/wp-content/uploads/ai-botkit/chat-media/images/2026/01/photo.jpg",
      "filename": "photo.jpg",
      "thumbnail_url": "/wp-content/uploads/ai-botkit/chat-media/images/2026/01/thumb_photo.jpg"
    },
    {
      "media_id": 124,
      "type": "link",
      "url": "https://example.com/article",
      "title": "Article Title",
      "description": "Article description",
      "image": "https://example.com/og-image.jpg"
    }
  ],

  "recommendations_shown": [
    {
      "type": "product",
      "id": 456,
      "position": 1,
      "clicked": false
    }
  ],

  "sources": [
    {
      "document_id": 789,
      "chunk_id": 1011,
      "title": "Source Document",
      "relevance": 0.85
    }
  ],

  "feedback": {
    "rating": "helpful",
    "timestamp": "2026-01-28T10:30:00Z"
  }
}
```

### 4.2 Conversations Table Extension

**Table:** `{prefix}ai_botkit_conversations`
**Modification:** New index for history queries

```sql
-- Add composite index for efficient history queries
ALTER TABLE {prefix}ai_botkit_conversations
ADD INDEX idx_user_updated (user_id, updated_at DESC);

-- Add archived flag (soft delete for history management)
ALTER TABLE {prefix}ai_botkit_conversations
ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER guest_ip;

-- Add index for archived filter
ALTER TABLE {prefix}ai_botkit_conversations
ADD INDEX idx_archived (is_archived);
```

#### Extended Schema (Phase 2)

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| ... | ... | ... | ... | (existing columns) |
| `is_archived` | TINYINT(1) | NO | 0 | **NEW:** Archived status |

### 4.3 Chatbots Table Extension

**Table:** `{prefix}ai_botkit_chatbots`
**Modification:** Template relationship

```sql
-- Add template reference
ALTER TABLE {prefix}ai_botkit_chatbots
ADD COLUMN template_id BIGINT(20) UNSIGNED AFTER model_config;

-- Add index for template lookup
ALTER TABLE {prefix}ai_botkit_chatbots
ADD INDEX idx_template (template_id);
```

#### Extended Schema (Phase 2)

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| ... | ... | ... | ... | (existing columns) |
| `template_id` | BIGINT(20) UNSIGNED | YES | NULL | **NEW:** Source template ID |

---

## 5. New Indexes for Phase 2

### 5.1 Search Functionality Indexes

```sql
-- Fulltext search on message content
ALTER TABLE {prefix}ai_botkit_messages
ADD FULLTEXT INDEX ft_content (content);

-- Composite index for filtered search
ALTER TABLE {prefix}ai_botkit_messages
ADD INDEX idx_convo_created (conversation_id, created_at DESC);
```

### 5.2 History Functionality Indexes

```sql
-- Efficient user history retrieval
ALTER TABLE {prefix}ai_botkit_conversations
ADD INDEX idx_user_updated (user_id, updated_at DESC);

-- Archived conversations filter
ALTER TABLE {prefix}ai_botkit_conversations
ADD INDEX idx_user_archived (user_id, is_archived, updated_at DESC);
```

### 5.3 Media Indexes

```sql
-- Find media by message
ALTER TABLE {prefix}ai_botkit_media
ADD INDEX idx_message (message_id);

-- Orphan cleanup
ALTER TABLE {prefix}ai_botkit_media
ADD INDEX idx_orphan (status, created_at);
```

### 5.4 Recommendation Indexes

```sql
-- User activity timeline
ALTER TABLE {prefix}ai_botkit_user_interactions
ADD INDEX idx_user_time (user_id, created_at DESC);

-- Item popularity
ALTER TABLE {prefix}ai_botkit_user_interactions
ADD INDEX idx_item_type (item_type, item_id, created_at DESC);

-- Interaction type analysis
ALTER TABLE {prefix}ai_botkit_user_interactions
ADD INDEX idx_type_time (interaction_type, created_at DESC);
```

### 5.5 Index Summary

| Table | Index | Columns | Purpose |
|-------|-------|---------|---------|
| messages | ft_content | content | Fulltext search |
| messages | idx_convo_created | conversation_id, created_at | Search with filters |
| conversations | idx_user_updated | user_id, updated_at | History listing |
| conversations | idx_user_archived | user_id, is_archived, updated_at | Filtered history |
| media | idx_message | message_id | Media lookup |
| media | idx_orphan | status, created_at | Cleanup queries |
| user_interactions | idx_user_time | user_id, created_at | User timeline |
| user_interactions | idx_item_type | item_type, item_id, created_at | Item analytics |
| templates | idx_category | category | Template filtering |
| chatbots | idx_template | template_id | Template usage |

---

## 6. Entity Relationships

### 6.1 Complete ER Diagram (Phase 1 + Phase 2)

```
+------------------------+          +---------------------------+
|       documents        |          |         chatbots          |
+------------------------+          +---------------------------+
| PK id                  |          | PK id                     |
|    title               |          |    name                   |
|    source_type         |          |    active                 |
|    source_id           |          |    avatar                 |
|    file_path           |          |    feedback               |
|    mime_type           |          |    style (JSON)           |
|    status              |          |    messages_template(JSON)|
|    created_at          |          |    model_config (JSON)    |
|    updated_at          |    +-----|    template_id (NEW)      |
+----------+-------------+    |     |    created_at             |
           |                  |     |    updated_at             |
           |1                 |     +-------------+-------------+
           |                  |                   |
           |N                 |                  1|
+----------+-------------+    |                   |N
|   document_metadata    |    |     +-------------+-------------+
+------------------------+    |     |  content_relationships    |
| PK id                  |    |     +---------------------------+
| FK document_id         |    |     | PK id                     |
|    meta_key            |    |     |    source_type = 'chatbot'|
|    meta_value          |    |     |    source_id              |
|    created_at          |    |     |    target_type = 'document'
|    updated_at          |    |     |    target_id              |
+------------------------+    |     |    relationship_type      |
           |                  |     |    metadata (JSON)        |
           |1                 |     |    created_at             |
           |                  |     +---------------------------+
           |N                 |
+----------+-------------+    |     +---------------------------+
|        chunks          |    |     |      conversations        |
+------------------------+    |     +---------------------------+
| PK id                  |    |     | PK id                     |
| FK document_id         |    |     | FK chatbot_id ------------|
|    content             |    |     |    user_id                |
|    chunk_index         |    |     |    session_id             |
|    metadata (JSON)     |    |     |    guest_ip               |
|    created_at          |    |     |    is_archived (NEW)      |
+----------+-------------+    |     |    created_at             |
           |                  |     |    updated_at             |
           |1                 |     +-------------+-------------+
           |                  |                   |
           |N                 |                  1|
+----------+-------------+    |                   |N
|      embeddings        |    |     +-------------+-------------+
+------------------------+    |     |        messages           |
| PK id                  |    |     +---------------------------+
| FK chunk_id            |    |     | PK id                     |
|    embedding (BLOB)    |    |     | FK conversation_id        |
|    model               |    |     |    role                   |
|    created_at          |    |     |    content                |
+------------------------+    |     |    metadata (JSON) (EXT)  |  <-- Extended with attachments
                              |     |    created_at             |
                              |     +-------------+-------------+
                              |                   |
+---------------------------+ |                  1|
|       templates (NEW)     | |                   |N
+---------------------------+ |     +-------------+-------------+
| PK id                     | |     |      media (NEW)          |
|    name                   | |     +---------------------------+
|    description            | |     | PK id                     |
|    category               |<+     | FK message_id             |
|    style (JSON)           |       | FK conversation_id        |
|    messages_template(JSON)|       |    user_id                |
|    model_config (JSON)    |       |    media_type             |
|    conversation_starters  |       |    file_name              |
|    thumbnail              |       |    file_path              |
|    is_system              |       |    file_url               |
|    is_active              |       |    mime_type              |
|    usage_count            |       |    file_size              |
|    created_by             |       |    metadata (JSON)        |
|    created_at             |       |    status                 |
|    updated_at             |       |    created_at             |
+---------------------------+       |    updated_at             |
                                    +---------------------------+

+---------------------------+       +---------------------------+
|      analytics            |       | user_interactions (NEW)   |
+---------------------------+       +---------------------------+
| PK id                     |       | PK id                     |
| FK chatbot_id             |       |    user_id                |
|    event_type             |       |    session_id             |
|    event_data (JSON)      |       |    interaction_type       |
|    created_at             |       |    item_type              |
+---------------------------+       |    item_id                |
                                    | FK chatbot_id             |
+---------------------------+       |    metadata (JSON)        |
|       wp_content          |       |    created_at             |
+---------------------------+       +---------------------------+
| PK id                     |
|    post_id                |
|    post_type              |
|    status                 |
|    action                 |
|    priority               |
|    created_at             |
|    updated_at             |
+---------------------------+
```

### 6.2 Relationship Summary

| Relationship | Type | FK Location | Description |
|--------------|------|-------------|-------------|
| Document -> Chunks | 1:N | chunks.document_id | Document has many chunks |
| Chunk -> Embeddings | 1:N | embeddings.chunk_id | Chunk can have multiple embeddings |
| Document -> Metadata | 1:N | document_metadata.document_id | Document extensible metadata |
| Chatbot -> Conversations | 1:N | conversations.chatbot_id | Chatbot has many conversations |
| Conversation -> Messages | 1:N | messages.conversation_id | Conversation has many messages |
| Message -> Media | 1:N | media.message_id | Message can have attachments |
| Conversation -> Media | 1:N | media.conversation_id | Alternative media grouping |
| Chatbot <-> Documents | M:N | content_relationships | Knowledge base association |
| Template -> Chatbots | 1:N | chatbots.template_id | Template spawns chatbots |
| User -> Interactions | 1:N | user_interactions.user_id | User activity tracking |
| Chatbot -> Interactions | 1:N | user_interactions.chatbot_id | Context tracking |
| Chatbot -> Analytics | 1:N | analytics.chatbot_id | Usage analytics |

### 6.3 New Phase 2 Relationships

```
Template --(1:N)--> Chatbot
    |
    +-- chatbots.template_id references templates.id
    +-- Soft relationship (template can be deleted, chatbot keeps config)

Message --(1:N)--> Media
    |
    +-- media.message_id references messages.id
    +-- Cascade delete when message deleted
    +-- Media metadata duplicated in messages.metadata.attachments

User --(1:N)--> User_Interactions
    |
    +-- user_interactions.user_id references wp_users.ID
    +-- No cascade (cleanup via scheduled task)

Chatbot --(1:N)--> User_Interactions
    |
    +-- user_interactions.chatbot_id references chatbots.id
    +-- Context for recommendation scoping
```

---

## 7. Data Lifecycle Diagrams

### 7.1 Template Lifecycle

```
[Admin Creates Template]
        |
        v
+------------------+
| Status: active   |
| is_system: false |
| usage_count: 0   |
+--------+---------+
         |
         v (Admin applies to chatbot)
+------------------+
| usage_count++    |
| chatbot.template |
| _id = id         |
+--------+---------+
         |
         v (Admin deactivates)
+------------------+
| is_active: false |
| (hidden from UI) |
+--------+---------+
         |
         v (Admin deletes)
+------------------+
| DELETE from DB   |
| (if not system)  |
+------------------+

Note: System templates (is_system: true) cannot be deleted or modified
```

### 7.2 Media Lifecycle

```
[User Uploads File]
        |
        v
+------------------+
| Status: active   |
| message_id: null |
| (pre-upload)     |
+--------+---------+
         |
         v (Message saved)
+------------------+
| message_id: set  |
| metadata in msg  |
+--------+---------+
         |
    +----+----+
    |         |
Message   Message NOT
deleted   deleted
    |         |
    v         v
+--------+ +------------------+
|Status: | |File remains      |
|deleted | |accessible        |
+---+----+ +------------------+
    |
    v (Cleanup cron runs)
+------------------+
| File deleted     |
| Record deleted   |
+------------------+

[Orphan Detection]
        |
        v
+------------------+
| message_id: null |
| created_at < 24h |
+--------+---------+
         |
         v (Cleanup marks as orphaned)
+------------------+
| status: orphaned |
+--------+---------+
         |
         v (After 7 days)
+------------------+
| DELETE file      |
| DELETE record    |
+------------------+
```

### 7.3 User Interaction Lifecycle

```
[User Performs Action]
        |
        v
+------------------+
| Record created   |
| with metadata    |
+--------+---------+
         |
         v (Used for recommendations)
+------------------+
| Queried by       |
| Recommendation   |
| Engine           |
+--------+---------+
         |
         v (After 90 days)
+------------------+
| Aggregated to    |
| analytics table  |
+--------+---------+
         |
         v
+------------------+
| DELETE from      |
| interactions     |
+------------------+

[Aggregation Process]
         |
         v
+------------------+
| Daily cron       |
| - Count by type  |
| - Group by item  |
| - Store summary  |
+--------+---------+
         |
         v
+------------------+
| analytics table  |
| event_type:      |
| 'interaction_    |
|  summary'        |
+------------------+
```

### 7.4 Conversation Archive Lifecycle

```
[Active Conversation]
        |
        v
+------------------+
| is_archived: 0   |
| Visible in UI    |
+--------+---------+
         |
         v (User archives)
+------------------+
| is_archived: 1   |
| Hidden from list |
| Still searchable |
+--------+---------+
         |
         v (User unarchives)
+------------------+
| is_archived: 0   |
| Back in list     |
+--------+---------+
         |
         v (User deletes)
+------------------+
| DELETE messages  |
| DELETE media     |
| DELETE convo     |
+------------------+

[Retention Policy - Optional]
         |
         v
+------------------+
| Archived > 1yr   |
| Auto-delete      |
| (if configured)  |
+------------------+
```

---

## 8. WordPress Options (Phase 2 Extensions)

### 8.1 New Plugin Options

| Option Key | Type | Default | Purpose |
|------------|------|---------|---------|
| `ai_botkit_history_per_page` | int | 10 | Default history pagination |
| `ai_botkit_search_per_page` | int | 20 | Default search pagination |
| `ai_botkit_max_media_size` | int | 10485760 | Max upload size (bytes) |
| `ai_botkit_allowed_image_types` | array | ['image/jpeg','image/png','image/gif','image/webp'] | Allowed image MIME types |
| `ai_botkit_allowed_video_types` | array | ['video/mp4','video/webm'] | Allowed video MIME types |
| `ai_botkit_allowed_doc_types` | array | ['application/pdf','text/plain'] | Allowed document types |
| `ai_botkit_media_cleanup_days` | int | 30 | Days before orphan cleanup |
| `ai_botkit_recommendation_enabled` | bool | true | Enable recommendations |
| `ai_botkit_recommendation_limit` | int | 5 | Max recommendations per request |
| `ai_botkit_interaction_retention_days` | int | 90 | Interaction data retention |
| `ai_botkit_pdf_paper_size` | string | 'letter' | Default PDF size |
| `ai_botkit_pdf_include_branding` | bool | true | Include site branding in PDF |
| `ai_botkit_templates_installed` | bool | false | System templates installed flag |

---

## 9. Migration Scripts

### 9.1 Phase 2 Database Migration

```php
/**
 * Migration script for Phase 2 database changes
 *
 * @since 2.0.0
 */
class Phase_2_Migration {

    public static function run(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;

        // 1. Create templates table
        self::create_templates_table($prefix, $charset_collate);

        // 2. Create media table
        self::create_media_table($prefix, $charset_collate);

        // 3. Create user_interactions table
        self::create_interactions_table($prefix, $charset_collate);

        // 4. Add fulltext index to messages
        self::add_messages_fulltext_index($prefix);

        // 5. Add columns to existing tables
        self::extend_conversations_table($prefix);
        self::extend_chatbots_table($prefix);

        // 6. Add new indexes
        self::add_new_indexes($prefix);

        // 7. Install system templates
        self::install_system_templates();

        // 8. Update version
        update_option('ai_botkit_version', '2.0.0');
    }

    private static function create_templates_table($prefix, $charset_collate): void {
        global $wpdb;

        $sql = "CREATE TABLE IF NOT EXISTS {$prefix}ai_botkit_templates (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            category VARCHAR(50) NOT NULL DEFAULT 'general',
            style JSON,
            messages_template JSON,
            model_config JSON,
            conversation_starters JSON,
            thumbnail VARCHAR(255),
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            usage_count INT NOT NULL DEFAULT 0,
            created_by BIGINT(20) UNSIGNED,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_category (category),
            INDEX idx_is_system (is_system),
            INDEX idx_is_active (is_active),
            INDEX idx_created_by (created_by)
        ) {$charset_collate};";

        $wpdb->query($sql);
    }

    private static function create_media_table($prefix, $charset_collate): void {
        global $wpdb;

        $sql = "CREATE TABLE IF NOT EXISTS {$prefix}ai_botkit_media (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            message_id BIGINT(20) UNSIGNED,
            conversation_id BIGINT(20) UNSIGNED,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            media_type VARCHAR(20) NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            file_url VARCHAR(500) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            file_size BIGINT(20) NOT NULL DEFAULT 0,
            metadata JSON,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_message (message_id),
            INDEX idx_conversation (conversation_id),
            INDEX idx_user (user_id),
            INDEX idx_type (media_type),
            INDEX idx_status (status),
            INDEX idx_created (created_at)
        ) {$charset_collate};";

        $wpdb->query($sql);
    }

    private static function create_interactions_table($prefix, $charset_collate): void {
        global $wpdb;

        $sql = "CREATE TABLE IF NOT EXISTS {$prefix}ai_botkit_user_interactions (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            session_id VARCHAR(100),
            interaction_type VARCHAR(50) NOT NULL,
            item_type VARCHAR(50) NOT NULL,
            item_id BIGINT(20) UNSIGNED NOT NULL,
            chatbot_id BIGINT(20) UNSIGNED,
            metadata JSON,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_user_time (user_id, created_at DESC),
            INDEX idx_item (item_type, item_id),
            INDEX idx_type (interaction_type),
            INDEX idx_chatbot (chatbot_id),
            INDEX idx_session (session_id),
            INDEX idx_created (created_at)
        ) {$charset_collate};";

        $wpdb->query($sql);
    }

    private static function add_messages_fulltext_index($prefix): void {
        global $wpdb;

        // Check if index exists
        $index_exists = $wpdb->get_var(
            "SELECT COUNT(1) FROM information_schema.STATISTICS
             WHERE table_schema = DATABASE()
             AND table_name = '{$prefix}ai_botkit_messages'
             AND index_name = 'ft_content'"
        );

        if (!$index_exists) {
            $wpdb->query(
                "ALTER TABLE {$prefix}ai_botkit_messages
                 ADD FULLTEXT INDEX ft_content (content)"
            );
        }
    }

    private static function extend_conversations_table($prefix): void {
        global $wpdb;

        // Check if column exists
        $column_exists = $wpdb->get_var(
            "SELECT COUNT(1) FROM information_schema.COLUMNS
             WHERE table_schema = DATABASE()
             AND table_name = '{$prefix}ai_botkit_conversations'
             AND column_name = 'is_archived'"
        );

        if (!$column_exists) {
            $wpdb->query(
                "ALTER TABLE {$prefix}ai_botkit_conversations
                 ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER guest_ip"
            );

            $wpdb->query(
                "ALTER TABLE {$prefix}ai_botkit_conversations
                 ADD INDEX idx_archived (is_archived)"
            );

            $wpdb->query(
                "ALTER TABLE {$prefix}ai_botkit_conversations
                 ADD INDEX idx_user_updated (user_id, updated_at DESC)"
            );
        }
    }

    private static function extend_chatbots_table($prefix): void {
        global $wpdb;

        // Check if column exists
        $column_exists = $wpdb->get_var(
            "SELECT COUNT(1) FROM information_schema.COLUMNS
             WHERE table_schema = DATABASE()
             AND table_name = '{$prefix}ai_botkit_chatbots'
             AND column_name = 'template_id'"
        );

        if (!$column_exists) {
            $wpdb->query(
                "ALTER TABLE {$prefix}ai_botkit_chatbots
                 ADD COLUMN template_id BIGINT(20) UNSIGNED AFTER model_config"
            );

            $wpdb->query(
                "ALTER TABLE {$prefix}ai_botkit_chatbots
                 ADD INDEX idx_template (template_id)"
            );
        }
    }

    private static function add_new_indexes($prefix): void {
        global $wpdb;

        // Add composite index for filtered message search
        $wpdb->query(
            "ALTER TABLE {$prefix}ai_botkit_messages
             ADD INDEX idx_convo_created (conversation_id, created_at DESC)"
        );
    }

    private static function install_system_templates(): void {
        if (get_option('ai_botkit_templates_installed')) {
            return;
        }

        $template_manager = new \AI_BotKit\Core\Template_Manager();
        $template_manager->install_system_templates();

        update_option('ai_botkit_templates_installed', true);
    }
}
```

### 9.2 Rollback Script

```php
/**
 * Rollback script for Phase 2 (emergency use only)
 */
class Phase_2_Rollback {

    public static function run(): void {
        global $wpdb;
        $prefix = $wpdb->prefix;

        // WARNING: This will delete all Phase 2 data!

        // 1. Drop new tables
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}ai_botkit_templates");
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}ai_botkit_media");
        $wpdb->query("DROP TABLE IF EXISTS {$prefix}ai_botkit_user_interactions");

        // 2. Remove new columns
        $wpdb->query(
            "ALTER TABLE {$prefix}ai_botkit_conversations
             DROP COLUMN is_archived"
        );

        $wpdb->query(
            "ALTER TABLE {$prefix}ai_botkit_chatbots
             DROP COLUMN template_id"
        );

        // 3. Remove fulltext index (optional - keep for search performance)
        // $wpdb->query(
        //     "ALTER TABLE {$prefix}ai_botkit_messages
        //      DROP INDEX ft_content"
        // );

        // 4. Remove new indexes
        $wpdb->query(
            "ALTER TABLE {$prefix}ai_botkit_conversations
             DROP INDEX idx_archived,
             DROP INDEX idx_user_updated"
        );

        // 5. Reset version
        update_option('ai_botkit_version', '1.0.0');
        delete_option('ai_botkit_templates_installed');
    }
}
```

---

## 10. Query Examples

### 10.1 History Queries

```sql
-- Get user's conversation history with previews
SELECT
    c.id,
    c.chatbot_id,
    cb.name AS chatbot_name,
    c.created_at,
    c.updated_at,
    (SELECT content FROM {prefix}ai_botkit_messages
     WHERE conversation_id = c.id
     ORDER BY created_at ASC LIMIT 1) AS preview,
    (SELECT COUNT(*) FROM {prefix}ai_botkit_messages
     WHERE conversation_id = c.id) AS message_count
FROM {prefix}ai_botkit_conversations c
JOIN {prefix}ai_botkit_chatbots cb ON c.chatbot_id = cb.id
WHERE c.user_id = %d
  AND c.is_archived = 0
ORDER BY c.updated_at DESC
LIMIT %d OFFSET %d;
```

### 10.2 Search Queries

```sql
-- Fulltext search with filters
SELECT
    m.id,
    m.conversation_id,
    m.role,
    m.content,
    m.created_at,
    MATCH(m.content) AGAINST(%s IN NATURAL LANGUAGE MODE) AS relevance,
    c.chatbot_id,
    cb.name AS chatbot_name
FROM {prefix}ai_botkit_messages m
JOIN {prefix}ai_botkit_conversations c ON m.conversation_id = c.id
JOIN {prefix}ai_botkit_chatbots cb ON c.chatbot_id = cb.id
WHERE MATCH(m.content) AGAINST(%s IN NATURAL LANGUAGE MODE)
  AND c.user_id = %d
  AND m.created_at BETWEEN %s AND %s
ORDER BY relevance DESC
LIMIT %d OFFSET %d;
```

### 10.3 Recommendation Queries

```sql
-- Get user's recent interactions for recommendations
SELECT
    item_type,
    item_id,
    interaction_type,
    COUNT(*) AS interaction_count,
    MAX(created_at) AS last_interaction
FROM {prefix}ai_botkit_user_interactions
WHERE user_id = %d
  AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY item_type, item_id, interaction_type
ORDER BY last_interaction DESC
LIMIT 50;

-- Get popular items in user's interest categories
SELECT
    p.ID AS product_id,
    p.post_title AS product_name,
    COUNT(i.id) AS view_count
FROM {prefix}posts p
JOIN {prefix}ai_botkit_user_interactions i
    ON i.item_id = p.ID AND i.item_type = 'product'
WHERE p.post_status = 'publish'
  AND i.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY p.ID
ORDER BY view_count DESC
LIMIT 10;
```

### 10.4 Media Cleanup Queries

```sql
-- Find orphaned media files
SELECT id, file_path, created_at
FROM {prefix}ai_botkit_media
WHERE message_id IS NULL
  AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);

-- Find deleted media pending file cleanup
SELECT id, file_path
FROM {prefix}ai_botkit_media
WHERE status = 'deleted'
  AND updated_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
```

---

## 11. Data Validation Rules

### 11.1 Template Validation

| Field | Validation Rules |
|-------|------------------|
| name | Required, 1-255 chars, sanitize_text_field |
| description | Optional, wp_kses_post |
| category | Required, enum: support, sales, marketing, education, general |
| style | Optional, valid JSON, schema validation |
| messages_template | Optional, valid JSON, schema validation |
| model_config | Optional, valid JSON, schema validation |
| conversation_starters | Optional, valid JSON array |
| thumbnail | Optional, valid URL or attachment ID |

### 11.2 Media Validation

| Field | Validation Rules |
|-------|------------------|
| file | Required, MIME type in whitelist, size <= max_size |
| media_type | Required, enum: image, video, document, link |
| file_name | Required, sanitize_file_name |
| file_path | Required, within uploads directory |
| mime_type | Required, in allowed list |
| file_size | Required, positive integer |

### 11.3 User Interaction Validation

| Field | Validation Rules |
|-------|------------------|
| user_id | Required, valid WordPress user ID |
| interaction_type | Required, enum: page_view, product_view, course_view, search, add_to_cart, purchase, enrollment, recommendation_click, recommendation_dismiss, chat_inquiry |
| item_type | Required, enum: product, course, lesson, post, page, search_query |
| item_id | Required, positive integer |
| metadata | Optional, valid JSON |

---

## 12. Appendix: Quick Reference

### 12.1 Phase 2 Table Summary

| Table | Rows (Est.) | Key Indexes |
|-------|-------------|-------------|
| ai_botkit_templates | 10-50 | category, is_system |
| ai_botkit_media | 100-10000 | message_id, conversation_id, status |
| ai_botkit_user_interactions | 10000-1M | user_id+created_at, item_type+item_id |

### 12.2 Storage Estimates

| Component | Size per Record | Notes |
|-----------|-----------------|-------|
| Template | ~5KB | JSON columns |
| Media metadata | ~500B | JSON metadata |
| Media file (avg) | ~500KB | Images, documents |
| User interaction | ~200B | Lean records |

### 12.3 Performance Considerations

| Query Type | Expected Time | Optimization |
|------------|---------------|--------------|
| History list | <50ms | idx_user_updated |
| Fulltext search | <200ms | ft_content + cache |
| Recommendations | <100ms | idx_user_time + cache |
| Media lookup | <20ms | idx_message |

---

*Data Model Document - Phase 1 + Phase 2*
*Last Updated: 2026-01-28*
