# RECOVERED: AI BotKit Chatbot - Functional Specification

> **RECOVERED DOCUMENT:** Functional requirements inferred from code analysis
> **Generated:** 2026-01-28
> **Confidence Score:** 80%
> **Review Required:** Yes - Verify business intent and add acceptance criteria

---

## Document Status

| Section | Status | Confidence |
|---------|--------|------------|
| Product Overview | RECOVERED | 85% |
| Functional Requirements | RECOVERED | 78% |
| Non-Functional Requirements | RECOVERED | 82% |
| User Stories | INFERRED | 70% |
| Acceptance Criteria | NEEDS MANUAL COMPLETION | 60% |

---

## 1. Product Overview

### 1.1 Product Description

AI BotKit Chatbot (KnowVault) is a WordPress plugin that provides AI-powered conversational interfaces using Retrieval Augmented Generation (RAG) technology. The system enables website visitors to ask questions and receive contextually relevant answers based on the site's content.

### 1.2 Target Users

| User Type | Description | Inferred From |
|-----------|-------------|---------------|
| **Site Administrators** | Configure chatbots, manage documents, view analytics | Admin AJAX handlers, capabilities |
| **Content Editors** | Manage knowledge base documents | `manage_ai_botkit_documents` capability |
| **Logged-in Users** | Use chatbot with conversation history | User_Authentication, rate limiting |
| **Guest Visitors** | Use chatbot (IP-tracked) | guest_ip handling in Conversation |
| **LearnDash Students** | Access enrollment-aware content | LearnDash integration |
| **WooCommerce Shoppers** | Get product assistance | WooCommerce_Assistant |

### 1.3 Key Value Propositions

**Inferred from code implementation:**

1. **Intelligent Q&A:** Answer questions based on actual site content
2. **Multi-Source Knowledge:** Combine WordPress content, PDFs, and URLs
3. **Provider Flexibility:** Multiple LLM provider support with fallback
4. **Seamless Integration:** Deep LearnDash and WooCommerce integration
5. **Enterprise Features:** Rate limiting, analytics, health monitoring

---

## 2. Functional Requirements

### FR-001: Document Ingestion

**Confidence:** 88%
**Source:** `class-document-loader.php`, `class-rag-engine.php`

**Description:** System shall allow administrators to import documents from multiple sources for use in the knowledge base.

#### FR-001.1: PDF File Upload

| Attribute | Value |
|-----------|-------|
| **Source** | `Document_Loader::load_from_file()` |
| **Library** | smalot/pdfparser |
| **Max Size** | Not specified in code (WordPress limits apply) |
| **Storage** | `wp-content/uploads/ai-botkit/documents/` |

**Inferred Acceptance Criteria:**
- [ ] PDF files can be uploaded via admin interface
- [ ] Text content is extracted from uploaded PDFs
- [ ] Extracted content is stored with document metadata
- [ ] Upload errors are handled gracefully

#### FR-001.2: URL Import

| Attribute | Value |
|-----------|-------|
| **Source** | `Document_Loader::load_from_url()` |
| **Library** | fivefilters/readability.php |
| **Content Extraction** | Main article content via Readability algorithm |

**Inferred Acceptance Criteria:**
- [ ] URLs can be imported via admin interface
- [ ] Main content is extracted from web pages
- [ ] Navigation, ads, and boilerplate are filtered out
- [ ] Invalid URLs are rejected with error message

#### FR-001.3: WordPress Content Import

| Attribute | Value |
|-----------|-------|
| **Source** | `Document_Loader::load_from_post()` |
| **Post Types** | All public post types (configurable) |
| **Metadata** | Title, categories, tags, featured image |

**Inferred Acceptance Criteria:**
- [ ] Any public post type can be imported
- [ ] Post content includes rendered shortcodes
- [ ] Metadata (categories, tags) is preserved
- [ ] Existing imports can be updated

---

### FR-002: Text Chunking and Embedding Generation

**Confidence:** 85%
**Source:** `class-text-chunker.php`, `class-embeddings-generator.php`

**Description:** System shall split documents into optimal chunks and generate vector embeddings for semantic search.

#### FR-002.1: Text Chunking

