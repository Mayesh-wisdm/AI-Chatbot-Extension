# RECOVERED: AI BotKit Chatbot - Data Model Documentation

> **RECOVERED DOCUMENT:** Schema extracted from database creation and model classes
> **Generated:** 2026-01-28
> **Confidence Score:** 90%
> **Review Required:** Minor - Verify relationship constraints and cascade behaviors

---

## Document Status

| Section | Status | Confidence |
|---------|--------|------------|
| Entity Descriptions | RECOVERED | 92% |
| Table Schemas | RECOVERED | 95% |
| Relationships | RECOVERED | 88% |
| Indexes | RECOVERED | 95% |
| Data Lifecycle | INFERRED | 75% |

---

## 1. Data Model Overview

### 1.1 Entity Summary

| Entity | Table Name | Purpose | Primary Key |
|--------|------------|---------|-------------|
| Document | `ai_botkit_documents` | Source document metadata | id (BIGINT) |
| Document Metadata | `ai_botkit_document_metadata` | Extended document properties | id (BIGINT) |
| Chunk | `ai_botkit_chunks` | Text segments for embedding | id (BIGINT) |
| Embedding | `ai_botkit_embeddings` | Vector representations | id (BIGINT) |
| Chatbot | `ai_botkit_chatbots` | Chatbot configuration | id (BIGINT) |
| Conversation | `ai_botkit_conversations` | Chat session data | id (BIGINT) |
| Message | `ai_botkit_messages` | Individual chat messages | id (BIGINT) |
| Content Relationship | `ai_botkit_content_relationships` | Entity associations | id (BIGINT) |
| Analytics | `ai_botkit_analytics` | Usage events | id (BIGINT) |
| WP Content | `ai_botkit_wp_content` | WordPress content sync queue | id (BIGINT) |

### 1.2 Table Prefix

**Note:** Tables support migration from `ai_botkit_` prefix to `knowvault_` prefix via the `Table_Helper` class. The migration is user-initiated and does not happen automatically.

---

## 2. Entity Descriptions

### 2.1 Document Entity

**Table:** `{prefix}ai_botkit_documents`
**Source:** `class-ai-botkit-activator.php:create_*_tables()`
**Confidence:** 95%

**Purpose:** Stores metadata about source documents that have been ingested into the knowledge base.

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | BIGINT(20) UNSIGNED | NO | AUTO_INCREMENT | Primary key |
| `title` | VARCHAR(255) | NO | - | Document title |
| `source_type` | VARCHAR(50) | NO | - | Source type: 'file', 'url', 'post' |
| `source_id` | BIGINT(20) | YES | NULL | WordPress post ID (for post type) |
| `file_path` | VARCHAR(255) | YES | NULL | File path for uploaded files |
| `mime_type` | VARCHAR(100) | YES | NULL | MIME type of the document |
| `status` | VARCHAR(20) | NO | 'pending' | Processing status |
| `created_at` | DATETIME | NO | CURRENT_TIMESTAMP | Creation timestamp |
| `updated_at` | DATETIME | NO | CURRENT_TIMESTAMP | Last update timestamp |

**Status Values:**
| Status | Description |
|--------|-------------|
| `pending` | Awaiting processing |
| `processing` | Currently being processed |
| `completed` | Successfully processed |
| `failed` | Processing failed |

**Indexes:**
| Index Name | Columns | Type |
|------------|---------|------|
| PRIMARY | id | Primary Key |
| source_type_id | source_type, source_id | Non-unique |
| status | status | Non-unique |

---

### 2.2 Document Metadata Entity

**Table:** `{prefix}ai_botkit_document_metadata`
**Source:** `class-ai-botkit-activator.php`, `RAG_Engine::store_document_metadata()`
**Confidence:** 92%

**Purpose:** Stores extended key-value metadata for documents, including processing results.

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | BIGINT(20) UNSIGNED | NO | AUTO_INCREMENT | Primary key |
| `document_id` | BIGINT(20) UNSIGNED | NO | - | FK to documents.id |
| `meta_key` | VARCHAR(255) | NO | - | Metadata key name |
| `meta_value` | LONGTEXT | YES | NULL | Metadata value (may be JSON) |
| `created_at` | DATETIME | NO | CURRENT_TIMESTAMP | Creation timestamp |
| `updated_at` | DATETIME | NO | CURRENT_TIMESTAMP | Last update timestamp |

