# KnowVault (KnowVault) - AI Chatbot for WordPress

> **Version:** 2.0.0
> **Requires WordPress:** 5.8+
> **Requires PHP:** 7.4+
> **License:** GPL v2 or later

## Description

KnowVault is an advanced RAG (Retrieval-Augmented Generation) based AI chatbot plugin for WordPress. It enables intelligent, context-aware chatbots trained on your business content, supporting multiple AI providers and storing all data locally on your WordPress installation.

### Key Features

- **WordPress Native Training**: No external vector database required
- **Multi-Provider Support**: OpenAI, Anthropic Claude, Google Gemini, Together AI
- **Unlimited Chatbots**: Create purpose-specific bots
- **Data Privacy**: All data stored locally, GDPR-compliant
- **Multilingual**: 50+ languages supported
- **No Coding Required**: Visual builder for all configurations

---

## Phase 2 Features (v2.0.0)

Version 2.0.0 introduces six major feature enhancements designed to improve user experience, administrative capabilities, and e-commerce/LMS integration.

### 1. Chat History (Logged-in Users)

Access and manage previous conversations with ease.

**Features:**
- View paginated list of past conversations
- Resume any previous conversation seamlessly
- Switch between conversations while preserving context
- Conversation previews showing first message and metadata
- Delete or archive unwanted conversations
- Mark conversations as favorites for quick access
- Filter by date range (Today, 7 days, 30 days, custom)

**Access:**
- Available to all logged-in WordPress users
- Guest conversations are saved but not retrievable by guests
- History panel integrated into chat widget header

**Usage:**
```
1. Open chat widget
2. Click the "History" icon in the header
3. Browse, search, or select a conversation to resume
```

### 2. Search Functionality

Full-text search across chat history with role-based access control.

**For Users:**
- Search within your own conversation history
- Find specific messages by keywords or phrases
- Results ranked by relevance with highlighted matches
- Filter by date range, chatbot, or message role

**For Administrators:**
- Search across ALL user conversations site-wide
- Filter by specific users
- Export search results
- Monitor chatbot interactions for quality assurance

**Technical Details:**
- MySQL FULLTEXT indexing for fast searches
- Boolean operator support (AND, OR, NOT)
- Search suggestions and autocomplete
- Results display search execution time

### 3. Rich Media Support

Send and receive various media types within chat conversations.

**Supported Media:**
| Type | Formats | Max Size |
|------|---------|----------|
| Images | JPEG, PNG, GIF, WebP | 10MB |
| Videos | MP4, WebM | 10MB |
| Documents | PDF, TXT | 10MB |
| Links | Any URL | N/A |

**Features:**
- Drag-and-drop file uploads
- Image preview and lightbox viewing
- Video embedding (YouTube, Vimeo)
- Link preview cards with OpenGraph data
- Secure file storage in WordPress uploads
- Automatic media cleanup for orphaned files

**Security:**
- MIME type validation (not just extension)
- File scanning for malicious content
- Configurable allowed types via filter

### 4. Conversation Templates

Pre-built and custom chatbot configurations for rapid deployment.

**Pre-built Templates:**

| Template | Category | Use Case |
|----------|----------|----------|
| **FAQ Bot** | Support | Direct answers with source citations |
| **Customer Support** | Support | Ticket references, escalation flows |
| **Product Advisor** | Sales | Needs assessment, product matching |
| **Lead Capture** | Marketing | Multi-step forms, CRM integration |

**Template Features:**
- Create templates from existing chatbot configurations
- Apply templates to new or existing chatbots
- Import/export templates as JSON
- Customize style, messages, model config, and conversation starters
- System templates cannot be modified or deleted

**Admin Usage:**
```
1. Navigate to KnowVault > Templates
2. Select a pre-built template or create custom
3. Click "Apply to Chatbot" and select target bot
4. Choose merge (combine) or replace mode
```

### 5. Chat Transcripts Export (PDF)

Download conversation transcripts as professional PDF documents.

**Features:**
- Export any conversation to branded PDF
- Includes site logo and brand colors
- Timestamps on each message
- User and bot messages clearly distinguished
- Paper size options (Letter, A4)
- Optional metadata inclusion

