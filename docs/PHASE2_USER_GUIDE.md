# KnowVault Phase 2 - User Guide

> **Version:** 2.0.0
> **Last Updated:** 2026-01-29
> **Audience:** End Users and Site Administrators

This guide covers the six new features introduced in KnowVault version 2.0.0.

---

## Table of Contents

1. [Chat History](#1-chat-history)
2. [Search Functionality](#2-search-functionality)
3. [Rich Media Support](#3-rich-media-support)
4. [Conversation Templates](#4-conversation-templates)
5. [Chat Transcripts Export](#5-chat-transcripts-export)
6. [Product and Course Suggestions](#6-product-and-course-suggestions)
7. [Frequently Asked Questions](#7-frequently-asked-questions)

---

## 1. Chat History

Access and manage your previous conversations with the chatbot.

### Who Can Use This Feature

- **Logged-in users only** - You must be signed into your WordPress account
- Guests can chat but cannot access history

### Accessing Chat History

1. Open the chat widget by clicking the chat bubble
2. Look for the **History** icon (clock symbol) in the chat header
3. Click to open the history panel

[Screenshot placeholder: Chat widget header with History icon highlighted]

### Viewing Your Conversations

The history panel displays your conversations with:

- **Chatbot name and avatar** - Which bot you spoke with
- **Date/time** - When you last interacted
- **Preview** - First 100 characters of your first message
- **Message count** - Total messages in the conversation

[Screenshot placeholder: History panel showing conversation list]

### Resuming a Conversation

1. Click on any conversation in the list
2. All previous messages load in the chat window
3. Continue typing to add new messages
4. The conversation updates with new timestamps

### Switching Between Conversations

- Click any conversation to switch to it
- Your current conversation is preserved
- A visual indicator shows which conversation is active
- Draft messages are saved when switching

### Managing Conversations

**Delete a Conversation:**
1. Hover over the conversation
2. Click the trash icon
3. Confirm deletion in the dialog
4. Note: This is permanent and cannot be undone

**Mark as Favorite:**
1. Click the star icon on any conversation
2. Starred conversations appear in the "Favorites" filter
3. Click again to remove from favorites

**Filter by Date:**
1. Use the date filter dropdown
2. Options: Today, Last 7 days, Last 30 days, Custom range
3. Clear filter to see all conversations

### Pagination

If you have many conversations:
- Conversations load 10 at a time
- Use "Load More" button or scroll to load additional pages
- Page numbers show your current position

---

## 2. Search Functionality

Find specific information across your chat history.

### Using the Search Box

1. Open the history panel
2. Type your search query in the search box
3. Press Enter or wait for automatic search (300ms delay)
4. Results appear below with highlighted matches

[Screenshot placeholder: Search input with query and results]

### Search Tips

**Effective Searches:**
- Use specific keywords: "reset password" instead of "password"
- Include key terms from the answer you're looking for
- Try different word forms: "shipping" vs "ship"

**Boolean Operators:**
- **AND**: Find messages with all terms (default)
- **OR**: Find messages with any term
- **NOT**: Exclude terms

Examples:
- `password reset` - Messages with both words
- `password OR username` - Messages with either word
- `account NOT delete` - Account messages excluding delete

### Search Results

Each result shows:
- **Message content** with search terms highlighted
- **Conversation info** - Which chatbot, date/time
- **Sender** - Whether it was your message or the bot's
- **Relevance score** - How well it matches your query

### Search Filters

Narrow your results using filters:

| Filter | Options | Description |
|--------|---------|-------------|
| Date Range | Today, 7 days, 30 days, Custom | Limit to time period |
| Chatbot | All or specific bot | Filter by chatbot |
| Message Type | All, User, Bot | Filter by sender |

### Jumping to Results

1. Click on any search result
2. The full conversation opens
3. The message is highlighted and scrolled into view
4. Continue the conversation or return to search

### Search for Administrators

Administrators have additional capabilities:

- Search across **all user conversations**
- Filter by specific users
- Export search results
- Access via **KnowVault > Conversations** in admin

[Screenshot placeholder: Admin search interface]

---

## 3. Rich Media Support

Send and receive images, videos, files, and links in your chats.

### Uploading Files

**Drag and Drop:**
1. Drag a file from your computer
2. Drop it onto the chat window
3. File uploads automatically

**Click to Upload:**
1. Click the attachment icon (paperclip) in the message input
2. Select a file from your computer
3. Click Open to upload

[Screenshot placeholder: Attachment button and upload interface]

### Supported File Types

| Type | Formats | Max Size |
|------|---------|----------|
| Images | JPEG, PNG, GIF, WebP | 10 MB |
| Videos | MP4, WebM | 10 MB |
| Documents | PDF, TXT | 10 MB |

### Viewing Media

**Images:**
- Thumbnails display inline in the chat
- Click to open in lightbox view
- Zoom and pan available in lightbox

**Videos:**
- Video player embedded in chat
- Play, pause, and volume controls
- Click to expand

**Documents:**
- File icon with name displayed
- Click to download

### Sharing Links

When you paste a URL in your message:

1. The system detects the link
2. A preview card appears showing:
   - Page title
   - Description
   - Preview image
   - Site name
3. Click the preview to open the link

**Supported Video Embeds:**
- YouTube videos embed directly
- Vimeo videos embed directly
- Other video links show as link previews

[Screenshot placeholder: Link preview card example]

### Media in Chat History

- All media is saved with your conversation
- Media appears when you resume a conversation
- Search includes media descriptions
- Export includes media references

---

## 4. Conversation Templates

*This feature is primarily for administrators*

Templates let site admins create pre-configured chatbot setups for different purposes.

### Pre-Built Templates

KnowVault includes four ready-to-use templates:

**FAQ Bot**
- Best for: Knowledge base and documentation sites
- Features: Direct answers, source citations, helpfulness feedback
- Tone: Professional, informative

**Customer Support**
- Best for: Help desks and support teams
- Features: Ticket references, escalation prompts, human handoff
- Tone: Helpful, empathetic

**Product Advisor**
- Best for: E-commerce and retail sites
- Features: Needs assessment, product matching, comparison tables
- Tone: Consultative, sales-oriented

**Lead Capture**
- Best for: Marketing and sales pages
- Features: Multi-step forms, field validation, CRM integration
- Tone: Engaging, action-oriented

### Using Templates (Administrators)

**Apply a Template to an Existing Chatbot:**
1. Go to **KnowVault > Templates**
2. Browse available templates
3. Click **Apply to Chatbot** on your chosen template
4. Select the target chatbot
5. Choose merge (combine settings) or replace (overwrite)
6. Click Apply

**Create a Template from a Chatbot:**
1. Go to **KnowVault > My Bots**
2. Click the menu on your configured chatbot
3. Select **Save as Template**
4. Enter a name and description
5. Click Save

**Import/Export Templates:**
- Export templates as JSON files for backup
- Import templates from JSON files
- Share templates between sites

[Screenshot placeholder: Template management interface]

---

## 5. Chat Transcripts Export

Download your conversations as PDF documents.

### Exporting Your Conversations

1. Open the chat widget and go to History
2. Select the conversation you want to export
3. Click the **Export** button (download icon)
4. Choose your options:
   - Include timestamps: Yes/No
   - Include branding: Yes/No
   - Paper size: Letter or A4
5. Click **Download PDF**

[Screenshot placeholder: Export options dialog]

### What's Included in the PDF

- Site logo (if branding enabled)
- Conversation date and chatbot name
- All messages in chronological order
- Clear distinction between your messages and bot responses
- Timestamps on each message (if enabled)
- Page numbers

### Export Permissions

| User Type | Can Export |
|-----------|------------|
| Regular Users | Own conversations only |
| Administrators | Any conversation |

### Bulk Export (Administrators)

Administrators can export multiple conversations:

1. Go to **KnowVault > Conversations**
2. Select multiple conversations using checkboxes
3. Choose **Bulk Actions > Export PDF**
4. Downloads a ZIP file with all PDFs

---

## 6. Product and Course Suggestions

Get personalized recommendations while chatting.

### How Recommendations Work

The chatbot analyzes multiple signals to suggest relevant products and courses:

| Signal | Description |
|--------|-------------|
| **Conversation Context** | What you're asking about right now |
| **Browsing History** | Pages and products you've viewed recently |
| **Purchase History** | What you've bought before |
| **Your Requests** | When you directly ask for recommendations |

### Viewing Recommendations

Suggestions appear as cards within the chat:

- **Product Cards** (WooCommerce): Image, title, price, Add to Cart button
- **Course Cards** (LearnDash): Image, title, Enroll Now button

[Screenshot placeholder: Recommendation cards in chat]

### Interacting with Recommendations

- Click the product/course image or title to view details
- Click **Add to Cart** to add a product directly
- Click **Enroll Now** to start course enrollment
- Scroll horizontally if multiple recommendations are shown

### Privacy Note

Your interaction data is used only to improve recommendations:
- Stored locally on this website
- Not shared with third parties
- You can request deletion under GDPR

---

## 7. Frequently Asked Questions

### Chat History

**Q: Why don't I see the History button?**
A: You need to be logged in to access chat history. Guest visitors cannot access history.

**Q: How long are conversations kept?**
A: Conversations are kept indefinitely unless you or an administrator deletes them.

**Q: Can I recover a deleted conversation?**
A: No, deletion is permanent. Export important conversations before deleting.

**Q: Why can't I see my old conversations?**
A: Conversations from before you logged in are saved but linked to the guest session. Contact an administrator if you need to recover them.

### Search

**Q: Why aren't my searches finding anything?**
A: Try:
- Using different keywords
- Checking your spelling
- Removing filters to broaden the search
- Using simpler, single words first

**Q: Can I search for messages sent by the bot?**
A: Yes, search works on both your messages and bot responses. Use the Message Type filter to narrow down.

**Q: How current are search results?**
A: Search indexes update in real-time as new messages are added.

### Media

**Q: Why can't I upload my file?**
A: Check:
- File size (max 10 MB)
- File type (only JPEG, PNG, GIF, WebP, MP4, WebM, PDF, TXT)
- Your login status (must be logged in)

**Q: Where are my uploaded files stored?**
A: Files are stored securely in the WordPress uploads folder on this website's server.

**Q: Can the bot see my uploaded images?**
A: Currently, the bot receives image files but AI image understanding depends on the AI model being used.

### Templates

**Q: Can I create my own templates?**
A: Template creation is an administrator feature. Contact your site admin to request a custom template.

**Q: Will applying a template delete my chatbot settings?**
A: When using "merge" mode, your existing settings are preserved and template settings are added. "Replace" mode overwrites your settings.

### Export

**Q: Why is my PDF taking a long time?**
A: Large conversations with many messages take longer to generate. Please wait for the download to complete.

**Q: Can I export to formats other than PDF?**
A: Currently, only PDF export is supported.

**Q: Why does my PDF look different from the chat?**
A: PDF formatting is optimized for print and may differ slightly from the web interface.

### Recommendations

**Q: Why aren't recommendations showing?**
A: Recommendations require:
- Being logged in
- WooCommerce or LearnDash to be active
- The feature to be enabled by your administrator

**Q: How can I get better recommendations?**
A: The system learns from:
- Your browsing behavior
- Purchase history
- Conversation topics
The more you interact, the better recommendations become.

**Q: Can I disable recommendations?**
A: Contact your site administrator to request recommendation opt-out.

---

## Getting Help

If you experience issues with any Phase 2 features:

1. Check this FAQ section first
2. Clear your browser cache and try again
3. Log out and log back in
4. Contact your site administrator
5. Report persistent issues to support

---

*Phase 2 User Guide*
*Last Updated: 2026-01-29*