| Attribute | Value |
|-----------|-------|
| **Source** | `Text_Chunker::split_text()` |
| **Chunk Size** | 1000 characters (default) |
| **Overlap** | 200 characters (default) |
| **Min Size** | 700 characters |

**Chunking Algorithm:**
1. Split by paragraphs
2. Split large paragraphs by sentences
3. Merge small chunks
4. Add overlaps for context continuity

**Inferred Acceptance Criteria:**
- [ ] Documents are split into chunks of approximately 1000 characters
- [ ] Chunks maintain semantic boundaries (paragraphs, sentences)
- [ ] Adjacent chunks have 200-character overlap
- [ ] Small final chunks are merged with previous
- [ ] UTF-8 text is properly handled

#### FR-002.2: Embedding Generation

| Attribute | Value |
|-----------|-------|
| **Source** | `Embeddings_Generator::generate_embeddings()` |
| **Default Model** | text-embedding-3-small (OpenAI) |
| **Dimensions** | 1536 |
| **Batch Processing** | Yes |

**Inferred Acceptance Criteria:**
- [ ] Each chunk is converted to a 1536-dimension vector
- [ ] Embeddings are generated in batches for efficiency
- [ ] Failed embeddings are logged and retried
- [ ] API errors are handled gracefully

---

### FR-003: Vector Storage

**Confidence:** 90%
**Source:** `class-vector-database.php`, `class-pinecone-database.php`

**Description:** System shall store document embeddings in either a local database or cloud vector service.

#### FR-003.1: Local Vector Storage

| Attribute | Value |
|-----------|-------|
| **Source** | `Vector_Database` class |
| **Storage Format** | Base64-encoded binary (LONGBLOB) |
| **Search Method** | Cosine similarity |

**Inferred Acceptance Criteria:**
- [ ] Embeddings are stored in MySQL database
- [ ] Cosine similarity search works correctly
- [ ] Old embeddings are cleaned up on document update
- [ ] Storage statistics are available

#### FR-003.2: Pinecone Cloud Storage

| Attribute | Value |
|-----------|-------|
| **Source** | `Pinecone_Database` class |
| **API Endpoints** | upsert, query, delete, fetch |
| **Metadata Filtering** | By document_id |

**Inferred Acceptance Criteria:**
- [ ] Pinecone can be enabled/disabled in settings
- [ ] Vectors are synced to Pinecone when enabled
- [ ] Queries filter by chatbot's knowledge base
- [ ] Connection can be tested before enabling

---

### FR-004: Context Retrieval with Re-ranking

**Confidence:** 85%
**Source:** `class-retriever.php`

**Description:** System shall retrieve relevant context from the knowledge base for each user query.

#### FR-004.1: Similarity Search

| Attribute | Value |
|-----------|-------|
| **Source** | `Retriever::find_context()` |
| **Max Results** | 5 (default) |
| **Min Similarity** | 0.0 (configurable) |

**Inferred Acceptance Criteria:**
- [ ] Query is converted to embedding
- [ ] Most similar chunks are retrieved
- [ ] Results meet minimum similarity threshold
- [ ] Results are cached for repeated queries

#### FR-004.2: Result Re-ranking

| Attribute | Value |
|-----------|-------|
| **Source** | `Retriever::rerank_chunks()` |
| **Factors** | Similarity, recency, content type |

**Re-ranking Boosts:**
| Content Type | Boost Factor |
|--------------|--------------|
| Page | 1.2x |
| Product | 1.15x |
| Course | 1.15x |
| Post | 1.1x |

**Inferred Acceptance Criteria:**
- [ ] Results are re-ranked by combined score
- [ ] Recent content is boosted
- [ ] Important content types are prioritized
- [ ] Duplicate/similar results are filtered

#### FR-004.3: Context Window Expansion

| Attribute | Value |
|-----------|-------|
| **Source** | `Retriever::expand_context()` |
| **Window Size** | 3 chunks |

**Inferred Acceptance Criteria:**
- [ ] Surrounding chunks from same document are included
- [ ] Context maintains document continuity
- [ ] Expanded context improves response quality

---

### FR-005: Multi-Provider LLM Completions

**Confidence:** 92%
**Source:** `class-llm-client.php`