**Common Meta Keys:**
| Key | Type | Description |
|-----|------|-------------|
| `processing_results` | JSON | Processing outcome details |
| `chunk_count` | integer | Number of chunks created |
| `embedding_count` | integer | Number of embeddings generated |
| `processing_time` | float | Time taken to process |
| `mime_type` | string | Detected MIME type |
| `file_size` | integer | File size in bytes |
| `error` | string | Error message if failed |
| `error_time` | datetime | When error occurred |

**Indexes:**
| Index Name | Columns | Type |
|------------|---------|------|
| PRIMARY | id | Primary Key |
| document_meta | document_id, meta_key | Unique |
| document_id | document_id | Non-unique |
| meta_key | meta_key | Non-unique |

---

### 2.3 Chunk Entity

**Table:** `{prefix}ai_botkit_chunks`
**Source:** `class-ai-botkit-activator.php`, `Vector_Database::store_chunk()`
**Confidence:** 95%

**Purpose:** Stores text segments extracted from documents for vector embedding.

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | BIGINT(20) UNSIGNED | NO | AUTO_INCREMENT | Primary key |
| `document_id` | BIGINT(20) UNSIGNED | NO | - | FK to documents.id |
| `content` | TEXT | NO | - | Chunk text content |
| `chunk_index` | INT | NO | - | Position within document |
| `metadata` | JSON | YES | NULL | Chunk metadata |
| `created_at` | DATETIME | NO | CURRENT_TIMESTAMP | Creation timestamp |

**Metadata JSON Structure:**
```json
{
  "chunk_index": 0,
  "total_chunks": 5,
  "has_previous": false,
  "has_next": true,
  "size": 1024,
  "original_size": 950,
  "has_overlap_prev": false,
  "has_overlap_next": true,
  "source_type": "post",
  "post_type": "page",
  "post_id": 123
}
```

**Indexes:**
| Index Name | Columns | Type |
|------------|---------|------|
| PRIMARY | id | Primary Key |
| document_id | document_id | Non-unique |
| content | content | FULLTEXT |

---

### 2.4 Embedding Entity

**Table:** `{prefix}ai_botkit_embeddings`
**Source:** `class-ai-botkit-activator.php`, `Vector_Database::store_embeddings()`
**Confidence:** 95%

**Purpose:** Stores vector embeddings generated from text chunks.

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | BIGINT(20) UNSIGNED | NO | AUTO_INCREMENT | Primary key |
| `chunk_id` | BIGINT(20) UNSIGNED | NO | - | FK to chunks.id |
| `embedding` | LONGBLOB | NO | - | Serialized vector data |
| `model` | VARCHAR(100) | NO | - | Embedding model used |
| `created_at` | DATETIME | NO | CURRENT_TIMESTAMP | Creation timestamp |

**Embedding Storage Format:**
```
Format: base64(pack('f*', ...vector_values))
Dimensions: 1536 (OpenAI text-embedding-3-small)
Size: ~6KB per embedding
```

**Serialization/Deserialization:**
```php
// Serialize
$serialized = base64_encode(pack('f*', ...$vector));

// Deserialize
$vector = array_values(unpack('f*', base64_decode($serialized)));
```

**Indexes:**
| Index Name | Columns | Type |
|------------|---------|------|
| PRIMARY | id | Primary Key |
| chunk_model | chunk_id, model | Unique |

---

### 2.5 Chatbot Entity

**Table:** `{prefix}ai_botkit_chatbots`
**Source:** `class-ai-botkit-activator.php`, `class-chatbot.php`
**Confidence:** 92%

**Purpose:** Stores chatbot configurations including appearance and behavior settings.

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | BIGINT(20) | NO | AUTO_INCREMENT | Primary key |
| `name` | VARCHAR(255) | NO | - | Chatbot display name |
| `active` | TINYINT(1) | NO | 0 | Enabled status |
| `avatar` | INT(11) | NO | 0 | Avatar attachment ID |
| `feedback` | TINYINT(1) | NO | 0 | Feedback enabled |
| `style` | JSON | YES | NULL | UI styling configuration |
| `messages_template` | JSON | YES | NULL | Message templates |
| `model_config` | JSON | YES | NULL | LLM configuration |
| `created_at` | DATETIME | NO | CURRENT_TIMESTAMP | Creation timestamp |
| `updated_at` | DATETIME | NO | CURRENT_TIMESTAMP | Last update timestamp |

