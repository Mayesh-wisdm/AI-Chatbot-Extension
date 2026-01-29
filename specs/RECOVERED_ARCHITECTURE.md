# RECOVERED: AI BotKit Chatbot - Architecture Documentation

> **RECOVERED DOCUMENT:** Auto-generated from code analysis
> **Generated:** 2026-01-28
> **Confidence Score:** 85%
> **Review Required:** Yes - Verify design pattern interpretations and add architectural decision rationale

---

## Document Status

| Aspect | Status | Confidence |
|--------|--------|------------|
| System Overview | RECOVERED | 90% |
| Component Architecture | RECOVERED | 88% |
| Data Flow | RECOVERED | 85% |
| Database Schema | RECOVERED | 95% |
| API Contracts | RECOVERED | 92% |
| Security Architecture | RECOVERED | 80% |
| Performance Architecture | RECOVERED | 82% |

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
| **Dependencies** | Guzzle HTTP, smalot/pdfparser, fivefilters/readability.php |

### 1.3 High-Level Architecture Diagram

```
+-----------------------------------------------------------------------------------+
|                           AI BOTKIT CHATBOT PLUGIN                                 |
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
|  DATA ACCESS LAYER          |                   |                   |            |
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
|  +----------------+  +----------------+                                            |
|  |Chatbot         |  |Conversation    |                                            |
|  | (Entity/CRUD)  |  | (Entity/CRUD)  |                                            |
|  +----------------+  +----------------+                                            |
|                                                                                    |
+-----------------------------------------------------------------------------------+
```

---

## 2. Component Architecture

### 2.1 Core Components

#### 2.1.1 RAG_Engine (Orchestrator)

**File:** `includes/core/class-rag-engine.php`
**Namespace:** `AI_BotKit\Core`
**Confidence:** 90%

**Responsibilities:**
- Orchestrates the complete RAG pipeline
- Coordinates document processing (load -> chunk -> embed -> store)
- Manages chat response generation with context retrieval
- Handles streaming responses
- Manages conversation state and history
- Implements rate limit checking
- Supports banned keyword filtering
- Handles LearnDash enrollment-aware context

**Key Dependencies:**
```php
class RAG_Engine {
    private $document_loader;      // Document_Loader
    private $text_chunker;         // Text_Chunker
    private $embeddings_generator; // Embeddings_Generator
    private $vector_database;      // Vector_Database
    private $retriever;            // Retriever
    private $llm_client;           // LLM_Client
    private $cache_manager;        // Cache_Manager
    private $rate_limiter;         // Rate_Limiter
}
```

**Key Methods:**
| Method | Purpose |
|--------|---------|
| `process_document()` | Process document through RAG pipeline |
| `generate_response()` | Generate chat response with context |
| `stream_response()` | Stream chat response in real-time |
| `process_queue()` | Process pending documents from queue |

#### 2.1.2 LLM_Client (Multi-Provider AI Interface)

**File:** `includes/core/class-llm-client.php`
**Namespace:** `AI_BotKit\Core`
**Confidence:** 92%

**Responsibilities:**
- Provides unified interface to multiple LLM providers
- Handles embeddings generation
- Manages chat completions (sync and streaming)
- Implements response caching
- Translates between provider-specific formats

**Supported Providers:**

| Provider | Embeddings | Completions | API Endpoint |
|----------|------------|-------------|--------------|
| OpenAI | text-embedding-3-small | gpt-4-turbo, gpt-4o-mini, gpt-3.5-turbo | api.openai.com |
| Anthropic | (via VoyageAI) | claude-3-opus, claude-3-sonnet | api.anthropic.com |
| Google AI | text-embedding-004 | gemini-1.5-flash | generativelanguage.googleapis.com |
| Together AI | Various | Various open models | api.together.xyz |
| VoyageAI | voyage-2 | - | api.voyageai.com |

**Request Flow:**
```
[User Request]
     |
     v
[LLM_Client::generate_completion()]
     |
     +-- Check cache (Unified_Cache_Manager)
     |
     +-- Determine provider (ai_botkit_engine option)
     |
     +-- Build provider-specific request
     |       - OpenAI: standard format
     |       - Anthropic: system prompt extraction
     |       - Google: contents array format
     |       - Together: OpenAI-compatible format
     |
     +-- Make API request (wp_remote_post)
     |
     +-- Transform response to standard format
     |
     +-- Cache response
     |
     v
[Standardized Response]
```