**Description:** System shall support multiple LLM providers for generating chat responses.

#### FR-005.1: OpenAI Provider

| Attribute | Value |
|-----------|-------|
| **API** | api.openai.com/v1 |
| **Models** | gpt-4-turbo, gpt-4o-mini, gpt-3.5-turbo |
| **Features** | Embeddings, completions, streaming |

**Inferred Acceptance Criteria:**
- [ ] OpenAI API key can be configured
- [ ] Model selection is available
- [ ] Responses are generated correctly
- [ ] Streaming responses work

#### FR-005.2: Anthropic Provider

| Attribute | Value |
|-----------|-------|
| **API** | api.anthropic.com/v1 |
| **Models** | claude-3-opus, claude-3-sonnet |
| **Features** | Completions, streaming |
| **Embeddings** | Via VoyageAI |

**Inferred Acceptance Criteria:**
- [ ] Anthropic API key can be configured
- [ ] System prompt is handled correctly
- [ ] Claude models can be selected
- [ ] VoyageAI embeddings work with Anthropic

#### FR-005.3: Google AI Provider

| Attribute | Value |
|-----------|-------|
| **API** | generativelanguage.googleapis.com |
| **Models** | gemini-1.5-flash |
| **Features** | Embeddings, completions |

**Inferred Acceptance Criteria:**
- [ ] Google API key can be configured
- [ ] Contents format is correctly transformed
- [ ] Gemini models can be selected

#### FR-005.4: Together AI Provider

| Attribute | Value |
|-----------|-------|
| **API** | api.together.xyz/v1 |
| **Models** | Various open models |
| **Features** | Embeddings, completions, streaming |

**Inferred Acceptance Criteria:**
- [ ] Together AI API key can be configured
- [ ] Open-source models are available
- [ ] OpenAI-compatible format works

#### FR-005.5: VoyageAI Provider

| Attribute | Value |
|-----------|-------|
| **API** | api.voyageai.com/v1 |
| **Models** | voyage-2 |
| **Features** | Embeddings only |

**Inferred Acceptance Criteria:**
- [ ] VoyageAI API key can be configured
- [ ] voyage-2 embeddings work correctly
- [ ] Used with Anthropic completions

---

### FR-006: Chat Interface

**Confidence:** 85%
**Source:** `class-shortcode-handler.php`, `public/templates/`

**Description:** System shall provide embeddable chat interfaces for website visitors.

#### FR-006.1: Embedded Chat Shortcode

| Attribute | Value |
|-----------|-------|
| **Shortcode** | `[ai_botkit_chat]` |
| **Handler** | `Shortcode_Handler::render_chat()` |

**Attributes:**
| Attribute | Type | Description |
|-----------|------|-------------|
| `id` | int | Chatbot ID |
| `title` | string | Chat window title |
| `welcome_message` | string | Initial message |
| `placeholder` | string | Input placeholder |
| `context` | string | Additional context |
| `width` | string | Container width |
| `height` | string | Container height |
| `theme` | string | Theme name |
| `widget` | bool | Widget mode |

**Inferred Acceptance Criteria:**
- [ ] Chat interface renders on any page
- [ ] All shortcode attributes work correctly
- [ ] Chat is responsive on mobile
- [ ] Custom theming is applied

#### FR-006.2: Floating Widget Shortcode

| Attribute | Value |
|-----------|-------|
| **Shortcode** | `[ai_botkit_widget]` |
| **Handler** | `Shortcode_Handler::render_widget()` |

**Attributes:**
| Attribute | Type | Description |
|-----------|------|-------------|
| `id` | int | Chatbot ID |
| `position` | string | bottom-right, bottom-left |
| `offset_x` | string | X offset |
| `offset_y` | string | Y offset |
| `title` | string | Widget title |
| `welcome_message` | string | Initial message |
| `button_text` | string | Toggle button text |
| `theme` | string | Theme name |

**Inferred Acceptance Criteria:**
- [ ] Widget appears in specified corner
- [ ] Widget can be opened/closed
- [ ] Position offsets work correctly
- [ ] Widget is accessible on mobile

#### FR-006.3: Sitewide Chatbot

