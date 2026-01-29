# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [2.0.0] - 2026-01-29

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
- 16 new filters for extensibility (see DEVELOPER.md)
- 12 new actions for integration points (see DEVELOPER.md)

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

## [Unreleased]

### Changed
- Rebranded plugin from "AI BotKit" to "KnowVault"
- Updated color scheme from green to blue:
  - Primary color changed from #008858 to #1E3A8A (dark royal blue)
  - Accent color changed to #00BFA6 (teal) for highlights
  - Updated all UI elements, buttons, and active states to new blue color scheme
- Updated all documentation, user-facing text, and admin interface references to reflect new branding
- Renamed main plugin file from `ai-botkit-for-lead-generation.php` to `knowVault.php`
- Updated version from 1.0.3 to 1.1.0
- Updated text domain from `ai-botkit-for-lead-generation` to `knowvault`
- Database tables now use `knowvault_` prefix for new installations (backward compatible with `ai_botkit_` tables)

### Added
- Database migration system to migrate from old `ai_botkit_` tables to new `knowvault_` tables
- Admin notice prompting users to update database when old tables are detected
- Table helper utility class for backward compatibility with both old and new table structures

### Added
- VoyageAI API key verification button for Anthropic embeddings
- Model dropdowns now visible even without API keys (with warning toast)
- Safe default model selection when switching AI engines:
  - Anthropic: Claude 3.5 Haiku (chat), Voyage 3 Lite (embeddings)
  - Together AI: Llama 3.3 Instruct (chat), BGE Base EN v1.5 (embeddings)
 - Toast notifications for API verification and Pinecone connection tests
 - Warning toast when switching to Anthropic without VoyageAI key

### Changed
- Improved user experience by showing model options immediately when switching engines
- Enhanced API key validation to include VoyageAI provider
- Updated JavaScript engine switching logic to populate dropdowns regardless of API key status
 - Replaced inline result areas with toast notifications for verification flows

### Fixed
- Model dropdowns no longer blank when switching to Anthropic or Together AI engines
- VoyageAI API key verification now properly integrated with existing test infrastructure

---

## [1.0.3] - 2025-12-XX

### Added
- Initial public release
- RAG-based chatbot functionality
- Multi-provider LLM support (OpenAI, Anthropic, Google, Together AI)
- Knowledge base management
- Vector storage (local and Pinecone)
- Rate limiting for logged-in users
- Analytics dashboard
- Shortcode embedding
- Multilingual support

---

## Version History Summary

| Version | Date | Highlights |
|---------|------|------------|
| 2.0.0 | 2026-01-29 | Phase 2: History, Search, Media, Templates, Export, Recommendations |
| 1.1.0 | TBD | Rebranding to KnowVault, blue color scheme |
| 1.0.3 | 2025-12 | Initial public release |