#### 2.1.3 Vector_Database (Local Storage)

**File:** `includes/core/class-vector-database.php`
**Namespace:** `AI_BotKit\Core`
**Confidence:** 88%

**Responsibilities:**
- Stores document chunks in MySQL
- Stores embeddings as serialized binary blobs
- Performs cosine similarity searches
- Supports hybrid local/Pinecone operation
- Manages chunk lifecycle

**Vector Storage Format:**
```
Embedding Storage: base64(pack('f*', ...vector))
Dimensions: 1536 (OpenAI text-embedding-3-small)
Similarity: Cosine similarity calculation
```

**Key Methods:**
| Method | Purpose |
|--------|---------|
| `store_embeddings()` | Store vectors with metadata |
| `find_similar()` | Find similar vectors using cosine similarity |
| `delete_document_embeddings()` | Clean up document vectors |
| `get_stats()` | Get database statistics |

#### 2.1.4 Pinecone_Database (Cloud Vector Storage)

**File:** `includes/core/class-pinecone-database.php`
**Namespace:** `AI_BotKit\Core`
**Confidence:** 90%

**Responsibilities:**
- Cloud vector storage via Pinecone API
- High-performance similarity search
- Metadata filtering for document scoping
- User enrollment-aware context filtering
- Batch operations support

**API Operations:**
| Operation | Endpoint | Purpose |
|-----------|----------|---------|
| `upsert_vectors()` | POST /vectors/upsert | Store vectors |
| `query_vectors()` | POST /query | Similarity search |
| `delete_vectors()` | POST /vectors/delete | Remove vectors |
| `fetch_vectors()` | POST /vectors/fetch | Retrieve by ID |
| `describe_index_stats()` | GET /describe_index_stats | Index statistics |

#### 2.1.5 Document_Loader

**File:** `includes/core/class-document-loader.php`
**Namespace:** `AI_BotKit\Core`
**Confidence:** 85%

**Responsibilities:**
- Loads content from multiple source types
- Extracts text from PDFs using smalot/pdfparser
- Extracts content from URLs using fivefilters/readability.php
- Loads WordPress post content with metadata

**Supported Source Types:**
| Type | Method | Library |
|------|--------|---------|
| `file` | `load_from_file()` | smalot/pdfparser (PDF), native (text) |
| `url` | `load_from_url()` | fivefilters/readability.php |
| `post` | `load_from_post()` | WordPress $wpdb |

#### 2.1.6 Text_Chunker

**File:** `includes/core/class-text-chunker.php`
**Namespace:** `AI_BotKit\Core`
**Confidence:** 88%

**Responsibilities:**
- Splits documents into optimal chunks for embedding
- Maintains context through overlap
- UTF-8 aware processing
- Intelligent splitting by paragraphs and sentences

**Chunking Configuration:**
| Setting | Default | Purpose |
|---------|---------|---------|
| `chunk_size` | 1000 chars | Maximum chunk size |
| `chunk_overlap` | 200 chars | Overlap between chunks |
| `min_chunk_size` | 700 chars | Minimum chunk size |

**Chunking Algorithm:**
```
1. Normalize text (UTF-8, line endings, whitespace)
2. Split by paragraphs (\n\s*\n)
3. If chunk > max_size, split by sentences
4. Merge small chunks to meet min_size
5. Add overlaps from adjacent chunks
6. Attach metadata (index, size, has_previous, has_next)
```

#### 2.1.7 Retriever

**File:** `includes/core/class-retriever.php`
**Namespace:** `AI_BotKit\Core`
**Confidence:** 85%

**Responsibilities:**
- Finds relevant context for user queries
- Deduplicates similar results
- Re-ranks results by relevance score
- Expands context window around matches

**Retrieval Settings:**
| Setting | Default | Purpose |
|---------|---------|---------|
| `max_results` | 5 | Maximum chunks to return |
| `min_similarity` | 0.0 | Minimum similarity threshold |
| `context_window` | 3 | Adjacent chunks to include |
| `deduplication_threshold` | 0.95 | Duplicate detection threshold |
| `reranking_enabled` | true | Enable result re-ranking |

**Re-ranking Factors:**
- Base similarity score
- Content recency (decay over 30 days)
- Content type boost (page: 1.2, product: 1.15, course: 1.15, post: 1.1)

### 2.2 Data Models

#### 2.2.1 Chatbot Model