| Attribute | Value |
|-----------|-------|
| **Source** | `Shortcode_Handler::render_sitewide_chatbot()` |
| **Option** | `ai_botkit_chatbot_sitewide_enabled` |

**Inferred Acceptance Criteria:**
- [ ] One chatbot can be enabled sitewide
- [ ] Widget appears on all pages
- [ ] Can be disabled from settings
- [ ] Does not conflict with page-specific chatbots

---

### FR-007: Conversation Persistence

**Confidence:** 88%
**Source:** `class-conversation.php`, `class-rag-engine.php`

**Description:** System shall persist conversation history across sessions.

#### FR-007.1: Session Management

| Attribute | Value |
|-----------|-------|
| **Source** | `Conversation` model |
| **Session ID** | Client-generated |
| **Guest ID** | SHA256 hashed IP |

**Inferred Acceptance Criteria:**
- [ ] Conversations are linked to user accounts
- [ ] Guest conversations are tracked by IP hash
- [ ] Session ID persists across page loads
- [ ] Conversation can be resumed

#### FR-007.2: Message Storage

| Attribute | Value |
|-----------|-------|
| **Source** | `Conversation::add_message()` |
| **Roles** | user, assistant |
| **Metadata** | tokens, model |

**Inferred Acceptance Criteria:**
- [ ] All messages are stored in database
- [ ] Token usage is tracked per message
- [ ] Model information is recorded
- [ ] Messages can be retrieved for history

#### FR-007.3: History Retrieval

| Attribute | Value |
|-----------|-------|
| **Source** | `Conversation::get_messages()` |
| **Limit** | 5 messages (default) |

**Inferred Acceptance Criteria:**
- [ ] Users can view conversation history
- [ ] History is paginated
- [ ] Old conversations can be cleared
- [ ] First message preview is available

---

### FR-008: Rate Limiting

**Confidence:** 85%
**Source:** `class-rate-limiter.php`

**Description:** System shall enforce usage limits to prevent abuse and control costs.

#### FR-008.1: Token Bucket Limiting

| Attribute | Value |
|-----------|-------|
| **Source** | `Rate_Limiter::check_user_limits()` |
| **Default Limit** | 100,000 tokens/day |
| **Option** | `ai_botkit_token_bucket_limit` |

**Inferred Acceptance Criteria:**
- [ ] Token usage is tracked per user
- [ ] Users exceeding limit receive error message
- [ ] Limits reset daily
- [ ] Admins can configure limit value

#### FR-008.2: Message Count Limiting

| Attribute | Value |
|-----------|-------|
| **Source** | `Rate_Limiter::check_user_limits()` |
| **Default Limit** | 60 messages/day |
| **Option** | `ai_botkit_max_requests_per_day` |

**Inferred Acceptance Criteria:**
- [ ] Message count is tracked per user
- [ ] Guest users are tracked by IP
- [ ] Users exceeding limit receive error message
- [ ] Admins can configure limit value

#### FR-008.3: Rate Limit Display

| Attribute | Value |
|-----------|-------|
| **Source** | `Rate_Limiter::get_remaining_limits()` |

**Inferred Acceptance Criteria:**
- [ ] Users can view their remaining limits
- [ ] Usage statistics are accurate
- [ ] Reset time is communicated

---

### FR-009: Analytics Tracking

**Confidence:** 82%
**Source:** `class-analytics.php`, `class-chatbot.php`

**Description:** System shall track usage analytics for chatbots.

#### FR-009.1: Event Logging

| Attribute | Value |
|-----------|-------|
| **Source** | `Chatbot::log_event()`, `Analytics::log_event()` |
| **Storage** | `ai_botkit_analytics` table |

**Event Types (Inferred):**
- Chat message sent
- Chat message received
- Conversation started
- Conversation ended
- Document processed
- Error occurred

**Inferred Acceptance Criteria:**
- [ ] All significant events are logged
- [ ] Events include chatbot ID
- [ ] Event data is stored as JSON
- [ ] Timestamps are recorded

#### FR-009.2: Analytics Dashboard

| Attribute | Value |
|-----------|-------|
| **Source** | `Chatbot::get_analytics()`, REST API |
| **Date Range** | Configurable (default 30 days) |

