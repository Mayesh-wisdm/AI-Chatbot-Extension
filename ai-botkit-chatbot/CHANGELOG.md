# KnowVault (AI BotKit) Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Fixed
- **Recommendations wrapper**: Removed suggestions wrapper from template (was showing below greeting). Now dynamically created only after bot messages that trigger recommendations, attached to the last assistant message
- **Recommendations layout**: Suggestions wrapper is dynamically created and attached to the last assistant message, so recommendation cards appear below the bot reply and scroll with chat messages (no longer on top of / behind chat)
- **Add to Cart**: Prevented chat window from closing when clicking Add to Cart (guard widget/button in document click handler; only attach widget toggle/close when floating widget exists; added mousedown stopPropagation on suggestion buttons)
- **View Cart**: Added click handler for `.ai-botkit-view-cart` so "View Cart" opens cart URL in new window after add-to-cart success
- **Cart status checking**: Backend now checks if products are already in cart before rendering buttons. Products in cart show "View Cart" button instead of "Add to Cart"
- **Links open in new window**: All recommendation links (View Cart, View Product, Continue Learning, product card clicks) now open in new window/tab with `target="_blank"` and `rel="noopener,noreferrer"`
- **Conversation history**: Recommendations are regenerated when loading conversation history (based on last user/assistant message exchange), so recommendations persist as part of the conversation
- **Recommendation Engine**: Improved fallback recommendations to show any published products when no featured products exist
- **Recommendation Engine**: Enhanced keyword extraction to normalize product terms (e.g., "t-shirt", "T-Shirt", "tshirt" → "tshirt")
- **Recommendation Engine**: Added comprehensive console logging for debugging recommendation display issues
- **Recommendation Engine**: Fixed AJAX handler to properly pass `chatbot_id` and `session_id` parameters
- **Chat Suggestions (chat-suggestions.js)**: Fixed `ReferenceError: aiBotKitSuggestions is not defined` by using `typeof` check before referencing optional `aiBotKitSuggestions` config
- **Chat Suggestions**: Use `ai_botkitChat.botID` for chatbot ID when requesting recommendations (was `chatbotId`, which is not localized)