**File:** `includes/models/class-chatbot.php`
**Namespace:** `AI_BotKit\Models`
**Confidence:** 92%

**Pattern:** Active Record with Repository methods

**Entity Fields:**
| Field | Type | Purpose |
|-------|------|---------|
| `id` | BIGINT | Primary key |
| `name` | VARCHAR(255) | Display name |
| `active` | TINYINT(1) | Enabled status |
| `avatar` | INT | Avatar attachment ID |
| `feedback` | TINYINT(1) | Feedback enabled |
| `style` | JSON | UI styling configuration |
| `messages_template` | JSON | Message templates |
| `model_config` | JSON | LLM configuration |

**Key Operations:**
| Method | Type | Purpose |
|--------|------|---------|
| `save()` | Instance | Create or update chatbot |
| `delete()` | Instance | Delete chatbot |
| `get_analytics()` | Instance | Get chatbot analytics |
| `add_content()` | Instance | Associate content with chatbot |
| `get_all()` | Static | Get all chatbots |
| `get_active()` | Static | Get active chatbots |

#### 2.2.2 Conversation Model

**File:** `includes/models/class-conversation.php`
**Namespace:** `AI_BotKit\Models`
**Confidence:** 90%

**Pattern:** Active Record with session-based lookup

**Entity Fields:**
| Field | Type | Purpose |
|-------|------|---------|
| `id` | BIGINT | Primary key |
| `chatbot_id` | BIGINT | Associated chatbot |
| `user_id` | BIGINT | WordPress user ID |
| `session_id` | VARCHAR(100) | Browser session ID |
| `guest_ip` | VARCHAR(64) | SHA256 hashed IP for guests |

**Key Operations:**
| Method | Type | Purpose |
|--------|------|---------|
| `save()` | Instance | Create or update conversation |
| `add_message()` | Instance | Add message to conversation |
| `get_messages()` | Instance | Get conversation messages |
| `delete()` | Instance | Delete conversation and messages |
| `get_by_user()` | Static | Get user conversations |
| `get_by_session_id()` | Static | Get conversation by session |

### 2.3 Integration Components

#### 2.3.1 WordPress_Content Integration

**File:** `includes/integration/class-wordpress-content.php`
**Confidence:** 85%

**Purpose:** Synchronizes WordPress content lifecycle with RAG system

**Hooked Actions:**
| Hook | Handler | Purpose |
|------|---------|---------|
| `save_post` | `handle_post_update()` | Queue updated content |
| `before_delete_post` | `handle_post_delete()` | Remove from vector DB |
| `wp_trash_post` | `handle_post_trash()` | Handle trashed content |
| `untrash_post` | `handle_post_untrash()` | Restore content |

#### 2.3.2 LearnDash Integration

**File:** `includes/integration/class-learndash.php`
**Confidence:** 82%

**Purpose:** Syncs LearnDash LMS content and supports enrollment-aware context

**Supported Content Types:**
| Post Type | Hook | Handler |
|-----------|------|---------|
| `sfwd-courses` | `save_post_sfwd-courses` | `handle_course_update()` |
| `sfwd-lessons` | `save_post_sfwd-lessons` | `handle_lesson_update()` |
| `sfwd-topic` | `save_post_sfwd-topic` | `handle_topic_update()` |
| `sfwd-quiz` | `save_post_sfwd-quiz` | `handle_quiz_update()` |
| `sfwd-question` | `save_post_sfwd-question` | `handle_question_update()` |

**Enrollment-Aware Context:**
- Retrieves user enrolled courses via `learndash_user_get_enrolled_courses()`
- Filters context results based on enrollment
- Shows enrollment prompts for non-enrolled users

#### 2.3.3 WooCommerce Integration

**File:** `includes/integration/class-woocommerce.php`
**File:** `includes/integration/class-woocommerce-assistant.php`
**Confidence:** 80%

**Purpose:** Product catalog sync and shopping assistant functionality

**Hooked Actions:**
| Hook | Handler | Purpose |
|------|---------|---------|
| `woocommerce_update_product` | `handle_product_update()` | Sync product content |
| `woocommerce_delete_product` | `handle_product_delete()` | Remove product |
| `woocommerce_save_product_variation` | `handle_variation_update()` | Sync variations |

**Shopping Assistant Features:**
- Intent detection (product info, cart, order status, recommendations)
- Response enhancement with product data
- Product interaction tracking

