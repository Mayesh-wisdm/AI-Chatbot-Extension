# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