### Changed
- **Recommendation Engine**: Fallback recommendations now return up to 5 products (increased from 3) and include non-featured products if no featured products exist
- **Recommendation Engine**: Added debug logging to `showRecommendationCards()` and `getRecommendations()` functions for easier troubleshooting
- **UI consistency**: Recommendation panel now uses chat theme (`.ai-botkit-chat` sets --ai-botkit-primary, --ai-botkit-card-bg, --ai-botkit-border, etc. to match --ai-botkit-chat-* variables) so colors align with the chat window
- **Recommendations UI**: Improved spacing, padding, and styling for suggestions wrapper and cards to better integrate with chat messages. Reduced header title size and improved margins for cleaner appearance
- **Recommendation title color**: Fixed suggestion title color to use darker default (#555 instead of #888) for better readability on light backgrounds
- **Backend recommendation settings**: Added "Recommendation Settings" section in chatbot form (Appearance tab) with color pickers for Title Color, Card Background, and Card Border. Settings are saved in chatbot style JSON and applied via CSS variables
- **Widget script**: Widget toggle, outside-click close, and minimize are only attached when floating widget elements exist (avoids errors on shortcode-only pages)

### Added
- **Documentation**: Created `RECOMMENDATION_TESTING_GUIDE.md` with comprehensive testing instructions and debugging checklist

### Planned
- Additional chatbot templates for specific industries
- Advanced analytics with custom reports
- Multi-language knowledge base support
- Improved context ranking algorithms
- Webhook integrations for external systems

---

## [2.0.0] - 2026-02-06

### Phase 2 Release - Enhanced Features

This major release introduces six new feature areas designed to improve user experience, administrative capabilities, and integration with e-commerce and LMS platforms.

### Added

#### Feature 1: Chat History (FR-201 to FR-209)

- **FR-201**: Paginated conversation list for logged-in users
- **FR-202**: View complete message history of any conversation
- **FR-203**: Seamless switching between conversations with state preservation
- **FR-204**: Conversation previews (first message, metadata)
- **FR-205**: Pagination for large history (10 items per page default)
- **FR-206**: Delete conversation functionality with confirmation
- **FR-207**: Mark conversations as favorites
- **FR-208**: Filter conversations by date range
- **FR-209**: History panel integrated into chat widget UI
- New class: `Chat_History_Handler` for history management
- New REST endpoint: `GET /wp-json/ai-botkit/v1/history`
- New AJAX handler: `ai_botkit_get_history_list`

#### Feature 2: Search Functionality (FR-210 to FR-219)

- **FR-210**: Search input interface with debounced queries
- **FR-211**: Full-text search using MySQL FULLTEXT indexing
- **FR-212**: Admin global search across all user conversations
- **FR-213**: User-scoped search (own conversations only)
- **FR-214**: Search result highlighting with `<mark>` tags
- **FR-215**: Date range filters for search
- **FR-216**: Chatbot filter for search results
- **FR-217**: Search suggestions and autocomplete
- **FR-218**: Search result pagination
- **FR-219**: Search analytics tracking
- New class: `Search_Handler` for fulltext search operations
- New REST endpoint: `GET /wp-json/ai-botkit/v1/search`
- Added FULLTEXT index on `ai_botkit_messages.content`

#### Feature 3: Rich Media Support (FR-220 to FR-229)

- **FR-220**: Image upload support (JPEG, PNG, GIF, WebP)
- **FR-221**: Video upload support (MP4, WebM)
- **FR-222**: Document upload support (PDF, TXT)
- **FR-223**: Video embedding for YouTube and Vimeo URLs
- **FR-224**: Link preview cards with OpenGraph data extraction
- **FR-225**: Drag-and-drop file upload interface
- **FR-226**: Image preview and lightbox viewing
- **FR-227**: File download functionality
- **FR-228**: Media storage management in WordPress uploads
- **FR-229**: Automatic orphaned media cleanup (30-day retention)
- New class: `Media_Handler` for media operations
- New database table: `ai_botkit_media`
- New REST endpoint: `POST /wp-json/ai-botkit/v1/media/upload`
- New REST endpoint: `GET /wp-json/ai-botkit/v1/media/link-preview`

#### Feature 4: Conversation Templates (FR-230 to FR-239)

- **FR-230**: Template listing with category filtering
- **FR-231**: Template creation from scratch
- **FR-232**: Template creation from existing chatbot
- **FR-233**: Apply template to chatbot (merge or replace)
- **FR-234**: Template import/export as JSON
- **FR-235**: Pre-built FAQ Bot template
- **FR-236**: Pre-built Customer Support template
- **FR-237**: Pre-built Product Advisor template
- **FR-238**: Pre-built Lead Capture template
- **FR-239**: System template protection (non-editable/deletable)
- New class: `Template_Manager` for template CRUD
- New model: `Template` entity class
- New database table: `ai_botkit_templates`
- New REST endpoints for template management

#### Feature 5: Chat Transcripts Export (FR-240 to FR-249)

- **FR-240**: Export single conversation to PDF
- **FR-241**: PDF branding with site logo and colors
- **FR-242**: Configurable paper size (Letter, A4)
- **FR-243**: Optional metadata inclusion in export
- **FR-244**: User self-service export (own conversations)
- **FR-245**: Admin export any conversation
- **FR-246**: PDF streaming download
- **FR-247**: Export permission verification
- **FR-248**: Custom PDF template styling
- **FR-249**: Export action logging for audit
- New class: `Export_Handler` for PDF generation
- Added Dompdf library dependency
- New REST endpoint: `GET /wp-json/ai-botkit/v1/export/{id}/pdf`

#### Feature 6: LMS/WooCommerce Suggestions (FR-250 to FR-259)

- **FR-250**: Recommendation engine with multi-signal scoring
- **FR-251**: Conversation context analysis for recommendations
- **FR-252**: User interaction tracking (page views, clicks)
- **FR-253**: WooCommerce product recommendations
- **FR-254**: LearnDash course recommendations
- **FR-255**: Purchase/enrollment history integration
- **FR-256**: Recommendation cards in chat UI
- **FR-257**: Click-through tracking for recommendations
- **FR-258**: Configurable signal weights
- **FR-259**: Complementary item suggestions
- New class: `Recommendation_Engine` for suggestion logic
- New database table: `ai_botkit_user_interactions`
- New REST endpoint: `GET /wp-json/ai-botkit/v1/recommendations`
- New REST endpoint: `POST /wp-json/ai-botkit/v1/recommendations/track`

#### New WordPress Hooks

- 16 new filters for extensibility (see docs/DEVELOPER.md)
- 12 new actions for integration points (see docs/DEVELOPER.md)

#### New Database Schema

- Added `ai_botkit_templates` table for template storage
- Added `ai_botkit_media` table for chat media attachments
- Added `ai_botkit_user_interactions` table for recommendation tracking
- Added FULLTEXT index on `ai_botkit_messages.content`
- Added composite index on `ai_botkit_conversations (user_id, updated_at)`

#### New Dependencies

- Dompdf library for PDF generation

### Changed

- Extended `Ajax_Handler` with 10+ new public AJAX actions
- Extended `Admin_Ajax_Handler` with 10+ new admin AJAX actions
- Extended `REST_API` with 15+ new endpoints
- Extended `Conversation` model with archive support
- Extended chat widget UI with history panel, search, and media upload
- Improved `WooCommerce_Assistant` with recommendation engine integration

### Security

- MIME type validation for all file uploads (not just extension)
- File content scanning for malicious code
- User ownership verification for history access
- Admin-only global search capability
- Permission checks on all export operations
- Rate limiting on search and recommendation endpoints
- Input sanitization for fulltext search queries
- CSRF protection on all new AJAX handlers

### Breaking Changes

- None. Full backward compatibility with Phase 1.

### Migration Notes

- Database migration runs automatically on plugin update
- New tables created automatically
- Existing data preserved
- FULLTEXT index added to messages table (may take time on large datasets)
- System templates installed on first activation

---

## [1.1.0] - 2026-01-15

### Added

- Frontend chat search in history panel: search input and results in Chat History for logged-in users
- Transcript export button: Export PDF button on each conversation in the history panel
- LMS/WooCommerce suggestion cards: recommendation cards rendered after assistant messages
- Rich media sent with message: uploaded files are sent with chat message and included in bot context
- Search loading indicator: "Searching..." spinner and text in history panel search
- Message attachments in chat: user message bubbles now show uploaded images and file links inline
- VoyageAI API key verification button for Anthropic embeddings
- Model dropdowns now visible even without API keys (with warning toast)
- Safe default model selection when switching AI engines
- Toast notifications for API verification and Pinecone connection tests
- Database migration system to migrate from old `ai_botkit_` tables to new `knowvault_` tables
- Admin notice prompting users to update database when old tables are detected
- Table helper utility class for backward compatibility

### Changed

- Rebranded plugin from "AI BotKit" to "KnowVault"
- Updated color scheme from green to blue:
  - Primary color changed from #008858 to #1E3A8A (dark royal blue)
  - Accent color changed to #00BFA6 (teal) for highlights
  - Updated all UI elements, buttons, and active states
- Updated all documentation and admin interface references to reflect new branding
- Renamed main plugin file from `ai-botkit-for-lead-generation.php` to `knowVault.php`
- Updated text domain from `ai-botkit-for-lead-generation` to `knowvault`
- Database tables now use `knowvault_` prefix for new installations (backward compatible)
- Improved user experience by showing model options immediately when switching engines
- Enhanced API key validation to include VoyageAI provider

### Fixed

- Admin chatbot sessions search: sessions page now uses correct AJAX action
- Chat export and suggestions config: use correct localized name on frontend
- Search suggestion click closing bot: proper event handling for suggestions
- Export button visibility: conversation actions bar always visible
- Bot image refusal: system prompt updated for better image handling
- Bot course/product refusal: system prompt updated for recommendations
- Guest user display on View Conversation page: show "Guest User" for guest conversations
- Message template processing: greeting now supports placeholders and shortcodes
- New conversation button: properly starts new conversation and closes panel
- Conversation ID sync: new messages saved to correct conversation
- Knowledge Base Add URL: fixed namespace when linking document to chatbot
- Model dropdowns no longer blank when switching to Anthropic or Together AI

---

## [1.0.3] - 2025-12-15

### Added

- Initial public release
- RAG-based chatbot functionality with document processing pipeline
- Multi-provider LLM support:
  - OpenAI (GPT-4 Turbo, GPT-4, GPT-3.5 Turbo)
  - Anthropic (Claude 3.7 Sonnet, 3.5 Sonnet/Haiku, 3 Opus)
  - Google (Gemini 1.5 Flash/Pro)
  - Together AI (Llama 3.3, DeepSeek V3, Mistral)
- Knowledge base management:
  - PDF document upload and processing
  - TXT and Markdown file support
  - URL content import
  - WordPress post/page indexing
- Vector storage options:
  - Local MySQL with cosine similarity
  - Pinecone cloud database integration
- Chat interface:
  - Embeddable via shortcode
  - Floating widget mode
  - Inline mode
  - Customizable appearance
- Admin features:
  - Chatbot creation and management
  - Settings configuration
  - Analytics dashboard
  - Rate limiting for logged-in users
- Security features:
  - Nonce verification
  - Capability-based access control
  - Input sanitization
  - Prepared SQL statements
- Multilingual support (50+ languages)

---

## [1.0.2] - 2025-11-01

### Added

- Together AI provider support
- Improved streaming response handling
- Bulk document processing with progress indicator

### Fixed

- Memory leak during large document processing
- Conversation context missing recent messages
- Styling issues in dark theme

---

## [1.0.1] - 2025-10-15

### Added

- Pinecone vector database integration
- Google Gemini model support
- Site-wide chatbot option

### Fixed

- WooCommerce product variations not syncing
- Conversation history not loading on page refresh
- Avatar upload issues in some browsers

---

## [1.0.0] - 2025-10-01

### Added

- Core RAG (Retrieval-Augmented Generation) functionality
- OpenAI GPT-4 and GPT-3.5 support
- Text embedding generation
- Document chunking with configurable size
- WordPress content indexing
- Basic chat interface
- Admin dashboard
- Initial settings management

---

## Version History Summary

| Version | Date | Highlights |
|---------|------|------------|
| 2.0.0 | 2026-02-06 | Phase 2: History, Search, Media, Templates, Export, Recommendations |
| 1.1.0 | 2026-01-15 | Rebranding to KnowVault, blue color scheme, bug fixes |
| 1.0.3 | 2025-12-15 | Initial public release with full feature set |
| 1.0.2 | 2025-11-01 | Together AI, streaming improvements |
| 1.0.1 | 2025-10-15 | Pinecone, Gemini, site-wide chatbot |
| 1.0.0 | 2025-10-01 | Initial release |

---

## Upgrade Notes

### Upgrading to 2.0.0

1. **Backup Required**: Create a full backup before upgrading
2. **Database Migration**: A migration will run automatically on upgrade
3. **New Tables**: Three new database tables will be created
4. **Settings Review**: Review new Phase 2 settings after upgrade
5. **Clear Caches**: Clear all caches after upgrading

### Upgrading to 1.1.0

1. **Rebranding**: Plugin is now called "KnowVault" (formerly AI BotKit)
2. **Color Scheme**: UI has changed from green to blue
3. **Database Tables**: New prefix option available; old tables still supported

### Breaking Changes

None across all versions. Full backward compatibility maintained.

---

## Support

For questions or issues:
- Review documentation in `docs/` folder
- Check troubleshooting guides in `docs/USER_GUIDE.md`
- Contact support through your license portal

---

*KnowVault (AI BotKit) - Built by WisdmLabs*