---

## 3. Data Flow Diagrams

### 3.1 Document Ingestion Flow

**Confidence:** 88%

```
+-------------+     +----------------+     +---------------+     +------------------+
|   SOURCE    |     | Document_Loader|     | Text_Chunker  |     | Embeddings_      |
| - PDF file  |---->| - PDF parser   |---->| - Paragraph   |---->| Generator        |
| - URL       |     | - Readability  |     | - Sentence    |     | - OpenAI API     |
| - WP Post   |     | - WP content   |     | - Overlap     |     | - Batch process  |
+-------------+     +----------------+     +---------------+     +------------------+
                           |                      |                       |
                           v                      v                       v
                    +-------------+       +---------------+       +---------------+
                    | documents   |       | chunks        |       | embeddings    |
                    | table       |       | table         |       | table         |
                    | - id        |       | - document_id |       | - chunk_id    |
                    | - title     |       | - content     |       | - embedding   |
                    | - status    |       | - metadata    |       | - model       |
                    +-------------+       +---------------+       +---------------+
                                                                         |
                                                                         v
                                                    +----------------------------------+
                                                    |      OR Pinecone Cloud          |
                                                    | - Upsert vectors with metadata  |
                                                    | - Document ID filtering         |
                                                    +----------------------------------+
```

### 3.2 Chat Query Flow

**Confidence:** 85%

```
+------------+     +---------------+     +-----------------+     +---------------+
|   USER     |     | Ajax_Handler  |     | RAG_Engine      |     | Rate_Limiter  |
|  Message   |---->| - Nonce check |---->| generate_       |---->| - Token check |
|            |     | - IP blocking |     | response()      |     | - Message chk |
+------------+     +---------------+     +-----------------+     +---------------+
                                                |                       |
                                                v                       v
                                         [If rate limited] -----> Return error message
                                                |
                                                v
                    +----------------+    +----------------+    +----------------+
                    | Embeddings_    |    | Vector_Database|    | Retriever      |
                    | Generator      |    | or Pinecone    |    | - Deduplicate  |
                    | - Query embed  |--->| - find_similar |--->| - Rerank       |
                    +----------------+    +----------------+    | - Expand ctx   |
                                                                +----------------+
                                                                       |
                                                                       v
                    +----------------+    +----------------+    +----------------+
                    | Conversation   |    | LLM_Client     |    | Format         |
                    | - Get history  |    | - Build prompt |    | Response       |
                    | - Store turns  |<---| - Generate     |<---| - Include ctx  |
                    +----------------+    | - Cache        |    | - Token count  |
                                          +----------------+    +----------------+
                                                                       |
                                                                       v
                                                                 [JSON Response]
```

### 3.3 Streaming Response Flow

**Confidence:** 82%

```
+------------+     +---------------+     +-----------------+     +---------------+
|   USER     |     | RAG_Engine    |     | LLM_Client      |     | HTTP_Client   |
|  Message   |---->| stream_       |---->| stream_         |---->| - POST request|
|            |     | response()    |     | completion()    |     | - stream:true |
+------------+     +---------------+     +-----------------+     +---------------+
                          |                                             |
                          v                                             v
                   [Build context]                              [SSE Connection]
                          |                                             |
                          v                                             v
                   +---------------+                            +---------------+
                   | Callback      |<---------------------------| Parse chunks  |
                   | function      |     chunk.delta.content    | - OpenAI fmt  |
                   | - Echo chunk  |                            | - Anthropic   |
                   | - Accumulate  |                            | - Google fmt  |
                   +---------------+                            +---------------+
                          |
                          v
                   [Store complete message in conversation]
```

---

## 4. Database Schema

### 4.1 Entity Relationship Diagram

**Confidence:** 95%