**Style JSON Structure:**
```json
{
  "primaryColor": "#0066cc",
  "backgroundColor": "#ffffff",
  "textColor": "#333333",
  "fontFamily": "system-ui",
  "fontSize": "14px",
  "borderRadius": "8px",
  "position": "bottom-right"
}
```

**Messages Template JSON Structure:**
```json
{
  "greeting": "Hello! How can I help you today?",
  "fallback": "I'm sorry, I don't have information about that.",
  "error": "Something went wrong. Please try again.",
  "thinking": "Thinking..."
}
```

**Model Config JSON Structure:**
```json
{
  "model": "gpt-4o-mini",
  "temperature": 0.7,
  "max_tokens": 1000,
  "context_length": 5,
  "min_chunk_relevance": 0.2,
  "personality": "friendly assistant",
  "tone": "professional",
  "max_messages": 5
}
```

**Indexes:**
| Index Name | Columns | Type |
|------------|---------|------|
| PRIMARY | id | Primary Key |

---

### 2.6 Conversation Entity

**Table:** `{prefix}ai_botkit_conversations`
**Source:** `class-ai-botkit-activator.php`, `class-conversation.php`
**Confidence:** 92%

**Purpose:** Stores chat session data linking users/guests to chatbots.

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | BIGINT(20) UNSIGNED | NO | AUTO_INCREMENT | Primary key |
| `chatbot_id` | BIGINT(20) UNSIGNED | NO | - | FK to chatbots.id |
| `user_id` | BIGINT(20) UNSIGNED | YES | NULL | WordPress user ID |
| `session_id` | VARCHAR(100) | NO | - | Browser session identifier |
| `guest_ip` | VARCHAR(64) | YES | NULL | SHA256 hashed IP for guests |
| `created_at` | DATETIME | NO | CURRENT_TIMESTAMP | Session start time |
| `updated_at` | DATETIME | NO | CURRENT_TIMESTAMP | Last activity time |

**User Identification Logic:**
```php
if (is_user_logged_in()) {
    // Use user_id
    $user_id = get_current_user_id();
} else {
    // Use hashed IP
    $guest_ip = hash('sha256', $_SERVER['REMOTE_ADDR']);
}
```

**Indexes:**
| Index Name | Columns | Type |
|------------|---------|------|
| PRIMARY | id | Primary Key |
| user_id | user_id | Non-unique |
| session_id | session_id | Non-unique |
| guest_ip | guest_ip | Non-unique |

---

### 2.7 Message Entity

**Table:** `{prefix}ai_botkit_messages`
**Source:** `class-ai-botkit-activator.php`, `class-conversation.php`
**Confidence:** 95%

**Purpose:** Stores individual chat messages within conversations.

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | BIGINT(20) UNSIGNED | NO | AUTO_INCREMENT | Primary key |
| `conversation_id` | BIGINT(20) UNSIGNED | NO | - | FK to conversations.id |
| `role` | VARCHAR(20) | NO | - | Message role |
| `content` | TEXT | NO | - | Message content |
| `metadata` | JSON | YES | NULL | Message metadata |
| `created_at` | DATETIME | NO | CURRENT_TIMESTAMP | Message timestamp |

**Role Values:**
| Role | Description |
|------|-------------|
| `user` | Message from the user |
| `assistant` | Response from the AI |

**Metadata JSON Structure:**
```json
{
  "tokens": 150,
  "model": "gpt-4o-mini"
}
```

**Indexes:**
| Index Name | Columns | Type |
|------------|---------|------|
| PRIMARY | id | Primary Key |
| conversation_id | conversation_id | Non-unique |

---

### 2.8 Content Relationship Entity

**Table:** `{prefix}ai_botkit_content_relationships`
**Source:** `class-ai-botkit-activator.php`, `class-chatbot.php`
**Confidence:** 88%

