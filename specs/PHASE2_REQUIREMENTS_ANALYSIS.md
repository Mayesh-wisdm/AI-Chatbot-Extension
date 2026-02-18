# Phase 2: Requirements Analysis Document

**Project:** AI BotKit Chatbot (KnowVault)
**Phase:** Phase 2 - Enhanced Features
**Document Version:** 1.0
**Generated:** 2026-01-28
**Status:** Draft - Pending Review

---

## Executive Summary

This document provides a detailed requirements analysis for Phase 2 of the AI BotKit Chatbot project. Phase 2 introduces six major feature enhancements building upon the core RAG functionality established in Phase 1. The total estimated effort is 62-78 hours across all features.

### Phase 2 Feature Overview

| Feature | Estimated Hours | Priority |
|---------|----------------|----------|
| Chat History | 6-8h | Must |
| Search Functionality | 11-14h | Must |
| Rich Media Support | 13-16h | Should |
| Conversation Templates | 10-13h | Should |
| Chat Transcripts Export | 7-9h | Could |
| LMS/WooCommerce Suggestions | 15-18h | Must |
| **Total** | **62-78h** | |

---

## 1. Chat History

### 1.1 Feature Overview

Allow logged-in users to view their previous conversations with the chatbot, improving continuity in multi-step or recurring interactions.

**Clarified Scope:** Logged-in WordPress users only. Guest conversations are saved but not retrievable by guests.

---

### FR-201: Conversation History Retrieval

**Description:** The system shall allow authenticated users to retrieve a list of their previous conversations with any chatbot on the site.

**Priority:** Must

**Acceptance Criteria:**

```gherkin
Given a logged-in WordPress user
When they access the chat history panel
Then they see a list of their previous conversations
And each conversation shows the chatbot name, date, and first message preview
And conversations are sorted by most recent first

Given a guest user (not logged in)
When they interact with the chatbot
Then their conversation is saved to the database
But they cannot access the history panel
And no "View History" option is displayed

Given a logged-in user with no previous conversations
When they access the chat history panel
Then they see an empty state message
And are prompted to start a new conversation
```

**Dependencies:**
- FR-007 (Conversation Persistence) from Phase 1
- User Authentication system

**Data Requirements:**
- Query `ai_botkit_conversations` table filtered by `user_id`
- Include conversation metadata: `chatbot_id`, `created_at`, `updated_at`
- First message preview from `ai_botkit_messages` table

---

### FR-202: Conversation Switching

**Description:** The system shall allow users to switch between different saved conversations and load the full message history.

**Priority:** Must

**Acceptance Criteria:**

```gherkin
Given a logged-in user viewing their conversation list
When they click on a conversation entry
Then the chat interface loads that conversation's messages
And the user can continue the conversation from where they left off
And the currently active conversation is visually highlighted

Given a user is in an active conversation
When they switch to a different conversation
Then the current conversation state is preserved
And they can switch back without losing context
```

**Dependencies:**
- FR-201 (Conversation History Retrieval)
- FR-007 (Conversation Persistence)

---

### FR-203: Conversation Management

**Description:** The system shall allow users to manage their conversation history including starting new conversations and deleting old ones.

**Priority:** Should

**Acceptance Criteria:**

```gherkin
Given a logged-in user viewing their conversation list
When they click "New Conversation"
Then a fresh conversation is started
And it appears at the top of their history list

Given a logged-in user viewing a conversation
When they click "Delete Conversation"
Then a confirmation dialog appears
And upon confirmation, the conversation is permanently deleted
And all associated messages are removed

Given an administrator
When they access the admin conversation panel
Then they can view and delete any user's conversations
And they can see user information associated with each conversation
```

**Dependencies:**
- FR-201, FR-202

---

### User Stories - Chat History

| ID | User Story | Acceptance Criteria Reference |
|----|------------|-------------------------------|
| US-201 | As a logged-in user, I want to see my past conversations so that I can reference previous answers | FR-201 |
| US-202 | As a logged-in user, I want to continue a previous conversation so that I don't have to repeat context | FR-202 |
| US-203 | As a logged-in user, I want to delete my conversation history so that I can manage my data | FR-203 |
| US-204 | As an administrator, I want to view all user conversations so that I can provide support | FR-203 |

---

## 2. Search Functionality

### 2.1 Feature Overview

Allow users and administrators to search within chat history for quick retrieval of past conversations or specific answers.

**Clarified Scope:** Dual-level access - Admins can search all conversations, users can only search their own.

---

### FR-210: User Search Interface

**Description:** The system shall provide authenticated users with a search interface to find content within their own conversation history.