```
+--------------------+          +----------------------+          +------------------+
|     documents      |          |       chatbots       |          |   conversations  |
+--------------------+          +----------------------+          +------------------+
| PK id              |          | PK id                |          | PK id            |
|    title           |          |    name              |          |    chatbot_id ---|-------+
|    source_type     |          |    active            |          |    user_id       |       |
|    source_id       |          |    avatar            |          |    session_id    |       |
|    file_path       |          |    feedback          |          |    guest_ip      |       |
|    mime_type       |          |    style (JSON)      |          |    created_at    |       |
|    status          |          |    messages_template |          |    updated_at    |       |
|    created_at      |          |    model_config      |          +--------+---------+       |
|    updated_at      |          |    created_at        |                   |                 |
+--------+-----------+          |    updated_at        |                   |                 |
         |                      +----------+-----------+                   |                 |
         |                                 |                               |                 |
         |1                               1|                              1|                 |
         |                                 |                               |                 |
         |N                               N|                              N|                 |
+--------+----------+          +-----------+-----------+          +--------+---------+       |
|      chunks       |          | content_relationships |          |     messages     |       |
+-------------------+          +-----------------------+          +------------------+       |
| PK id             |          | PK id                 |          | PK id            |       |
|    document_id ---|          |    source_type        |          |    conversation_id       |
|    content        |          |    source_id ---------|----------|    role          |       |
|    chunk_index    |          |    target_type        |          |    content       |       |
|    metadata (JSON)|          |    target_id          |          |    metadata(JSON)|       |
|    created_at     |          |    relationship_type  |          |    created_at    |       |
+--------+----------+          |    metadata (JSON)    |          +------------------+       |
         |                     |    created_at         |                                     |
         |1                    +-----------------------+                                     |
         |                                                                                   |
         |N                                                                                  |
+--------+----------+          +-------------------+              +------------------+       |
|    embeddings     |          |    analytics      |              |    wp_content    |       |
+-------------------+          +-------------------+              +------------------+       |
| PK id             |          | PK id             |              | PK id            |       |
|    chunk_id ------|          |    chatbot_id ----|--------------|    post_id       |       |
|    embedding(BLOB)|          |    event_type     |              |    post_type     |       |
|    model          |          |    event_data(JSON|              |    status        |       |
|    created_at     |          |    created_at     |              |    action        |       |
+-------------------+          +-------------------+              |    priority      |       |
                                                                  |    created_at    |       |
+-------------------+                                             |    updated_at    |       |
| document_metadata |                                             +------------------+       |
+-------------------+                                                                        |
| PK id             |                                                                        |
|    document_id ---|                                                                        |
|    meta_key       |                                                                        |
|    meta_value     |                                                                        |
|    created_at     |                                                                        |
|    updated_at     |                                                                        |
+-------------------+                                                                        |
```

### 4.2 Table Details

| Table | Purpose | Key Indexes |
|-------|---------|-------------|
| `{prefix}ai_botkit_documents` | Source document metadata | source_type_id, status |
| `{prefix}ai_botkit_document_metadata` | Extended document metadata | document_meta, meta_key |
| `{prefix}ai_botkit_chunks` | Text chunks for embedding | document_id, FULLTEXT(content) |
| `{prefix}ai_botkit_embeddings` | Vector embeddings | chunk_model |
| `{prefix}ai_botkit_chatbots` | Chatbot configurations | - |
| `{prefix}ai_botkit_conversations` | Chat sessions | user_id, session_id, guest_ip |
| `{prefix}ai_botkit_messages` | Chat messages | conversation_id |
| `{prefix}ai_botkit_content_relationships` | Chatbot-content mappings | source, target, relationship_type |
| `{prefix}ai_botkit_analytics` | Analytics events | event_type, chatbot_id |
| `{prefix}ai_botkit_wp_content` | WordPress content sync queue | post_type_id, status |

**Note:** Tables support migration from `ai_botkit_` prefix to `knowvault_` prefix via Table_Helper class.

---

## 5. API Contracts

### 5.1 REST API Endpoints

**Namespace:** `ai-botkit/v1`
**Confidence:** 92%

#### 5.1.1 Chat Message

```
POST /wp-json/ai-botkit/v1/chat/message

Request:
{
  "message": string (required),
  "conversation_id": string (optional),
  "bot_id": integer (required),
  "context": string (optional)
}

Response (200):
{
  "response": string,
  "context": array,
  "metadata": {
    "tokens": integer,
    "model": string,
    "context_chunks": integer,
    "conversation_id": string,
    "processing_time": float
  }
}

Response (403):
{
  "error": "Access denied. Your IP address has been blocked."
}

Response (500):
{
  "error": string
}

Permission: check_chat_permission (currently returns true)
```

#### 5.1.2 List Conversations

```
GET /wp-json/ai-botkit/v1/conversations

Response (200):
[
  {
    "id": integer,
    "chatbot_id": integer,
    "user_id": integer,
    "session_id": string,
    "guest_ip": string|null,
    "created_at": datetime,
    "updated_at": datetime
  }
]

Permission: check_history_permission (filterable)
```