**Purpose:** Stores many-to-many relationships between entities (primarily chatbots and documents).

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | BIGINT(20) UNSIGNED | NO | AUTO_INCREMENT | Primary key |
| `source_type` | VARCHAR(50) | NO | - | Source entity type |
| `source_id` | BIGINT(20) UNSIGNED | NO | - | Source entity ID |
| `target_type` | VARCHAR(50) | NO | - | Target entity type |
| `target_id` | BIGINT(20) UNSIGNED | NO | - | Target entity ID |
| `relationship_type` | VARCHAR(50) | NO | - | Relationship type |
| `metadata` | JSON | YES | NULL | Relationship metadata |
| `created_at` | DATETIME | NO | CURRENT_TIMESTAMP | Creation timestamp |

**Common Relationship Types:**
| Source Type | Target Type | Relationship Type | Description |
|-------------|-------------|-------------------|-------------|
| chatbot | document | knowledge_base | Document in chatbot's knowledge base |

**Usage Example:**
```php
// Add document to chatbot's knowledge base
$chatbot->add_content('document', $document_id, $metadata);

// Query in Pinecone
$filter['document_id'] = array('$in' => $document_ids);
```

**Indexes:**
| Index Name | Columns | Type |
|------------|---------|------|
| PRIMARY | id | Primary Key |
| source | source_type, source_id | Non-unique |
| target | target_type, target_id | Non-unique |
| relationship_type | relationship_type | Non-unique |

---

### 2.9 Analytics Entity

**Table:** `{prefix}ai_botkit_analytics`
**Source:** `class-ai-botkit-activator.php`, `class-chatbot.php`, `class-analytics.php`
**Confidence:** 85%

**Purpose:** Stores usage analytics events for chatbots.

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | BIGINT(20) UNSIGNED | NO | AUTO_INCREMENT | Primary key |
| `chatbot_id` | BIGINT(20) UNSIGNED | YES | NULL | FK to chatbots.id |
| `event_type` | VARCHAR(50) | NO | - | Type of event |
| `event_data` | JSON | NO | - | Event payload |
| `created_at` | DATETIME | NO | CURRENT_TIMESTAMP | Event timestamp |

**Event Types (Inferred):**
| Event Type | Description |
|------------|-------------|
| chat_message | Chat message sent/received |
| conversation_start | New conversation started |
| conversation_end | Conversation ended |
| document_processed | Document processing complete |
| error | Error occurred |

**Event Data Examples:**
```json
// Chat message event
{
  "user_id": 123,
  "message_length": 50,
  "response_time_ms": 2500
}

// Error event
{
  "error_type": "api_error",
  "error_message": "Rate limit exceeded"
}
```

**Indexes:**
| Index Name | Columns | Type |
|------------|---------|------|
| PRIMARY | id | Primary Key |
| event_type | event_type | Non-unique |
| chatbot_id | chatbot_id | Non-unique |

---

### 2.10 WordPress Content Queue Entity

**Table:** `{prefix}ai_botkit_wp_content`
**Source:** `class-ai-botkit-activator.php`
**Confidence:** 85%

**Purpose:** Queues WordPress content for processing into the knowledge base.

| Column | Type | Nullable | Default | Description |
|--------|------|----------|---------|-------------|
| `id` | BIGINT(20) UNSIGNED | NO | AUTO_INCREMENT | Primary key |
| `post_id` | BIGINT(20) UNSIGNED | NO | - | WordPress post ID |
| `post_type` | VARCHAR(50) | NO | - | WordPress post type |
| `status` | VARCHAR(20) | NO | 'pending' | Queue status |
| `action` | VARCHAR(20) | NO | 'create' | Requested action |
| `priority` | VARCHAR(20) | NO | 'normal' | Processing priority |
| `created_at` | DATETIME | NO | CURRENT_TIMESTAMP | Queue entry time |
| `updated_at` | DATETIME | NO | CURRENT_TIMESTAMP | Last update time |

**Status Values:**
| Status | Description |
|--------|-------------|
| pending | Awaiting processing |
| processing | Currently being processed |
| completed | Successfully processed |
| failed | Processing failed |

**Action Values:**
| Action | Description |
|--------|-------------|
| create | New content to add |
| update | Existing content to update |
| delete | Content to remove |

**Priority Values:**
| Priority | Description |
|----------|-------------|
| high | Process first |
| normal | Standard processing |
| low | Process when idle |

**Indexes:**
| Index Name | Columns | Type |
|------------|---------|------|
| PRIMARY | id | Primary Key |
| post_type_id | post_id, post_type | Unique |
| status | status | Non-unique |
| post_type | post_type | Non-unique |