**Priority:** Must

**Acceptance Criteria:**

```gherkin
Given a logged-in user with conversation history
When they enter a search query in the search box
Then results matching their query are displayed
And results only include their own conversations
And search matches are highlighted in the results
And results show conversation context (date, chatbot name)

Given a search query with no matches
When the search is executed
Then a "No results found" message is displayed
And suggestions for refining the search are shown

Given a search query
When results are displayed
Then results are ranked by relevance
And most relevant matches appear first
And pagination is available for many results
```

**Dependencies:**
- FR-201 (Conversation History Retrieval)
- FULLTEXT index on `ai_botkit_messages.content`

**Data Requirements:**
- New FULLTEXT index on messages table
- Search results entity: `message_id`, `conversation_id`, `content_snippet`, `relevance_score`, `created_at`

---

### FR-211: Admin Global Search

**Description:** The system shall provide administrators with a global search interface to search across all user conversations.

**Priority:** Must

**Acceptance Criteria:**

```gherkin
Given an administrator with `manage_ai_botkit` capability
When they access the admin search interface
Then they can search across all conversations site-wide
And results include user information (username, email)
And they can filter by chatbot, date range, and user

Given an administrator searching conversations
When they apply filters
Then only conversations matching all filter criteria are shown
And filters can be combined (e.g., chatbot + date range + user)

Given a non-administrator user
When they attempt to access admin search
Then they receive an access denied error
And they are redirected to their user-level search
```

**Dependencies:**
- FR-210 (User Search Interface)
- User capability: `can_search_all_conversations`

---

### FR-212: Search Filters and Options

**Description:** The system shall provide filtering options to refine search results.

**Priority:** Should

**Acceptance Criteria:**

```gherkin
Given a user performing a search
When they access filter options
Then they can filter by:
  - Date range (from/to)
  - Chatbot (dropdown of available chatbots)
  - Message role (user messages, bot responses, or both)

Given date range filters are applied
When search is executed
Then only messages within the specified date range are returned

Given a chatbot filter is selected
When search is executed
Then only messages from conversations with that chatbot are returned
```

**Dependencies:**
- FR-210, FR-211

---

### User Stories - Search Functionality

| ID | User Story | Acceptance Criteria Reference |
|----|------------|-------------------------------|
| US-210 | As a user, I want to search my conversation history so that I can find specific information quickly | FR-210 |
| US-211 | As an administrator, I want to search all conversations so that I can review chatbot interactions | FR-211 |
| US-212 | As a user, I want to filter search results so that I can narrow down to relevant conversations | FR-212 |
| US-213 | As a support agent, I want to search by user so that I can find a specific customer's interactions | FR-211 |

---

## 3. Rich Media Support

### 3.1 Feature Overview

Allow the chatbot to send and display images, videos, links, and downloadable files in responses, enhancing interaction quality beyond text-only conversations.

**Clarified Scope:** Full media support including images, embedded videos (YouTube/Vimeo), file downloads, and rich link previews.

---

### FR-220: Image Display in Messages

**Description:** The system shall support displaying images within chatbot responses.

**Priority:** Must

**Acceptance Criteria:**

```gherkin
Given a chatbot response contains an image reference
When the message is rendered
Then the image is displayed inline within the message
And images are responsive (scale appropriately)
And images can be clicked to view full size in a lightbox
And alt text is displayed for accessibility

Given an image fails to load
When the message is rendered
Then a placeholder with error message is shown
And the rest of the message content is still displayed

Given an administrator configuring rich media
When they upload an image for chatbot responses
Then images are validated for allowed types (jpg, png, gif, webp)
And images are stored in wp-content/uploads/ai-botkit/chat-media/
And image size limits are enforced (configurable, default 5MB)
```

**Dependencies:**
- WordPress Media Library integration
- New upload endpoint for chat attachments

**Data Requirements:**
- New `ai_botkit_message_attachments` table
- Fields: `id`, `message_id`, `attachment_type`, `file_url`, `file_name`, `file_size`, `mime_type`, `metadata` (JSON)

---

### FR-221: Video Embedding

**Description:** The system shall support embedding videos from YouTube and Vimeo within chatbot responses.

**Priority:** Should

**Acceptance Criteria:**

```gherkin
Given a chatbot response contains a YouTube or Vimeo URL
When the message is rendered
Then the video is embedded using oEmbed
And the video player is responsive
And users can play the video inline without leaving the chat

Given an unsupported video URL
When the message is rendered
Then the URL is displayed as a clickable link
And appropriate messaging indicates external video

Given video embedding is disabled in settings
When a video URL is in a response
Then the URL is displayed as a regular link
```