**Access Levels:**
- **Users**: Export their own conversations
- **Administrators**: Export any conversation

**Branding:**
- Automatic site logo inclusion
- Customizable primary color
- Page numbers and footer
- Professional transcript layout

### 6. LMS/WooCommerce Suggestions

Intelligent product and course recommendations during chat.

**Recommendation Sources:**
| Signal | Weight | Description |
|--------|--------|-------------|
| Conversation Context | 35% | Keywords and intent from current chat |
| Browsing History | 25% | Pages and products viewed recently |
| Purchase/Enrollment History | 25% | Previous purchases and course enrollments |
| Explicit Request | 15% | Direct user requests for recommendations |

**Integrations:**
- **WooCommerce**: Product suggestions, "Add to Cart" actions
- **LearnDash**: Course recommendations, "Enroll Now" actions
- **Combined**: Cross-sell products with related courses

**Display:**
- Suggestion cards within chat interface
- Product image, title, price, and CTA
- Course image, title, and enrollment button
- Click tracking for analytics

**Configuration:**
```
KnowVault > Settings > Recommendations
- Enable/disable recommendations
- Configure signal weights
- Set maximum suggestions per response
```

---

## Installation

1. Upload the plugin to `/wp-content/plugins/` or install via WordPress admin
2. Activate through the Plugins screen
3. Navigate to **KnowVault > Settings** to configure API keys
4. Add content to **KnowVault > Knowledge Base**
5. Create your first chatbot at **KnowVault > My Bots**

### Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher (MySQL 8.0+ recommended for fulltext search)
- API key from at least one AI provider

---

## Configuration

### API Keys

Configure your AI provider in **KnowVault > Settings**:

| Provider | Required Keys |
|----------|---------------|
| OpenAI | OpenAI API Key |
| Anthropic | Anthropic API Key + VoyageAI API Key (embeddings) |
| Google | Google AI API Key |
| Together AI | Together AI API Key |

### Rate Limiting

Control API usage for logged-in users:

- **Max Tokens per Conversation**: Default 100,000 tokens/24h
- **Max Messages in 24 Hours**: Default 60 messages/24h

### Phase 2 Settings

New settings introduced in v2.0.0:

| Setting | Default | Description |
|---------|---------|-------------|
| `ai_botkit_history_per_page` | 10 | Conversations per page in history |
| `ai_botkit_search_per_page` | 20 | Search results per page |
| `ai_botkit_max_media_size` | 10MB | Maximum upload file size |
| `ai_botkit_recommendation_enabled` | true | Enable/disable suggestions |
| `ai_botkit_pdf_paper_size` | letter | Default PDF paper size |

---

## Shortcodes

### Floating Widget
```
[ai_botkit_chat id="1"]
```

### Inline Widget
```
[ai_botkit_chat id="1" inline="true"]
```

---

## Hooks and Filters

For developers extending Phase 2 features, see `docs/DEVELOPER.md` for complete documentation of:

- 16+ new WordPress filters
- 12+ new WordPress actions
- REST API endpoints
- AJAX handlers

---

## Database Tables

| Table | Purpose |
|-------|---------|
| `{prefix}ai_botkit_chatbots` | Chatbot configurations |
| `{prefix}ai_botkit_conversations` | Chat sessions |
| `{prefix}ai_botkit_messages` | Individual messages |
| `{prefix}ai_botkit_documents` | Knowledge base documents |
| `{prefix}ai_botkit_templates` | Conversation templates (Phase 2) |
| `{prefix}ai_botkit_media` | Chat media attachments (Phase 2) |
| `{prefix}ai_botkit_user_interactions` | Recommendation tracking (Phase 2) |

---

## Support

- **Documentation**: See `USER_DOCUMENTATION.md` for detailed user guide
- **Developer Docs**: See `docs/DEVELOPER.md` for API reference
- **Phase 2 User Guide**: See `docs/PHASE2_USER_GUIDE.md`

---

## Changelog

See `CHANGELOG.md` for version history.

---

## License

GPL v2 or later