#### 5.1.3 Get Conversation

```
GET /wp-json/ai-botkit/v1/conversations/{id}

Response (200):
{
  "id": string,
  "messages": [
    {
      "id": integer,
      "conversation_id": integer,
      "role": "user"|"assistant",
      "content": string,
      "metadata": json,
      "created_at": datetime
    }
  ]
}

Response (404):
{
  "error": "Conversation not found"
}

Permission: check_history_permission
```

#### 5.1.4 Delete Conversation

```
DELETE /wp-json/ai-botkit/v1/conversations/{id}

Response (204): No content

Permission: check_history_permission
```

#### 5.1.5 List Documents

```
GET /wp-json/ai-botkit/v1/documents

Response (200):
[
  {
    "id": integer,
    "title": string,
    "source_type": string,
    "source_id": integer|null,
    "file_path": string|null,
    "mime_type": string|null,
    "status": string,
    "created_at": datetime,
    "updated_at": datetime
  }
]

Permission: check_documents_permission
```

#### 5.1.6 Create Document

```
POST /wp-json/ai-botkit/v1/documents

Request:
{
  "title": string (required),
  "content": string (required),
  "type": "text"|"url"|"file" (required)
}

Response (201):
{
  "document_id": integer,
  "chunk_count": integer,
  "embedding_count": integer,
  "metadata": object
}

Permission: check_documents_permission
```

#### 5.1.7 Get Analytics

```
GET /wp-json/ai-botkit/v1/analytics?start_date=YYYY-MM-DD&end_date=YYYY-MM-DD

Response (200):
{
  "daily_messages": [
    { "date": "YYYY-MM-DD", "count": integer }
  ],
  "user_engagement": [
    { "user_id": integer, "message_count": integer }
  ],
  "total_messages": integer,
  "active_users": integer
}

Permission: view_ai_botkit_analytics capability
```

### 5.2 AJAX Endpoints

**Confidence:** 90%

#### 5.2.1 Public AJAX Actions

| Action | Nonce | Description |
|--------|-------|-------------|
| `ai_botkit_chat_message` | ai_botkit_chat | Send chat message |
| `ai_botkit_get_history` | ai_botkit_chat | Get conversation history |
| `ai_botkit_clear_conversation` | ai_botkit_chat | Clear conversation |
| `ai_botkit_feedback` | ai_botkit_chat | Submit message feedback |

#### 5.2.2 Admin AJAX Actions (30+)

**Requirement:** `check_ajax_referer('ai_botkit_admin', 'nonce')` + `current_user_can('manage_options')`

| Category | Actions |
|----------|---------|
| **API Testing** | `ai_botkit_test_api_connection`, `ai_botkit_test_pinecone_connection` |
| **Chatbot CRUD** | `ai_botkit_save_chatbot`, `ai_botkit_get_chatbot`, `ai_botkit_delete_chatbot`, `ai_botkit_enable_chatbot`, `ai_botkit_enable_chatbot_sitewide` |
| **Documents** | `ai_botkit_upload_file`, `ai_botkit_import_url`, `ai_botkit_import_wp_content`, `ai_botkit_delete_document`, `ai_botkit_reprocess_document` |
| **Knowledge Base** | `ai_botkit_add_chatbot_documents`, `ai_botkit_remove_chatbot_document`, `ai_botkit_get_chatbot_documents`, `ai_botkit_get_available_documents` |
| **Migration** | `ai_botkit_get_migration_status`, `ai_botkit_start_migration`, `ai_botkit_download_migration_log`, `ai_botkit_clear_migration_lock` |
| **Analytics** | `ai_botkit_get_analytics_data`, `ai_botkit_get_knowledge_base_data` |
| **Settings** | `ai_botkit_set_rate_limits`, `ai_botkit_reset_rate_limits`, `ai_botkit_update_fallback_order` |

---

## 6. Security Architecture

**Confidence:** 80%

### 6.1 Authentication & Authorization

#### 6.1.1 Custom Capabilities

| Capability | Default Roles | Purpose |
|------------|---------------|---------|
| `manage_ai_botkit` | administrator | Full plugin management |
| `edit_ai_botkit_settings` | administrator | Edit settings |
| `view_ai_botkit_analytics` | administrator | View analytics |
| `manage_ai_botkit_documents` | administrator, editor | Document management |
| `use_ai_botkit_chat` | all roles | Chat access |
| `view_ai_botkit_history` | administrator, editor, author | View history |