**Dependencies:**
- WordPress oEmbed API
- FR-220 (for attachment storage patterns)

---

### FR-222: File Download Support

**Description:** The system shall support attaching downloadable files to chatbot responses.

**Priority:** Should

**Acceptance Criteria:**

```gherkin
Given a chatbot response includes a file attachment
When the message is rendered
Then a download card is displayed showing:
  - File name
  - File size
  - File type icon
  - Download button

Given a user clicks the download button
When the file is served
Then the file downloads with proper headers
And download is logged for analytics

Given an administrator uploading a file for responses
When they upload a file
Then allowed file types are validated (pdf, doc, docx, xls, xlsx, zip, txt)
And file size limits are enforced (configurable, default 10MB)
And files are scanned for security
And files are stored in protected directory with .htaccess
```

**Dependencies:**
- WordPress file handling
- Security sanitization functions

---

### FR-223: Rich Link Previews

**Description:** The system shall generate rich previews for URLs included in chatbot responses.

**Priority:** Could

**Acceptance Criteria:**

```gherkin
Given a chatbot response contains a URL
When the message is rendered
Then a link preview card is generated showing:
  - Page title (from og:title or page title)
  - Description (from og:description or meta description)
  - Thumbnail image (from og:image if available)
  - Domain name

Given a URL cannot be parsed for metadata
When the message is rendered
Then the URL is displayed as a standard clickable link
And no preview card is shown

Given link preview generation
When fetching remote page metadata
Then requests include timeout (5 seconds)
And results are cached (1 hour TTL)
And private/internal URLs are not fetched
```

**Dependencies:**
- HTTP client for metadata fetching
- Cache for preview data

---

### FR-224: Media Security and Validation

**Description:** The system shall enforce security measures for all media uploads and displays.

**Priority:** Must

**Acceptance Criteria:**

```gherkin
Given any file upload for chat media
When the file is processed
Then MIME type is validated against allowed list
And file extension matches MIME type
And file is scanned for malicious content
And file name is sanitized

Given a user attempts to upload a disallowed file type
When validation fails
Then upload is rejected with clear error message
And no file is stored

Given media files in storage
When directory protection is configured
Then .htaccess prevents direct PHP execution
And files are served through WordPress with permission checks
```

**Dependencies:**
- WordPress security functions
- File validation utilities

---

### User Stories - Rich Media Support

| ID | User Story | Acceptance Criteria Reference |
|----|------------|-------------------------------|
| US-220 | As a user, I want to see images in chatbot responses so that I can better understand visual content | FR-220 |
| US-221 | As a user, I want to watch embedded videos so that I can view tutorials without leaving the chat | FR-221 |
| US-222 | As a user, I want to download files shared by the chatbot so that I can save documentation | FR-222 |
| US-223 | As a user, I want to see link previews so that I know what I'm clicking on | FR-223 |
| US-224 | As an administrator, I want media uploads validated so that my site remains secure | FR-224 |

---

## 4. Conversation Templates

### 4.1 Feature Overview

Provide pre-built chatbot configurations for common use cases, reducing setup time and improving first-time user activation.

**Clarified Scope:** Admin-defined templates only (no marketplace). Four pre-built types: FAQ Bot, Customer Support, Product Advisor, Lead Capture.

---

### FR-230: Template Data Model

**Description:** The system shall store conversation templates with configurable properties.

**Priority:** Must

**Acceptance Criteria:**

```gherkin
Given a template data structure
When stored in the database
Then it includes:
  - Template ID and name
  - Description and category
  - System prompt configuration
  - Welcome message
  - Suggested questions
  - UI customizations (colors, position)
  - Behavior settings (fallback message, response length)

Given a template is created
When it is saved
Then it is validated for required fields
And JSON structure is validated
And it receives a unique identifier
```

**Data Requirements:**

New table: `ai_botkit_templates`

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Primary key |
| name | VARCHAR(255) | Template name |
| slug | VARCHAR(100) | URL-safe identifier |
| description | TEXT | Template description |
| category | VARCHAR(50) | faq, support, sales, lead |
| config | LONGTEXT | JSON configuration |
| is_builtin | TINYINT | 1 for pre-built templates |
| created_at | DATETIME | Creation timestamp |
| updated_at | DATETIME | Last update timestamp |

**Dependencies:**
- Database schema migration

---

### FR-231: Template Builder UI

**Description:** The system shall provide an admin interface for creating and editing conversation templates.

**Priority:** Must

**Acceptance Criteria:**