---

## 3. Entity Relationship Diagram

**Confidence:** 90%

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
|    updated_at          |          |    created_at             |
+----------+-------------+          |    updated_at             |
           |                        +-------------+-------------+
           |1                                     |
           |                                      |1
           |N                                     |
+----------+-------------+          +-------------+-------------+
|   document_metadata    |          |  content_relationships    |
+------------------------+          +---------------------------+
| PK id                  |          | PK id                     |
| FK document_id --------|          |    source_type = 'chatbot'|
|    meta_key            |          |    source_id --------------|------+
|    meta_value          |          |    target_type = 'document'|      |
|    created_at          |          |    target_id (-> documents)|      |
|    updated_at          |          |    relationship_type      |      |
+------------------------+          |    metadata (JSON)        |      |
           |                        |    created_at             |      |
           |1                       +---------------------------+      |
           |                                                           |
           |N                                                          |
+----------+-------------+          +---------------------------+      |
|        chunks          |          |      conversations        |<-----+
+------------------------+          +---------------------------+
| PK id                  |          | PK id                     |
| FK document_id --------|          | FK chatbot_id ------------|
|    content             |          |    user_id                |
|    chunk_index         |          |    session_id             |
|    metadata (JSON)     |          |    guest_ip               |
|    created_at          |          |    created_at             |
+----------+-------------+          |    updated_at             |
           |                        +-------------+-------------+
           |1                                     |
           |                                      |1
           |N                                     |
+----------+-------------+                        |N
|      embeddings        |          +-------------+-------------+
+------------------------+          |        messages           |
| PK id                  |          +---------------------------+
| FK chunk_id -----------|          | PK id                     |
|    embedding (BLOB)    |          | FK conversation_id -------|
|    model               |          |    role                   |
|    created_at          |          |    content                |
+------------------------+          |    metadata (JSON)        |
                                    |    created_at             |
                                    +---------------------------+

+------------------------+          +---------------------------+
|      analytics         |          |       wp_content          |
+------------------------+          +---------------------------+
| PK id                  |          | PK id                     |
| FK chatbot_id ---------|          |    post_id                |
|    event_type          |          |    post_type              |
|    event_data (JSON)   |          |    status                 |
|    created_at          |          |    action                 |
+------------------------+          |    priority               |
                                    |    created_at             |
                                    |    updated_at             |
                                    +---------------------------+
```

---

## 4. Relationships

### 4.1 Relationship Summary

| Relationship | Type | Description | Cascade |
|--------------|------|-------------|---------|
| Document -> Chunks | 1:N | Document has many chunks | Manual delete |
| Chunk -> Embeddings | 1:N | Chunk has embeddings per model | Manual delete |
| Document -> Metadata | 1:N | Document has metadata entries | Manual delete |
| Chatbot -> Conversations | 1:N | Chatbot has many conversations | Manual delete |
| Conversation -> Messages | 1:N | Conversation has many messages | Manual delete |
| Chatbot <-> Documents | M:N | Via content_relationships | Manual delete |
| Chatbot -> Analytics | 1:N | Chatbot has analytics events | Manual delete |

### 4.2 Relationship Details

#### 4.2.1 Document -> Chunks

```php
// In Vector_Database
$chunks = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$prefix}chunks WHERE document_id = %d",
    $document_id
));

// Cleanup on document update
$this->delete_document_embeddings($document_id);
```

#### 4.2.2 Chunk -> Embeddings

```php
// Store embedding for chunk
$wpdb->insert(
    "{$prefix}embeddings",
    [
        'chunk_id' => $chunk_id,
        'embedding' => $serialized_vector,
        'model' => 'text-embedding-3-small'
    ]
);
```

#### 4.2.3 Chatbot <-> Documents (Many-to-Many)

```php
// Add document to chatbot
$chatbot->add_content('document', $document_id);

// Query relationship
$wpdb->get_col($wpdb->prepare(
    "SELECT target_id FROM {$prefix}content_relationships
     WHERE source_type = 'chatbot'
     AND source_id = %d
     AND relationship_type = 'knowledge_base'",
    $chatbot_id
));
```

#### 4.2.4 Conversation -> Messages

```php
// Add message to conversation
$conversation->add_message([
    'conversation_id' => $id,
    'role' => 'user',
    'content' => $message,
    'metadata' => ['tokens' => $tokens]
]);