#### 6.1.2 User Identification

| User Type | Identification Method |
|-----------|----------------------|
| Logged-in | WordPress user ID |
| Guest | SHA256 hashed IP address |

### 6.2 Input Validation

#### 6.2.1 AJAX Security

```php
// Admin endpoints
check_ajax_referer('ai_botkit_admin', 'nonce');
current_user_can('manage_options');

// Public endpoints
check_ajax_referer('ai_botkit_chat', 'nonce');
```

#### 6.2.2 Sanitization Functions

| Function | Use Case |
|----------|----------|
| `sanitize_text_field()` | Single-line text |
| `sanitize_textarea_field()` | Multi-line text |
| `sanitize_key()` | IDs and keys |
| `absint()` | Integers |
| `wp_kses_post()` | Rich text content |

### 6.3 IP Blocking

```php
// Block check
$blocked_ips = json_decode(get_option('ai_botkit_blocked_ips', '[]'), true);
$is_blocked = in_array($_SERVER['REMOTE_ADDR'], $blocked_ips);
```

### 6.4 Banned Keywords

```php
// Keyword filtering in RAG_Engine
$banned_keywords = json_decode(get_option('ai_botkit_banned_keywords', '[]'), true);
// Word boundary matching
$pattern = '/\b' . preg_quote($keyword, '/') . '\b/i';
```

### 6.5 API Key Storage

- API keys stored in WordPress options table
- Keys retrieved via `get_option()` calls
- No encryption at application level (relies on wp-config.php security)

---

## 7. Performance Architecture

**Confidence:** 82%

### 7.1 Caching Strategy

#### 7.1.1 Unified_Cache_Manager

**Multi-tier caching with group-based expiration:**

| Cache Group | TTL | Purpose |
|-------------|-----|---------|
| `database` | 5 min | Query results |
| `ajax` | 2 min | AJAX responses |
| `migration` | 5 min | Migration status |
| `admin_interface` | 5 min | Admin page data |
| `content` | 10 min | Processed content |
| `performance` | 15 min | Performance metrics |

#### 7.1.2 Cache Integration Points

| Component | Cache Key Pattern | Purpose |
|-----------|-------------------|---------|
| LLM_Client | `completion_{hash}` | Cache LLM responses |
| Vector_Database | `similar_{hash}` | Cache similarity searches |
| Retriever | `context_{hash}` | Cache context results |
| Conversation | `conversation_{id}` | Cache conversation history |

### 7.2 Rate Limiting

**Dual rate limiting system:**

| Limit Type | Default | Window | Storage |
|------------|---------|--------|---------|
| Token bucket | 100,000 tokens | 24 hours | Calculated from messages table |
| Message count | 60 messages | 24 hours | Calculated from messages table |

### 7.3 Optimization Classes

| Class | Purpose |
|-------|---------|
| `Database_Optimizer` | Index creation and query optimization |
| `AJAX_Optimizer` | AJAX request optimization |
| `AJAX_Response_Compressor` | Response compression |
| `Content_Optimizer` | Content processing optimization |
| `Admin_Interface_Optimizer` | Admin page optimization |

### 7.4 Health Monitoring

**Health_Checks System:**

| Check | Warning Threshold | Critical Threshold |
|-------|-------------------|-------------------|
| Memory usage | 75% | 90% |
| Cache expired ratio | - | - |
| Queue stuck items | - | - |
| API connectivity | - | - |
| Database tables | - | - |

**Scheduled Check:** `ai_botkit_hourly_health_check` action (hourly)

---

## 8. Design Patterns Detected

**Confidence:** 85%

| Pattern | Implementation | Location |
|---------|---------------|----------|
| **Singleton** | `Cache_Configuration::get_instance()` | class-cache-configuration.php |
| **Factory** | Document loading by source type | Document_Loader |
| **Strategy** | Multi-provider LLM implementations | LLM_Client |
| **Observer** | WordPress hooks for content sync | WordPress_Content, LearnDash |
| **Repository** | Entity data access abstraction | Chatbot, Conversation models |
| **Pipeline** | Document processing chain | RAG_Engine |
| **Decorator** | Response enhancement | WooCommerce_Assistant |
| **Adapter** | Unified vector DB interface | Vector_Database, Pinecone_Database |
| **Template Method** | AJAX handler patterns | Admin/Public Ajax_Handler |