```gherkin
Given an administrator accessing the template builder
When they open the interface
Then they can:
  - Enter template name and description
  - Select template category
  - Configure system prompt with variables
  - Set welcome message
  - Add suggested starter questions
  - Customize UI appearance
  - Set behavior parameters

Given an administrator editing an existing template
When they save changes
Then the template is updated
And chatbots using this template can be updated

Given an administrator with a template
When they preview the template
Then they see how the chatbot will appear
And can test the welcome message and suggestions
```

**Dependencies:**
- Admin React/JS components
- FR-230 (Template Data Model)

---

### FR-232: Template Application

**Description:** The system shall allow applying templates to chatbot configurations.

**Priority:** Must

**Acceptance Criteria:**

```gherkin
Given an administrator creating a new chatbot
When they select "Start from template"
Then they see available templates grouped by category
And each template shows name, description, and preview

Given a template is selected for a new chatbot
When applied
Then all template settings are copied to the chatbot
And the chatbot can be further customized
And the template origin is tracked

Given an administrator with an existing chatbot
When they apply a template
Then they are warned about settings that will be overwritten
And they can choose to merge or replace settings
```

**Dependencies:**
- FR-230, FR-231
- FR-013 (Chatbot Management) from Phase 1

---

### FR-233: Pre-built Templates

**Description:** The system shall include four pre-built templates for common use cases.

**Priority:** Must

**Acceptance Criteria:**

```gherkin
Given a fresh plugin installation
When the plugin is activated
Then four pre-built templates are available:
  - FAQ Bot
  - Customer Support
  - Product Advisor
  - Lead Capture

Given the FAQ Bot template
When applied to a chatbot
Then it is configured for:
  - Direct Q&A responses from knowledge base
  - Source citations in responses
  - "Did this help?" feedback prompt
  - Suggested follow-up questions

Given the Customer Support template
When applied to a chatbot
Then it is configured for:
  - Ticket reference collection
  - Escalation flow triggers
  - Human handoff capability hooks
  - Empathetic response tone

Given the Product Advisor template
When applied to a chatbot
Then it is configured for:
  - Needs assessment questions
  - Product matching logic
  - Comparison capabilities
  - Call-to-action buttons

Given the Lead Capture template
When applied to a chatbot
Then it is configured for:
  - Multi-step form flow
  - Field validation
  - CRM integration hooks
  - Thank you / next steps messaging
```

**Dependencies:**
- FR-230, FR-231, FR-232

---

### User Stories - Conversation Templates

| ID | User Story | Acceptance Criteria Reference |
|----|------------|-------------------------------|
| US-230 | As an administrator, I want to create chatbot templates so that I can reuse configurations | FR-230, FR-231 |
| US-231 | As an administrator, I want to apply templates to chatbots so that setup is faster | FR-232 |
| US-232 | As a new user, I want pre-built templates so that I can get started quickly | FR-233 |
| US-233 | As an agency, I want templates so that I can deploy consistent chatbots across client sites | FR-232 |

---

## 5. Chat Transcripts Export

### 5.1 Feature Overview

Allow administrators and users to export conversations in PDF format for compliance, review, and sharing purposes.

**Clarified Scope:** PDF export only (no CSV). Admins can export any conversation; users can export their own.

---

### FR-240: PDF Export Generation

**Description:** The system shall generate PDF exports of conversation transcripts.

**Priority:** Must

**Acceptance Criteria:**

```gherkin
Given a conversation selected for export
When PDF generation is triggered
Then a PDF document is generated containing:
  - Site branding (logo, site name)
  - Conversation metadata (date, chatbot name, user)
  - All messages in chronological order
  - Message timestamps
  - Clear visual distinction between user and bot messages

Given a conversation with media attachments
When PDF is generated
Then images are embedded in the PDF
And file attachments are listed with download links
And videos are shown as screenshots with links

Given a long conversation
When PDF is generated
Then content is properly paginated
And page numbers are included
And header/footer are consistent across pages
```

**Dependencies:**
- PDF library (dompdf or TCPDF)
- FR-007 (Conversation Persistence)

**Data Requirements:**
- PDF template configuration
- Temporary file storage for generation

---

### FR-241: Admin Export Interface

**Description:** The system shall provide administrators with an interface to export any conversation.

**Priority:** Must

**Acceptance Criteria:**

```gherkin
Given an administrator viewing conversations in admin
When they select a conversation
Then an "Export PDF" option is available
And clicking it generates and downloads the PDF

Given an administrator on the conversations list
When they select multiple conversations
Then they can batch export as individual PDFs in a ZIP file

Given an administrator exporting a conversation
When the PDF is generated
Then full user information is included
And admin notes (if any) are included
And export is logged for audit purposes
```