**Metrics (Inferred):**
- Daily message counts
- User engagement (messages per user)
- Total messages
- Active users

**Inferred Acceptance Criteria:**
- [ ] Analytics are viewable in admin
- [ ] Date range can be filtered
- [ ] Charts display usage trends
- [ ] Data can be exported

---

### FR-010: Health Monitoring

**Confidence:** 80%
**Source:** `class-health-checks.php`

**Description:** System shall monitor its own health and alert administrators to issues.

#### FR-010.1: Health Checks

| Attribute | Value |
|-----------|-------|
| **Source** | `Health_Checks::run_health_check()` |
| **Schedule** | Hourly (`ai_botkit_hourly_health_check`) |

**Checks (Inferred):**
- API connectivity (OpenAI, Anthropic, etc.)
- Database table existence
- Memory usage (>75% warning, >90% critical)
- Cache health
- Queue health

**Inferred Acceptance Criteria:**
- [ ] Health checks run automatically
- [ ] Critical issues are flagged
- [ ] Status is viewable in admin
- [ ] Manual check trigger is available

---

### FR-011: LearnDash Integration

**Confidence:** 82%
**Source:** `class-learndash.php`

**Description:** System shall integrate with LearnDash LMS to provide course-aware chatbot functionality.

#### FR-011.1: Content Synchronization

| Attribute | Value |
|-----------|-------|
| **Source** | `LearnDash::handle_*_update()` methods |
| **Content Types** | Courses, lessons, topics, quizzes, questions |

**Hooked Actions:**
- `save_post_sfwd-courses`
- `save_post_sfwd-lessons`
- `save_post_sfwd-topic`
- `save_post_sfwd-quiz`
- `save_post_sfwd-question`

**Inferred Acceptance Criteria:**
- [ ] LearnDash content is auto-synced
- [ ] Updates trigger re-processing
- [ ] Deletions remove from vector DB
- [ ] Quiz questions are included

#### FR-011.2: Enrollment-Aware Context

| Attribute | Value |
|-----------|-------|
| **Source** | `RAG_Engine::process_context_for_enrollment()` |
| **Filter** | `ai_botkit_user_aware_context` |

**Inferred Acceptance Criteria:**
- [ ] Non-enrolled users see limited content
- [ ] Enrollment prompt is shown
- [ ] Enrolled users see full content
- [ ] Context includes course metadata

---

### FR-012: WooCommerce Integration

**Confidence:** 80%
**Source:** `class-woocommerce.php`, `class-woocommerce-assistant.php`

**Description:** System shall integrate with WooCommerce to provide shopping assistance.

#### FR-012.1: Product Synchronization

| Attribute | Value |
|-----------|-------|
| **Source** | `WooCommerce::handle_product_update()` |
| **Content** | Products, variations |

**Hooked Actions:**
- `woocommerce_update_product`
- `woocommerce_delete_product`
- `woocommerce_save_product_variation`

**Inferred Acceptance Criteria:**
- [ ] Products are auto-synced
- [ ] Variations are included
- [ ] Product updates trigger re-processing
- [ ] Deleted products are removed

#### FR-012.2: Shopping Assistant

| Attribute | Value |
|-----------|-------|
| **Source** | `WooCommerce_Assistant` class |
| **Filter** | `ai_botkit_pre_response` |

**Intent Detection:**
- Product information queries
- Shopping cart assistance
- Order status inquiries
- Product recommendations

**Inferred Acceptance Criteria:**
- [ ] Shopping intents are detected
- [ ] Product data enhances responses
- [ ] Cart information is accessible
- [ ] Order status can be queried

---

### FR-013: Admin Management

**Confidence:** 85%
**Source:** `class-admin.php`, `class-ajax-handler.php` (admin)

**Description:** System shall provide comprehensive admin interface for management.

#### FR-013.1: Chatbot Management

| Attribute | Value |
|-----------|-------|
| **Source** | Admin AJAX handlers |
| **CRUD** | Create, read, update, delete |

**Inferred Acceptance Criteria:**
- [ ] Chatbots can be created
- [ ] Chatbots can be edited
- [ ] Chatbots can be deleted
- [ ] Chatbots can be enabled/disabled
- [ ] Sitewide chatbot can be designated

