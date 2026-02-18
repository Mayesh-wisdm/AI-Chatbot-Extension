# Phase 2 Functional Specification

**Project:** AI BotKit Chatbot
**Phase:** Phase 2 - Enhanced Features
**Document Version:** 1.0
**Generated:** 2026-01-28
**Status:** Final Specification

---

## Table of Contents

1. [Overview](#1-overview)
2. [Functional Requirements](#2-functional-requirements)
   - [Feature 1: Chat History](#feature-1-chat-history-fr-201-to-fr-209)
   - [Feature 2: Search Functionality](#feature-2-search-functionality-fr-210-to-fr-219)
   - [Feature 3: Rich Media Support](#feature-3-rich-media-support-fr-220-to-fr-229)
   - [Feature 4: Conversation Templates](#feature-4-conversation-templates-fr-230-to-fr-239)
   - [Feature 5: Chat Transcripts Export](#feature-5-chat-transcripts-export-fr-240-to-fr-249)
   - [Feature 6: LMS/WooCommerce Suggestions](#feature-6-lmswoocommerce-suggestions-fr-250-to-fr-259)
3. [Non-Functional Requirements](#3-non-functional-requirements)
4. [Acceptance Criteria Summary](#4-acceptance-criteria-summary)
5. [API Specifications](#5-api-specifications)
6. [Database Schema Changes](#6-database-schema-changes)
7. [Security Requirements](#7-security-requirements)

---

## 1. Overview

### 1.1 Phase 2 Scope Summary

Phase 2 of the AI BotKit Chatbot project extends the core RAG (Retrieval Augmented Generation) functionality established in Phase 1 with six major feature enhancements designed to improve user experience, administrative capabilities, and integration with e-commerce/LMS platforms.

### 1.2 Features Being Added

| # | Feature | Description | Priority | Est. Hours |
|---|---------|-------------|----------|------------|
| 1 | Chat History | View and resume previous conversations | Must | 6-8h |
| 2 | Search Functionality | Full-text search across chat history | Must | 11-14h |
| 3 | Rich Media Support | Images, videos, files, and link previews | Should | 13-16h |
| 4 | Conversation Templates | Pre-built chatbot configurations | Should | 10-13h |
| 5 | Chat Transcripts Export | PDF export of conversations | Could | 7-9h |
| 6 | LMS/WooCommerce Suggestions | Product and course recommendations | Must | 15-18h |

**Total Estimated Effort:** 62-78 hours

### 1.3 Target Users

| User Type | Key Needs |
|-----------|-----------|
| **Site Visitors (Logged-in)** | Access chat history, download transcripts, receive personalized recommendations |
| **Site Visitors (Guests)** | Interact with chatbot (no history access) |
| **Site Administrators** | Manage templates, search all conversations, export reports, configure recommendations |
| **Support Agents** | Search conversation history, review chat transcripts for customer support |
| **E-commerce Managers** | Review product recommendation performance, configure suggestion settings |

### 1.4 Key Clarifications from Phase 0.5

| Feature | Scope Decision |
|---------|---------------|
| Chat History | **Logged-in users only** - Guest conversations saved but not retrievable |
| Search | **Dual-level access** - Admins search all, users search own |
| Rich Media | **Full support** - Images, embedded videos, file downloads, link previews |
| Templates | **Admin-defined** - 4 pre-built types (FAQ, Support, Product Advisor, Lead Capture) |
| Export | **PDF only** - Admin + user self-service |
| Suggestions | **Full engine** - 4 signals (context, browsing, history, explicit) |

---

## 2. Functional Requirements

---

### Feature 1: Chat History (FR-201 to FR-209)

Enable logged-in users to view, manage, and resume their previous conversations with chatbots.

---

### FR-201: List User Conversations

**Description:** The system shall display a paginated list of the user's previous conversations, showing conversation metadata and message previews.

**Priority:** Must

**User Story:** As a logged-in user, I want to see a list of my past conversations so that I can reference previous answers and continue where I left off.

**Acceptance Criteria:**

```gherkin
Given a logged-in WordPress user with existing conversations
When they access the chat history panel
Then they see a list of their conversations sorted by most recent first
And each conversation displays:
  - Chatbot name and avatar
  - Date/time of last activity
  - Preview of the first message (truncated to 100 characters)
  - Total message count

Given a logged-in user with more than 10 conversations
When they view the history panel
Then conversations are paginated with 10 items per page
And pagination controls allow navigation between pages

Given a guest user (not logged in)
When they interact with the chatbot
Then their conversation is saved to the database
But the history panel is not visible to them
```

**Technical Notes:**
- Uses `ai_botkit_conversations` table filtered by `user_id`
- Implements `Chat_History_Handler::get_user_history()` method
- Cache results per user with 2-minute TTL
- Requires composite index on `(user_id, updated_at DESC)`

**Dependencies:** FR-007 (Phase 1 Conversation Persistence)

---

### FR-202: View Conversation Messages

**Description:** The system shall allow users to load and view the complete message history of a selected conversation.

**Priority:** Must

**User Story:** As a logged-in user, I want to view all messages in a past conversation so that I can review the complete discussion.

**Acceptance Criteria:**

```gherkin
Given a logged-in user viewing their conversation list
When they click on a conversation entry
Then the chat interface loads all messages from that conversation
And messages are displayed in chronological order
And each message shows:
  - Message content
  - Sender (user or bot)
  - Timestamp
  - Any media attachments

Given a conversation with more than 50 messages
When the conversation is loaded
Then messages are loaded in batches for performance
And older messages can be loaded on demand (scroll pagination)
```

**Technical Notes:**
- Uses `Conversation::get_messages()` with pagination support
- Lazy load messages in batches of 50
- Preserve scroll position when loading older messages

**Dependencies:** FR-201

---

### FR-203: Switch Between Conversations

**Description:** The system shall enable seamless switching between different saved conversations while preserving context.

**Priority:** Must

**User Story:** As a logged-in user, I want to switch between conversations so that I can reference multiple discussions without losing my place.

**Acceptance Criteria:**

```gherkin
Given a user is viewing conversation A
When they select conversation B from the history list
Then conversation B loads in the chat interface
And conversation A's state is preserved in memory
And the currently active conversation is visually highlighted in the list

Given a user switches from conversation A to B and back to A
When they return to conversation A
Then all messages are still visible
And scroll position is restored
And any draft message is preserved
```

**Technical Notes:**
- Implement client-side conversation state caching
- Use session storage for draft message preservation
- Visual indicator for active conversation in sidebar

**Dependencies:** FR-201, FR-202

---

### FR-204: Conversation Previews

**Description:** The system shall generate and display meaningful previews for each conversation in the history list.

**Priority:** Should

**User Story:** As a logged-in user, I want to see previews of my conversations so that I can quickly identify the topic without opening each one.

**Acceptance Criteria:**

```gherkin
Given a conversation exists with messages
When displayed in the history list
Then the preview shows the first user message (truncated to 100 chars)
And if the message is truncated, an ellipsis is appended
And empty conversations show "No messages yet"

Given a conversation's first message contains only media
When displayed in the history list
Then the preview shows "[Image]", "[Video]", or "[File: filename]" as appropriate
```

**Technical Notes:**
- Extract preview via `Chat_History_Handler::get_conversation_preview()`
- Handle HTML stripping and entity decoding
- Cache previews with conversation list

**Dependencies:** FR-201

---

### FR-205: Pagination for Large History

**Description:** The system shall efficiently handle users with extensive conversation histories through pagination.

**Priority:** Must

**User Story:** As a power user, I want paginated history so that the interface remains responsive even with hundreds of conversations.

**Acceptance Criteria:**

```gherkin
Given a user has 100+ conversations
When they access the history panel
Then only the first 10 conversations load initially
And total count and page information is displayed
And "Load More" or pagination controls are available

Given a user is on page 3 of their history
When they select a conversation to view
Then they can return to page 3 of the history list
And the pagination state is preserved
```

**Technical Notes:**
- Default pagination: 10 items per page (configurable via `ai_botkit_history_per_page` option)
- Use cursor-based pagination for consistent results
- Include total count in API response

**Dependencies:** FR-201

---

### FR-206: Delete Conversation

**Description:** The system shall allow users to permanently delete their own conversations.

**Priority:** Should

**User Story:** As a logged-in user, I want to delete my conversations so that I can manage my chat history and remove unwanted records.

**Acceptance Criteria:**

```gherkin
Given a logged-in user viewing a conversation
When they click "Delete Conversation"
Then a confirmation dialog appears with a warning message
And upon confirmation, the conversation is permanently deleted
And all associated messages and media are removed
And the user is redirected to the history list

Given an administrator viewing conversations in admin
When they delete a conversation
Then the conversation and all data are permanently removed
And the action is logged for audit purposes

Given a user attempts to delete another user's conversation
When the delete action is triggered
Then the action is blocked
And an "Access Denied" error is displayed
```

**Technical Notes:**
- Hard delete with cascade to messages and media
- Implement ownership verification before deletion
- Log admin deletions to analytics table

**Dependencies:** FR-201, FR-202

---

### FR-207: Mark Conversation as Favorite

**Description:** The system shall allow users to mark conversations as favorites for quick access.

**Priority:** Could

**User Story:** As a logged-in user, I want to mark important conversations as favorites so that I can quickly find them later.

**Acceptance Criteria:**

```gherkin
Given a logged-in user viewing a conversation
When they click the "Favorite" star icon
Then the conversation is marked as a favorite
And the star icon changes to indicate favorite status
And the conversation appears in a "Favorites" filter view

Given a user has favorited conversations
When they view the history panel
Then a "Favorites" filter option is available
And selecting it shows only favorited conversations
```

**Technical Notes:**
- Add `is_favorite` boolean column to conversations table
- Implement filter in history query
- Store preference per user-conversation pair

**Dependencies:** FR-201

---

### FR-208: Filter Conversations by Date

**Description:** The system shall allow users to filter their conversation history by date range.

**Priority:** Could

**User Story:** As a logged-in user, I want to filter conversations by date so that I can find discussions from a specific time period.

**Acceptance Criteria:**

```gherkin
Given a logged-in user viewing the history panel
When they access date filter options
Then they can select:
  - Today
  - Last 7 days
  - Last 30 days
  - Custom date range

Given a user selects "Last 7 days" filter
When the filter is applied
Then only conversations updated within the last 7 days are shown
And the active filter is visually indicated
And clearing the filter restores the full list
```

**Technical Notes:**
- Implement date range parameters in history API
- Use `updated_at` field for filtering
- Combine with pagination seamlessly

**Dependencies:** FR-201, FR-205

---

### FR-209: Integration with Existing Chat UI

**Description:** The system shall seamlessly integrate the history panel with the existing chat widget interface.

**Priority:** Must

**User Story:** As a user, I want the history feature to feel like a natural part of the chatbot so that accessing past conversations is intuitive.

**Acceptance Criteria:**

```gherkin
Given a logged-in user opens the chat widget
When the widget loads
Then a "History" button/icon is visible in the header
And clicking it reveals the history panel
And the panel slides in smoothly from the side

Given a user is viewing the history panel
When they click "New Conversation"
Then a fresh conversation is started
And it appears at the top of the history list
And the chat input becomes active

Given a user resumes a conversation from history
When they send a new message
Then the message is appended to the existing conversation
And the conversation's updated_at timestamp is refreshed
And the conversation moves to the top of the history list
```

**Technical Notes:**
- Extend existing `chat.js` with history module
- Add `history.js` and `history-panel.css` files
- Use AJAX handler `ai_botkit_get_history_list`

**Dependencies:** All FR-20x requirements

---

### Feature 2: Search Functionality (FR-210 to FR-219)

Enable full-text search across chat history with appropriate access controls.

---

### FR-210: Search Input Interface

**Description:** The system shall provide a search input field for querying chat history content.

**Priority:** Must

**User Story:** As a user, I want a search box so that I can quickly find specific information in my chat history.

**Acceptance Criteria:**

```gherkin
Given a logged-in user viewing the history panel
When they see the search input
Then it displays a placeholder "Search your conversations..."
And a search icon is visible
And the input supports keyboard submission (Enter key)

Given a user begins typing in the search box
When they pause for 300ms
Then search suggestions appear (if enabled)
And pressing Enter executes the search

Given a user with an active search
When they click the clear button (X)
Then the search query is cleared
And results return to the default history view
```

**Technical Notes:**
- Implement debounced search (300ms delay)
- Support both instant and submitted search modes
- Accessible with keyboard navigation

**Dependencies:** FR-201 (History Panel)

---

### FR-211: Full-Text Search on Messages

**Description:** The system shall perform full-text search on message content using MySQL FULLTEXT indexing.

**Priority:** Must

**User Story:** As a user, I want to search the full text of messages so that I can find conversations containing specific words or phrases.

**Acceptance Criteria:**

```gherkin
Given a user enters a search query
When the search is executed
Then the system searches across all message content
And results are ranked by relevance (MATCH score)
And results include messages from both user and bot
And search time is displayed (e.g., "Found 15 results in 0.12s")

Given a search query "pricing options"
When results are returned
Then messages containing both words score higher
And partial matches (containing either word) are included
And boolean operators (AND, OR, NOT) are supported

Given a search with no matches
When results are displayed
Then a "No results found" message appears
And suggestions for refining the search are shown
```

**Technical Notes:**
- Add FULLTEXT index on `ai_botkit_messages.content`
- Use `MATCH(content) AGAINST(query IN NATURAL LANGUAGE MODE)`
- Escape special characters to prevent SQL injection

**Dependencies:** FR-210

---

### FR-212: Admin Global Search

**Description:** The system shall provide administrators with the ability to search across all user conversations site-wide.

**Priority:** Must

**User Story:** As an administrator, I want to search all conversations so that I can review chatbot interactions for support and quality assurance.

**Acceptance Criteria:**

```gherkin
Given an administrator with `manage_ai_botkit` capability
When they access the admin search interface
Then they can search across all conversations
And results include user information (username, email)
And they can filter by specific users

Given an administrator searching conversations
When results are displayed
Then each result shows:
  - Message content with highlighted matches
  - User who sent/received the message
  - Chatbot name
  - Date and time
  - Link to full conversation

Given a non-administrator attempts admin search
When they access the admin search endpoint
Then they receive a 403 Forbidden response
And are redirected to user-level search
```

**Technical Notes:**
- Requires `manage_ai_botkit` or `can_search_all_conversations` capability
- Admin UI in WordPress admin area
- Log admin searches for audit purposes

**Dependencies:** FR-210, FR-211

---

### FR-213: User Personal Search

**Description:** The system shall restrict regular users to searching only their own conversation history.

**Priority:** Must

**User Story:** As a user, I want to search my own conversations so that I can find information while maintaining privacy.

**Acceptance Criteria:**

```gherkin
Given a logged-in regular user
When they perform a search
Then results are automatically filtered to their user_id
And they cannot see other users' conversations
And no user_id filter option is available

Given a user searches for "shipping policy"
When results are returned
Then only messages from their own conversations appear
And conversations from other users are excluded
```

**Technical Notes:**
- Auto-inject `user_id` filter for non-admins
- Never expose other users' data in API responses
- Verify ownership at both API and database query levels

**Dependencies:** FR-210, FR-211

---

### FR-214: Search Filters (date, chatbot, user)

**Description:** The system shall provide filtering options to refine search results.

**Priority:** Should

**User Story:** As a user, I want to filter search results so that I can narrow down to relevant conversations.

**Acceptance Criteria:**

```gherkin
Given a user performing a search
When they access filter options
Then they can filter by:
  - Date range (from/to date pickers)
  - Chatbot (dropdown of available chatbots)
  - Message role (user messages, bot responses, or both)

Given date range filters are applied
When search is executed
Then only messages within the specified date range are returned
And the active filter is displayed as a removable chip

Given multiple filters are applied
When results are shown
Then all filter criteria are applied (AND logic)
And each active filter can be independently removed
```

**Technical Notes:**
- Filters passed as query parameters
- Admin-only filter: `user_id` for filtering by specific user
- Combine filters with FULLTEXT search efficiently

**Dependencies:** FR-210, FR-211

---

### FR-215: Search Results Display

**Description:** The system shall display search results in a clear, actionable format.

**Priority:** Must

**User Story:** As a user, I want clear search results so that I can quickly identify relevant conversations.

**Acceptance Criteria:**

```gherkin
Given search results are available
When displayed to the user
Then each result shows:
  - Message excerpt with context (up to 200 characters)
  - Conversation metadata (chatbot name, date)
  - Relevance indicator (score or ranking)
  - Action to view full conversation

Given a user clicks on a search result
When the action is triggered
Then the full conversation opens
And the view scrolls to the matching message
And the matching message is visually highlighted
```

**Technical Notes:**
- Display snippet with 50 chars before and after match
- Include `conversation_id` and `message_id` for navigation
- Implement smooth scroll to message

**Dependencies:** FR-211

---

### FR-216: Search Term Highlighting

**Description:** The system shall highlight search terms in the results to improve scanability.

**Priority:** Should

**User Story:** As a user, I want my search terms highlighted so that I can quickly see why each result matched.

**Acceptance Criteria:**

```gherkin
Given search results contain matching text
When displayed
Then search terms are wrapped in <mark> tags
And highlighted text uses a distinct background color
And highlighting is case-insensitive
And multiple occurrences are all highlighted

Given a search term spans multiple words
When highlighting is applied
Then each word is highlighted individually
And partial word matches within results are highlighted
```

**Technical Notes:**
- Use `Search_Handler::highlight_matches()` method
- Sanitize HTML before highlighting to prevent XSS
- CSS class `.ai-botkit-highlight` for styling

**Dependencies:** FR-215

---

### FR-217: Search Relevance Ranking

**Description:** The system shall rank search results by relevance score.

**Priority:** Should

**User Story:** As a user, I want the most relevant results first so that I find what I need quickly.

**Acceptance Criteria:**

```gherkin
Given a search query returns multiple results
When results are displayed
Then they are sorted by relevance score (highest first)
And the relevance score is calculated using MySQL FULLTEXT scoring
And exact phrase matches rank higher than word matches
And recent messages receive a slight boost

Given two results with similar relevance
When sorting is applied
Then more recent messages appear first
And the sort order is consistent across page loads
```

**Technical Notes:**
- Use `MATCH...AGAINST` relevance score
- Apply recency weighting: `score * (1 + 0.1 * recency_factor)`
- Include score in API response for debugging

**Dependencies:** FR-211

---

### FR-218: Search Pagination

**Description:** The system shall paginate search results for large result sets.

**Priority:** Must

**User Story:** As a user, I want paginated search results so that I can browse through many matches without performance issues.

**Acceptance Criteria:**

```gherkin
Given a search returns more than 20 results
When results are displayed
Then only the first 20 results are shown
And pagination controls are visible
And total result count is displayed

Given a user navigates to page 2 of search results
When the page loads
Then results 21-40 are displayed
And the search query is preserved
And filters remain applied
And page number is reflected in URL/state
```

**Technical Notes:**
- Default: 20 results per page (configurable via `ai_botkit_search_per_page`)
- Use offset-based pagination for FULLTEXT queries
- Include `total`, `pages`, `current_page` in response

**Dependencies:** FR-211

---

### FR-219: Search Performance Optimization

**Description:** The system shall optimize search performance to meet response time requirements.

**Priority:** Must

**User Story:** As a user, I want fast search results so that I can find information without frustrating delays.

**Acceptance Criteria:**

```gherkin
Given a search query on a database with 100,000+ messages
When the search is executed
Then results return within 500ms (P95)
And the FULLTEXT index is utilized (verified via EXPLAIN)
And no full table scans occur

Given repeated identical searches
When executed within 5 minutes
Then cached results are returned
And response time is under 50ms
And cache is invalidated when new messages are added
```

**Technical Notes:**
- FULLTEXT index: `ADD FULLTEXT INDEX ft_content (content)`
- Cache search results with key: `search:{query_hash}:{filters_hash}:{page}`
- Cache TTL: 5 minutes
- Invalidate on new message insert via hook

**Dependencies:** FR-211

---

### Feature 3: Rich Media Support (FR-220 to FR-229)

Enable chatbot responses to include images, videos, files, and rich link previews.

---

### FR-220: Image Attachments in Messages

**Description:** The system shall support displaying images within chatbot messages.

**Priority:** Must

**User Story:** As a user, I want to see images in chatbot responses so that I can better understand visual content.

**Acceptance Criteria:**

```gherkin
Given a chatbot response contains an image reference
When the message is rendered
Then the image is displayed inline within the message bubble
And the image is responsive (max-width: 100%)
And a placeholder is shown while the image loads
And alt text is displayed for accessibility

Given an image fails to load
When the message is rendered
Then a placeholder with "Image unavailable" is shown
And the broken image icon is replaced with a fallback
And the rest of the message content is still visible
```

**Technical Notes:**
- Supported formats: JPEG, PNG, GIF, WebP
- Max file size: 5MB (configurable)
- Store in `wp-content/uploads/ai-botkit/chat-media/images/{year}/{month}/`
- Generate thumbnails for faster loading

**Dependencies:** FR-224 (Media Upload Handling)

---

### FR-221: Video Embeds (YouTube/Vimeo)

**Description:** The system shall support embedding videos from YouTube and Vimeo within chat messages.

**Priority:** Should

**User Story:** As a user, I want to watch videos in the chat so that I can view tutorials without leaving the conversation.

**Acceptance Criteria:**

```gherkin
Given a chatbot response contains a YouTube URL
When the message is rendered
Then the video is embedded using oEmbed
And a responsive player (16:9 aspect ratio) is displayed
And users can play the video inline
And video thumbnail shows before playback

Given a chatbot response contains a Vimeo URL
When the message is rendered
Then the video is embedded similarly to YouTube
And controls are accessible and functional

Given video embedding is disabled in settings
When a video URL is in a response
Then the URL is displayed as a clickable link
And a "Watch on YouTube" label is shown
```

**Technical Notes:**
- Use WordPress oEmbed API (`wp_oembed_get()`)
- Extract video ID from URL patterns
- Cache embed HTML for 24 hours
- Support URL patterns:
  - `youtube.com/watch?v=VIDEO_ID`
  - `youtu.be/VIDEO_ID`
  - `vimeo.com/VIDEO_ID`

**Dependencies:** FR-220

---

### FR-222: File Attachments (PDF, DOC)

**Description:** The system shall support attaching downloadable files to chatbot responses.

**Priority:** Should

**User Story:** As a user, I want to download files shared by the chatbot so that I can save documentation for later reference.

**Acceptance Criteria:**

```gherkin
Given a chatbot response includes a file attachment
When the message is rendered
Then a download card is displayed showing:
  - File name (truncated if long)
  - File type icon (PDF, DOC, etc.)
  - File size (formatted: KB, MB)
  - Download button

Given a user clicks the download button
When the file is served
Then the file downloads with the original filename
And proper Content-Disposition headers are set
And download is logged for analytics

Given an administrator uploads a file for responses
When they upload a file
Then only allowed types are accepted (PDF, DOC, DOCX, XLS, XLSX, TXT, ZIP)
And file size is limited to 10MB
And file is scanned for security threats
```

**Technical Notes:**
- Store files in `wp-content/uploads/ai-botkit/chat-media/files/{year}/{month}/`
- Serve files through WordPress with permission checks
- Add `.htaccess` to prevent direct PHP execution
- Track downloads in analytics

**Dependencies:** FR-224

---

### FR-223: Rich Link Previews

**Description:** The system shall generate rich previews for URLs included in chatbot responses.

**Priority:** Could

**User Story:** As a user, I want to see link previews so that I know what I am clicking on before visiting a link.

**Acceptance Criteria:**

```gherkin
Given a chatbot response contains a URL
When the message is rendered
Then a link preview card is generated showing:
  - Page title (from og:title or <title>)
  - Description (from og:description or meta description, max 150 chars)
  - Thumbnail image (from og:image, if available)
  - Domain name with favicon

Given a URL cannot be parsed for metadata
When the message is rendered
Then the URL is displayed as a standard clickable link
And no preview card is shown
And the link opens in a new tab

Given link preview generation
When fetching remote page metadata
Then requests include a 5-second timeout
And results are cached for 1 hour
And internal/private URLs are not fetched
And rate limiting prevents abuse
```

**Technical Notes:**
- Fetch OpenGraph tags using HTTP client
- Use `fivefilters/readability.php` for extraction
- Cache previews in `ai_botkit_media` table with type `link`
- Whitelist allowed domains if needed

**Dependencies:** None (standalone feature)

---

### FR-224: Media Upload Handling

**Description:** The system shall handle media file uploads for chat attachments securely.

**Priority:** Must

**User Story:** As an administrator, I want to upload media for chatbot responses so that I can include visual aids and documents.

**Acceptance Criteria:**

```gherkin
Given an administrator uploads a media file
When the upload is processed
Then the file is validated for:
  - Allowed MIME type (whitelist)
  - File extension matching MIME type
  - File size within limits
  - No embedded PHP or executable code

Given a valid file is uploaded
When processing completes
Then the file is moved to the appropriate directory
And a database record is created in ai_botkit_media
And a unique filename is generated (preventing overwrites)
And the public URL is returned

Given an invalid file is uploaded
When validation fails
Then the upload is rejected with a clear error message
And no file is stored on the server
And the error is logged for review
```

**Technical Notes:**
- Use `Media_Handler::upload_media()` method
- Validate MIME type using `finfo_file()`, not just extension
- Generate filename: `{uniqid}_{sanitized_original_name}`
- Create thumbnail for images

**Dependencies:** None (core infrastructure)

---

### FR-225: Media Display Components

**Description:** The system shall provide reusable UI components for displaying different media types.

**Priority:** Must

**User Story:** As a developer, I want consistent media components so that media displays uniformly across the interface.

**Acceptance Criteria:**

```gherkin
Given a message contains media attachments
When the message is rendered
Then the appropriate component is used based on media type:
  - ImageAttachment component for images
  - VideoEmbed component for videos
  - FileDownload component for documents
  - LinkPreview component for URLs

Given multiple media items in one message
When rendered
Then media items are displayed in a gallery/carousel format
And each item can be individually interacted with
And the order matches the attachment order
```

**Technical Notes:**
- Create JavaScript components for each media type
- CSS classes: `.ai-botkit-media-image`, `.ai-botkit-media-video`, etc.
- Support lazy loading for images
- Add `recommendation-cards.css` for styled cards

**Dependencies:** FR-220, FR-221, FR-222, FR-223

---

### FR-226: Lightbox for Images

**Description:** The system shall display images in a lightbox when clicked for full-size viewing.

**Priority:** Should

**User Story:** As a user, I want to view images full-size so that I can see details clearly.

**Acceptance Criteria:**

```gherkin
Given an image is displayed in a chat message
When the user clicks on the image
Then a lightbox overlay opens with the full-size image
And the background is dimmed
And a close button (X) is visible
And clicking outside the image closes the lightbox

Given a lightbox is open
When the user presses Escape key
Then the lightbox closes
And focus returns to the chat interface

Given an image in lightbox view
When navigation is possible (multiple images in message)
Then left/right arrows allow navigation between images
And keyboard arrows also navigate
```

**Technical Notes:**
- Implement lightweight lightbox (no external library dependency)
- Support touch gestures for mobile (swipe, pinch-zoom)
- Preload adjacent images for smooth navigation
- Trap focus within lightbox for accessibility

**Dependencies:** FR-220

---

### FR-227: File Download Handling

**Description:** The system shall securely serve file downloads to authorized users.

**Priority:** Must

**User Story:** As a user, I want secure file downloads so that I can access documents shared by the chatbot.

**Acceptance Criteria:**

```gherkin
Given a user clicks download on a file attachment
When the download is requested
Then the system verifies the file exists
And serves the file with proper headers:
  - Content-Type: application/octet-stream
  - Content-Disposition: attachment; filename="..."
  - Content-Length: file_size
And download progress is tracked (if supported by browser)

Given a user attempts to download a non-existent file
When the request is processed
Then a 404 error is returned
And an appropriate error message is displayed
And the failed attempt is logged

Given file download analytics
When a download completes
Then the download is logged with:
  - Media ID
  - User ID (if logged in)
  - Timestamp
  - Conversation context
```

**Technical Notes:**
- Serve files via WordPress endpoint (not direct file access)
- Use `readfile()` for streaming large files
- Set `X-Content-Type-Options: nosniff` header
- Rate limit downloads per user/IP

**Dependencies:** FR-222

---

### FR-228: Media Security (validation, sanitization)

**Description:** The system shall enforce comprehensive security measures for all media operations.

**Priority:** Must

**User Story:** As an administrator, I want secure media handling so that my site is protected from malicious uploads.

**Acceptance Criteria:**

```gherkin
Given a file upload is received
When validation is performed
Then the system checks:
  - MIME type against whitelist (using file content, not extension)
  - File extension matches declared MIME type
  - File size within configured limits
  - File does not contain PHP tags or executable code
  - Filename is sanitized (remove special chars, limit length)

Given files are stored on the server
When directory protection is configured
Then .htaccess prevents direct PHP execution:
  - `php_flag engine off`
  - `AddHandler default-handler .php`
And directory listing is disabled
And files are served through WordPress with permission checks

Given a user attempts to access media they don't own
When the request is processed
Then access is denied with 403 Forbidden
And the attempt is logged for security review
```

**Technical Notes:**
- Whitelist approach: `apply_filters('ai_botkit_allowed_media_types', [...])`
- Scan for PHP code: `<?php`, `<?=`, `<script>`
- Use `wp_check_filetype_and_ext()` for WordPress integration
- Implement `Media_Handler::validate_file()` method

**Dependencies:** None (core security)

---

### FR-229: Storage Management

**Description:** The system shall manage media storage including organization, cleanup, and quotas.

**Priority:** Should

**User Story:** As an administrator, I want media storage managed automatically so that my server storage is used efficiently.

**Acceptance Criteria:**

```gherkin
Given media files are uploaded
When stored on the server
Then files are organized by type and date:
  - /images/{year}/{month}/filename.jpg
  - /videos/{year}/{month}/filename.mp4
  - /files/{year}/{month}/filename.pdf

Given orphaned media (no associated message)
When cleanup runs (daily cron)
Then media older than 24 hours without a message is marked orphaned
And orphaned media older than 7 days is deleted
And disk space is reclaimed

Given storage quota monitoring
When total media storage exceeds threshold (e.g., 1GB)
Then administrators receive a warning notification
And oldest orphaned media is prioritized for cleanup
```

**Technical Notes:**
- Schedule cleanup via `wp_schedule_event('daily', 'ai_botkit_media_cleanup')`
- Implement `Media_Handler::cleanup_orphaned_media()` method
- Store file size in database for quota calculation
- Option: `ai_botkit_media_cleanup_days` (default: 30)

**Dependencies:** FR-224

---

### Feature 4: Conversation Templates (FR-230 to FR-239)

Provide pre-built and custom chatbot configuration templates for rapid deployment.

---

### FR-230: Template Data Model

**Description:** The system shall store conversation templates with configurable properties for chatbot configuration.

**Priority:** Must

**User Story:** As an administrator, I want template storage so that I can save and reuse chatbot configurations.

**Acceptance Criteria:**

```gherkin
Given a template is created
When stored in the database
Then it includes:
  - Unique ID and name
  - Description and category
  - System prompt configuration
  - Welcome message
  - Suggested questions (conversation starters)
  - UI styling (colors, position)
  - Model configuration (provider, temperature, max_tokens)
  - Is_system flag (for pre-built templates)

Given a template is saved
When validation is performed
Then required fields (name, category) are enforced
And JSON structures are validated against schemas
And duplicate names are prevented
```

**Technical Notes:**
- Table: `ai_botkit_templates`
- JSON columns: `style`, `messages_template`, `model_config`, `conversation_starters`
- Categories: `support`, `sales`, `marketing`, `education`, `general`
- System templates marked with `is_system = 1`

**Dependencies:** None (new table creation)

---

### FR-231: Admin Template List View

**Description:** The system shall provide administrators with a list view of all available templates.

**Priority:** Must

**User Story:** As an administrator, I want to see all templates so that I can manage and select configurations for chatbots.

**Acceptance Criteria:**

```gherkin
Given an administrator accesses the template management page
When the page loads
Then all templates are displayed in a grid/list view
And each template card shows:
  - Template name
  - Category badge
  - Description preview
  - Thumbnail (if available)
  - Usage count
  - System/Custom indicator

Given templates exist in multiple categories
When filter options are available
Then administrators can filter by category
And can toggle between "All", "System", and "Custom" views
And can sort by name, usage, or date created
```

**Technical Notes:**
- Admin menu page: "AI BotKit > Templates"
- Use WordPress admin table styling
- Implement `Template_Manager::get_templates()` with filters

**Dependencies:** FR-230

---

### FR-232: Template Builder/Editor

**Description:** The system shall provide a visual interface for creating and editing templates.

**Priority:** Must

**User Story:** As an administrator, I want a template builder so that I can create custom chatbot configurations without coding.

**Acceptance Criteria:**

```gherkin
Given an administrator opens the template builder
When the interface loads
Then they can configure:
  - Basic info: name, description, category
  - System prompt with variable placeholders
  - Welcome message
  - Conversation starters (add/remove/reorder)
  - UI styling (color pickers, position selector)
  - Model settings (dropdowns, sliders)

Given an administrator is editing a template
When they make changes
Then changes are validated in real-time
And unsaved changes prompt confirmation on exit
And save action persists all changes

Given an administrator editing a system template
When they attempt to save
Then they are prompted to "Save as Copy" instead
And the original system template is preserved
```

**Technical Notes:**
- React-based template builder component
- Live validation of JSON structures
- Auto-save drafts to localStorage
- Support variable placeholders: `{{site_name}}`, `{{user_name}}`

**Dependencies:** FR-230, FR-231

---

### FR-233: Template Preview

**Description:** The system shall provide a preview of how the chatbot will appear when a template is applied.

**Priority:** Should

**User Story:** As an administrator, I want to preview templates so that I can see the result before applying to a chatbot.

**Acceptance Criteria:**

```gherkin
Given an administrator is viewing a template
When they click "Preview"
Then a preview modal/panel opens showing:
  - Chat widget appearance with applied styling
  - Welcome message as displayed to users
  - Conversation starters as clickable buttons
  - Sample bot response with configured tone

Given preview mode is active
When the administrator makes changes
Then the preview updates in real-time
And changes are not saved until explicitly confirmed
```

**Technical Notes:**
- Use iframe or isolated container for preview
- Apply template styles dynamically
- Mock conversation for demonstration

**Dependencies:** FR-232

---

### FR-234: Apply Template to Chatbot

**Description:** The system shall allow applying templates to new or existing chatbots.

**Priority:** Must

**User Story:** As an administrator, I want to apply templates so that I can quickly configure chatbots with proven configurations.

**Acceptance Criteria:**

```gherkin
Given an administrator creating a new chatbot
When they select "Start from template"
Then available templates are shown grouped by category
And each template shows name, description, and preview thumbnail
And selecting a template pre-fills all chatbot settings

Given an administrator with an existing chatbot
When they apply a template
Then a warning shows which settings will be overwritten
And they can choose:
  - "Replace All" - overwrites all settings
  - "Merge" - only applies non-configured settings
  - "Cancel" - aborts the operation

Given a template is applied to a chatbot
When the application completes
Then the chatbot's template_id is set
And the template's usage_count is incremented
And a success message is displayed
```

**Technical Notes:**
- Implement `Template_Manager::apply_to_chatbot()` method
- Track template origin in `chatbots.template_id`
- Log template application in analytics

**Dependencies:** FR-230, FR-231

---

### FR-235: Pre-built FAQ Bot Template

**Description:** The system shall include a pre-built FAQ Bot template optimized for Q&A interactions.

**Priority:** Must

**User Story:** As an administrator, I want an FAQ template so that I can quickly deploy a Q&A chatbot for my knowledge base.

**Acceptance Criteria:**

```gherkin
Given a fresh plugin installation
When system templates are installed
Then the "FAQ Bot" template is available
And it is configured with:
  - System prompt focused on accurate, sourced answers
  - Welcome message: "Hello! Ask me anything about [site_name]."
  - Conversation starters: ["What can you help me with?", "Search the knowledge base"]
  - Neutral, professional styling
  - Source citations enabled in responses
  - "Did this help?" feedback prompt

Given the FAQ Bot template is applied
When a user asks a question
Then the chatbot provides a direct answer
And cites the source document/page
And offers follow-up question suggestions
```

**Technical Notes:**
- Template slug: `faq-bot`
- Category: `support`
- Load from `data/templates/faq-bot.json`
- Include helpfulness feedback component

**Dependencies:** FR-230

---

### FR-236: Pre-built Customer Support Template

**Description:** The system shall include a pre-built Customer Support template for help desk scenarios.

**Priority:** Must

**User Story:** As an administrator, I want a support template so that I can deploy a customer service chatbot quickly.

**Acceptance Criteria:**

```gherkin
Given a fresh plugin installation
When system templates are installed
Then the "Customer Support" template is available
And it is configured with:
  - System prompt with empathetic, solution-focused tone
  - Welcome message: "Hi! How can I help you today?"
  - Conversation starters: ["I have a problem", "Track my order", "Speak to a human"]
  - Ticket reference collection prompts
  - Escalation trigger phrases
  - Human handoff capability hooks

Given the Customer Support template is applied
When a user expresses frustration
Then the chatbot responds with empathy
And offers to connect with a human agent
And logs the escalation request
```

**Technical Notes:**
- Template slug: `customer-support`
- Category: `support`
- Include escalation keywords: "angry", "frustrated", "human", "manager"
- Hook: `ai_botkit_escalation_triggered`

**Dependencies:** FR-230

---

### FR-237: Pre-built Product Advisor Template

**Description:** The system shall include a pre-built Product Advisor template for guided product discovery.

**Priority:** Must

**User Story:** As an administrator, I want a product advisor template so that I can help users find the right products through conversation.

**Acceptance Criteria:**

```gherkin
Given a fresh plugin installation
When system templates are installed
Then the "Product Advisor" template is available
And it is configured with:
  - System prompt focused on needs assessment
  - Welcome message: "Hi! I can help you find the perfect product. What are you looking for?"
  - Conversation starters: ["Help me choose", "Compare products", "What's popular?"]
  - Product matching question flow
  - Comparison table formatting
  - Add to cart call-to-action buttons

Given the Product Advisor template is applied
When a user describes their needs
Then the chatbot asks clarifying questions
And recommends matching products with reasons
And provides easy purchase actions
```

**Technical Notes:**
- Template slug: `product-advisor`
- Category: `sales`
- Integrate with FR-250 (Recommendation Engine)
- Include product card display components

**Dependencies:** FR-230, FR-250

---

### FR-238: Pre-built Lead Capture Template

**Description:** The system shall include a pre-built Lead Capture template for collecting visitor information.

**Priority:** Must

**User Story:** As an administrator, I want a lead capture template so that I can collect potential customer information through conversation.

**Acceptance Criteria:**

```gherkin
Given a fresh plugin installation
When system templates are installed
Then the "Lead Capture" template is available
And it is configured with:
  - System prompt guiding conversational data collection
  - Welcome message: "Hello! I'd love to help you. May I ask a few questions?"
  - Multi-step form flow (name, email, interest, budget)
  - Field validation (email format, required fields)
  - CRM integration hooks
  - Thank you / next steps messaging

Given the Lead Capture template is applied
When a user completes the form flow
Then their information is stored securely
And a confirmation message is displayed
And the `ai_botkit_lead_captured` hook fires with lead data
```

**Technical Notes:**
- Template slug: `lead-capture`
- Category: `marketing`
- Store leads in custom table or WordPress user meta
- Hooks for CRM integration: `ai_botkit_lead_captured`

**Dependencies:** FR-230

---

### FR-239: Template Import/Export

**Description:** The system shall allow importing and exporting templates as JSON files.

**Priority:** Could

**User Story:** As an administrator, I want to import/export templates so that I can share configurations between sites.

**Acceptance Criteria:**

```gherkin
Given an administrator viewing a template
When they click "Export"
Then a JSON file downloads containing:
  - All template configuration
  - Metadata (name, description, category)
  - Version information
And the filename is: {template-slug}-export.json

Given an administrator on the templates page
When they click "Import Template"
Then a file upload dialog opens
And they can select a JSON file
And the file is validated before import
And conflicts (same name) prompt for resolution

Given an imported template conflicts with existing
When the import dialog shows
Then options are:
  - "Replace Existing" - overwrites the template
  - "Import as Copy" - creates with new name
  - "Cancel" - aborts import
```

**Technical Notes:**
- Implement `Template_Manager::export_template()` returning JSON string
- Implement `Template_Manager::import_template()` accepting JSON
- Validate JSON schema on import
- Include version for compatibility checking

**Dependencies:** FR-230

---

### Feature 5: Chat Transcripts Export (FR-240 to FR-249)

Enable PDF export of conversation transcripts for record-keeping and compliance.

---

### FR-240: Admin Export Interface

**Description:** The system shall provide administrators with an interface to export conversation transcripts.

**Priority:** Must

**User Story:** As an administrator, I want to export conversations so that I can create records for compliance and review.

**Acceptance Criteria:**

```gherkin
Given an administrator viewing a conversation in admin
When they see the conversation detail page
Then an "Export PDF" button is visible
And clicking it generates and downloads a PDF transcript

Given an administrator viewing the conversations list
When they select multiple conversations (checkboxes)
Then a "Bulk Export" option becomes available
And selecting it exports all selected as individual PDFs in a ZIP file
```

**Technical Notes:**
- Admin page: "AI BotKit > Conversations"
- Use `Export_Handler::export_to_pdf()` method
- Bulk export uses ZIP library

**Dependencies:** FR-241 (PDF Generation)

---

### FR-241: PDF Generation

**Description:** The system shall generate formatted PDF documents from conversation transcripts.

**Priority:** Must

**User Story:** As an administrator, I want professional PDF transcripts so that I can share and archive conversations.

**Acceptance Criteria:**

```gherkin
Given a conversation selected for export
When PDF generation is triggered
Then a PDF document is generated containing:
  - Header with site branding (logo, site name)
  - Conversation metadata (date, chatbot name, user)
  - All messages in chronological order
  - Clear visual distinction between user and bot messages
  - Timestamps on each message
  - Page numbers in footer

Given a conversation with media attachments
When PDF is generated
Then images are embedded inline (resized to fit)
And file attachments are listed with names and sizes
And video embeds show as screenshots with links
And links are clickable in the PDF

Given a long conversation (100+ messages)
When PDF is generated
Then content is properly paginated
And headers repeat on each page
And generation completes within 30 seconds
```

**Technical Notes:**
- Use Dompdf library for PDF generation
- Template file: `public/templates/pdf/transcript.php`
- Max image width: 400px
- Paper sizes: Letter (default), A4

**Dependencies:** None (core feature)

---

### FR-242: PDF Branding (logo, colors)

**Description:** The system shall allow customization of PDF export appearance with site branding.

**Priority:** Could

**User Story:** As an administrator, I want branded PDF exports so that documents look professional and match my brand.

**Acceptance Criteria:**

```gherkin
Given export settings in admin
When an administrator configures PDF branding
Then they can:
  - Upload a logo for the header
  - Set company name
  - Configure primary color
  - Set footer text (e.g., "Confidential")

Given branded PDF settings are configured
When any PDF is exported
Then the branding is applied consistently
And the logo appears in the header
And colors are used for message styling

Given no custom branding is configured
When a PDF is exported
Then default AI BotKit branding is used
And site name from WordPress settings is shown
```

**Technical Notes:**
- Options: `ai_botkit_pdf_logo`, `ai_botkit_pdf_company_name`, `ai_botkit_pdf_primary_color`
- Apply via CSS in PDF template
- Filter: `ai_botkit_pdf_styles` for advanced customization

**Dependencies:** FR-241

---

### FR-243: Conversation Selection for Export

**Description:** The system shall allow selecting specific conversations or date ranges for export.

**Priority:** Should

**User Story:** As an administrator, I want to select which conversations to export so that I can create focused reports.

**Acceptance Criteria:**

```gherkin
Given an administrator on the export page
When they want to export specific conversations
Then they can:
  - Select individual conversations by checkbox
  - Filter by date range
  - Filter by chatbot
  - Filter by user
And apply filters before export

Given filters are applied
When "Export Selected" is clicked
Then only matching conversations are exported
And the export includes filter metadata
```

**Technical Notes:**
- Reuse search/filter components from FR-214
- Include filter summary in export metadata

**Dependencies:** FR-240

---

### FR-244: User Self-Service PDF Download

**Description:** The system shall allow users to download PDF transcripts of their own conversations.

**Priority:** Should

**User Story:** As a user, I want to download my conversations so that I have a record of the information shared.

**Acceptance Criteria:**

```gherkin
Given a logged-in user viewing their conversation history
When they open a conversation
Then a "Download PDF" button is visible
And clicking it generates and downloads their conversation

Given a user exporting their conversation
When the PDF is generated
Then only their messages and bot responses are included
And their user information is shown
And no other user data is exposed

Given a guest user (not logged in)
When they view a conversation
Then no export option is available
And a prompt suggests creating an account
```

**Technical Notes:**
- Verify ownership before allowing export
- Use user-specific branding (their name as "From:")
- Log user exports for audit

**Dependencies:** FR-241

---

### FR-245: Export Progress Indicator

**Description:** The system shall display progress during PDF generation for large exports.

**Priority:** Could

**User Story:** As a user, I want to see export progress so that I know the system is working.

**Acceptance Criteria:**

```gherkin
Given a PDF export is requested
When generation takes more than 2 seconds
Then a progress indicator is displayed
And the indicator shows current status:
  - "Preparing conversation data..."
  - "Generating PDF..."
  - "Download ready!"

Given a bulk export of multiple conversations
When generation is in progress
Then progress shows: "Exporting 5 of 12 conversations..."
And estimated time remaining is shown (if calculable)
```

**Technical Notes:**
- Use AJAX polling or WebSocket for progress updates
- Store progress in transient: `ai_botkit_export_progress_{user_id}`
- Timeout after 60 seconds with error

**Dependencies:** FR-241

---

### FR-246: Batch Export for Admins

**Description:** The system shall support batch export of multiple conversations for administrators.

**Priority:** Should

**User Story:** As an administrator, I want to batch export conversations so that I can efficiently create archives.

**Acceptance Criteria:**

```gherkin
Given an administrator with multiple conversations selected
When they click "Batch Export"
Then processing begins in the background
And they receive a notification when complete
And the result is a ZIP file containing individual PDFs

Given a batch export of 50+ conversations
When the export runs
Then it processes in chunks (10 at a time)
And progress is logged
And timeout is extended appropriately
And admin can cancel if needed
```

**Technical Notes:**
- Use background processing (Action Scheduler or wp-cron)
- Generate ZIP using PHP ZipArchive
- Store temporary files in `wp-content/uploads/ai-botkit/exports/`
- Clean up after 24 hours

**Dependencies:** FR-240, FR-241

---

### FR-247: Export Scheduling

**Description:** The system shall allow administrators to schedule recurring exports.

**Priority:** Could

**User Story:** As an administrator, I want to schedule exports so that I receive regular reports automatically.

**Acceptance Criteria:**

```gherkin
Given an administrator on the export settings page
When they configure scheduled exports
Then they can set:
  - Frequency (daily, weekly, monthly)
  - Time of day
  - Filter criteria (chatbot, date range: "last 7 days")
  - Delivery method (download link, email)

Given a scheduled export is due
When the cron runs
Then conversations matching criteria are exported
And the admin is notified (email with download link)
And the export is logged in history
```

**Technical Notes:**
- Use WordPress cron: `wp_schedule_event()`
- Store schedule in options: `ai_botkit_scheduled_exports`
- Email via `wp_mail()` with download link

**Dependencies:** FR-246

---

### FR-248: Export History/Audit Log

**Description:** The system shall maintain a log of all export activities for audit purposes.

**Priority:** Should

**User Story:** As an administrator, I want an export history so that I can track who exported what and when.

**Acceptance Criteria:**

```gherkin
Given an administrator accesses export history
When the history page loads
Then they see a list of all exports showing:
  - Date and time
  - User who performed export
  - Conversations exported (count or IDs)
  - Export type (single, batch, scheduled)
  - Success/failure status

Given an export occurs (any type)
When it completes
Then a record is added to the audit log
And includes metadata (filters used, file size)
And is retained for 90 days (configurable)
```

**Technical Notes:**
- Log to `ai_botkit_analytics` with `event_type: 'export'`
- Include in event_data: conversation_ids, user_id, export_type

**Dependencies:** FR-240

---

### FR-249: GDPR Data Export Support

**Description:** The system shall support GDPR data subject access requests by including chat data in WordPress exports.

**Priority:** Should

**User Story:** As a user, I want my chat data included in data exports so that I can exercise my GDPR rights.

**Acceptance Criteria:**

```gherkin
Given a user requests their data via WordPress privacy tools
When WordPress generates the data export
Then AI BotKit conversation data is included
And includes:
  - All conversations and messages
  - Media attachments (as file references)
  - User interaction history

Given a user requests data deletion via WordPress
When WordPress processes the erasure request
Then AI BotKit data is deleted:
  - All conversations owned by user
  - All messages in those conversations
  - All media uploaded by user
  - User interaction records
And deletion is logged for compliance
```

**Technical Notes:**
- Hook into `wp_privacy_personal_data_exporters`
- Hook into `wp_privacy_personal_data_erasers`
- Format data per WordPress privacy guidelines

**Dependencies:** FR-206 (Delete Conversation)

---

### Feature 6: LMS/WooCommerce Suggestions (FR-250 to FR-259)

Enhance user experience with personalized product and course recommendations.

---

### FR-250: Recommendation Engine Core

**Description:** The system shall provide a recommendation engine that combines multiple signals to suggest relevant products or courses.

**Priority:** Must

**User Story:** As a user, I want personalized recommendations so that I discover relevant products and courses.

**Acceptance Criteria:**

```gherkin
Given the recommendation engine
When processing a recommendation request
Then it considers four signal types:
  1. Conversation context (current chat content) - weight: 0.35
  2. Browsing history (pages viewed in session) - weight: 0.25
  3. Purchase/enrollment history (past transactions) - weight: 0.25
  4. Explicit request ("recommend me a course") - weight: 0.15
And signals are weighted and combined into a score
And top recommendations are returned (default: 5)

Given a recommendation request
When no data is available for any signal
Then an appropriate message is returned
And no empty recommendation cards are shown
And fallback to popular/featured items occurs

Given recommendations are generated
When multiple items have similar scores
Then items are deduplicated by ID
And results are sorted by final score descending
```

**Technical Notes:**
- Class: `Recommendation_Engine`
- Configurable weights via `ai_botkit_recommendation_signals` filter
- Cache recommendations per user for 10 minutes

**Dependencies:** FR-012 (WooCommerce), FR-011 (LearnDash) from Phase 1

---

### FR-251: Conversation Context Analysis

**Description:** The system shall analyze conversation content to identify product/course recommendation opportunities.

**Priority:** Must

**User Story:** As a user, I want recommendations based on my conversation so that suggestions are relevant to what I'm discussing.

**Acceptance Criteria:**

```gherkin
Given an active conversation
When a user message indicates interest in a topic
Then the system identifies relevant keywords
And matches keywords against product/course attributes:
  - Title and description
  - Categories and tags
  - Custom attributes
And products/courses with matches are scored by relevance

Given a conversation about "beginner photography course"
When context analysis runs
Then it detects:
  - Topic: "photography"
  - Level: "beginner"
  - Type: "course"
And courses matching these criteria are scored highest

Given recent conversation history
When generating recommendations
Then the last 5 messages are analyzed
And more recent messages have higher weight
```

**Technical Notes:**
- Extend `WooCommerce_Assistant::detect_shopping_intent()`
- Use NLP-lite approach: keyword extraction, phrase matching
- Intent patterns: "looking for", "recommend", "help me find", "best"

**Dependencies:** FR-250

---

### FR-252: Browsing History Tracking

**Description:** The system shall track user browsing within the session to inform recommendations.

**Priority:** Should

**User Story:** As a user, I want recommendations based on my browsing so that the chatbot understands my interests.

**Acceptance Criteria:**

```gherkin
Given a user browsing the website
When they view product or course pages
Then the page views are tracked in their session
And stored in ai_botkit_user_interactions table:
  - user_id
  - session_id
  - item_type (product/course/post)
  - item_id
  - viewed_at timestamp

Given browsing history for a user
When recommendations are requested
Then recently viewed items influence suggestions
And related items to viewed products are included
And items viewed multiple times get higher weight

Given session expiration
When the session ends
Then browsing history for that session is cleared (guests)
And logged-in users retain history for 30 days (configurable)
```

**Technical Notes:**
- Track via JavaScript: detect product/course pages
- Store in `ai_botkit_user_interactions` table
- Interaction type: `page_view`, `product_view`, `course_view`

**Dependencies:** FR-250

---

### FR-253: Purchase/Enrollment History Integration

**Description:** The system shall use past purchase and enrollment data to inform recommendations.

**Priority:** Should

**User Story:** As a returning customer, I want recommendations based on my purchases so that I find complementary products.

**Acceptance Criteria:**

```gherkin
Given a logged-in user with WooCommerce purchase history
When recommendations are generated
Then the system queries completed orders
And identifies purchased product categories/tags
And recommends complementary or related products
And excludes already-purchased items (optional)

Given a logged-in user with LearnDash course enrollments
When recommendations are generated
Then the system queries enrolled courses
And identifies course categories/topics
And recommends next-level or related courses
And excludes already-enrolled courses

Given a user with no purchase/enrollment history
When recommendations are generated
Then this signal is skipped (weight redistributed)
And other signals are used with adjusted weights
```

**Technical Notes:**
- Query WooCommerce: `wc_get_orders(['customer_id' => $user_id])`
- Query LearnDash: `learndash_user_get_enrolled_courses($user_id)`
- Identify patterns: category affinity, price range, learning level

**Dependencies:** FR-250

---

### FR-254: Explicit Recommendation Requests

**Description:** The system shall detect and respond to explicit requests for recommendations.

**Priority:** Must

**User Story:** As a user, I want to ask for recommendations directly so that I get immediate suggestions.

**Acceptance Criteria:**

```gherkin
Given a user message containing recommendation intent
When the message matches patterns like:
  - "recommend a course"
  - "suggest a product"
  - "what should I buy"
  - "help me find"
  - "best [product type] for [use case]"
Then the system triggers recommendation generation
And uses conversation context to refine suggestions
And displays recommendation cards in the response

Given an explicit recommendation request with criteria
When the user specifies: "recommend a course under $50 for beginners"
Then criteria are extracted:
  - Price: < $50
  - Level: beginner
And only matching items are suggested
And criteria are acknowledged in the response

Given a recommendation request with no matching items
When criteria cannot be met
Then the chatbot explains no exact matches found
And suggests loosening criteria
Or shows closest alternatives with explanation
```

**Technical Notes:**
- Intent detection patterns in `Recommendation_Engine::analyze_conversation()`
- Extract constraints: price, level, category, brand
- Respond with conversational context + card UI

**Dependencies:** FR-250, FR-251

---

### FR-255: Suggestion UI Cards

**Description:** The system shall display product/course suggestions as interactive cards within the chat interface.

**Priority:** Must

**User Story:** As a user, I want attractive product cards so that I can easily see and act on recommendations.

**Acceptance Criteria:**

```gherkin
Given recommendations are available
When displayed in chat
Then each suggestion appears as a card showing:
  - Product/course image (thumbnail, 100x100px)
  - Title (max 50 characters, truncate with ellipsis)
  - Short description (max 80 characters)
  - Price (products) or duration (courses)
  - Star rating (if available)
  - Primary action button

Given multiple recommendations (3-5 items)
When displayed in chat
Then cards are shown in a horizontal scrollable row
And navigation arrows appear when more than 3 cards
And on mobile, cards stack vertically or use swipe

Given a recommendation card
When the user hovers/focuses
Then the card elevates slightly (visual feedback)
And the action button becomes more prominent
```

**Technical Notes:**
- CSS file: `recommendation-cards.css`
- JavaScript component: `recommendations.js`
- Responsive breakpoint: 480px (mobile stack)
- Include product/course ID for tracking

**Dependencies:** FR-250

---

### FR-256: Add to Cart Action

**Description:** The system shall enable users to add recommended WooCommerce products to cart directly from the chat.

**Priority:** Must

**User Story:** As a shopper, I want to add products to cart from chat so that purchasing is convenient.

**Acceptance Criteria:**

```gherkin
Given a WooCommerce product recommendation card
When the user clicks "Add to Cart"
Then the product is added to the WooCommerce cart
And a success message appears: "Added to cart!"
And the button changes to "View Cart" or shows quantity selector
And the cart icon/count updates (if visible)

Given a variable product recommendation
When the user clicks "Add to Cart"
Then they are redirected to the product page
Or a variation selector appears in chat (if supported)

Given the add to cart action fails (e.g., out of stock)
When the error occurs
Then an appropriate message is shown: "Sorry, this item is currently out of stock"
And the error is logged for review
```

**Technical Notes:**
- Use WooCommerce AJAX add-to-cart endpoint
- Handle variable products by redirecting to product page
- Update mini-cart via `wc_add_to_cart_message_html` filter

**Dependencies:** FR-255

---

### FR-257: Enroll Now Action

**Description:** The system shall enable users to initiate course enrollment from recommended LearnDash courses.

**Priority:** Must

**User Story:** As a learner, I want to enroll in courses from chat so that starting a new course is easy.

**Acceptance Criteria:**

```gherkin
Given a LearnDash course recommendation card
When the user is NOT enrolled
Then the action button shows "Enroll Now"
And clicking it navigates to the course enrollment page
Or initiates enrollment if course is free

Given a LearnDash course recommendation card
When the user IS already enrolled
Then the action button shows "Continue Learning"
And clicking it navigates to where they left off
And progress percentage is shown on the card

Given a paid course requiring purchase
When the user clicks "Enroll Now"
Then they are directed to the course purchase page
And checkout flow is initiated
```

**Technical Notes:**
- Check enrollment: `sfwd_lms_has_access($course_id, $user_id)`
- Free enrollment: `ld_update_course_access($user_id, $course_id)`
- Paid courses: redirect to course page or WooCommerce product

**Dependencies:** FR-255

---

### FR-258: LearnDash Course Suggestions

**Description:** The system shall generate course recommendations specific to LearnDash LMS content.

**Priority:** Must

**User Story:** As a learner, I want course recommendations so that I know what to learn next.

**Acceptance Criteria:**

```gherkin
Given a user interacting with the chatbot
When they express interest in learning a topic
Then LearnDash courses matching the topic are suggested
And suggestions consider:
  - Course categories and tags
  - Prerequisites (suggest prerequisite first)
  - User's current enrollments (suggest next level)
  - Course difficulty level

Given a user enrolled in multiple courses
When recommendations are generated
Then courses in the same track/pathway are prioritized
And completion progress influences suggestions
And "Next recommended" is highlighted

Given LearnDash course recommendations
When displayed as cards
Then cards show:
  - Course image
  - Course title
  - Lesson count
  - Estimated duration
  - Difficulty level badge
  - Enrollment status (if applicable)
```

**Technical Notes:**
- Query: `get_posts(['post_type' => 'sfwd-courses', ...])`
- Consider course relationships (prerequisites, series)
- Include `learndash_course_info()` data

**Dependencies:** FR-250, FR-255

---

### FR-259: WooCommerce Product Suggestions

**Description:** The system shall generate product recommendations specific to WooCommerce catalog.

**Priority:** Must

**User Story:** As a shopper, I want product recommendations so that I discover items matching my needs.

**Acceptance Criteria:**

```gherkin
Given a user interacting with the chatbot
When they express interest in a product category
Then WooCommerce products matching are suggested
And suggestions consider:
  - Product categories and tags
  - Price range (if specified)
  - Product attributes (size, color, etc.)
  - Stock status (only in-stock items)
  - User's purchase history

Given a user viewing a product page
When they open the chatbot
Then related products are suggested
And "Frequently bought together" items are included
And upsell/cross-sell products are prioritized

Given WooCommerce product recommendations
When displayed as cards
Then cards show:
  - Product image
  - Product name
  - Price (including sale price if applicable)
  - Star rating (if reviews enabled)
  - Stock status badge (if low stock)
  - Add to Cart button
```

**Technical Notes:**
- Use `wc_get_products()` with taxonomy queries
- Include WooCommerce built-in related products
- Respect catalog visibility settings

**Dependencies:** FR-250, FR-255, FR-256

---

## 3. Non-Functional Requirements

### NFR-201: Performance

**Description:** Phase 2 features shall maintain system performance standards.

**Priority:** Must

**Requirements:**

| Metric | Target | Measurement |
|--------|--------|-------------|
| Search response time | < 500ms (P95) | Server-side query + render |
| History list load time | < 300ms | API response time |
| Recommendation generation | < 1000ms | Full pipeline |
| PDF generation (typical) | < 10s | Conversations < 100 messages |
| PDF generation (large) | < 30s | Conversations > 100 messages |
| Media upload | < 5s | 5MB file |

**Acceptance Criteria:**

```gherkin
Given search functionality
When a search query is executed on 100,000+ messages
Then results return within 500ms (P95)
And FULLTEXT index is utilized (verified via EXPLAIN)

Given recommendation generation
When called with all 4 signals
Then results return within 1000ms
And recommendations are cached for 10 minutes
```

---

### NFR-202: Security

**Description:** Phase 2 features shall maintain security standards.

**Priority:** Must

**Requirements:**

| Area | Requirement |
|------|-------------|
| File Upload | MIME type validation, size limits, malware scanning |
| Search | SQL injection prevention, user data isolation |
| Export | Access control, ownership verification |
| API | Nonce verification, capability checks |
| Media | Protected directories, no direct execution |

**Acceptance Criteria:**

```gherkin
Given search functionality
When queries are processed
Then SQL injection is prevented (prepared statements)
And users can only search their own data (unless admin)

Given media uploads
When files are processed
Then MIME types are validated against content
And malicious files are rejected
And stored files cannot be executed
```

---

### NFR-203: Scalability

**Description:** Phase 2 features shall scale with increased usage.

**Priority:** Should

**Requirements:**

| Component | Scale Target | Strategy |
|-----------|--------------|----------|
| History | 1000+ conversations/user | Pagination, indexing |
| Search | 1M+ messages | FULLTEXT index, caching |
| Media | 10GB+ storage | Date-organized directories, CDN-ready |
| Recommendations | 100K+ products/courses | Cached scoring, batch processing |

**Acceptance Criteria:**

```gherkin
Given a site with 100,000 conversations
When search functionality is used
Then response times remain under 500ms
And database load stays within acceptable limits

Given growing media storage
When files exceed 1GB total
Then administrators are notified
And cleanup procedures are documented
```

---

### NFR-204: Accessibility

**Description:** Phase 2 UI features shall meet WCAG 2.1 AA standards.

**Priority:** Should

**Requirements:**

| Component | Accessibility Requirement |
|-----------|---------------------------|
| History Panel | Keyboard navigable, screen reader labels |
| Search | Accessible search input, result announcements |
| Media | Alt text for images, captions for videos |
| Recommendation Cards | Focusable, action buttons labeled |
| Lightbox | Focus trapping, escape key support |

**Acceptance Criteria:**

```gherkin
Given the chat history panel
When navigated with keyboard only
Then all items are focusable (Tab key)
And selection works with Enter key
And screen readers announce conversation details

Given search results
When displayed
Then results are announced to screen readers
And result count is communicated
```

---

### NFR-205: Backward Compatibility

**Description:** Phase 2 shall maintain compatibility with Phase 1 installations.

**Priority:** Must

**Requirements:**

| Area | Compatibility |
|------|---------------|
| Database | Migration script, no breaking changes |
| API | New endpoints only, existing unchanged |
| Hooks | New hooks, existing preserved |
| Settings | New options with defaults |

**Acceptance Criteria:**

```gherkin
Given an existing Phase 1 installation
When Phase 2 is deployed
Then all existing features continue working
And no data loss occurs
And migration runs automatically on update
```

---

## 4. Acceptance Criteria Summary

### Feature 1: Chat History

| FR | Acceptance Summary |
|----|-------------------|
| FR-201 | List displays with metadata, previews, pagination |
| FR-202 | Messages load completely, chronologically |
| FR-203 | Switching preserves state, visual highlighting |
| FR-204 | Preview shows first 100 chars or media indicator |
| FR-205 | 10 items per page, navigation works |
| FR-206 | Confirmation dialog, cascade delete, ownership check |
| FR-207 | Star toggle, filter view for favorites |
| FR-208 | Date presets and custom range filtering |
| FR-209 | History button visible, panel slides, resume works |

### Feature 2: Search Functionality

| FR | Acceptance Summary |
|----|-------------------|
| FR-210 | Search input with debounce, clear button |
| FR-211 | FULLTEXT search, relevance scoring |
| FR-212 | Admin can search all, user ID filter |
| FR-213 | Auto-filter to own user_id |
| FR-214 | Date, chatbot, role filters combinable |
| FR-215 | Excerpts with metadata, click to navigate |
| FR-216 | <mark> tags on matching terms |
| FR-217 | Sorted by relevance with recency boost |
| FR-218 | 20 per page, pagination controls |
| FR-219 | < 500ms, FULLTEXT utilized, cached |

### Feature 3: Rich Media Support

| FR | Acceptance Summary |
|----|-------------------|
| FR-220 | Inline images, responsive, placeholder on load |
| FR-221 | YouTube/Vimeo oEmbed, responsive player |
| FR-222 | Download card with icon, size, click to download |
| FR-223 | OpenGraph extraction, cached preview cards |
| FR-224 | MIME validation, size limits, secure storage |
| FR-225 | Component per type, gallery for multiple |
| FR-226 | Lightbox opens on click, keyboard accessible |
| FR-227 | Secure download with proper headers |
| FR-228 | Whitelist MIME, scan content, .htaccess protection |
| FR-229 | Organized by date, orphan cleanup, quota alerts |

### Feature 4: Conversation Templates

| FR | Acceptance Summary |
|----|-------------------|
| FR-230 | Template table with JSON config columns |
| FR-231 | Grid view with cards, category filters |
| FR-232 | Visual builder for all configuration options |
| FR-233 | Live preview of widget appearance |
| FR-234 | Apply with merge/replace options |
| FR-235 | FAQ Bot: sourced answers, feedback prompt |
| FR-236 | Customer Support: empathy, escalation |
| FR-237 | Product Advisor: needs assessment, matching |
| FR-238 | Lead Capture: form flow, CRM hooks |
| FR-239 | JSON export/import with conflict resolution |

### Feature 5: Chat Transcripts Export

| FR | Acceptance Summary |
|----|-------------------|
| FR-240 | Admin export button, bulk ZIP export |
| FR-241 | PDF with branding, messages, timestamps |
| FR-242 | Custom logo, colors, footer text |
| FR-243 | Filter selection before export |
| FR-244 | User download button, ownership verified |
| FR-245 | Progress indicator for long exports |
| FR-246 | Background batch processing, ZIP result |
| FR-247 | Scheduled exports with email notification |
| FR-248 | Audit log of all export activities |
| FR-249 | WordPress privacy tools integration |

### Feature 6: LMS/WooCommerce Suggestions

| FR | Acceptance Summary |
|----|-------------------|
| FR-250 | 4 signals, weighted scoring, top 5 results |
| FR-251 | Keyword extraction, attribute matching |
| FR-252 | Session tracking, interaction table |
| FR-253 | WC orders + LD enrollments queried |
| FR-254 | Intent patterns detected, criteria extracted |
| FR-255 | Card UI with image, title, price, action |
| FR-256 | Add to Cart AJAX, success message |
| FR-257 | Enroll Now / Continue Learning buttons |
| FR-258 | Course-specific metadata, prerequisites |
| FR-259 | Product-specific, related products included |

---

## 5. API Specifications

### 5.1 New REST API Endpoints

**Namespace:** `ai-botkit/v1`

#### Chat History Endpoints

| Endpoint | Method | Description | Auth |
|----------|--------|-------------|------|
| `/history` | GET | List user conversations | Logged-in |
| `/history/{id}` | GET | Get conversation details | Owner/Admin |
| `/history/{id}/resume` | POST | Resume conversation | Owner |
| `/history/{id}` | DELETE | Archive/delete conversation | Owner/Admin |

#### Search Endpoints

| Endpoint | Method | Description | Auth |
|----------|--------|-------------|------|
| `/search` | GET | Search messages | Logged-in |
| `/search/suggestions` | GET | Get search suggestions | Logged-in |

#### Media Endpoints

| Endpoint | Method | Description | Auth |
|----------|--------|-------------|------|
| `/media/upload` | POST | Upload media file | Logged-in |
| `/media/{id}` | GET | Get media details | Owner/Admin |
| `/media/{id}` | DELETE | Delete media | Owner/Admin |
| `/media/link-preview` | GET | Get URL preview | Logged-in |

#### Template Endpoints

| Endpoint | Method | Description | Auth |
|----------|--------|-------------|------|
| `/templates` | GET | List templates | Admin |
| `/templates/{id}` | GET | Get template | Admin |
| `/templates` | POST | Create template | Admin |
| `/templates/{id}` | PUT | Update template | Admin |
| `/templates/{id}` | DELETE | Delete template | Admin |
| `/templates/{id}/apply` | POST | Apply to chatbot | Admin |

#### Export Endpoints

| Endpoint | Method | Description | Auth |
|----------|--------|-------------|------|
| `/export/{id}/pdf` | GET | Download PDF | Owner/Admin |
| `/export/batch` | POST | Batch export | Admin |

#### Recommendation Endpoints

| Endpoint | Method | Description | Auth |
|----------|--------|-------------|------|
| `/recommendations` | GET | Get recommendations | Logged-in |
| `/recommendations/track` | POST | Track interaction | Logged-in |

### 5.2 New AJAX Actions

#### Public AJAX (wp_ajax_ and wp_ajax_nopriv_)

| Action | Purpose | Nonce |
|--------|---------|-------|
| `ai_botkit_get_history_list` | Get conversation list | Required |
| `ai_botkit_resume_conversation` | Resume conversation | Required |
| `ai_botkit_search_messages` | Search chat history | Required |
| `ai_botkit_upload_chat_media` | Upload attachment | Required |
| `ai_botkit_get_link_preview` | Get URL preview | Required |
| `ai_botkit_get_recommendations` | Get suggestions | Required |
| `ai_botkit_track_interaction` | Track user action | Required |
| `ai_botkit_export_pdf` | Export conversation | Required |

#### Admin AJAX (wp_ajax_ only)

| Action | Purpose | Capability |
|--------|---------|-----------|
| `ai_botkit_admin_get_templates` | List templates | manage_ai_botkit |
| `ai_botkit_admin_save_template` | Create/update template | manage_ai_botkit |
| `ai_botkit_admin_delete_template` | Delete template | manage_ai_botkit |
| `ai_botkit_admin_apply_template` | Apply to chatbot | manage_ai_botkit |
| `ai_botkit_admin_search_all` | Search all messages | can_search_all_conversations |
| `ai_botkit_admin_bulk_export` | Batch export | manage_ai_botkit |

---

## 6. Database Schema Changes

### 6.1 New Tables

#### ai_botkit_templates

```sql
CREATE TABLE {prefix}ai_botkit_templates (
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
    INDEX idx_is_active (is_active)
);
```

#### ai_botkit_media

```sql
CREATE TABLE {prefix}ai_botkit_media (
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
    INDEX idx_status (status)
);
```

#### ai_botkit_user_interactions

```sql
CREATE TABLE {prefix}ai_botkit_user_interactions (
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
    INDEX idx_chatbot (chatbot_id)
);
```

### 6.2 Table Modifications

#### ai_botkit_messages

```sql
-- Add FULLTEXT index for search
ALTER TABLE {prefix}ai_botkit_messages
ADD FULLTEXT INDEX ft_content (content);
```

#### ai_botkit_conversations

```sql
-- Add archived flag and indexes
ALTER TABLE {prefix}ai_botkit_conversations
ADD COLUMN is_archived TINYINT(1) NOT NULL DEFAULT 0 AFTER guest_ip;

ALTER TABLE {prefix}ai_botkit_conversations
ADD INDEX idx_user_updated (user_id, updated_at DESC);

ALTER TABLE {prefix}ai_botkit_conversations
ADD INDEX idx_archived (is_archived);
```

#### ai_botkit_chatbots

```sql
-- Add template reference
ALTER TABLE {prefix}ai_botkit_chatbots
ADD COLUMN template_id BIGINT(20) UNSIGNED AFTER model_config;

ALTER TABLE {prefix}ai_botkit_chatbots
ADD INDEX idx_template (template_id);
```

---

## 7. Security Requirements

### 7.1 Access Control Matrix

| Resource | Guest | Subscriber | Admin |
|----------|-------|------------|-------|
| View own history | No | Yes | Yes |
| Search own messages | No | Yes | Yes |
| Search all messages | No | No | Yes |
| Upload media | No | Yes | Yes |
| Download own export | No | Yes | Yes |
| Download any export | No | No | Yes |
| Manage templates | No | No | Yes |
| Delete conversations | No | Own only | Any |
| Get recommendations | Yes (session) | Yes | Yes |

### 7.2 Input Validation

| Input Type | Validation |
|------------|-----------|
| Search query | `sanitize_text_field()`, max 200 chars, escape FULLTEXT special chars |
| File upload | MIME whitelist, content scan, size limit, filename sanitization |
| Template JSON | Schema validation, `wp_kses_post()` for text fields |
| Date filters | `DateTime::createFromFormat()` validation |
| IDs | `absint()`, ownership verification |

### 7.3 Output Sanitization

| Output Type | Sanitization |
|-------------|--------------|
| Search results | `esc_html()` for content, `esc_attr()` for attributes |
| Message content | `wp_kses_post()` for rich content |
| File URLs | `esc_url()` |
| JSON responses | `wp_send_json()` with proper encoding |
| PDF content | HTML entity encoding, XSS prevention |

### 7.4 File Security

```
wp-content/uploads/ai-botkit/chat-media/
├── .htaccess (DirectoryIndex disabled, PHP disabled)
├── images/
│   └── {year}/{month}/
├── videos/
│   └── {year}/{month}/
└── files/
    └── {year}/{month}/
```

**.htaccess content:**
```apache
Options -Indexes
<FilesMatch "\.(php|php\d|phtml|pl|py|jsp|asp|sh|cgi)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>
```

### 7.5 Capability Requirements

| Capability | Default Roles | Purpose |
|------------|---------------|---------|
| `view_ai_botkit_history` | subscriber+ | View own chat history |
| `search_ai_botkit_all` | administrator | Search all conversations |
| `export_ai_botkit_all` | administrator | Export any conversation |
| `manage_ai_botkit_templates` | administrator | Template CRUD operations |
| `upload_ai_botkit_media` | subscriber+ | Upload chat attachments |

---

## Appendix A: Pre-built Template Configurations

### FAQ Bot Template

```json
{
  "name": "FAQ Bot",
  "slug": "faq-bot",
  "category": "support",
  "description": "Optimized for answering frequently asked questions with source citations",
  "style": {
    "primaryColor": "#2563eb",
    "position": "bottom-right"
  },
  "messages_template": {
    "greeting": "Hello! I'm here to help answer your questions about {{site_name}}. What would you like to know?",
    "fallback": "I couldn't find information about that in our knowledge base. Would you like me to connect you with a human?",
    "thinking": "Searching our knowledge base..."
  },
  "model_config": {
    "temperature": 0.3,
    "personality": "You are a helpful FAQ assistant. Always cite your sources when providing answers. If you don't know the answer, say so clearly."
  },
  "conversation_starters": [
    {"text": "What can you help me with?", "icon": "help-circle"},
    {"text": "Search the knowledge base", "icon": "search"},
    {"text": "Contact support", "icon": "mail"}
  ]
}
```

### Customer Support Template

```json
{
  "name": "Customer Support",
  "slug": "customer-support",
  "category": "support",
  "description": "Help desk style chatbot with escalation and ticket tracking",
  "style": {
    "primaryColor": "#059669",
    "position": "bottom-right"
  },
  "messages_template": {
    "greeting": "Hi there! I'm your support assistant. How can I help you today?",
    "fallback": "I apologize, but I'm having trouble understanding your issue. Let me connect you with a human agent who can help.",
    "handoff": "I understand this needs personal attention. Let me connect you with our support team."
  },
  "model_config": {
    "temperature": 0.5,
    "personality": "You are an empathetic customer support agent. Always acknowledge the customer's feelings. Collect ticket numbers when mentioned. Offer to escalate complex issues to human agents."
  },
  "conversation_starters": [
    {"text": "I have a problem", "icon": "alert-circle"},
    {"text": "Track my order", "icon": "package"},
    {"text": "Speak to a human", "icon": "user"}
  ]
}
```

---

## Appendix B: Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-01-28 | Documentation Agent | Initial specification |

---

*Phase 2 Functional Specification - AI BotKit Chatbot*
*Generated: 2026-01-28*