**Dependencies:**
- FR-240 (PDF Export Generation)
- FR-211 (Admin conversation access)

---

### FR-242: User Self-Service Export

**Description:** The system shall allow users to export their own conversation transcripts.

**Priority:** Should

**Acceptance Criteria:**

```gherkin
Given a logged-in user viewing their conversation history
When they select a conversation
Then an "Download PDF" option is available
And clicking it generates and downloads their conversation

Given a user exporting their conversation
When the PDF is generated
Then only their messages and bot responses are included
And their user information is shown
And no other user data is exposed

Given a guest user
When they view a conversation (before logging out)
Then no export option is available
And they are prompted to create an account to save conversations
```

**Dependencies:**
- FR-240 (PDF Export Generation)
- FR-201 (Conversation History for users)

---

### FR-243: Export Branding and Customization

**Description:** The system shall allow customization of PDF export appearance.

**Priority:** Could

**Acceptance Criteria:**

```gherkin
Given export settings in admin
When an administrator configures PDF branding
Then they can:
  - Upload a logo for the header
  - Set company name
  - Configure footer text
  - Choose color scheme

Given branded PDF settings are configured
When any PDF is exported
Then the branding is applied consistently
And branding appears on all pages

Given no custom branding is configured
When a PDF is exported
Then default plugin branding is used
And site name from WordPress settings is shown
```

**Dependencies:**
- FR-240, FR-241, FR-242

---

### User Stories - Chat Transcripts Export

| ID | User Story | Acceptance Criteria Reference |
|----|------------|-------------------------------|
| US-240 | As an administrator, I want to export conversations as PDF so that I can share them with stakeholders | FR-240, FR-241 |
| US-241 | As a user, I want to download my conversations so that I have a record of the information | FR-242 |
| US-242 | As a compliance officer, I want branded exports so that documents look professional | FR-243 |
| US-243 | As a support manager, I want batch export so that I can review multiple conversations | FR-241 |

---

## 6. LMS/WooCommerce Product Suggestions

### 6.1 Feature Overview

Enhance user experience by suggesting relevant courses or products based on conversation context, browsing history, purchase history, and explicit requests.

**Clarified Scope:** Full recommendation engine with four signal sources: conversation context, browsing history, purchase/enrollment history, and explicit requests.

---

### FR-250: Recommendation Engine Core

**Description:** The system shall provide a recommendation engine that combines multiple signals to suggest relevant products or courses.

**Priority:** Must

**Acceptance Criteria:**

```gherkin
Given the recommendation engine
When processing a recommendation request
Then it considers four signal types:
  1. Conversation context (current chat content)
  2. Browsing history (pages viewed in session)
  3. Purchase/enrollment history (user's past transactions)
  4. Explicit request ("recommend me a course")
And signals are weighted and combined
And top recommendations are returned

Given a recommendation request
When recommendations are generated
Then at least one signal source must have data
And results are deduplicated
And results are scored by relevance
And maximum 5 recommendations are returned

Given no relevant products/courses are found
When recommendations are requested
Then an appropriate message is returned
And no empty recommendation cards are shown
```

**Dependencies:**
- FR-012 (WooCommerce Integration) from Phase 1
- FR-011 (LearnDash Integration) from Phase 1

**Data Requirements:**
- Recommendation score calculation algorithm
- Signal weight configuration
- Recommendation cache

---

### FR-251: Conversation Context Analysis

**Description:** The system shall analyze conversation content to identify product/course recommendation opportunities.

**Priority:** Must

**Acceptance Criteria:**

```gherkin
Given an active conversation
When a user message indicates interest in a topic
Then the system identifies relevant keywords
And matches keywords against product/course attributes
And products/courses with matching tags, categories, or content are scored

Given a conversation about a specific topic
When context analysis runs
Then it considers:
  - Explicit product/course mentions
  - Topic keywords
  - Problem statements (e.g., "I need help with X")
  - Intent signals (e.g., "looking for", "want to learn")

Given conversation context
When generating product matches
Then matches are ranked by relevance score
And recently discussed topics are weighted higher
```

**Dependencies:**
- Existing `WooCommerce_Assistant::detect_shopping_intent()` extension

---

### FR-252: Browsing History Tracking

**Description:** The system shall track user browsing within the session to inform recommendations.

**Priority:** Should

**Acceptance Criteria:**