#### FR-013.2: Document Management

| Attribute | Value |
|-----------|-------|
| **Source** | Admin AJAX handlers |
| **Actions** | Upload, import, delete, reprocess |

**Inferred Acceptance Criteria:**
- [ ] Files can be uploaded
- [ ] URLs can be imported
- [ ] WordPress content can be imported
- [ ] Documents can be deleted
- [ ] Documents can be reprocessed
- [ ] Processing status is visible

#### FR-013.3: Knowledge Base Management

| Attribute | Value |
|-----------|-------|
| **Source** | Admin AJAX handlers |
| **Actions** | Add, remove, list documents per chatbot |

**Inferred Acceptance Criteria:**
- [ ] Documents can be added to chatbots
- [ ] Documents can be removed from chatbots
- [ ] Document list is viewable per chatbot
- [ ] Available documents are listable

#### FR-013.4: Settings Management

| Attribute | Value |
|-----------|-------|
| **Source** | `Admin::register_settings()` |
| **Categories** | LLM, processing, rate limits, integrations |

**Inferred Acceptance Criteria:**
- [ ] API keys can be configured
- [ ] Model settings can be adjusted
- [ ] Rate limits can be set
- [ ] Integration settings can be toggled

---

### FR-014: User Authentication and Permissions

**Confidence:** 80%
**Source:** `class-user-authentication.php`

**Description:** System shall enforce role-based access control.

#### FR-014.1: Custom Capabilities

| Capability | Default Roles |
|------------|---------------|
| `manage_ai_botkit` | administrator |
| `edit_ai_botkit_settings` | administrator |
| `view_ai_botkit_analytics` | administrator |
| `manage_ai_botkit_documents` | administrator, editor |
| `use_ai_botkit_chat` | all |
| `view_ai_botkit_history` | administrator, editor, author |

**Inferred Acceptance Criteria:**
- [ ] Capabilities are added on activation
- [ ] Capabilities are removed on deactivation
- [ ] Access is enforced per capability
- [ ] Roles can be customized

#### FR-014.2: Permission Filters

| Filter | Purpose |
|--------|---------|
| `ai_botkit_can_use_chat` | Control chat access |
| `ai_botkit_can_view_history` | Control history access |
| `ai_botkit_can_manage_documents` | Control document access |
| `ai_botkit_can_manage_settings` | Control settings access |

**Inferred Acceptance Criteria:**
- [ ] Permissions are filterable
- [ ] Custom logic can be added
- [ ] Denied access shows appropriate message

---

## 3. Non-Functional Requirements

### NFR-001: Performance

**Confidence:** 82%
**Source:** Cache managers, optimizer classes, database indexes

#### NFR-001.1: Response Time

| Metric | Target | Source |
|--------|--------|--------|
| Chat response | < 3 seconds | Cached context, streaming |
| Document processing | Background | Queue processing |
| Page load impact | Minimal | Lazy loading |

**Implementation Evidence:**
- Multi-layer caching (Unified_Cache_Manager)
- Response caching (completion_* keys)
- Context caching (context_* keys)
- Streaming response support

#### NFR-001.2: Caching Strategy

| Cache Type | TTL | Purpose |
|------------|-----|---------|
| LLM completions | 1 hour | Reduce API calls |
| Similarity results | 1 hour | Reduce DB queries |
| Context results | 1 hour | Reduce processing |
| Conversation history | 1 hour | Session performance |

#### NFR-001.3: Database Optimization

| Optimization | Source |
|--------------|--------|
| Indexed columns | `Database_Optimizer` |
| Fulltext search | chunks.content |
| Query optimization | Prepared statements |

---

### NFR-002: Security

**Confidence:** 80%
**Source:** AJAX handlers, User_Authentication, input validation

#### NFR-002.1: Input Validation

| Layer | Method |
|-------|--------|
| AJAX | Nonce verification |
| REST | Parameter schemas |
| Data | WordPress sanitization functions |

**Sanitization Functions Used:**
- `sanitize_text_field()`
- `sanitize_textarea_field()`
- `sanitize_key()`
- `absint()`
- `wp_kses_post()`