// Delete cascade
$wpdb->delete("{$prefix}messages", ['conversation_id' => $id]);
$wpdb->delete("{$prefix}conversations", ['id' => $id]);
```

---

## 5. Data Lifecycle

### 5.1 Document Lifecycle

**Confidence:** 80%

```
[Upload/Import]
      |
      v
+------------------+
|  Status: pending |
+--------+---------+
         |
         v (Queue processor runs)
+------------------+
| Status: processing|
+--------+---------+
         |
    +----+----+
    |         |
    v         v
+-------+  +--------+
|Success|  |Failure |
+---+---+  +----+---+
    |           |
    v           v
+----------+ +--------+
|completed | | failed |
+----------+ +--------+
```

**State Transitions:**
| From | To | Trigger |
|------|-----|---------|
| - | pending | New document created |
| pending | processing | Queue processor picks up |
| processing | completed | Processing succeeds |
| processing | failed | Processing fails |
| completed | processing | Reprocess requested |
| failed | processing | Reprocess requested |

### 5.2 Chunk/Embedding Lifecycle

**Confidence:** 85%

```
[Document Processing]
         |
         v
+------------------+
| Create chunks    |
| (Text_Chunker)   |
+--------+---------+
         |
         v
+------------------+
| Generate         |
| embeddings       |
| (Embeddings_Gen) |
+--------+---------+
         |
         v
+------------------+
| Store in DB      |
| or Pinecone      |
+------------------+

[Document Update]
         |
         v
+------------------+
| Delete old       |
| chunks/embeddings|
+--------+---------+
         |
         v
[Reprocess as new]
```

### 5.3 Conversation Lifecycle

**Confidence:** 82%

```
[New Chat Session]
         |
         v
+------------------+
| Create or find   |
| conversation     |
| (by session_id)  |
+--------+---------+
         |
         v
+------------------+
| Add messages     |
| (user/assistant) |
+--------+---------+
         |
         v
+------------------+
| Update timestamp |
| (updated_at)     |
+------------------+

[Clear Conversation]
         |
         v
+------------------+
| Delete messages  |
+--------+---------+
         |
         v
+------------------+
| Delete convo     |
| (optional)       |
+------------------+
```

---

## 6. Indexes and Performance

### 6.1 Index Summary

| Table | Index | Columns | Purpose |
|-------|-------|---------|---------|
| documents | source_type_id | source_type, source_id | Find by source |
| documents | status | status | Queue processing |
| document_metadata | document_meta | document_id, meta_key | Unique meta lookup |
| chunks | document_id | document_id | Find chunks by doc |
| chunks | content | content (FULLTEXT) | Text search |
| embeddings | chunk_model | chunk_id, model | Unique embedding |
| conversations | user_id | user_id | Find by user |
| conversations | session_id | session_id | Session lookup |
| conversations | guest_ip | guest_ip | Guest tracking |
| messages | conversation_id | conversation_id | Message retrieval |
| content_relationships | source | source_type, source_id | Find relationships |
| content_relationships | target | target_type, target_id | Reverse lookup |
| analytics | event_type | event_type | Event filtering |
| analytics | chatbot_id | chatbot_id | Per-chatbot stats |
| wp_content | post_type_id | post_id, post_type | Unique queue entry |
| wp_content | status | status | Queue processing |

### 6.2 Query Optimization Notes

**Embedding Similarity Search:**
```sql
-- Local database: Requires full table scan
SELECT c.id, c.content, c.metadata, e.embedding
FROM chunks c
JOIN embeddings e ON e.chunk_id = c.id
JOIN documents d ON c.document_id = d.id
JOIN content_relationships cr ON cr.target_id = d.id
WHERE cr.source_id = ?  -- chatbot_id
```

**Recommendation:** Use Pinecone for large datasets to avoid full table scans.

**Analytics Queries:**
```sql
-- Optimized by chatbot_id and event_type indexes
SELECT event_type, COUNT(*), DATE(created_at)
FROM analytics
WHERE chatbot_id = ?
  AND created_at >= ?
  AND created_at <= ?