```gherkin
Given a user browsing the website
When they view product or course pages
Then the page views are tracked in their session
And product/course IDs are recorded
And timestamps are recorded

Given browsing history for a user
When recommendations are requested
Then recently viewed items influence suggestions
And related items to viewed products are included
And viewed items themselves may be re-suggested if appropriate

Given session expiration
When the session ends
Then browsing history for that session is cleared
And logged-in users retain history for longer (configurable)
```

**Dependencies:**
- Session management
- WordPress page detection hooks

**Data Requirements:**
- Session storage for browsing history
- `user_id`, `session_id`, `page_type`, `item_id`, `viewed_at`

---

### FR-253: Purchase and Enrollment History

**Description:** The system shall use past purchase and enrollment data to inform recommendations.

**Priority:** Should

**Acceptance Criteria:**

```gherkin
Given a logged-in user with purchase history
When recommendations are generated
Then the system queries WooCommerce orders
And identifies product categories/tags purchased
And recommends complementary or related products

Given a logged-in user with course enrollments
When recommendations are generated
Then the system queries LearnDash enrollments
And identifies course categories/topics enrolled
And recommends next-level or related courses

Given a user with no purchase/enrollment history
When recommendations are generated
Then this signal is skipped
And other signals are weighted accordingly
```

**Dependencies:**
- WooCommerce API for order history
- LearnDash API for enrollment data

---

### FR-254: Suggestion Card UI

**Description:** The system shall display product/course suggestions as interactive cards within the chat interface.

**Priority:** Must

**Acceptance Criteria:**

```gherkin
Given recommendations are available
When displayed in chat
Then each suggestion appears as a card showing:
  - Product/course image (thumbnail)
  - Title
  - Short description (truncated)
  - Price (products) or duration (courses)
  - Primary action button

Given a product suggestion card
When the user clicks the action button
Then for WooCommerce products: "Add to Cart" adds item and shows confirmation
Then for external links: "View Product" opens product page

Given a course suggestion card
When the user clicks the action button
Then for enrolled users: "Continue Learning" links to course
Then for non-enrolled: "Enroll Now" links to enrollment/purchase

Given multiple recommendations
When displayed in chat
Then cards are shown in a horizontal scrollable row
And navigation arrows appear for more than 3 cards
And cards are responsive on mobile (vertical stack)
```

**Dependencies:**
- FR-250 (Recommendation Engine)
- Frontend chat UI components

---

### FR-255: Explicit Recommendation Requests

**Description:** The system shall detect and respond to explicit requests for recommendations.

**Priority:** Must

**Acceptance Criteria:**

```gherkin
Given a user message containing recommendation intent
When the message includes phrases like:
  - "recommend a course"
  - "suggest a product"
  - "what should I buy"
  - "help me find"
Then the system triggers recommendation generation
And uses conversation context to refine suggestions

Given an explicit recommendation request
When the user specifies criteria (e.g., "under $50", "beginner level")
Then the criteria are used to filter recommendations
And only matching items are suggested

Given a recommendation request with no matching items
When criteria cannot be met
Then the system explains no exact matches found
And suggests loosening criteria
Or shows closest alternatives
```

**Dependencies:**
- FR-250, FR-251
- Intent detection patterns

---

### User Stories - LMS/WooCommerce Suggestions

| ID | User Story | Acceptance Criteria Reference |
|----|------------|-------------------------------|
| US-250 | As a shopper, I want product recommendations so that I discover relevant items | FR-250, FR-254 |
| US-251 | As a student, I want course recommendations so that I know what to learn next | FR-250, FR-254 |
| US-252 | As a user, I want recommendations based on my browsing so that suggestions are relevant | FR-252 |
| US-253 | As a returning customer, I want recommendations based on my purchases so that I find complementary products | FR-253 |
| US-254 | As a user, I want to ask for recommendations directly so that I get immediate suggestions | FR-255 |
| US-255 | As a user, I want to add recommended products to cart from chat so that shopping is convenient | FR-254 |

---

## 7. Non-Functional Requirements (Phase 2)

### NFR-201: Performance

**Description:** Phase 2 features shall maintain system performance standards.

**Priority:** Must

**Acceptance Criteria:**

```gherkin
Given search functionality
When a search query is executed
Then results return within 2 seconds
And FULLTEXT index is utilized for efficiency

Given history retrieval
When conversation list is loaded
Then results return within 1 second
And pagination prevents loading more than 20 items initially

Given recommendation generation
When recommendations are requested
Then results return within 3 seconds
And recommendations are cached for 5 minutes

Given PDF export
When generating a transcript
Then PDF generates within 10 seconds for typical conversations (<100 messages)
And progress indicator is shown for longer exports
```

---

### NFR-202: Scalability

**Description:** Phase 2 features shall scale with increased usage.

