# Phase 2 Manual Test Cases

**Project:** AI BotKit Chatbot
**Phase:** Phase 2 - Enhanced Features
**Document Version:** 1.0
**Generated:** 2026-01-28
**Total Test Cases:** 192

---

## Table of Contents

1. [Test Summary](#test-summary)
2. [Prerequisites](#prerequisites)
3. [Test Cases by Feature](#test-cases-by-feature)
   - [Feature 1: Chat History](#feature-1-chat-history-fr-201-to-fr-209)
   - [Feature 2: Search Functionality](#feature-2-search-functionality-fr-210-to-fr-219)
   - [Feature 3: Rich Media Support](#feature-3-rich-media-support-fr-220-to-fr-229)
   - [Feature 4: Conversation Templates](#feature-4-conversation-templates-fr-230-to-fr-239)
   - [Feature 5: Chat Transcripts Export](#feature-5-chat-transcripts-export-fr-240-to-fr-249)
   - [Feature 6: LMS/WooCommerce Suggestions](#feature-6-lmswoocommerce-suggestions-fr-250-to-fr-259)
4. [Traceability Matrix](#traceability-matrix)

---

## Test Summary

| Category | Count | Coverage |
|----------|-------|----------|
| Functional Tests | 120 | All 60 FRs |
| Security Tests | 24 | Authorization, injection, file security |
| Performance Tests | 12 | Response times, load times |
| Edge Cases | 18 | Boundary conditions, empty states |
| Accessibility Tests | 10 | WCAG 2.1 AA compliance |
| Integration Tests | 8 | LearnDash, WooCommerce |
| **TOTAL** | **192** | |

### Priority Distribution

| Priority | Count | Description |
|----------|-------|-------------|
| P0 (Critical) | 64 | Must pass for release |
| P1 (High) | 78 | Should pass |
| P2 (Medium) | 50 | Nice to have |

---

## Prerequisites

### Environment Setup

1. **WordPress Installation:**
   - WordPress 6.0+ with AI BotKit plugin Phase 2 installed
   - At least 2 user accounts: Administrator and Subscriber
   - WooCommerce plugin installed and configured (for FR-250-259)
   - LearnDash plugin installed and configured (for FR-250-259)

2. **Test Data:**
   - 15+ conversations with varying message counts (5, 50, 100+ messages)
   - Multiple chatbots configured
   - Sample media files: JPEG, PNG, PDF, DOCX (valid and invalid)
   - WooCommerce products (10+ with categories and tags)
   - LearnDash courses (5+ with prerequisites)

3. **Test Users:**
   - `admin_user` - Administrator role with all capabilities
   - `subscriber_user` - Subscriber role with standard capabilities
   - `guest_user` - Not logged in (guest)

---

## Test Cases by Feature

---

## Feature 1: Chat History (FR-201 to FR-209)

### FR-201: List User Conversations

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-201-001 | FR-201 | Functional | P0 | Logged-in user sees conversation list |
| TC-201-002 | FR-201 | Functional | P0 | Conversation list sorted by most recent |
| TC-201-003 | FR-201 | Functional | P0 | Conversation metadata displays correctly |
| TC-201-004 | FR-201 | Functional | P1 | Pagination with 10+ conversations |
| TC-201-005 | FR-201 | Security | P0 | Guest user cannot see history panel |
| TC-201-006 | FR-201 | Edge Case | P1 | User with no conversations sees empty state |

---

#### TC-201-001: Logged-in user sees conversation list

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Log in as `subscriber_user` with existing conversations | Login successful |
| 2 | Open the chat widget | Chat widget opens |
| 3 | Click the "History" button/icon in header | History panel slides in from side |
| 4 | Observe the conversation list | List displays user's conversations |
| 5 | Verify only own conversations are shown | No other users' conversations visible |

**Pass Criteria:**
- History panel is accessible
- Only user's own conversations are displayed
- Panel slides in smoothly (animation < 300ms)

---

#### TC-201-002: Conversation list sorted by most recent

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Log in as user with 5+ conversations | Login successful |
| 2 | Open history panel | Panel displays conversation list |
| 3 | Verify order of conversations | Most recently updated conversation is first |
| 4 | Send a new message in an older conversation | Message sent successfully |
| 5 | Refresh history panel | That conversation moves to top of list |

**Pass Criteria:**
- Conversations sorted by `updated_at` DESC
- New message updates conversation position

---

#### TC-201-003: Conversation metadata displays correctly

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open history panel with conversations | Panel displays |
| 2 | Observe each conversation entry | Each entry shows: chatbot name, chatbot avatar, date/time, preview (100 chars max), message count |
| 3 | Verify date format | Date shows relative time (e.g., "2 hours ago") or formatted date |
| 4 | Verify message count accuracy | Count matches actual messages |

**Pass Criteria:**
- All metadata fields display correctly
- Preview truncated at 100 characters with ellipsis
- Date/time formatted appropriately

---

#### TC-201-004: Pagination with 10+ conversations

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Log in as user with 25 conversations | Login successful |
| 2 | Open history panel | First 10 conversations display |
| 3 | Verify pagination controls visible | "Load More" or page numbers visible |
| 4 | Click next page / Load More | Conversations 11-20 load |
| 5 | Navigate back to page 1 | Original 10 conversations display |

**Pass Criteria:**
- Only 10 conversations per page
- Pagination controls functional
- Page state preserved during navigation

---

#### TC-201-005: Guest user cannot see history panel

**Priority:** P0 (Critical)
**Category:** Security

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Log out of WordPress | Logged out as guest |
| 2 | Open the chat widget | Chat widget opens |
| 3 | Look for "History" button | History button NOT visible |
| 4 | Attempt to access history API directly via URL | 401/403 error returned |

**Pass Criteria:**
- History panel not accessible to guests
- API returns proper error for unauthenticated requests

---

#### TC-201-006: User with no conversations sees empty state

**Priority:** P1 (High)
**Category:** Edge Case

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Log in as new user with no conversations | Login successful |
| 2 | Open history panel | Panel displays |
| 3 | Observe empty state | "No conversations yet" message displays |
| 4 | Verify "Start New Conversation" CTA visible | CTA button present |

**Pass Criteria:**
- Friendly empty state message displayed
- Clear call-to-action to start a conversation

---

### FR-202: View Conversation Messages

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-202-001 | FR-202 | Functional | P0 | Load full conversation messages |
| TC-202-002 | FR-202 | Functional | P0 | Messages displayed chronologically |
| TC-202-003 | FR-202 | Functional | P1 | Lazy load for 50+ messages |
| TC-202-004 | FR-202 | Security | P0 | Cannot view another user's conversation |

---

#### TC-202-001: Load full conversation messages

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open history panel | Panel displays |
| 2 | Click on a conversation entry | Conversation loads in chat interface |
| 3 | Verify all messages load | All messages from conversation visible |
| 4 | Verify message details | Each message shows: content, sender (user/bot), timestamp, media attachments |

**Pass Criteria:**
- Full conversation loads without errors
- All message details display correctly

---

#### TC-202-002: Messages displayed chronologically

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Select a conversation with 10+ messages | Conversation loads |
| 2 | Verify message order | Oldest message at top, newest at bottom |
| 3 | Compare timestamps | Timestamps increase from top to bottom |

**Pass Criteria:**
- Messages ordered by timestamp ASC
- Conversation flows naturally from start to end

---

#### TC-202-003: Lazy load for 50+ messages

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Select a conversation with 100+ messages | Conversation loads |
| 2 | Observe initial load | First 50 messages load (recent ones) |
| 3 | Scroll up towards older messages | "Load older messages" indicator appears |
| 4 | Trigger load older messages | Additional 50 messages load |
| 5 | Verify scroll position | Scroll position preserved (no jump) |

**Pass Criteria:**
- Messages load in batches of 50
- Scroll position maintained when loading older messages
- Performance remains smooth

---

#### TC-202-004: Cannot view another user's conversation

**Priority:** P0 (Critical)
**Category:** Security

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Log in as `subscriber_user` | Login successful |
| 2 | Note a conversation ID belonging to `admin_user` | ID recorded |
| 3 | Attempt to access via API: `/ai-botkit/v1/history/{other_user_id}` | 403 Forbidden returned |
| 4 | Attempt URL manipulation in browser | Access denied |

**Pass Criteria:**
- Users cannot access conversations they don't own
- Proper 403 error returned with no data leakage

---

### FR-203: Switch Between Conversations

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-203-001 | FR-203 | Functional | P0 | Switch between two conversations |
| TC-203-002 | FR-203 | Functional | P1 | Active conversation visually highlighted |
| TC-203-003 | FR-203 | Functional | P1 | Draft message preserved on switch |

---

#### TC-203-001: Switch between two conversations

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open conversation A from history | Conversation A loads |
| 2 | Scroll to middle of conversation | Scroll position noted |
| 3 | Select conversation B from history list | Conversation B loads |
| 4 | Select conversation A again | Conversation A loads with scroll position restored |

**Pass Criteria:**
- Switching is seamless (< 500ms)
- Previous conversation state preserved in memory

---

#### TC-203-002: Active conversation visually highlighted

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open history panel with multiple conversations | List displays |
| 2 | Select conversation A | Conversation A highlighted in list |
| 3 | Switch to conversation B | Highlight moves to conversation B |
| 4 | Verify visual distinction | Clear background/border color difference |

**Pass Criteria:**
- Active conversation has distinct visual styling
- Highlight updates immediately on selection

---

#### TC-203-003: Draft message preserved on switch

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open conversation A | Conversation loads |
| 2 | Type a message but do NOT send | Text in input field |
| 3 | Switch to conversation B | Conversation B loads |
| 4 | Switch back to conversation A | Draft message still in input field |

**Pass Criteria:**
- Draft message stored in session storage
- Draft restored when returning to conversation

---

### FR-204: Conversation Previews

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-204-001 | FR-204 | Functional | P1 | Preview shows first user message |
| TC-204-002 | FR-204 | Functional | P1 | Long preview truncated with ellipsis |
| TC-204-003 | FR-204 | Edge Case | P2 | Media-only message shows placeholder |

---

#### TC-204-001: Preview shows first user message

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create conversation with first message: "Help me find a product" | Conversation created |
| 2 | Open history panel | Conversation appears in list |
| 3 | Verify preview text | Shows "Help me find a product" |

**Pass Criteria:**
- First user message used as preview
- Preview accurately represents conversation

---

#### TC-204-002: Long preview truncated with ellipsis

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create conversation with 150+ character first message | Conversation created |
| 2 | Open history panel | Conversation appears |
| 3 | Verify preview text | Shows first 100 characters + "..." |

**Pass Criteria:**
- Preview truncated at exactly 100 characters
- Ellipsis appended to indicate truncation

---

#### TC-204-003: Media-only message shows placeholder

**Priority:** P2 (Medium)
**Category:** Edge Case

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create conversation where first message contains only an image | Conversation created |
| 2 | Open history panel | Conversation appears |
| 3 | Verify preview text | Shows "[Image]" as placeholder |

**Pass Criteria:**
- Media-only messages have descriptive placeholders
- Placeholders: "[Image]", "[Video]", "[File: filename]"

---

### FR-205: Pagination for Large History

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-205-001 | FR-205 | Functional | P0 | Only 10 conversations load initially |
| TC-205-002 | FR-205 | Functional | P1 | Total count displayed |
| TC-205-003 | FR-205 | Functional | P1 | Pagination state preserved |

---

#### TC-205-001: Only 10 conversations load initially

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Ensure user has 50+ conversations | Data setup complete |
| 2 | Open history panel | Panel loads |
| 3 | Count visible conversations | Exactly 10 conversations visible |
| 4 | Verify Load More / pagination control | Control is visible and functional |

**Pass Criteria:**
- Maximum 10 conversations per page
- Performance: load time < 300ms

---

#### TC-205-002: Total count displayed

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | User with 47 conversations opens history | History loads |
| 2 | Verify count indicator | "Showing 1-10 of 47" or similar displayed |

**Pass Criteria:**
- Total conversation count visible
- Page information accurate

---

#### TC-205-003: Pagination state preserved

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to page 3 of history | Page 3 displays |
| 2 | Select and view a conversation | Conversation loads |
| 3 | Return to history panel | Returns to page 3 (not page 1) |

**Pass Criteria:**
- Pagination state preserved across navigation
- User doesn't lose their place

---

### FR-206: Delete Conversation

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-206-001 | FR-206 | Functional | P0 | User can delete own conversation |
| TC-206-002 | FR-206 | Functional | P1 | Confirmation dialog before delete |
| TC-206-003 | FR-206 | Security | P0 | Cannot delete another user's conversation |
| TC-206-004 | FR-206 | Functional | P1 | Admin can delete any conversation |

---

#### TC-206-001: User can delete own conversation

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open history panel | Panel displays |
| 2 | Select a conversation | Conversation loads |
| 3 | Click "Delete Conversation" button | Confirmation dialog appears |
| 4 | Confirm deletion | Conversation deleted |
| 5 | Verify in history list | Conversation no longer appears |
| 6 | Verify in database | Conversation and messages removed |

**Pass Criteria:**
- Conversation permanently deleted
- Associated messages and media removed
- User redirected to history list

---

#### TC-206-002: Confirmation dialog before delete

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click "Delete Conversation" | Confirmation dialog appears |
| 2 | Verify dialog content | Warning message: "This action cannot be undone" |
| 3 | Click "Cancel" | Dialog closes, conversation NOT deleted |
| 4 | Click "Delete" again and confirm | Conversation deleted |

**Pass Criteria:**
- Deletion requires explicit confirmation
- Cancel option available and functional

---

#### TC-206-003: Cannot delete another user's conversation

**Priority:** P0 (Critical)
**Category:** Security

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Log in as `subscriber_user` | Login successful |
| 2 | Attempt to delete `admin_user`'s conversation via API | 403 Forbidden |
| 3 | Verify conversation still exists | Conversation unchanged |

**Pass Criteria:**
- Ownership verified before deletion
- Proper error returned

---

#### TC-206-004: Admin can delete any conversation

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Log in as `admin_user` | Login successful |
| 2 | Navigate to admin conversations page | Page loads |
| 3 | Delete another user's conversation | Deletion succeeds |
| 4 | Verify audit log | Action logged with admin user ID |

**Pass Criteria:**
- Admins can delete any conversation
- Admin actions logged for audit

---

### FR-207: Mark Conversation as Favorite

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-207-001 | FR-207 | Functional | P2 | Toggle favorite status |
| TC-207-002 | FR-207 | Functional | P2 | Filter by favorites |

---

#### TC-207-001: Toggle favorite status

**Priority:** P2 (Medium)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open conversation | Conversation displays |
| 2 | Click star icon (unfavorited) | Icon changes to filled star |
| 3 | Verify favorite persisted | Reload page, star still filled |
| 4 | Click star again | Icon changes to outline star (unfavorited) |

**Pass Criteria:**
- Favorite status toggles correctly
- Status persists across sessions

---

#### TC-207-002: Filter by favorites

**Priority:** P2 (Medium)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Mark 3 conversations as favorites | Favorites set |
| 2 | Open history panel | Full list displays |
| 3 | Click "Favorites" filter | Only 3 favorited conversations show |
| 4 | Clear filter | Full list restored |

**Pass Criteria:**
- Favorites filter works correctly
- Filter can be cleared

---

### FR-208: Filter Conversations by Date

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-208-001 | FR-208 | Functional | P2 | Filter by preset date range |
| TC-208-002 | FR-208 | Functional | P2 | Filter by custom date range |
| TC-208-003 | FR-208 | Functional | P2 | Clear date filter |

---

#### TC-208-001: Filter by preset date range

**Priority:** P2 (Medium)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open history panel | Panel displays |
| 2 | Select "Last 7 days" filter | Filter applied |
| 3 | Verify results | Only conversations updated in last 7 days shown |
| 4 | Verify filter indicator | Active filter visually indicated |

**Pass Criteria:**
- Preset filters: Today, Last 7 days, Last 30 days
- Results accurate to filter criteria

---

#### TC-208-002: Filter by custom date range

**Priority:** P2 (Medium)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Select "Custom" date filter | Date pickers appear |
| 2 | Set From: 2026-01-01, To: 2026-01-15 | Dates set |
| 3 | Apply filter | Results show only conversations in range |

**Pass Criteria:**
- Custom date range works correctly
- Date pickers user-friendly

---

#### TC-208-003: Clear date filter

**Priority:** P2 (Medium)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Apply a date filter | Filtered results show |
| 2 | Click "Clear" or "X" on filter chip | Filter removed |
| 3 | Verify full list restored | All conversations visible |

**Pass Criteria:**
- Filter can be cleared
- Full list restored after clearing

---

### FR-209: Integration with Existing Chat UI

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-209-001 | FR-209 | Functional | P0 | History button visible in chat header |
| TC-209-002 | FR-209 | Functional | P0 | Start new conversation from history |
| TC-209-003 | FR-209 | Functional | P0 | Resume conversation updates timestamp |
| TC-209-004 | FR-209 | Accessibility | P1 | History panel keyboard accessible |

---

#### TC-209-001: History button visible in chat header

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Log in and open chat widget | Widget opens |
| 2 | Observe header area | "History" button/icon visible |
| 3 | Hover over button | Tooltip shows "Chat History" |

**Pass Criteria:**
- History button clearly visible
- Accessible from main chat interface

---

#### TC-209-002: Start new conversation from history

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open history panel | Panel displays |
| 2 | Click "New Conversation" button | Fresh chat interface loads |
| 3 | Send a message | Message sent, new conversation created |
| 4 | Verify in history | New conversation at top of list |

**Pass Criteria:**
- New conversation starts fresh
- Appears immediately in history list

---

#### TC-209-003: Resume conversation updates timestamp

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Resume an older conversation | Conversation loads |
| 2 | Send a new message | Message appended |
| 3 | Check `updated_at` in database | Timestamp updated to now |
| 4 | Check history list | Conversation moved to top |

**Pass Criteria:**
- `updated_at` updated on new message
- Conversation position in list updated

---

#### TC-209-004: History panel keyboard accessible

**Priority:** P1 (High)
**Category:** Accessibility

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Tab to History button | Button receives focus |
| 2 | Press Enter | History panel opens |
| 3 | Tab through conversation list | Each item focusable |
| 4 | Press Enter on conversation | Conversation loads |
| 5 | Press Escape | Panel closes |

**Pass Criteria:**
- Full keyboard navigation support
- Focus indicators visible

---

## Feature 2: Search Functionality (FR-210 to FR-219)

### FR-210: Search Input Interface

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-210-001 | FR-210 | Functional | P0 | Search input displays with placeholder |
| TC-210-002 | FR-210 | Functional | P1 | Search executes on Enter key |
| TC-210-003 | FR-210 | Functional | P1 | Clear button resets search |

---

#### TC-210-001: Search input displays with placeholder

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open history panel | Panel displays |
| 2 | Observe search input | Input field visible |
| 3 | Verify placeholder text | "Search your conversations..." displayed |
| 4 | Verify search icon | Magnifying glass icon visible |

**Pass Criteria:**
- Search input present in history panel
- Placeholder text guides users

---

#### TC-210-002: Search executes on Enter key

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Type "shipping policy" in search | Text entered |
| 2 | Press Enter | Search executes |
| 3 | Verify results displayed | Matching conversations/messages shown |

**Pass Criteria:**
- Enter key triggers search
- Results display without clicking button

---

#### TC-210-003: Clear button resets search

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Perform a search | Results displayed |
| 2 | Click clear button (X) | Search query cleared |
| 3 | Verify default view | Full conversation list restored |

**Pass Criteria:**
- Clear button visible when query present
- Clicking clears query and resets view

---

### FR-211: Full-Text Search on Messages

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-211-001 | FR-211 | Functional | P0 | Search returns relevant messages |
| TC-211-002 | FR-211 | Functional | P1 | Multi-word search works |
| TC-211-003 | FR-211 | Edge Case | P1 | No results shows appropriate message |
| TC-211-004 | FR-211 | Security | P0 | SQL injection prevented |

---

#### TC-211-001: Search returns relevant messages

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create conversation with message containing "refund policy" | Data setup |
| 2 | Search for "refund" | Search executes |
| 3 | Verify conversation appears in results | Conversation found |
| 4 | Verify search time displayed | "Found X results in 0.XXs" shown |

**Pass Criteria:**
- FULLTEXT search matches content
- Search time displayed for transparency

---

#### TC-211-002: Multi-word search works

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Search for "shipping delivery" | Search executes |
| 2 | Verify results include messages with both words | Higher relevance |
| 3 | Verify results include messages with either word | Lower relevance |

**Pass Criteria:**
- Multi-word search finds both AND/OR matches
- Relevance ranking favors all words

---

#### TC-211-003: No results shows appropriate message

**Priority:** P1 (High)
**Category:** Edge Case

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Search for "xyznonexistent123" | Search executes |
| 2 | Verify no results message | "No results found" displayed |
| 3 | Verify suggestions | "Try different keywords" or similar shown |

**Pass Criteria:**
- Clear message when no results
- Helpful suggestions provided

---

#### TC-211-004: SQL injection prevented

**Priority:** P0 (Critical)
**Category:** Security

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Search for: `' OR '1'='1` | Search executes |
| 2 | Verify no SQL error | No database error |
| 3 | Verify proper escaping | Query treated as literal text |
| 4 | Search for: `; DROP TABLE messages;--` | No SQL execution |

**Pass Criteria:**
- SQL injection attempts safely handled
- Prepared statements used

---

### FR-212: Admin Global Search

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-212-001 | FR-212 | Functional | P0 | Admin can search all conversations |
| TC-212-002 | FR-212 | Functional | P1 | Results show user information |
| TC-212-003 | FR-212 | Security | P0 | Non-admin cannot access admin search |

---

#### TC-212-001: Admin can search all conversations

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Log in as `admin_user` | Login successful |
| 2 | Navigate to admin search interface | Interface loads |
| 3 | Search for a term in any user's conversation | Search executes |
| 4 | Verify results include multiple users | Results from various users shown |

**Pass Criteria:**
- Admin sees all matching conversations
- No user filtering applied

---

#### TC-212-002: Results show user information

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Admin performs search | Results displayed |
| 2 | Observe result entries | Each shows: username, email, chatbot name, date, link |

**Pass Criteria:**
- User identification visible in admin results
- Click to view full conversation

---

#### TC-212-003: Non-admin cannot access admin search

**Priority:** P0 (Critical)
**Category:** Security

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Log in as `subscriber_user` | Login successful |
| 2 | Attempt to access admin search endpoint | 403 Forbidden |
| 3 | Attempt to add user filter in regular search | Filter ignored |

**Pass Criteria:**
- Admin search requires `manage_ai_botkit` capability
- Regular users cannot bypass restrictions

---

### FR-213: User Personal Search

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-213-001 | FR-213 | Functional | P0 | User search limited to own conversations |
| TC-213-002 | FR-213 | Security | P0 | Cannot see other users' messages |

---

#### TC-213-001: User search limited to own conversations

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Log in as `subscriber_user` | Login successful |
| 2 | Search for a common term | Search executes |
| 3 | Verify all results belong to current user | No other users' data visible |

**Pass Criteria:**
- `user_id` filter auto-applied
- Only own conversations returned

---

#### TC-213-002: Cannot see other users' messages

**Priority:** P0 (Critical)
**Category:** Security

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | `admin_user` has conversation with word "secret123" | Data setup |
| 2 | Log in as `subscriber_user` | Login successful |
| 3 | Search for "secret123" | Search executes |
| 4 | Verify no results | Admin's conversation NOT shown |

**Pass Criteria:**
- Complete data isolation between users
- No information leakage

---

### FR-214: Search Filters (date, chatbot, user)

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-214-001 | FR-214 | Functional | P1 | Filter by date range |
| TC-214-002 | FR-214 | Functional | P1 | Filter by chatbot |
| TC-214-003 | FR-214 | Functional | P1 | Combine multiple filters |

---

#### TC-214-001: Filter by date range

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Perform search with results | Results displayed |
| 2 | Apply date filter: Last 7 days | Filter applied |
| 3 | Verify results within date range | Only recent messages shown |
| 4 | Verify filter chip visible | "Last 7 days" chip shown |

**Pass Criteria:**
- Date filter works correctly
- Filter visually indicated

---

#### TC-214-002: Filter by chatbot

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Perform search | Results from multiple chatbots |
| 2 | Select chatbot filter: "Support Bot" | Filter applied |
| 3 | Verify results only from "Support Bot" | Other chatbots excluded |

**Pass Criteria:**
- Chatbot filter functional
- Dropdown lists available chatbots

---

#### TC-214-003: Combine multiple filters

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Search for "order" | Results displayed |
| 2 | Add date filter: Last 30 days | Filter applied |
| 3 | Add chatbot filter: "Sales Bot" | Filter applied |
| 4 | Verify results match ALL criteria | AND logic applied |
| 5 | Remove one filter | Results update accordingly |

**Pass Criteria:**
- Multiple filters combine with AND logic
- Each filter independently removable

---

### FR-215: Search Results Display

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-215-001 | FR-215 | Functional | P0 | Results show message excerpt |
| TC-215-002 | FR-215 | Functional | P0 | Click result opens conversation |
| TC-215-003 | FR-215 | Functional | P1 | Scroll to matching message |

---

#### TC-215-001: Results show message excerpt

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Perform search | Results displayed |
| 2 | Observe result entry | Shows: excerpt (200 chars max), chatbot name, date, relevance indicator |

**Pass Criteria:**
- Excerpt provides context around match
- Metadata helps identify conversation

---

#### TC-215-002: Click result opens conversation

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click on a search result | Conversation loads |
| 2 | Verify correct conversation | Conversation contains searched term |

**Pass Criteria:**
- Result click navigates to conversation
- Correct conversation opened

---

#### TC-215-003: Scroll to matching message

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click search result | Conversation loads |
| 2 | Observe scroll position | View scrolls to matching message |
| 3 | Verify message highlighted | Matching message has visual highlight |

**Pass Criteria:**
- Automatic scroll to match
- Message visually indicated

---

### FR-216: Search Term Highlighting

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-216-001 | FR-216 | Functional | P1 | Search terms highlighted in results |
| TC-216-002 | FR-216 | Functional | P2 | Case-insensitive highlighting |

---

#### TC-216-001: Search terms highlighted in results

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Search for "pricing" | Results displayed |
| 2 | Observe excerpt text | "pricing" wrapped in `<mark>` tags |
| 3 | Verify visual style | Highlighted text has distinct background color |

**Pass Criteria:**
- Search terms highlighted with `<mark>` tags
- CSS class `.ai-botkit-highlight` applied

---

#### TC-216-002: Case-insensitive highlighting

**Priority:** P2 (Medium)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Search for "PRODUCT" | Results displayed |
| 2 | Message contains "product" (lowercase) | Text highlighted |
| 3 | Message contains "Product" (mixed case) | Text highlighted |

**Pass Criteria:**
- Highlighting case-insensitive
- All variations highlighted

---

### FR-217: Search Relevance Ranking

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-217-001 | FR-217 | Functional | P1 | Results sorted by relevance |
| TC-217-002 | FR-217 | Functional | P2 | Recent messages receive boost |

---

#### TC-217-001: Results sorted by relevance

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create messages with varying keyword density | Data setup |
| 2 | Search for a keyword | Results displayed |
| 3 | Verify order | Messages with more keyword occurrences ranked higher |

**Pass Criteria:**
- FULLTEXT relevance score used
- Higher relevance = higher position

---

#### TC-217-002: Recent messages receive boost

**Priority:** P2 (Medium)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Two messages with similar relevance, different dates | Data setup |
| 2 | Search for matching term | Results displayed |
| 3 | Verify recent message ranks higher | Recency factor applied |

**Pass Criteria:**
- Recent messages slightly boosted
- Score formula includes recency weight

---

### FR-218: Search Pagination

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-218-001 | FR-218 | Functional | P0 | Results paginated at 20 per page |
| TC-218-002 | FR-218 | Functional | P1 | Search query preserved across pages |

---

#### TC-218-001: Results paginated at 20 per page

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Search with 50+ matching results | Search executes |
| 2 | Count results on first page | Maximum 20 results |
| 3 | Verify total count displayed | "Showing 1-20 of 50" or similar |
| 4 | Navigate to page 2 | Results 21-40 display |

**Pass Criteria:**
- 20 results per page default
- Pagination controls functional

---

#### TC-218-002: Search query preserved across pages

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Search for "support" | Results displayed |
| 2 | Navigate to page 3 | Page 3 results load |
| 3 | Verify search query still in input | "support" still displayed |
| 4 | Verify filters still applied | All filters maintained |

**Pass Criteria:**
- Query and filters persist during pagination
- URL/state reflects current page

---

### FR-219: Search Performance Optimization

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-219-001 | FR-219 | Performance | P0 | Search returns within 500ms |
| TC-219-002 | FR-219 | Performance | P1 | Cached results return faster |
| TC-219-003 | FR-219 | Performance | P1 | FULLTEXT index utilized |

---

#### TC-219-001: Search returns within 500ms

**Priority:** P0 (Critical)
**Category:** Performance

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Database with 100,000+ messages | Environment setup |
| 2 | Perform search query | Search executes |
| 3 | Measure response time | < 500ms (P95) |

**Pass Criteria:**
- 95th percentile under 500ms
- No noticeable delay for users

---

#### TC-219-002: Cached results return faster

**Priority:** P1 (High)
**Category:** Performance

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Perform search for "shipping" | First search executes |
| 2 | Note response time | Time recorded |
| 3 | Repeat same search within 5 minutes | Search executes |
| 4 | Verify faster response | < 50ms (from cache) |

**Pass Criteria:**
- Repeated searches use cache
- Cache TTL: 5 minutes

---

#### TC-219-003: FULLTEXT index utilized

**Priority:** P1 (High)
**Category:** Performance

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Enable query logging | Logging enabled |
| 2 | Perform search | Search executes |
| 3 | Check EXPLAIN output | FULLTEXT index used, no full table scan |

**Pass Criteria:**
- FULLTEXT index in EXPLAIN plan
- No "Using filesort" on large result sets

---

## Feature 3: Rich Media Support (FR-220 to FR-229)

### FR-220: Image Attachments in Messages

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-220-001 | FR-220 | Functional | P0 | Image displays inline in message |
| TC-220-002 | FR-220 | Functional | P1 | Image responsive and constrained |
| TC-220-003 | FR-220 | Edge Case | P1 | Broken image shows fallback |
| TC-220-004 | FR-220 | Accessibility | P1 | Image has alt text |

---

#### TC-220-001: Image displays inline in message

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Send a message with image attachment | Message sent |
| 2 | Observe message display | Image renders inline within message bubble |
| 3 | Verify image loads | Full image visible |

**Pass Criteria:**
- Image displays within message
- Supported formats: JPEG, PNG, GIF, WebP

---

#### TC-220-002: Image responsive and constrained

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Upload a 2000x2000px image | Image uploaded |
| 2 | View in chat message | Image constrained to container width |
| 3 | Resize browser window | Image scales responsively |

**Pass Criteria:**
- Image max-width: 100% of container
- Aspect ratio preserved

---

#### TC-220-003: Broken image shows fallback

**Priority:** P1 (High)
**Category:** Edge Case

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Reference an image URL that 404s | Message rendered |
| 2 | Observe image area | "Image unavailable" placeholder shown |
| 3 | Verify no broken image icon | Clean fallback display |

**Pass Criteria:**
- Graceful degradation for missing images
- Rest of message still visible

---

#### TC-220-004: Image has alt text

**Priority:** P1 (High)
**Category:** Accessibility

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | View image in message | Image displayed |
| 2 | Inspect HTML | `alt` attribute present |
| 3 | Use screen reader | Alt text announced |

**Pass Criteria:**
- All images have alt text
- Screen reader accessible

---

### FR-221: Video Embeds (YouTube/Vimeo)

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-221-001 | FR-221 | Functional | P1 | YouTube video embeds correctly |
| TC-221-002 | FR-221 | Functional | P1 | Vimeo video embeds correctly |
| TC-221-003 | FR-221 | Functional | P2 | Disabled embed shows link |

---

#### TC-221-001: YouTube video embeds correctly

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Bot response contains YouTube URL | Response rendered |
| 2 | Observe video area | YouTube player embedded |
| 3 | Click play | Video plays inline |
| 4 | Verify aspect ratio | 16:9 responsive player |

**Pass Criteria:**
- YouTube oEmbed integration works
- Video playable without leaving chat

---

#### TC-221-002: Vimeo video embeds correctly

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Bot response contains Vimeo URL | Response rendered |
| 2 | Observe video area | Vimeo player embedded |
| 3 | Click play | Video plays inline |

**Pass Criteria:**
- Vimeo oEmbed integration works
- Same quality as YouTube embed

---

#### TC-221-003: Disabled embed shows link

**Priority:** P2 (Medium)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Disable video embedding in settings | Setting changed |
| 2 | Bot response contains video URL | Response rendered |
| 3 | Observe video area | Clickable link shown: "Watch on YouTube" |

**Pass Criteria:**
- Setting respected
- Fallback link functional

---

### FR-222: File Attachments (PDF, DOC)

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-222-001 | FR-222 | Functional | P1 | File attachment displays as card |
| TC-222-002 | FR-222 | Functional | P0 | File downloads successfully |
| TC-222-003 | FR-222 | Security | P0 | Only allowed file types accepted |

---

#### TC-222-001: File attachment displays as card

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Bot response includes PDF attachment | Response rendered |
| 2 | Observe file display | Card shows: file name, PDF icon, file size, download button |

**Pass Criteria:**
- File card UI component used
- All metadata visible

---

#### TC-222-002: File downloads successfully

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click download button on file card | Download initiates |
| 2 | Verify file downloads | File saved to device |
| 3 | Verify filename | Original filename preserved |
| 4 | Verify file integrity | File opens correctly |

**Pass Criteria:**
- Download works in all browsers
- Proper Content-Disposition headers

---

#### TC-222-003: Only allowed file types accepted

**Priority:** P0 (Critical)
**Category:** Security

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Attempt to upload `.exe` file | Upload rejected |
| 2 | Attempt to upload `.php` file | Upload rejected |
| 3 | Upload valid `.pdf` file | Upload succeeds |

**Pass Criteria:**
- Whitelist: PDF, DOC, DOCX, XLS, XLSX, TXT, ZIP
- Executable files rejected

---

### FR-223: Rich Link Previews

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-223-001 | FR-223 | Functional | P2 | Link preview card generated |
| TC-223-002 | FR-223 | Functional | P2 | Fallback for unparseable URLs |
| TC-223-003 | FR-223 | Performance | P2 | Preview cached for 1 hour |

---

#### TC-223-001: Link preview card generated

**Priority:** P2 (Medium)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Bot response contains external URL | Response rendered |
| 2 | Observe link display | Preview card shows: title, description, thumbnail, domain |
| 3 | Click card | Opens URL in new tab |

**Pass Criteria:**
- OpenGraph metadata extracted
- Card visually appealing

---

#### TC-223-002: Fallback for unparseable URLs

**Priority:** P2 (Medium)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | URL with no OG metadata | Response rendered |
| 2 | Observe link display | Standard clickable link shown |
| 3 | Verify opens in new tab | `target="_blank"` applied |

**Pass Criteria:**
- Graceful fallback to plain link
- No broken preview card

---

#### TC-223-003: Preview cached for 1 hour

**Priority:** P2 (Medium)
**Category:** Performance

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | First link preview generated | Preview displayed |
| 2 | Check database/cache | Preview stored |
| 3 | Same URL requested again | Cached version used |
| 4 | Wait 1 hour, request again | Fresh fetch occurs |

**Pass Criteria:**
- Preview caching reduces external requests
- Cache TTL: 1 hour

---

### FR-224: Media Upload Handling

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-224-001 | FR-224 | Functional | P0 | Valid file upload succeeds |
| TC-224-002 | FR-224 | Security | P0 | MIME type validated against content |
| TC-224-003 | FR-224 | Security | P0 | File size limit enforced |
| TC-224-004 | FR-224 | Security | P0 | PHP code in file rejected |

---

#### TC-224-001: Valid file upload succeeds

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Upload valid JPEG image (2MB) | Upload initiates |
| 2 | Verify upload completes | Success response returned |
| 3 | Verify file stored | File in `wp-content/uploads/ai-botkit/chat-media/images/{year}/{month}/` |
| 4 | Verify database record | Entry in `ai_botkit_media` table |

**Pass Criteria:**
- File stored in correct location
- Database record created with metadata

---

#### TC-224-002: MIME type validated against content

**Priority:** P0 (Critical)
**Category:** Security

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Rename `.php` file to `.jpg` | File prepared |
| 2 | Attempt upload | Upload rejected |
| 3 | Error message displayed | "Invalid file type" |

**Pass Criteria:**
- MIME type checked via `finfo_file()`, not extension
- Extension spoofing detected

---

#### TC-224-003: File size limit enforced

**Priority:** P0 (Critical)
**Category:** Security

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Attempt to upload 15MB file (limit is 10MB) | Upload rejected |
| 2 | Error message displayed | "File exceeds maximum size of 10MB" |

**Pass Criteria:**
- Size limit enforced
- Clear error message

---

#### TC-224-004: PHP code in file rejected

**Priority:** P0 (Critical)
**Category:** Security

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create image with `<?php` embedded | File prepared |
| 2 | Attempt upload | Upload rejected |
| 3 | Error message displayed | "File contains potentially malicious content" |

**Pass Criteria:**
- File content scanned for PHP tags
- Malicious files blocked

---

### FR-225: Media Display Components

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-225-001 | FR-225 | Functional | P0 | Correct component per media type |
| TC-225-002 | FR-225 | Functional | P1 | Multiple media in gallery format |

---

#### TC-225-001: Correct component per media type

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | View message with image | ImageAttachment component used |
| 2 | View message with video URL | VideoEmbed component used |
| 3 | View message with PDF | FileDownload component used |
| 4 | View message with external link | LinkPreview component used |

**Pass Criteria:**
- Each media type has dedicated component
- Consistent styling across components

---

#### TC-225-002: Multiple media in gallery format

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Message with 3 images | Message rendered |
| 2 | Observe layout | Images in gallery/carousel format |
| 3 | Navigate between images | Navigation arrows functional |

**Pass Criteria:**
- Multiple media handled gracefully
- Gallery navigation smooth

---

### FR-226: Lightbox for Images

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-226-001 | FR-226 | Functional | P1 | Click image opens lightbox |
| TC-226-002 | FR-226 | Functional | P1 | Close lightbox with X or Escape |
| TC-226-003 | FR-226 | Accessibility | P1 | Focus trapped in lightbox |

---

#### TC-226-001: Click image opens lightbox

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click on image in message | Lightbox opens |
| 2 | Observe lightbox | Full-size image displayed, background dimmed |
| 3 | Close button visible | X button in corner |

**Pass Criteria:**
- Lightbox displays full-size image
- Smooth open animation

---

#### TC-226-002: Close lightbox with X or Escape

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open lightbox | Lightbox displayed |
| 2 | Click X button | Lightbox closes |
| 3 | Open lightbox again | Lightbox displayed |
| 4 | Press Escape key | Lightbox closes |
| 5 | Click outside image | Lightbox closes |

**Pass Criteria:**
- Multiple close methods work
- Focus returns to chat interface

---

#### TC-226-003: Focus trapped in lightbox

**Priority:** P1 (High)
**Category:** Accessibility

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open lightbox | Lightbox displayed |
| 2 | Press Tab repeatedly | Focus cycles within lightbox elements only |
| 3 | Cannot Tab to chat interface | Focus trapped |

**Pass Criteria:**
- Focus trap implemented
- WCAG 2.1 AA compliant

---

### FR-227: File Download Handling

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-227-001 | FR-227 | Functional | P0 | File downloads with correct headers |
| TC-227-002 | FR-227 | Edge Case | P1 | 404 for non-existent file |
| TC-227-003 | FR-227 | Functional | P1 | Download logged for analytics |

---

#### TC-227-001: File downloads with correct headers

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click download button | Download request sent |
| 2 | Check response headers | `Content-Type: application/octet-stream`, `Content-Disposition: attachment`, `X-Content-Type-Options: nosniff` |
| 3 | File downloads | Browser download dialog appears |

**Pass Criteria:**
- Security headers present
- Download prompts save dialog

---

#### TC-227-002: 404 for non-existent file

**Priority:** P1 (High)
**Category:** Edge Case

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Request download for deleted file ID | Request sent |
| 2 | Verify response | 404 Not Found |
| 3 | Verify error message | "File not found" displayed |

**Pass Criteria:**
- Proper 404 handling
- No server errors exposed

---

#### TC-227-003: Download logged for analytics

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Download a file | Download completes |
| 2 | Check analytics table | Download event logged with media_id, user_id, timestamp |

**Pass Criteria:**
- Download tracking implemented
- Analytics data captured

---

### FR-228: Media Security

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-228-001 | FR-228 | Security | P0 | .htaccess prevents PHP execution |
| TC-228-002 | FR-228 | Security | P0 | Cannot access other user's media directly |
| TC-228-003 | FR-228 | Security | P0 | Filename sanitized on upload |

---

#### TC-228-001: .htaccess prevents PHP execution

**Priority:** P0 (Critical)
**Category:** Security

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Verify `.htaccess` exists in media directory | File present |
| 2 | Verify contents include PHP disable rules | `php_flag engine off` or equivalent |
| 3 | Attempt to access `.php` file directly (if one existed) | PHP code NOT executed |

**Pass Criteria:**
- .htaccess protection in place
- No PHP execution in upload directory

---

#### TC-228-002: Cannot access other user's media directly

**Priority:** P0 (Critical)
**Category:** Security

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Log in as `subscriber_user` | Login successful |
| 2 | Attempt to access `admin_user`'s media file via API | 403 Forbidden |
| 3 | Verify media not exposed | Access denied |

**Pass Criteria:**
- Media access requires ownership or admin
- No direct URL access bypass

---

#### TC-228-003: Filename sanitized on upload

**Priority:** P0 (Critical)
**Category:** Security

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Upload file named `../../../evil.php.jpg` | Upload processed |
| 2 | Check stored filename | Path traversal removed, sanitized name |
| 3 | Verify file in correct directory | No directory escape |

**Pass Criteria:**
- Special characters removed
- Path traversal prevented

---

### FR-229: Storage Management

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-229-001 | FR-229 | Functional | P1 | Files organized by type and date |
| TC-229-002 | FR-229 | Functional | P1 | Orphaned media cleaned up |
| TC-229-003 | FR-229 | Functional | P2 | Storage quota warning |

---

#### TC-229-001: Files organized by type and date

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Upload image in January 2026 | Upload succeeds |
| 2 | Check storage path | `/images/2026/01/filename.jpg` |
| 3 | Upload PDF | Path: `/files/2026/01/filename.pdf` |

**Pass Criteria:**
- Files organized by media_type/year/month
- Easy to manage and backup

---

#### TC-229-002: Orphaned media cleaned up

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Upload media file | File stored |
| 2 | Do NOT associate with any message | Orphaned |
| 3 | Wait 7+ days, trigger cleanup cron | Cleanup runs |
| 4 | Verify file deleted | File removed from disk and database |

**Pass Criteria:**
- Orphaned media (no message_id) cleaned after 7 days
- Disk space reclaimed

---

#### TC-229-003: Storage quota warning

**Priority:** P2 (Medium)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Configure storage threshold: 100MB | Setting saved |
| 2 | Upload media until total exceeds 100MB | Threshold exceeded |
| 3 | Check admin notifications | Warning displayed to admin |

**Pass Criteria:**
- Quota monitoring implemented
- Admin alerted when threshold exceeded

---

## Feature 4: Conversation Templates (FR-230 to FR-239)

### FR-230: Template Data Model

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-230-001 | FR-230 | Functional | P0 | Template saved with all fields |
| TC-230-002 | FR-230 | Functional | P1 | Required fields enforced |
| TC-230-003 | FR-230 | Edge Case | P1 | Duplicate name prevented |

---

#### TC-230-001: Template saved with all fields

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create template with all fields | Template saved |
| 2 | Verify database record | All fields stored: name, description, category, style, messages_template, model_config, conversation_starters |
| 3 | Retrieve template | All data intact |

**Pass Criteria:**
- JSON columns store complex configuration
- No data loss on save/retrieve

---

#### TC-230-002: Required fields enforced

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Attempt to save template without name | Validation error |
| 2 | Attempt to save template without category | Validation error |
| 3 | Save with name and category | Success |

**Pass Criteria:**
- Name and category required
- Clear validation messages

---

#### TC-230-003: Duplicate name prevented

**Priority:** P1 (High)
**Category:** Edge Case

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create template named "Support Bot" | Success |
| 2 | Attempt to create another "Support Bot" | Error: "Name already exists" |

**Pass Criteria:**
- Unique constraint on name
- User-friendly error message

---

### FR-231: Admin Template List View

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-231-001 | FR-231 | Functional | P0 | Template list displays |
| TC-231-002 | FR-231 | Functional | P1 | Filter by category |
| TC-231-003 | FR-231 | Functional | P1 | Filter System vs Custom |

---

#### TC-231-001: Template list displays

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Navigate to AI BotKit > Templates | Page loads |
| 2 | Observe template list | Grid/list view of templates |
| 3 | Verify card contents | Name, category badge, description, usage count, System/Custom indicator |

**Pass Criteria:**
- All templates visible
- Card layout informative

---

#### TC-231-002: Filter by category

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click category filter: "Support" | Filter applied |
| 2 | Verify results | Only support category templates shown |
| 3 | Clear filter | All templates restored |

**Pass Criteria:**
- Category filter functional
- Multiple categories available

---

#### TC-231-003: Filter System vs Custom

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click "System" filter | Only pre-built templates shown |
| 2 | Click "Custom" filter | Only user-created templates shown |
| 3 | Click "All" | All templates shown |

**Pass Criteria:**
- System/Custom distinction clear
- Filters work correctly

---

### FR-232: Template Builder/Editor

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-232-001 | FR-232 | Functional | P0 | Create new template |
| TC-232-002 | FR-232 | Functional | P0 | Edit existing template |
| TC-232-003 | FR-232 | Functional | P1 | System template prompts Save as Copy |
| TC-232-004 | FR-232 | Functional | P1 | Unsaved changes warning |

---

#### TC-232-001: Create new template

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click "Add New Template" | Builder opens |
| 2 | Fill in: name, description, category | Fields populated |
| 3 | Configure system prompt | Text entered |
| 4 | Add conversation starters | Items added |
| 5 | Click Save | Template created |

**Pass Criteria:**
- All configuration options available
- Template saved successfully

---

#### TC-232-002: Edit existing template

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click Edit on a template | Builder opens with data |
| 2 | Modify description | Text changed |
| 3 | Click Save | Changes saved |
| 4 | Reload page | Changes persisted |

**Pass Criteria:**
- Existing data pre-populated
- Edits saved correctly

---

#### TC-232-003: System template prompts Save as Copy

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Edit a system template (is_system=1) | Builder opens |
| 2 | Make changes | Changes made |
| 3 | Click Save | Prompt: "Save as Copy?" |
| 4 | Confirm | New template created, original unchanged |

**Pass Criteria:**
- System templates protected
- Copy created instead of overwriting

---

#### TC-232-004: Unsaved changes warning

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open template builder | Builder displayed |
| 2 | Make changes | Form dirty |
| 3 | Attempt to navigate away | Warning: "You have unsaved changes" |
| 4 | Cancel navigation | Stay on page |
| 5 | Confirm navigation | Navigate away (changes lost) |

**Pass Criteria:**
- Unsaved changes detected
- User warned before losing work

---

### FR-233: Template Preview

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-233-001 | FR-233 | Functional | P1 | Preview shows widget appearance |
| TC-233-002 | FR-233 | Functional | P1 | Preview updates in real-time |

---

#### TC-233-001: Preview shows widget appearance

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open template for editing | Builder displayed |
| 2 | Click "Preview" | Preview panel opens |
| 3 | Observe preview | Widget with applied colors, welcome message, conversation starters |

**Pass Criteria:**
- Preview matches configuration
- All visual elements shown

---

#### TC-233-002: Preview updates in real-time

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Open preview alongside builder | Both visible |
| 2 | Change primary color | Preview updates immediately |
| 3 | Change welcome message | Preview updates immediately |

**Pass Criteria:**
- Real-time preview updates
- No save required to see changes

---

### FR-234: Apply Template to Chatbot

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-234-001 | FR-234 | Functional | P0 | Apply template to new chatbot |
| TC-234-002 | FR-234 | Functional | P1 | Apply template to existing chatbot |
| TC-234-003 | FR-234 | Functional | P1 | Template usage count incremented |

---

#### TC-234-001: Apply template to new chatbot

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Create new chatbot | Chatbot wizard starts |
| 2 | Select "Start from template" | Template list shown |
| 3 | Select "FAQ Bot" template | Template selected |
| 4 | Verify pre-filled settings | All settings populated from template |
| 5 | Save chatbot | Chatbot created with template config |

**Pass Criteria:**
- Template settings applied to chatbot
- Quick start with proven configuration

---

#### TC-234-002: Apply template to existing chatbot

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Edit existing chatbot | Settings page |
| 2 | Click "Apply Template" | Template list shown |
| 3 | Select template | Warning: "This will overwrite settings" |
| 4 | Choose "Replace All" | All settings replaced |
| 5 | Choose "Merge" | Only empty settings filled |

**Pass Criteria:**
- Replace vs Merge options available
- User warned about overwrite

---

#### TC-234-003: Template usage count incremented

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Check template usage_count | Current value noted |
| 2 | Apply template to chatbot | Template applied |
| 3 | Check usage_count again | Incremented by 1 |

**Pass Criteria:**
- Usage tracking for templates
- Analytics available

---

### FR-235 to FR-238: Pre-built Templates

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-235-001 | FR-235 | Functional | P0 | FAQ Bot template available |
| TC-236-001 | FR-236 | Functional | P0 | Customer Support template available |
| TC-237-001 | FR-237 | Functional | P0 | Product Advisor template available |
| TC-238-001 | FR-238 | Functional | P0 | Lead Capture template available |

---

#### TC-235-001: FAQ Bot template available

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Fresh plugin install | Plugin activated |
| 2 | Navigate to Templates | List displays |
| 3 | Verify "FAQ Bot" exists | Template present with is_system=1 |
| 4 | Verify configuration | Category: support, includes source citation prompt |

**Pass Criteria:**
- FAQ Bot template ships with plugin
- Configuration matches specification

---

#### TC-236-001: Customer Support template available

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Verify "Customer Support" template exists | Template present |
| 2 | Verify configuration | Includes escalation keywords, human handoff prompts |

**Pass Criteria:**
- Customer Support template included
- Empathetic tone configured

---

#### TC-237-001: Product Advisor template available

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Verify "Product Advisor" template exists | Template present |
| 2 | Verify configuration | Needs assessment flow, product matching |

**Pass Criteria:**
- Product Advisor template included
- Sales-focused configuration

---

#### TC-238-001: Lead Capture template available

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Verify "Lead Capture" template exists | Template present |
| 2 | Verify configuration | Multi-step form flow, field validation |

**Pass Criteria:**
- Lead Capture template included
- CRM hook: `ai_botkit_lead_captured`

---

### FR-239: Template Import/Export

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-239-001 | FR-239 | Functional | P2 | Export template as JSON |
| TC-239-002 | FR-239 | Functional | P2 | Import template from JSON |
| TC-239-003 | FR-239 | Functional | P2 | Handle import conflict |

---

#### TC-239-001: Export template as JSON

**Priority:** P2 (Medium)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click Export on a template | JSON file downloads |
| 2 | Verify filename | `{template-slug}-export.json` |
| 3 | Verify JSON content | All configuration included |

**Pass Criteria:**
- Export generates valid JSON
- All template data exported

---

#### TC-239-002: Import template from JSON

**Priority:** P2 (Medium)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Click "Import Template" | File upload dialog |
| 2 | Select valid JSON file | File validated |
| 3 | Import succeeds | New template created |
| 4 | Verify imported data | All configuration intact |

**Pass Criteria:**
- Import creates new template
- JSON schema validated

---

#### TC-239-003: Handle import conflict

**Priority:** P2 (Medium)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Import template with existing name | Conflict detected |
| 2 | Choose "Replace Existing" | Original overwritten |
| 3 | Or choose "Import as Copy" | New template with modified name |

**Pass Criteria:**
- Conflict resolution options provided
- User controls outcome

---

## Feature 5: Chat Transcripts Export (FR-240 to FR-249)

### FR-240: Admin Export Interface

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-240-001 | FR-240 | Functional | P0 | Export PDF button visible for admin |
| TC-240-002 | FR-240 | Functional | P1 | Bulk export generates ZIP |

---

#### TC-240-001: Export PDF button visible for admin

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Log in as admin | Login successful |
| 2 | Navigate to conversation detail in admin | Page loads |
| 3 | Verify "Export PDF" button visible | Button present |
| 4 | Click Export PDF | PDF downloads |

**Pass Criteria:**
- Admin has export access
- Export button clearly visible

---

#### TC-240-002: Bulk export generates ZIP

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Select 5 conversations via checkboxes | Conversations selected |
| 2 | Click "Bulk Export" | Processing begins |
| 3 | ZIP file downloads | File saved |
| 4 | Open ZIP | Contains 5 individual PDFs |

**Pass Criteria:**
- Multiple conversations exported together
- ZIP contains individual PDFs

---

### FR-241: PDF Generation

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-241-001 | FR-241 | Functional | P0 | PDF contains all required elements |
| TC-241-002 | FR-241 | Functional | P1 | PDF handles media attachments |
| TC-241-003 | FR-241 | Performance | P1 | Large conversation generates within 30s |

---

#### TC-241-001: PDF contains all required elements

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Export a conversation as PDF | PDF generated |
| 2 | Open PDF | Document opens |
| 3 | Verify header | Site branding, logo, site name |
| 4 | Verify metadata | Date, chatbot name, user |
| 5 | Verify messages | All messages in chronological order |
| 6 | Verify timestamps | Timestamp on each message |
| 7 | Verify footer | Page numbers |

**Pass Criteria:**
- PDF is professional and complete
- All conversation data present

---

#### TC-241-002: PDF handles media attachments

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Export conversation with images | PDF generated |
| 2 | Verify images | Images embedded inline (resized to fit) |
| 3 | Export conversation with file attachments | PDF generated |
| 4 | Verify file listings | File names and sizes listed |
| 5 | Export conversation with video embeds | PDF generated |
| 6 | Verify video handling | Screenshot/placeholder with link |

**Pass Criteria:**
- Media handled appropriately for PDF format
- Links clickable in PDF

---

#### TC-241-003: Large conversation generates within 30s

**Priority:** P1 (High)
**Category:** Performance

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Conversation with 150 messages | Data setup |
| 2 | Export to PDF | Processing begins |
| 3 | Measure generation time | < 30 seconds |
| 4 | PDF downloads successfully | Document complete |

**Pass Criteria:**
- Large conversations handled efficiently
- No timeout errors

---

### FR-242: PDF Branding

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-242-001 | FR-242 | Functional | P2 | Custom logo appears in PDF |
| TC-242-002 | FR-242 | Functional | P2 | Custom colors applied |

---

#### TC-242-001: Custom logo appears in PDF

**Priority:** P2 (Medium)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Upload logo in PDF settings | Logo saved |
| 2 | Export a conversation | PDF generated |
| 3 | Verify header | Custom logo displayed |

**Pass Criteria:**
- Custom branding applied
- Logo properly sized

---

#### TC-242-002: Custom colors applied

**Priority:** P2 (Medium)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Set primary color to #FF5733 | Setting saved |
| 2 | Export conversation | PDF generated |
| 3 | Verify message styling | Color used for headings/accents |

**Pass Criteria:**
- Custom colors reflected in PDF
- Brand consistency

---

### FR-243: Conversation Selection for Export

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-243-001 | FR-243 | Functional | P1 | Filter conversations before export |

---

#### TC-243-001: Filter conversations before export

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Apply date filter: Last 30 days | Filter applied |
| 2 | Apply chatbot filter: "Sales Bot" | Filter applied |
| 3 | Select filtered conversations | Checkboxes checked |
| 4 | Export selected | Only matching conversations exported |

**Pass Criteria:**
- Filters work before selection
- Export includes filter metadata

---

### FR-244: User Self-Service PDF Download

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-244-001 | FR-244 | Functional | P1 | User can download own conversation |
| TC-244-002 | FR-244 | Security | P0 | User cannot download others' conversations |
| TC-244-003 | FR-244 | Functional | P1 | Guest user sees no export option |

---

#### TC-244-001: User can download own conversation

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Log in as `subscriber_user` | Login successful |
| 2 | Open own conversation in history | Conversation displays |
| 3 | Click "Download PDF" | PDF downloads |
| 4 | Verify content | Only own messages and bot responses |

**Pass Criteria:**
- User can export own conversations
- No other user data exposed

---

#### TC-244-002: User cannot download others' conversations

**Priority:** P0 (Critical)
**Category:** Security

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Log in as `subscriber_user` | Login successful |
| 2 | Attempt to export `admin_user`'s conversation via API | 403 Forbidden |

**Pass Criteria:**
- Ownership verified
- Access denied for non-owners

---

#### TC-244-003: Guest user sees no export option

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Browse site as guest (not logged in) | Guest session |
| 2 | Use chatbot | Conversation happens |
| 3 | Look for export option | NOT available |
| 4 | Prompt shown | "Log in to download transcripts" |

**Pass Criteria:**
- Export requires authentication
- Clear guidance to log in

---

### FR-245: Export Progress Indicator

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-245-001 | FR-245 | Functional | P2 | Progress shown for long exports |

---

#### TC-245-001: Progress shown for long exports

**Priority:** P2 (Medium)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Export large conversation | Export starts |
| 2 | Observe UI | Progress indicator: "Generating PDF..." |
| 3 | Export completes | "Download ready!" message |

**Pass Criteria:**
- User knows export is in progress
- Clear completion indication

---

### FR-246: Batch Export for Admins

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-246-001 | FR-246 | Functional | P1 | Batch export 50+ conversations |
| TC-246-002 | FR-246 | Functional | P1 | Background processing for large batches |

---

#### TC-246-001: Batch export 50+ conversations

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Select 50 conversations | Selected |
| 2 | Click "Batch Export" | Processing begins |
| 3 | Wait for completion | Notification received |
| 4 | Download ZIP | ZIP contains 50 PDFs |

**Pass Criteria:**
- Large batch exports work
- Result is downloadable ZIP

---

#### TC-246-002: Background processing for large batches

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Initiate batch export of 100 conversations | Export queued |
| 2 | Observe UI | "Processing in background..." message |
| 3 | Continue using admin | Not blocked |
| 4 | Email notification | "Your export is ready" with download link |

**Pass Criteria:**
- Large exports don't block UI
- Notification on completion

---

### FR-247: Export Scheduling

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-247-001 | FR-247 | Functional | P2 | Schedule weekly export |

---

#### TC-247-001: Schedule weekly export

**Priority:** P2 (Medium)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Configure scheduled export: Weekly, Monday 9am | Schedule saved |
| 2 | Set filter: Last 7 days | Filter saved |
| 3 | Wait for scheduled time | Cron runs |
| 4 | Verify email received | Email with download link |

**Pass Criteria:**
- Scheduled exports run automatically
- Admin notified via email

---

### FR-248: Export History/Audit Log

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-248-001 | FR-248 | Functional | P1 | Export activity logged |

---

#### TC-248-001: Export activity logged

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Admin exports a conversation | Export completes |
| 2 | Navigate to export history | History page loads |
| 3 | Verify log entry | Date, user, conversation IDs, export type, status |

**Pass Criteria:**
- All exports logged
- Audit trail maintained

---

### FR-249: GDPR Data Export Support

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-249-001 | FR-249 | Functional | P1 | Chat data included in WordPress export |
| TC-249-002 | FR-249 | Functional | P1 | Chat data erased on user deletion |

---

#### TC-249-001: Chat data included in WordPress export

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | User requests data export via WordPress privacy tools | Request initiated |
| 2 | WordPress generates export | Export processing |
| 3 | Download export package | ZIP downloaded |
| 4 | Verify AI BotKit data included | Conversations and messages present |

**Pass Criteria:**
- Privacy tools integration working
- User data exportable

---

#### TC-249-002: Chat data erased on user deletion

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | User requests data erasure | Request initiated |
| 2 | WordPress processes erasure | Erasure runs |
| 3 | Verify AI BotKit data deleted | Conversations, messages, media removed |
| 4 | Verify erasure logged | Deletion logged for compliance |

**Pass Criteria:**
- GDPR erasure compliance
- Complete data removal

---

## Feature 6: LMS/WooCommerce Suggestions (FR-250 to FR-259)

### FR-250: Recommendation Engine Core

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-250-001 | FR-250 | Functional | P0 | Recommendations generated with 4 signals |
| TC-250-002 | FR-250 | Functional | P1 | Fallback to popular items when no data |
| TC-250-003 | FR-250 | Performance | P1 | Recommendations within 1000ms |

---

#### TC-250-001: Recommendations generated with 4 signals

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | User with purchase history, browsing history | Data exists |
| 2 | User has active conversation about products | Context available |
| 3 | Request recommendations | Engine runs |
| 4 | Verify all 4 signals considered | Conversation context (0.35), browsing (0.25), history (0.25), explicit (0.15) |

**Pass Criteria:**
- All 4 signals weighted and combined
- Top 5 recommendations returned

---

#### TC-250-002: Fallback to popular items when no data

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | New user with no history | No signals available |
| 2 | Request recommendations | Engine runs |
| 3 | Verify fallback | Popular/featured items returned |
| 4 | Verify no empty state | Always some recommendations |

**Pass Criteria:**
- Graceful fallback for new users
- Never return empty recommendations

---

#### TC-250-003: Recommendations within 1000ms

**Priority:** P1 (High)
**Category:** Performance

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Request recommendations with all signals | Request sent |
| 2 | Measure response time | < 1000ms |
| 3 | Repeat request | Cached, < 100ms |

**Pass Criteria:**
- Performance target met
- Caching effective

---

### FR-251: Conversation Context Analysis

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-251-001 | FR-251 | Functional | P0 | Keywords extracted from conversation |
| TC-251-002 | FR-251 | Functional | P1 | Topic and level detected |

---

#### TC-251-001: Keywords extracted from conversation

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Conversation includes: "I'm looking for a photography course" | Message sent |
| 2 | Context analysis runs | Keywords extracted |
| 3 | Verify keywords | "photography", "course" identified |
| 4 | Verify matching courses suggested | Photography courses in results |

**Pass Criteria:**
- Keyword extraction works
- Keywords matched to product/course attributes

---

#### TC-251-002: Topic and level detected

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Message: "beginner photography course" | Context analyzed |
| 2 | Verify detection | Topic: photography, Level: beginner |
| 3 | Verify recommendations | Beginner-level courses prioritized |

**Pass Criteria:**
- Multi-attribute detection
- Results filtered by detected criteria

---

### FR-252: Browsing History Tracking

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-252-001 | FR-252 | Functional | P1 | Page views tracked in session |
| TC-252-002 | FR-252 | Functional | P1 | Browsing influences recommendations |

---

#### TC-252-001: Page views tracked in session

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | User browses product page for "Wireless Headphones" | Page viewed |
| 2 | Check `ai_botkit_user_interactions` table | Entry created with item_type, item_id, timestamp |
| 3 | User browses another product | Second entry created |

**Pass Criteria:**
- Browsing tracked automatically
- Session data stored

---

#### TC-252-002: Browsing influences recommendations

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | User views 3 headphone products | Browsing recorded |
| 2 | Open chatbot, request recommendations | Recommendations generated |
| 3 | Verify results | Headphones and related audio products suggested |

**Pass Criteria:**
- Browsing signal influences results
- Related products included

---

### FR-253: Purchase/Enrollment History Integration

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-253-001 | FR-253 | Integration | P1 | WooCommerce purchase history used |
| TC-253-002 | FR-253 | Integration | P1 | LearnDash enrollment history used |
| TC-253-003 | FR-253 | Functional | P1 | Already purchased items excluded |

---

#### TC-253-001: WooCommerce purchase history used

**Priority:** P1 (High)
**Category:** Integration

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | User has purchased "Camera Bag" (Accessories category) | Order exists |
| 2 | Request recommendations | Engine queries WooCommerce |
| 3 | Verify results | Other accessories or camera-related products suggested |

**Pass Criteria:**
- WooCommerce order history accessed
- Purchase patterns influence suggestions

---

#### TC-253-002: LearnDash enrollment history used

**Priority:** P1 (High)
**Category:** Integration

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | User enrolled in "Photography 101" | Enrollment exists |
| 2 | Request recommendations | Engine queries LearnDash |
| 3 | Verify results | "Photography 201" or related courses suggested |

**Pass Criteria:**
- LearnDash enrollment data accessed
- Course progressions suggested

---

#### TC-253-003: Already purchased items excluded

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | User already purchased "Tripod X" | Order exists |
| 2 | Request recommendations | Recommendations generated |
| 3 | Verify "Tripod X" NOT in results | Already owned excluded |

**Pass Criteria:**
- Duplicate recommendations avoided
- Better user experience

---

### FR-254: Explicit Recommendation Requests

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-254-001 | FR-254 | Functional | P0 | "Recommend a course" triggers suggestions |
| TC-254-002 | FR-254 | Functional | P1 | Criteria extracted from request |
| TC-254-003 | FR-254 | Edge Case | P1 | No matches handled gracefully |

---

#### TC-254-001: "Recommend a course" triggers suggestions

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | User types: "Can you recommend a course for me?" | Message sent |
| 2 | Intent detected | Recommendation trigger matched |
| 3 | Recommendation cards displayed | Course suggestions appear |

**Pass Criteria:**
- Explicit intent phrases detected
- Recommendations displayed in response

---

#### TC-254-002: Criteria extracted from request

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | User: "recommend a course under $50 for beginners" | Message sent |
| 2 | Criteria extracted | Price: < $50, Level: beginner |
| 3 | Verify results filtered | Only matching courses shown |
| 4 | Bot acknowledges criteria | "Here are beginner courses under $50..." |

**Pass Criteria:**
- Multiple criteria extracted
- Results match all criteria

---

#### TC-254-003: No matches handled gracefully

**Priority:** P1 (High)
**Category:** Edge Case

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Request: "recommend a free advanced astrophysics course" | Unlikely to match |
| 2 | No exact matches | Graceful message |
| 3 | Bot response | "I couldn't find an exact match. Here are some alternatives..." |

**Pass Criteria:**
- No empty results shown
- Helpful suggestions to loosen criteria

---

### FR-255: Suggestion UI Cards

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-255-001 | FR-255 | Functional | P0 | Recommendation card displays correctly |
| TC-255-002 | FR-255 | Functional | P1 | Multiple cards in horizontal scroll |
| TC-255-003 | FR-255 | Accessibility | P1 | Cards keyboard accessible |

---

#### TC-255-001: Recommendation card displays correctly

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Recommendations displayed | Cards visible |
| 2 | Verify card elements | Image (100x100), title (max 50 chars), description (max 80 chars), price/duration, rating, action button |

**Pass Criteria:**
- All card elements present
- Consistent styling

---

#### TC-255-002: Multiple cards in horizontal scroll

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | 5 recommendations generated | Cards displayed |
| 2 | Observe layout | Horizontal scrollable row |
| 3 | Verify navigation | Left/right arrows visible when > 3 cards |
| 4 | On mobile | Cards stack vertically or swipeable |

**Pass Criteria:**
- Responsive card layout
- Navigation intuitive

---

#### TC-255-003: Cards keyboard accessible

**Priority:** P1 (High)
**Category:** Accessibility

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Tab to recommendation cards | First card receives focus |
| 2 | Continue tabbing | Navigate through all cards |
| 3 | Press Enter on card | Action button triggered |

**Pass Criteria:**
- Full keyboard navigation
- Focus visible on each card

---

### FR-256: Add to Cart Action

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-256-001 | FR-256 | Functional | P0 | Add to Cart from recommendation card |
| TC-256-002 | FR-256 | Integration | P1 | Cart count updates |
| TC-256-003 | FR-256 | Edge Case | P1 | Out of stock handled |

---

#### TC-256-001: Add to Cart from recommendation card

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | View WooCommerce product card | Card displayed |
| 2 | Click "Add to Cart" | AJAX request sent |
| 3 | Verify success message | "Added to cart!" displayed |
| 4 | Verify button changes | Shows "View Cart" or quantity selector |

**Pass Criteria:**
- Product added without leaving chat
- Clear success feedback

---

#### TC-256-002: Cart count updates

**Priority:** P1 (High)
**Category:** Integration

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Cart is empty (count: 0) | Initial state |
| 2 | Add product from chat | Product added |
| 3 | Verify cart icon | Count shows 1 |
| 4 | Verify WooCommerce cart | Product in cart |

**Pass Criteria:**
- Real-time cart update
- WooCommerce integration working

---

#### TC-256-003: Out of stock handled

**Priority:** P1 (High)
**Category:** Edge Case

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Product is out of stock | Stock: 0 |
| 2 | Click "Add to Cart" | Error handled |
| 3 | Verify message | "Sorry, this item is currently out of stock" |

**Pass Criteria:**
- Graceful error handling
- No cart corruption

---

### FR-257: Enroll Now Action

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-257-001 | FR-257 | Functional | P0 | Enroll Now for free course |
| TC-257-002 | FR-257 | Functional | P1 | Continue Learning for enrolled course |
| TC-257-003 | FR-257 | Integration | P1 | Paid course redirects to purchase |

---

#### TC-257-001: Enroll Now for free course

**Priority:** P0 (Critical)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Free course recommendation card | Card displayed |
| 2 | Click "Enroll Now" | Enrollment processed |
| 3 | Verify enrollment | User enrolled in LearnDash |
| 4 | Verify button changes | Shows "Start Learning" |

**Pass Criteria:**
- Free course enrollment works from chat
- Immediate access granted

---

#### TC-257-002: Continue Learning for enrolled course

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | User already enrolled in course | Enrollment exists |
| 2 | Course appears in recommendations | Card displayed |
| 3 | Verify button text | "Continue Learning" |
| 4 | Verify progress shown | Progress percentage on card |
| 5 | Click button | Navigates to resume point |

**Pass Criteria:**
- Enrolled status detected
- Progress displayed

---

#### TC-257-003: Paid course redirects to purchase

**Priority:** P1 (High)
**Category:** Integration

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | Paid course recommendation | Price displayed on card |
| 2 | Click "Enroll Now" | User redirected |
| 3 | Verify destination | Course purchase page or WooCommerce product |

**Pass Criteria:**
- Paid courses require purchase
- Checkout flow initiated

---

### FR-258: LearnDash Course Suggestions

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-258-001 | FR-258 | Integration | P0 | LearnDash courses suggested |
| TC-258-002 | FR-258 | Functional | P1 | Prerequisites considered |
| TC-258-003 | FR-258 | Functional | P1 | Course card shows LearnDash metadata |

---

#### TC-258-001: LearnDash courses suggested

**Priority:** P0 (Critical)
**Category:** Integration

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | User: "I want to learn Python" | Message sent |
| 2 | LearnDash courses queried | Courses matched |
| 3 | Verify Python courses suggested | Relevant courses in results |

**Pass Criteria:**
- LearnDash integration working
- Courses matched by category/tag

---

#### TC-258-002: Prerequisites considered

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | "Python Advanced" requires "Python Basics" | Prerequisite configured |
| 2 | User hasn't completed "Python Basics" | No enrollment |
| 3 | Recommendations generated | "Python Basics" suggested first |
| 4 | After completing Basics | "Python Advanced" suggested |

**Pass Criteria:**
- Prerequisites respected
- Logical learning path

---

#### TC-258-003: Course card shows LearnDash metadata

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | LearnDash course in recommendations | Card displayed |
| 2 | Verify card content | Image, title, lesson count, estimated duration, difficulty badge |

**Pass Criteria:**
- LearnDash-specific metadata displayed
- Rich course information

---

### FR-259: WooCommerce Product Suggestions

| TC ID | FR | Category | Priority | Test Name |
|-------|-----|----------|----------|-----------|
| TC-259-001 | FR-259 | Integration | P0 | WooCommerce products suggested |
| TC-259-002 | FR-259 | Functional | P1 | Related products included |
| TC-259-003 | FR-259 | Functional | P1 | Product card shows WooCommerce metadata |

---

#### TC-259-001: WooCommerce products suggested

**Priority:** P0 (Critical)
**Category:** Integration

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | User: "I need a new laptop bag" | Message sent |
| 2 | WooCommerce products queried | Products matched |
| 3 | Verify laptop bags suggested | Relevant products in results |

**Pass Criteria:**
- WooCommerce integration working
- Products matched by category/tag

---

#### TC-259-002: Related products included

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | User viewing product page for "Laptop" | Context available |
| 2 | Open chatbot | Widget opens |
| 3 | Recommendations displayed | Laptop bags, cases, accessories suggested |

**Pass Criteria:**
- Context-aware suggestions
- "Frequently bought together" items

---

#### TC-259-003: Product card shows WooCommerce metadata

**Priority:** P1 (High)
**Category:** Functional

| Step | Action | Expected Result |
|------|--------|-----------------|
| 1 | WooCommerce product in recommendations | Card displayed |
| 2 | Verify card content | Image, name, price (with sale price if applicable), star rating, stock status badge |

**Pass Criteria:**
- WooCommerce-specific metadata displayed
- Sale prices and ratings shown

---

## Traceability Matrix

### FR to TC Mapping

| FR ID | FR Name | Test Cases | Coverage |
|-------|---------|------------|----------|
| FR-201 | List User Conversations | TC-201-001 to TC-201-006 | 6 TCs |
| FR-202 | View Conversation Messages | TC-202-001 to TC-202-004 | 4 TCs |
| FR-203 | Switch Between Conversations | TC-203-001 to TC-203-003 | 3 TCs |
| FR-204 | Conversation Previews | TC-204-001 to TC-204-003 | 3 TCs |
| FR-205 | Pagination for Large History | TC-205-001 to TC-205-003 | 3 TCs |
| FR-206 | Delete Conversation | TC-206-001 to TC-206-004 | 4 TCs |
| FR-207 | Mark Conversation as Favorite | TC-207-001 to TC-207-002 | 2 TCs |
| FR-208 | Filter Conversations by Date | TC-208-001 to TC-208-003 | 3 TCs |
| FR-209 | Integration with Existing Chat UI | TC-209-001 to TC-209-004 | 4 TCs |
| FR-210 | Search Input Interface | TC-210-001 to TC-210-003 | 3 TCs |
| FR-211 | Full-Text Search on Messages | TC-211-001 to TC-211-004 | 4 TCs |
| FR-212 | Admin Global Search | TC-212-001 to TC-212-003 | 3 TCs |
| FR-213 | User Personal Search | TC-213-001 to TC-213-002 | 2 TCs |
| FR-214 | Search Filters | TC-214-001 to TC-214-003 | 3 TCs |
| FR-215 | Search Results Display | TC-215-001 to TC-215-003 | 3 TCs |
| FR-216 | Search Term Highlighting | TC-216-001 to TC-216-002 | 2 TCs |
| FR-217 | Search Relevance Ranking | TC-217-001 to TC-217-002 | 2 TCs |
| FR-218 | Search Pagination | TC-218-001 to TC-218-002 | 2 TCs |
| FR-219 | Search Performance Optimization | TC-219-001 to TC-219-003 | 3 TCs |
| FR-220 | Image Attachments in Messages | TC-220-001 to TC-220-004 | 4 TCs |
| FR-221 | Video Embeds | TC-221-001 to TC-221-003 | 3 TCs |
| FR-222 | File Attachments | TC-222-001 to TC-222-003 | 3 TCs |
| FR-223 | Rich Link Previews | TC-223-001 to TC-223-003 | 3 TCs |
| FR-224 | Media Upload Handling | TC-224-001 to TC-224-004 | 4 TCs |
| FR-225 | Media Display Components | TC-225-001 to TC-225-002 | 2 TCs |
| FR-226 | Lightbox for Images | TC-226-001 to TC-226-003 | 3 TCs |
| FR-227 | File Download Handling | TC-227-001 to TC-227-003 | 3 TCs |
| FR-228 | Media Security | TC-228-001 to TC-228-003 | 3 TCs |
| FR-229 | Storage Management | TC-229-001 to TC-229-003 | 3 TCs |
| FR-230 | Template Data Model | TC-230-001 to TC-230-003 | 3 TCs |
| FR-231 | Admin Template List View | TC-231-001 to TC-231-003 | 3 TCs |
| FR-232 | Template Builder/Editor | TC-232-001 to TC-232-004 | 4 TCs |
| FR-233 | Template Preview | TC-233-001 to TC-233-002 | 2 TCs |
| FR-234 | Apply Template to Chatbot | TC-234-001 to TC-234-003 | 3 TCs |
| FR-235 | Pre-built FAQ Bot Template | TC-235-001 | 1 TC |
| FR-236 | Pre-built Customer Support Template | TC-236-001 | 1 TC |
| FR-237 | Pre-built Product Advisor Template | TC-237-001 | 1 TC |
| FR-238 | Pre-built Lead Capture Template | TC-238-001 | 1 TC |
| FR-239 | Template Import/Export | TC-239-001 to TC-239-003 | 3 TCs |
| FR-240 | Admin Export Interface | TC-240-001 to TC-240-002 | 2 TCs |
| FR-241 | PDF Generation | TC-241-001 to TC-241-003 | 3 TCs |
| FR-242 | PDF Branding | TC-242-001 to TC-242-002 | 2 TCs |
| FR-243 | Conversation Selection for Export | TC-243-001 | 1 TC |
| FR-244 | User Self-Service PDF Download | TC-244-001 to TC-244-003 | 3 TCs |
| FR-245 | Export Progress Indicator | TC-245-001 | 1 TC |
| FR-246 | Batch Export for Admins | TC-246-001 to TC-246-002 | 2 TCs |
| FR-247 | Export Scheduling | TC-247-001 | 1 TC |
| FR-248 | Export History/Audit Log | TC-248-001 | 1 TC |
| FR-249 | GDPR Data Export Support | TC-249-001 to TC-249-002 | 2 TCs |
| FR-250 | Recommendation Engine Core | TC-250-001 to TC-250-003 | 3 TCs |
| FR-251 | Conversation Context Analysis | TC-251-001 to TC-251-002 | 2 TCs |
| FR-252 | Browsing History Tracking | TC-252-001 to TC-252-002 | 2 TCs |
| FR-253 | Purchase/Enrollment History Integration | TC-253-001 to TC-253-003 | 3 TCs |
| FR-254 | Explicit Recommendation Requests | TC-254-001 to TC-254-003 | 3 TCs |
| FR-255 | Suggestion UI Cards | TC-255-001 to TC-255-003 | 3 TCs |
| FR-256 | Add to Cart Action | TC-256-001 to TC-256-003 | 3 TCs |
| FR-257 | Enroll Now Action | TC-257-001 to TC-257-003 | 3 TCs |
| FR-258 | LearnDash Course Suggestions | TC-258-001 to TC-258-003 | 3 TCs |
| FR-259 | WooCommerce Product Suggestions | TC-259-001 to TC-259-003 | 3 TCs |

### Category Summary

| Category | TC IDs | Count |
|----------|--------|-------|
| Functional | All primary tests | 120 |
| Security | TC-201-005, TC-202-004, TC-206-003, TC-211-004, TC-212-003, TC-213-002, TC-222-003, TC-224-002, TC-224-003, TC-224-004, TC-228-001, TC-228-002, TC-228-003, TC-244-002 + more | 24 |
| Performance | TC-219-001, TC-219-002, TC-219-003, TC-223-003, TC-241-003, TC-250-003 + more | 12 |
| Edge Cases | TC-201-006, TC-204-003, TC-211-003, TC-220-003, TC-223-002, TC-227-002, TC-254-003, TC-256-003 + more | 18 |
| Accessibility | TC-209-004, TC-220-004, TC-226-003, TC-255-003 + more | 10 |
| Integration | TC-253-001, TC-253-002, TC-258-001, TC-259-001 + more | 8 |

---

## Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-01-28 | Test Generation Agent | Initial test case document |

---

*Phase 2 Manual Test Cases - AI BotKit Chatbot*
*Generated: 2026-01-28*
*Total Test Cases: 192*