#### NFR-002.2: Access Control

| Protection | Implementation |
|------------|----------------|
| Admin endpoints | `current_user_can('manage_options')` |
| Capability checks | Custom capabilities |
| IP blocking | Configurable blocklist |
| Guest tracking | SHA256 hashed IPs |

#### NFR-002.3: Data Protection

| Data | Protection |
|------|------------|
| API keys | WordPress options (encrypted at rest if configured) |
| User IPs | SHA256 hashed |
| File uploads | .htaccess protection |

---

### NFR-003: Scalability

**Confidence:** 75%
**Source:** Pinecone integration, batch processing

#### NFR-003.1: Vector Storage Scalability

| Tier | Storage | Use Case |
|------|---------|----------|
| Local | MySQL | Small datasets, development |
| Cloud | Pinecone | Production, large scale |

#### NFR-003.2: Batch Processing

| Operation | Batch Size | Purpose |
|-----------|------------|---------|
| Embeddings | Configurable | Reduce API calls |
| Document queue | 5 per run | Background processing |
| Migration | 10 per batch | Controlled transfer |

#### NFR-003.3: Resource Management

| Resource | Management |
|----------|------------|
| Memory | Health check monitoring |
| API calls | Rate limiting |
| Database | Query optimization |

---

### NFR-004: Reliability

**Confidence:** 78%
**Source:** Exception classes, health checks

#### NFR-004.1: Error Handling

| Error Type | Exception Class |
|------------|-----------------|
| RAG Engine | `RAG_Engine_Exception` |
| LLM Request | `LLM_Request_Exception` |
| LLM Streaming | `LLM_Stream_Exception` |
| Embeddings | `LLM_Embedding_Exception` |
| Vector DB | `Vector_Database_Exception` |
| Retrieval | `Retriever_Exception` |
| Pinecone | `Pinecone_Exception` |
| Health Check | `Health_Check_Exception` |

#### NFR-004.2: Graceful Degradation

| Scenario | Behavior |
|----------|----------|
| Rate limit exceeded | Return user-friendly message |
| API failure | Log error, return fallback message |
| Missing context | Use chatbot's fallback message |
| Database error | Return safe defaults |

#### NFR-004.3: Monitoring

| Metric | Frequency |
|--------|-----------|
| Health checks | Hourly |
| Error logging | Real-time |
| Analytics | Event-driven |

---

### NFR-005: Maintainability

**Confidence:** 80%
**Source:** Code structure, namespacing

#### NFR-005.1: Code Organization

| Aspect | Implementation |
|--------|---------------|
| Namespacing | `AI_BotKit\*` |
| PSR-4 | Class per file |
| Separation | Core, Admin, Public, Integration |

#### NFR-005.2: Migration Support

| Migration | Support |
|-----------|---------|
| Table prefix | `ai_botkit_` to `knowvault_` |
| Data migration | Pinecone to/from local |
| Version tracking | `ai_botkit_version` option |

#### NFR-005.3: Extensibility

| Extension Point | Mechanism |
|-----------------|-----------|
| LLM providers | Strategy pattern |
| Content filters | WordPress filters |
| Event hooks | WordPress actions |

---

### NFR-006: Compatibility

**Confidence:** 85%
**Source:** Plugin header, requirements

#### NFR-006.1: WordPress Compatibility

| Requirement | Value |
|-------------|-------|
| WordPress | 5.8+ |
| PHP | 7.4+ |

#### NFR-006.2: Plugin Compatibility

| Plugin | Integration Level |
|--------|------------------|
| LearnDash | Deep (content sync, enrollment) |
| WooCommerce | Deep (product sync, assistant) |

#### NFR-006.3: Browser Compatibility

| Feature | Requirement |
|---------|-------------|
| JavaScript | Modern browsers (ES6) |
| CSS | Flexbox support |
| AJAX | Fetch or XMLHttpRequest |

---

## 4. User Stories (Inferred)

**Confidence:** 70%

### Administrator Stories