---

## 9. Extension Points

**Confidence:** 75%

### 9.1 WordPress Filters

| Filter | Purpose | Parameters |
|--------|---------|------------|
| `ai_botkit_before_llm_request` | Modify LLM request before sending | $request_data, $model |
| `ai_botkit_post_content` | Filter post content before processing | $content, $post_id |
| `ai_botkit_user_aware_context` | Enrollment-aware context filtering | $enrolled_courses, $bot_id |
| `ai_botkit_pre_response` | Enhance response (WooCommerce) | $response, $context |
| `ai_botkit_can_use_chat` | Check chat permission | $allowed, $user_id |
| `ai_botkit_can_view_history` | Check history permission | $allowed, $user_id |
| `ai_botkit_can_manage_documents` | Check documents permission | $allowed, $user_id |
| `ai_botkit_can_manage_settings` | Check settings permission | $allowed, $user_id |

### 9.2 WordPress Actions

| Action | Purpose | Parameters |
|--------|---------|------------|
| `ai_botkit_after_llm_response` | Post-processing of LLM response | $response_data, $request_data |
| `ai_botkit_llm_error` | LLM error handling | $exception, $request_data |
| `ai_botkit_chat_message` | Post chat message processing | $message, $response |
| `ai_botkit_embedding_batch_start` | Before embedding batch | $batch_size |
| `ai_botkit_embedding_batch_complete` | After embedding batch | $batch_size, $results |
| `ai_botkit_process_queue` | Document queue processing | - |

---

## 10. Assumptions and Review Notes

### 10.1 Assumptions Made

1. **Error Handling:** Assumed all custom exceptions extend PHP's base Exception class
2. **Database Transactions:** No explicit transaction handling observed; assumes WordPress's autocommit behavior
3. **Scaling:** Pinecone integration suggests horizontal scaling capability, but no load balancing observed
4. **Session Management:** Relies on browser-generated session IDs; no server-side session validation

### 10.2 Items Requiring Manual Review

1. **Security Audit:** API key storage should be reviewed for encryption requirements
2. **Performance Testing:** Cache TTL values should be validated under production load
3. **Error Recovery:** Document processing failure recovery mechanisms should be verified
4. **Rate Limit Accuracy:** Token counting from metadata may not match actual API usage

### 10.3 Incomplete Areas

1. **Webhook support:** No outgoing webhook integration detected
2. **Backup/Restore:** No backup functionality for chatbot configurations
3. **Multi-site:** No explicit multi-site support detected
4. **Queue Worker:** Document queue relies on page loads; no dedicated worker

---

## Appendix A: File Structure

```
ai-botkit-chatbot/
├── knowVault.php                           # Main plugin entry point
├── includes/
│   ├── class-ai-botkit.php                # Bootstrap class
│   ├── class-ai-botkit-activator.php      # Activation handler
│   ├── class-ai-botkit-deactivator.php    # Deactivation handler
│   ├── core/                              # Core RAG pipeline (25+ files)
│   │   ├── class-rag-engine.php          # Main orchestrator
│   │   ├── class-llm-client.php          # Multi-provider LLM
│   │   ├── class-vector-database.php     # Local vector storage
│   │   ├── class-pinecone-database.php   # Pinecone integration
│   │   ├── class-retriever.php           # Context retrieval
│   │   ├── class-document-loader.php     # Document loading
│   │   ├── class-text-chunker.php        # Text splitting
│   │   ├── class-embeddings-generator.php # Embeddings
│   │   ├── class-rate-limiter.php        # Rate limiting
│   │   ├── class-unified-cache-manager.php # Caching
│   │   └── ...                           # Optimizer classes
│   ├── admin/                            # Admin classes
│   ├── public/                           # Frontend classes
│   ├── models/                           # Data models
│   ├── integration/                      # Third-party integrations
│   ├── monitoring/                       # Monitoring classes
│   ├── utils/                            # Utility classes
│   └── vendor/                           # Composer dependencies
├── admin/
│   ├── css/                              # Admin styles
│   └── js/                               # Admin scripts
└── public/
    ├── css/                              # Frontend styles
    ├── js/                               # Frontend scripts
    └── templates/                        # PHP templates
```

---

*RECOVERED DOCUMENT - Generated by Spec Recovery Agent*
*Review and validate all sections before using as authoritative reference*