**Priority:** Should

**Acceptance Criteria:**

```gherkin
Given search functionality
When search index grows large
Then queries remain performant with FULLTEXT indexes
And search can be offloaded to external service if needed

Given history storage
When conversation volume is high
Then old conversations can be archived
And archived data can be retrieved on demand

Given media storage
When many media files are uploaded
Then files are organized by date folders
And CDN integration is possible for delivery
```

---

### NFR-203: Security

**Description:** Phase 2 features shall maintain security standards.

**Priority:** Must

**Acceptance Criteria:**

```gherkin
Given search functionality
When queries are processed
Then user can only search their own data
And admin search respects capability checks
And SQL injection is prevented

Given media uploads
When files are processed
Then all files are validated and sanitized
And malicious file types are rejected
And stored files are protected from direct execution

Given PDF exports
When transcripts are generated
Then user can only export their own conversations
And admin exports are logged
And exported files use secure temporary storage
```

---

### NFR-204: Accessibility

**Description:** Phase 2 UI features shall be accessible.

**Priority:** Should

**Acceptance Criteria:**

```gherkin
Given the chat history panel
When navigated with keyboard
Then all items are focusable
And selection works with Enter key
And screen readers announce conversation details

Given search functionality
When results are displayed
Then search results are announced to screen readers
And highlight markers are accessible

Given media in messages
When images are displayed
Then alt text is present and descriptive
And videos have accessible controls
```

---

### NFR-205: Internationalization

**Description:** Phase 2 features shall support translation.

**Priority:** Could

**Acceptance Criteria:**

```gherkin
Given all Phase 2 features
When text strings are used
Then all user-facing strings use WordPress i18n functions
And date/time formatting respects locale
And number formatting respects locale

Given PDF exports
When transcripts are generated
Then text direction (LTR/RTL) is respected
And date formats use locale settings
```

---

## 8. Data Requirements Summary

### 8.1 New Database Tables

| Table | Purpose | Feature |
|-------|---------|---------|
| `ai_botkit_templates` | Store conversation templates | FR-230 |
| `ai_botkit_message_attachments` | Store media attachments for messages | FR-220 |
| `ai_botkit_browsing_history` | Track user browsing for recommendations | FR-252 |

### 8.2 Schema Modifications

| Table | Modification | Purpose |
|-------|--------------|---------|
| `ai_botkit_messages` | Add FULLTEXT index on `content` | FR-210 search |
| `ai_botkit_conversations` | Add index on `user_id, updated_at` | FR-201 history retrieval |

### 8.3 New Entities

**Template Entity:**
```
Template {
  id: int
  name: string
  slug: string
  description: string
  category: enum(faq, support, sales, lead)
  config: {
    system_prompt: string
    welcome_message: string
    suggested_questions: string[]
    ui_settings: object
    behavior_settings: object
  }
  is_builtin: boolean
  created_at: datetime
  updated_at: datetime
}
```

**Message Attachment Entity:**
```
MessageAttachment {
  id: int
  message_id: int
  type: enum(image, video, file, link_preview)
  url: string
  filename: string
  filesize: int
  mime_type: string
  metadata: {
    width?: int
    height?: int
    duration?: int
    og_title?: string
    og_description?: string
    og_image?: string
  }
  created_at: datetime
}
```

**Browsing History Entity:**
```
BrowsingHistoryEntry {
  id: int
  user_id: int (nullable for guests)
  session_id: string
  page_type: enum(product, course, lesson, page)
  item_id: int
  viewed_at: datetime
}
```

---

## 9. Requirement Dependencies

### 9.1 Phase 1 Dependencies

Phase 2 features depend on these Phase 1 requirements:

| Phase 2 Feature | Depends On | Phase 1 Requirement |
|-----------------|------------|---------------------|
| Chat History | Conversation Persistence | FR-007 |
| Search | Conversation Persistence | FR-007 |
| Rich Media | Chat Interface | FR-006 |
| Templates | Chatbot Management | FR-013 |
| Export | Conversation Persistence | FR-007 |
| Suggestions | WooCommerce Integration | FR-012 |
| Suggestions | LearnDash Integration | FR-011 |

### 9.2 Internal Phase 2 Dependencies