| ID | Story | Derived From |
|----|-------|--------------|
| US-001 | As an admin, I want to create chatbots so that I can provide AI assistance on my site | Chatbot CRUD handlers |
| US-002 | As an admin, I want to upload documents so that my chatbot has knowledge to draw from | Document upload handlers |
| US-003 | As an admin, I want to configure API keys so that the chatbot can use LLM services | Settings management |
| US-004 | As an admin, I want to view analytics so that I understand chatbot usage | Analytics endpoints |
| US-005 | As an admin, I want to set rate limits so that I control costs and prevent abuse | Rate limit settings |
| US-006 | As an admin, I want to block IPs so that I can prevent abuse | IP blocking feature |
| US-007 | As an admin, I want to enable sitewide chatbot so that visitors can access it anywhere | Sitewide chatbot option |

### Content Editor Stories

| ID | Story | Derived From |
|----|-------|--------------|
| US-010 | As an editor, I want to manage knowledge base documents so that chatbot stays updated | Document management capability |
| US-011 | As an editor, I want to import WordPress content so that existing content is searchable | WordPress content import |

### End User Stories

| ID | Story | Derived From |
|----|-------|--------------|
| US-020 | As a user, I want to ask questions so that I get answers from the knowledge base | Chat interface |
| US-021 | As a user, I want to see my conversation history so that I can reference past answers | History feature |
| US-022 | As a user, I want streaming responses so that I don't wait for complete answer | Streaming support |
| US-023 | As a user, I want to provide feedback so that responses can be improved | Feedback feature |

### LearnDash Student Stories

| ID | Story | Derived From |
|----|-------|--------------|
| US-030 | As a student, I want to ask about my courses so that I get enrolled content | Enrollment-aware context |
| US-031 | As a non-enrolled user, I want to see enrollment prompts so that I know how to access content | Enrollment message generation |

### WooCommerce Shopper Stories

| ID | Story | Derived From |
|----|-------|--------------|
| US-040 | As a shopper, I want to ask about products so that I make informed decisions | Product sync |
| US-041 | As a shopper, I want help with my cart so that I complete my purchase | Shopping assistant |

---

## 5. Assumptions and Gaps

### 5.1 Assumptions Made

1. **Business Logic:** Inferred requirements from code behavior; actual business intent may differ
2. **Acceptance Criteria:** Derived from implementation; may not match original specifications
3. **Priority:** All requirements treated as P1 unless evidence suggests otherwise
4. **User Personas:** Inferred from capability assignments and feature usage

### 5.2 Documentation Gaps

| Gap | Impact | Recommendation |
|-----|--------|----------------|
| Original PRD not available | Cannot verify business intent | Review with stakeholders |
| No user testing documentation | Cannot verify UX requirements | Conduct user research |
| No performance benchmarks | Cannot verify NFR targets | Define and measure baselines |
| No error message catalog | Inconsistent user messaging | Create message standards |

### 5.3 Items Requiring Clarification

1. **Rate Limit Values:** Are 100K tokens and 60 messages appropriate defaults?
2. **Chunk Sizes:** Are 1000-character chunks optimal for the target content?
3. **Cache TTLs:** Are current expiration times appropriate?
4. **Fallback Behavior:** What should happen when all context is empty?
5. **Guest Privacy:** Is IP hashing sufficient for GDPR compliance?

---

## Appendix: Requirements Traceability

| Requirement | Source Files | Confidence |
|-------------|--------------|------------|
| FR-001 | class-document-loader.php | 88% |
| FR-002 | class-text-chunker.php, class-embeddings-generator.php | 85% |
| FR-003 | class-vector-database.php, class-pinecone-database.php | 90% |
| FR-004 | class-retriever.php | 85% |
| FR-005 | class-llm-client.php | 92% |
| FR-006 | class-shortcode-handler.php | 85% |
| FR-007 | class-conversation.php | 88% |
| FR-008 | class-rate-limiter.php | 85% |
| FR-009 | class-analytics.php | 82% |
| FR-010 | class-health-checks.php | 80% |
| FR-011 | class-learndash.php | 82% |
| FR-012 | class-woocommerce.php | 80% |
| FR-013 | class-ajax-handler.php (admin) | 85% |
| FR-014 | class-user-authentication.php | 80% |

---

*RECOVERED DOCUMENT - Generated by Spec Recovery Agent*
*Business intent cannot be fully inferred from code - manual review required*