GROUP BY event_type, DATE(created_at)
```

---

## 7. WordPress Options

### 7.1 Plugin Options

| Option Key | Type | Default | Purpose |
|------------|------|---------|---------|
| `ai_botkit_version` | string | - | Plugin version |
| `ai_botkit_openai_api_key` | string | - | OpenAI API key |
| `ai_botkit_anthropic_api_key` | string | - | Anthropic API key |
| `ai_botkit_google_api_key` | string | - | Google AI API key |
| `ai_botkit_together_api_key` | string | - | Together AI API key |
| `ai_botkit_voyageai_api_key` | string | - | VoyageAI API key |
| `ai_botkit_pinecone_api_key` | string | - | Pinecone API key |
| `ai_botkit_pinecone_host` | string | - | Pinecone host URL |
| `ai_botkit_enable_pinecone` | bool | false | Enable Pinecone |
| `ai_botkit_engine` | string | 'openai' | Selected LLM provider |
| `ai_botkit_chat_model` | string | 'gpt-4-turbo-preview' | Chat model |
| `ai_botkit_embedding_model` | string | 'text-embedding-3-small' | Embedding model |
| `ai_botkit_max_tokens` | int | 1000 | Max response tokens |
| `ai_botkit_temperature` | float | 0.7 | LLM temperature |
| `ai_botkit_chunk_size` | int | 1000 | Chunk size in chars |
| `ai_botkit_chunk_overlap` | int | 200 | Chunk overlap |
| `ai_botkit_batch_size` | int | 20 | Batch processing size |
| `ai_botkit_cache_ttl` | int | 3600 | Cache TTL in seconds |
| `ai_botkit_max_requests_per_day` | int | 60 | Rate limit (messages) |
| `ai_botkit_token_bucket_limit` | int | 100000 | Rate limit (tokens) |
| `ai_botkit_blocked_ips` | JSON | '[]' | Blocked IP list |
| `ai_botkit_banned_keywords` | JSON | '[]' | Banned keywords |
| `ai_botkit_chatbot_sitewide_enabled` | int | 0 | Sitewide chatbot ID |
| `ai_botkit_post_types` | array | ['post','page'] | Sync post types |
| `ai_botkit_fallback_order` | array | - | Provider fallback order |
| `knowvault_db_migration_completed` | bool | false | Migration status |

---

## 8. Data Migration

### 8.1 Table Prefix Migration

**From:** `ai_botkit_`
**To:** `knowvault_`

**Supported by:** `Table_Helper` class

```php
// Check which tables exist
Table_Helper::check_old_tables_exist();
Table_Helper::check_new_tables_exist();

// Get appropriate table name
$table = Table_Helper::get_table_name('documents');
// Returns: {prefix}ai_botkit_documents or {prefix}knowvault_documents
```

### 8.2 Pinecone Migration

**Class:** `Migration_Manager`
**Direction:** Local <-> Pinecone

**Operations:**
| Method | Direction | Description |
|--------|-----------|-------------|
| `migrate_to_pinecone()` | Local -> Pinecone | Migrate vectors to cloud |
| `migrate_from_pinecone()` | Pinecone -> Local | Download vectors |
| `get_migration_status()` | - | Check migration progress |

---

## 9. Assumptions and Notes

### 9.1 Foreign Key Constraints

**Note:** The schema does not define explicit foreign key constraints in the database. Referential integrity is maintained at the application level.

**Recommendation:** Consider adding foreign key constraints for:
- chunks.document_id -> documents.id
- embeddings.chunk_id -> chunks.id
- conversations.chatbot_id -> chatbots.id
- messages.conversation_id -> conversations.id

### 9.2 JSON Column Compatibility

**Note:** JSON columns require MySQL 5.7+ or MariaDB 10.2.7+. For older versions, JSON is stored as TEXT with application-level parsing.

### 9.3 FULLTEXT Index

**Note:** FULLTEXT index on chunks.content is for potential future full-text search capabilities, not currently used in the codebase.

### 9.4 Cascade Deletions

**Current Behavior:** Manual cascade deletion implemented in model classes.

**Example:**
```php
// Conversation::delete()
$wpdb->delete($this->messages_table, ['conversation_id' => $this->id]);
$wpdb->delete($this->table_name, ['id' => $this->id]);
```

---

*RECOVERED DOCUMENT - Generated by Spec Recovery Agent*
*Schema extracted from activator class and model implementations*