```
FR-201 (History Retrieval)
    ├── FR-202 (Conversation Switching)
    ├── FR-203 (Conversation Management)
    └── FR-210 (User Search)
            └── FR-211 (Admin Search)
                    └── FR-212 (Search Filters)

FR-220 (Image Display)
    ├── FR-221 (Video Embedding)
    ├── FR-222 (File Downloads)
    └── FR-223 (Link Previews)
            └── FR-224 (Media Security)

FR-230 (Template Data Model)
    └── FR-231 (Template Builder)
            └── FR-232 (Template Application)
                    └── FR-233 (Pre-built Templates)

FR-240 (PDF Generation)
    ├── FR-241 (Admin Export)
    └── FR-242 (User Export)
            └── FR-243 (Export Branding)

FR-250 (Recommendation Engine)
    ├── FR-251 (Context Analysis)
    ├── FR-252 (Browsing History)
    ├── FR-253 (Purchase History)
    ├── FR-254 (Suggestion Cards)
    └── FR-255 (Explicit Requests)
```

---

## 10. Priority Matrix

### 10.1 Must Have (P1)

| ID | Requirement | Rationale |
|----|-------------|-----------|
| FR-201 | Conversation History Retrieval | Core history feature |
| FR-202 | Conversation Switching | Essential for history usability |
| FR-210 | User Search Interface | Key search functionality |
| FR-211 | Admin Global Search | Admin oversight capability |
| FR-220 | Image Display | Foundation for rich media |
| FR-224 | Media Security | Security is non-negotiable |
| FR-230 | Template Data Model | Foundation for templates |
| FR-231 | Template Builder UI | Required to create templates |
| FR-232 | Template Application | Required to use templates |
| FR-233 | Pre-built Templates | Immediate value for users |
| FR-240 | PDF Export Generation | Core export capability |
| FR-241 | Admin Export Interface | Admin access to exports |
| FR-250 | Recommendation Engine | Core suggestion capability |
| FR-251 | Context Analysis | Primary recommendation signal |
| FR-254 | Suggestion Card UI | Required to display recommendations |
| FR-255 | Explicit Requests | User-driven recommendations |

### 10.2 Should Have (P2)

| ID | Requirement | Rationale |
|----|-------------|-----------|
| FR-203 | Conversation Management | Enhanced history control |
| FR-212 | Search Filters | Improved search usability |
| FR-221 | Video Embedding | Enhanced media experience |
| FR-222 | File Downloads | Document sharing capability |
| FR-242 | User Self-Service Export | User empowerment |
| FR-252 | Browsing History Tracking | Enhanced recommendations |
| FR-253 | Purchase/Enrollment History | Personalized recommendations |

### 10.3 Could Have (P3)

| ID | Requirement | Rationale |
|----|-------------|-----------|
| FR-223 | Rich Link Previews | Enhanced UX, not critical |
| FR-243 | Export Branding | Nice to have customization |

---

## 11. Implementation Sequence

Based on dependencies and priorities, recommended implementation order:

### Sprint 1: Foundation (16-20h)
1. FR-201: Conversation History Retrieval
2. FR-202: Conversation Switching
3. FR-203: Conversation Management
4. FR-210: User Search Interface

### Sprint 2: Search & Templates (14-18h)
1. FR-211: Admin Global Search
2. FR-212: Search Filters
3. FR-230: Template Data Model
4. FR-231: Template Builder UI

### Sprint 3: Templates & Media (14-18h)
1. FR-232: Template Application
2. FR-233: Pre-built Templates
3. FR-220: Image Display
4. FR-224: Media Security

### Sprint 4: Media & Export (12-14h)
1. FR-221: Video Embedding
2. FR-222: File Downloads
3. FR-223: Rich Link Previews
4. FR-240: PDF Export Generation
5. FR-241: Admin Export Interface
6. FR-242: User Self-Service Export

### Sprint 5: Recommendations (15-18h)
1. FR-250: Recommendation Engine
2. FR-251: Context Analysis
3. FR-252: Browsing History Tracking
4. FR-253: Purchase/Enrollment History
5. FR-254: Suggestion Card UI
6. FR-255: Explicit Requests

### Sprint 6: Polish (4-6h)
1. FR-243: Export Branding
2. Cross-feature integration testing
3. Performance optimization
4. Bug fixes and edge cases

---

## Appendix A: Glossary

| Term | Definition |
|------|------------|
| Chatbot | An AI-powered conversational interface configured for a specific purpose |
| Conversation | A complete interaction session between a user and chatbot |
| Template | A pre-configured chatbot setup that can be applied to new chatbots |
| Recommendation Signal | A data source used to inform product/course suggestions |
| FULLTEXT Index | MySQL index type optimized for text search operations |
| oEmbed | Protocol for embedding content from external services |

---

## Appendix B: Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-01-28 | Requirements Clarifier Agent | Initial document creation |

---

*Document generated by Requirements Clarifier Agent based on Phase 0.5 Clarification and Recovered Specification*
