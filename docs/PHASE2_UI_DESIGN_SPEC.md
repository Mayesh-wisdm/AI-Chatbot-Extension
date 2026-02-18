# Phase 2 UI/UX Design Specification

**Document Version:** 1.0
**Created:** 2026-01-28
**Status:** Draft
**Target:** AI BotKit Chatbot - Phase 2 Features

---

## Table of Contents

1. [Design System Foundation](#1-design-system-foundation)
2. [Chat History UI](#2-chat-history-ui)
3. [Search UI](#3-search-ui)
4. [Rich Media UI](#4-rich-media-ui)
5. [Conversation Templates Admin UI](#5-conversation-templates-admin-ui)
6. [Export UI](#6-export-ui)
7. [Product/Course Suggestions UI](#7-productcourse-suggestions-ui)
8. [Accessibility Requirements](#8-accessibility-requirements)
9. [Responsive Design Guidelines](#9-responsive-design-guidelines)
10. [Implementation Notes](#10-implementation-notes)

---

## 1. Design System Foundation

### 1.1 Existing UI Patterns (Maintain Consistency)

Based on analysis of existing CSS, the following design tokens are established:

#### Colors

| Token | Hex Value | Usage |
|-------|-----------|-------|
| `--primary` | `#1E3A8A` | Primary buttons, active states, links |
| `--primary-hover` | `#1E40AF` | Button hover states |
| `--primary-light` | `#E6F2FF` | Active sidebar item background |
| `--secondary-bg` | `#f9fafb` | Page backgrounds |
| `--card-bg` | `#ffffff` | Card backgrounds |
| `--border` | `#e5e7eb` | Border color |
| `--border-active` | `#c7d2fe` | Hover/focus border |
| `--text-primary` | `#1d2327` | Headings, primary text |
| `--text-secondary` | `#6b7280` | Secondary text, descriptions |
| `--text-muted` | `#71717A` | Footer text, metadata |
| `--success` | `#00BFA6` | Success states, active badges |
| `--success-bg` | `#DAF9DF` | Success badge background |
| `--warning` | `#DF8C2B` | Warning states |
| `--warning-bg` | `#DF8C2B1A` | Warning badge background |
| `--danger` | `#DE554B` | Error states, delete actions |
| `--danger-bg` | `#DE554B1A` | Danger badge background |
| `--assistant-bg` | `#f0f0f0` | Assistant message bubble |
| `--user-bg` | `#f0f0f0` | User message bubble |

#### Typography

| Element | Font Size | Weight | Line Height |
|---------|-----------|--------|-------------|
| Page Title | `1.5rem` (24px) | 700 | 1.2 |
| Card Title | `1.25rem` (20px) | 600 | 1.4 |
| Body Text | `0.875rem` (14px) | 400 | 1.5 |
| Small Text | `0.75rem` (12px) | 400 | 1.4 |
| Button Text | `0.875rem` (14px) | 500 | 1 |

#### Spacing Scale

```
4px   (0.25rem) - xs
8px   (0.5rem)  - sm
12px  (0.75rem) - md
16px  (1rem)    - base
24px  (1.5rem)  - lg
32px  (2rem)    - xl
48px  (3rem)    - 2xl
```

#### Border Radius

| Element | Radius |
|---------|--------|
| Buttons | `0.375rem` (6px) |
| Cards | `0.5rem` (8px) |
| Input fields | `0.5rem` (8px) |
| Badges | `49px` (pill) |
| Message bubbles | `20px` |
| Avatars | `50%` (circle) |
| Widget | `1rem` (16px) |

#### Shadows

```css
--shadow-sm: 0 1px 4px rgba(0, 0, 0, 0.03);
--shadow-md: 0 2px 8px rgba(0, 0, 0, 0.03);
--shadow-lg: 0 10px 40px rgba(0, 0, 0, 0.2);
--shadow-input: 2px 2px 10px 0px rgba(0, 0, 0, 0.15);
```

#### Transitions

```css
--transition-fast: 0.2s ease;
--transition-normal: 0.3s ease;
--transition-smooth: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
```

### 1.2 Component Patterns

#### Buttons

```
Primary:   bg-primary, text-white, rounded-md, py-2 px-4
Secondary: bg-gray-50, border-gray-300, text-black, rounded-md
Danger:    bg-red-500, text-white, rounded-md
Icon:      p-2, rounded-md, hover:bg-gray-100
```

#### Cards

```
Container: bg-white, border border-gray-200, rounded-lg, p-6, shadow-sm
Header:    flex justify-between items-center, mb-4
Body:      flex flex-col gap-4
Footer:    flex gap-2, pt-4, border-t (optional)
```

#### Form Fields

```
Input:  border border-gray-300, rounded-lg, px-3.5 py-2, shadow-input
        focus:border-primary, focus:ring-1 focus:ring-primary
Label:  text-sm font-medium text-gray-700, mb-1
Help:   text-xs text-gray-500, mt-1
Error:  text-xs text-red-500, mt-1
```

---

## 2. Chat History UI

### 2.1 Overview

**Purpose:** Allow logged-in users to view and resume previous conversations with the chatbot.

**Access:** Logged-in WordPress users only (per Phase 0.5 clarification).

### 2.2 History Panel Design

#### Location Options

**Option A: Slide-out Sidebar (Recommended)**
- Triggered by history icon in chat header
- Slides in from left side of chat widget
- Width: 280px on desktop, full-width on mobile
- Overlays the chat messages area

**Option B: Dropdown Panel**
- Triggered by history icon
- Drops down from header
- Max-height: 400px with scroll
- Positioned absolutely within chat container

#### Visual Structure

```
+----------------------------------------+
|  [<] Chat History          [Search] [X]|
+----------------------------------------+
|  [New Conversation]                    |
+----------------------------------------+
|  TODAY                                 |
|  +----------------------------------+  |
|  | [Bot Avatar] Product inquiry     |  |
|  | "Can you tell me about..."       |  |
|  | 2:34 PM                   [...]  |  |
|  +----------------------------------+  |
|  +----------------------------------+  |
|  | [Bot Avatar] Course enrollment   |  |
|  | "I need help with..."            |  |
|  | 11:20 AM                  [...]  |  |
|  +----------------------------------+  |
+----------------------------------------+
|  YESTERDAY                             |
|  +----------------------------------+  |
|  | [Bot Avatar] Support question    |  |
|  | "How do I reset my..."           |  |
|  | 4:15 PM                   [...]  |  |
|  +----------------------------------+  |
+----------------------------------------+
|  LAST 7 DAYS                           |
|  ...                                   |
+----------------------------------------+
```

### 2.3 Conversation List Item

#### Component Structure

```html
<div class="ai-botkit-history-item" role="button" tabindex="0">
  <div class="ai-botkit-history-avatar">
    <img src="bot-avatar.png" alt="" />
  </div>
  <div class="ai-botkit-history-content">
    <div class="ai-botkit-history-title">
      Product inquiry
    </div>
    <div class="ai-botkit-history-preview">
      Can you tell me about the pricing for...
    </div>
    <div class="ai-botkit-history-meta">
      <span class="ai-botkit-history-time">2:34 PM</span>
      <span class="ai-botkit-history-count">12 messages</span>
    </div>
  </div>
  <button class="ai-botkit-history-menu" aria-label="More options">
    <i class="ti ti-dots-vertical"></i>
  </button>
</div>
```

#### Styling Specifications

```css
.ai-botkit-history-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px 16px;
  cursor: pointer;
  border-bottom: 1px solid #e5e7eb;
  transition: background-color 0.2s ease;
}

.ai-botkit-history-item:hover {
  background-color: #f9fafb;
}

.ai-botkit-history-item.active {
  background-color: #E6F2FF;
  border-left: 3px solid #1E3A8A;
}

.ai-botkit-history-avatar {
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: #e5e5e5;
  flex-shrink: 0;
}

.ai-botkit-history-title {
  font-size: 14px;
  font-weight: 600;
  color: #1d2327;
  margin-bottom: 2px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.ai-botkit-history-preview {
  font-size: 13px;
  color: #6b7280;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.ai-botkit-history-meta {
  display: flex;
  gap: 12px;
  margin-top: 4px;
  font-size: 12px;
  color: #71717A;
}
```

### 2.4 Conversation Switching Interaction

#### State Transitions

```
[History Panel Closed]
        |
        v (click history icon)
[History Panel Opens] -- slide animation 300ms
        |
        v (click conversation)
[Loading State] -- show skeleton/spinner
        |
        v (data loaded)
[Conversation Loaded] -- fade in messages
        |
        v (auto-close panel on mobile)
[History Panel Closes]
```

#### Switching Behavior

1. **Click conversation item**
   - Show loading indicator in chat area
   - Load conversation messages from API
   - Scroll to bottom of messages
   - Update active state in history list

2. **Unsaved changes warning**
   - If current conversation has typed (unsent) message
   - Show confirmation dialog before switching

3. **Animation timing**
   - Panel slide: 300ms cubic-bezier(0.4, 0, 0.2, 1)
   - Content fade: 200ms ease-out
   - Loading spinner: rotate 1s linear infinite

### 2.5 Empty States

#### No Conversations

```
+----------------------------------------+
|             [Illustration]             |
|                                        |
|       No conversations yet             |
|                                        |
|    Start a new conversation to see     |
|    your chat history here.             |
|                                        |
|       [Start New Conversation]         |
+----------------------------------------+
```

**Specifications:**
- Illustration: 120x120px, muted colors
- Heading: 16px, font-weight 600
- Body: 14px, color #6b7280
- CTA button: Primary style

#### No Search Results

```
+----------------------------------------+
|          [Search illustration]         |
|                                        |
|    No conversations found for          |
|    "search term"                        |
|                                        |
|    Try a different search term         |
|                                        |
|          [Clear Search]                |
+----------------------------------------+
```

### 2.6 Loading States

#### History List Loading

```
+----------------------------------------+
|  +----------------------------------+  |
|  | [====] [================]        |  |
|  | [======================]         |  |
|  | [=======]                        |  |
|  +----------------------------------+  |
|  +----------------------------------+  |
|  | [====] [================]        |  |
|  | [======================]         |  |
|  | [=======]                        |  |
|  +----------------------------------+  |
+----------------------------------------+
```

**Skeleton styles:**
- Background: linear-gradient(90deg, #f0f0f0 25%, #e5e5e5 50%, #f0f0f0 75%)
- Animation: shimmer 1.5s infinite
- Border-radius: 4px

#### Conversation Loading

```
+----------------------------------------+
|                                        |
|                                        |
|             [Spinner]                  |
|        Loading conversation...         |
|                                        |
|                                        |
+----------------------------------------+
```

---

## 3. Search UI

### 3.1 Overview

**Purpose:** Allow users to search within their chat history; admins can search all conversations.

**Access Levels:**
- **Users:** Search own conversations only
- **Admins:** Search all conversations with advanced filters

### 3.2 User Search Interface

#### Search Input Placement

Located within the history panel header:

```
+----------------------------------------+
|  [<] Chat History                      |
+----------------------------------------+
|  +----------------------------------+  |
|  | [Search icon] Search messages... |  |
|  +----------------------------------+  |
+----------------------------------------+
|  [Conversation List]                   |
+----------------------------------------+
```

#### Component Structure

```html
<div class="ai-botkit-search-container">
  <div class="ai-botkit-search-input-wrapper">
    <i class="ti ti-search" aria-hidden="true"></i>
    <input
      type="search"
      class="ai-botkit-search-input"
      placeholder="Search messages..."
      aria-label="Search your conversations"
    />
    <button
      class="ai-botkit-search-clear"
      aria-label="Clear search"
      style="display: none;"
    >
      <i class="ti ti-x"></i>
    </button>
  </div>
</div>
```

#### Styling

```css
.ai-botkit-search-input-wrapper {
  display: flex;
  align-items: center;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 8px 12px;
  margin: 12px 16px;
  transition: border-color 0.2s, box-shadow 0.2s;
}

.ai-botkit-search-input-wrapper:focus-within {
  border-color: #1E3A8A;
  box-shadow: 0 0 0 2px rgba(30, 58, 138, 0.1);
}

.ai-botkit-search-input-wrapper i {
  color: #6b7280;
  font-size: 16px;
}

.ai-botkit-search-input {
  border: none;
  background: transparent;
  flex: 1;
  margin-left: 8px;
  font-size: 14px;
  outline: none;
}

.ai-botkit-search-clear {
  background: none;
  border: none;
  padding: 4px;
  cursor: pointer;
  color: #6b7280;
}
```

### 3.3 Admin Search Interface

#### Full-Page Search Panel

Located in WordPress admin under AI BotKit > Conversations:

```
+------------------------------------------------------------------+
|  AI BotKit > Conversations                                        |
+------------------------------------------------------------------+
|                                                                   |
|  Search Conversations                                             |
|  +-------------------------------------------------------------+ |
|  | [Search icon] Search in messages...                         | |
|  +-------------------------------------------------------------+ |
|                                                                   |
|  +-------------+ +-------------+ +-------------+ +-------------+  |
|  | Date Range  | | Chatbot     | | User        | | Status      |  |
|  | [Dropdown]  | | [Dropdown]  | | [Dropdown]  | | [Dropdown]  |  |
|  +-------------+ +-------------+ +-------------+ +-------------+  |
|                                                                   |
|  Showing 156 results for "pricing"              [Clear Filters]   |
|                                                                   |
+------------------------------------------------------------------+
|  +-------------------------------------------------------------+ |
|  | User: John Doe            Bot: Support Assistant             | |
|  | "What is the pricing for the premium plan?"                  | |
|  | Jan 28, 2026 2:34 PM                        [View] [Export]  | |
|  +-------------------------------------------------------------+ |
|  +-------------------------------------------------------------+ |
|  | User: jane@example.com    Bot: Sales Bot                     | |
|  | "Can you tell me more about pricing options..."              | |
|  | Jan 27, 2026 11:20 AM                       [View] [Export]  | |
|  +-------------------------------------------------------------+ |
+------------------------------------------------------------------+
|  [< Prev]  Page 1 of 16  [Next >]                                |
+------------------------------------------------------------------+
```

#### Filter Controls

```html
<div class="ai-botkit-search-filters">
  <div class="ai-botkit-filter-group">
    <label for="filter-date">Date Range</label>
    <select id="filter-date" class="ai-botkit-filter-select">
      <option value="">All Time</option>
      <option value="today">Today</option>
      <option value="week">Last 7 Days</option>
      <option value="month">Last 30 Days</option>
      <option value="custom">Custom Range</option>
    </select>
  </div>

  <div class="ai-botkit-filter-group">
    <label for="filter-chatbot">Chatbot</label>
    <select id="filter-chatbot" class="ai-botkit-filter-select">
      <option value="">All Chatbots</option>
      <!-- Dynamic options -->
    </select>
  </div>

  <div class="ai-botkit-filter-group">
    <label for="filter-user">User</label>
    <select id="filter-user" class="ai-botkit-filter-select">
      <option value="">All Users</option>
      <!-- Dynamic options with search -->
    </select>
  </div>

  <div class="ai-botkit-filter-group">
    <label for="filter-status">Status</label>
    <select id="filter-status" class="ai-botkit-filter-select">
      <option value="">All</option>
      <option value="resolved">Resolved</option>
      <option value="active">Active</option>
    </select>
  </div>
</div>
```

### 3.4 Search Results Display

#### Result Item with Highlighting

```html
<div class="ai-botkit-search-result">
  <div class="ai-botkit-search-result-header">
    <div class="ai-botkit-search-result-user">
      <img src="avatar.png" alt="" class="ai-botkit-search-result-avatar" />
      <span class="ai-botkit-search-result-name">John Doe</span>
    </div>
    <div class="ai-botkit-search-result-meta">
      <span class="ai-botkit-badge ai-botkit-badge-outline">Support Assistant</span>
      <span class="ai-botkit-search-result-date">Jan 28, 2026 2:34 PM</span>
    </div>
  </div>
  <div class="ai-botkit-search-result-content">
    What is the <mark class="ai-botkit-highlight">pricing</mark> for the premium plan?
    I'm interested in the annual subscription...
  </div>
  <div class="ai-botkit-search-result-actions">
    <button class="ai-botkit-btn-outline ai-botkit-btn-sm">
      <i class="ti ti-eye"></i> View
    </button>
    <button class="ai-botkit-btn-outline ai-botkit-btn-sm">
      <i class="ti ti-download"></i> Export
    </button>
  </div>
</div>
```

#### Highlight Styling

```css
.ai-botkit-highlight {
  background-color: #FEF3C7;
  color: inherit;
  padding: 1px 4px;
  border-radius: 2px;
  font-weight: 500;
}

/* For dark mode compatibility */
.ai-botkit-dark .ai-botkit-highlight {
  background-color: #D97706;
  color: #ffffff;
}
```

### 3.5 Interface Differences: Admin vs User

| Feature | User Interface | Admin Interface |
|---------|---------------|-----------------|
| Search scope | Own conversations | All conversations |
| Filters | None | Date, Chatbot, User, Status |
| Results display | Within history panel | Full-page list |
| Bulk actions | None | Export selected, Delete selected |
| User column | Hidden | Visible |
| Export button | Per conversation | Per result + bulk |

---

## 4. Rich Media UI

### 4.1 Overview

**Purpose:** Display images, videos, files, and link previews within chat messages.

**Supported Types:**
- Images (PNG, JPG, GIF, WebP)
- Videos (YouTube, Vimeo embeds)
- Files (PDF, DOC, ZIP, etc.)
- Rich links (with og:image preview)

### 4.2 Image Display in Messages

#### Inline Image

```html
<div class="ai-botkit-message assistant">
  <div class="ai-botkit-message-avatar">...</div>
  <div class="ai-botkit-message-content">
    <div class="ai-botkit-message-text">
      Here's the product image you requested:
    </div>
    <div class="ai-botkit-media-image">
      <img
        src="product.jpg"
        alt="Product name - Description"
        loading="lazy"
        class="ai-botkit-chat-image"
      />
      <button
        class="ai-botkit-image-expand"
        aria-label="View full size image"
      >
        <i class="ti ti-arrows-maximize"></i>
      </button>
    </div>
  </div>
</div>
```

#### Image Styling

```css
.ai-botkit-media-image {
  position: relative;
  margin-top: 8px;
  border-radius: 12px;
  overflow: hidden;
  max-width: 320px;
}

.ai-botkit-chat-image {
  width: 100%;
  height: auto;
  display: block;
  cursor: pointer;
  transition: transform 0.2s ease;
}

.ai-botkit-chat-image:hover {
  transform: scale(1.02);
}

.ai-botkit-image-expand {
  position: absolute;
  bottom: 8px;
  right: 8px;
  background: rgba(0, 0, 0, 0.6);
  color: white;
  border: none;
  border-radius: 6px;
  padding: 6px 10px;
  cursor: pointer;
  opacity: 0;
  transition: opacity 0.2s ease;
}

.ai-botkit-media-image:hover .ai-botkit-image-expand {
  opacity: 1;
}

/* Image gallery for multiple images */
.ai-botkit-image-gallery {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 8px;
  margin-top: 8px;
}

.ai-botkit-image-gallery.single {
  grid-template-columns: 1fr;
}

.ai-botkit-image-gallery.double {
  grid-template-columns: 1fr 1fr;
}
```

### 4.3 Video Embed Presentation

#### YouTube/Vimeo Embed

```html
<div class="ai-botkit-message assistant">
  <div class="ai-botkit-message-avatar">...</div>
  <div class="ai-botkit-message-content">
    <div class="ai-botkit-message-text">
      Check out this tutorial video:
    </div>
    <div class="ai-botkit-media-video">
      <div class="ai-botkit-video-wrapper">
        <!-- Lazy-loaded iframe -->
        <div class="ai-botkit-video-placeholder" data-video-id="xyz123">
          <img src="thumbnail.jpg" alt="Video: Tutorial title" />
          <button class="ai-botkit-video-play" aria-label="Play video">
            <i class="ti ti-player-play-filled"></i>
          </button>
        </div>
      </div>
      <div class="ai-botkit-video-info">
        <span class="ai-botkit-video-title">Getting Started Tutorial</span>
        <span class="ai-botkit-video-duration">5:32</span>
      </div>
    </div>
  </div>
</div>
```

#### Video Styling

```css
.ai-botkit-media-video {
  margin-top: 8px;
  max-width: 400px;
  border-radius: 12px;
  overflow: hidden;
  background: #000;
}

.ai-botkit-video-wrapper {
  position: relative;
  padding-bottom: 56.25%; /* 16:9 aspect ratio */
  height: 0;
}

.ai-botkit-video-wrapper iframe,
.ai-botkit-video-placeholder {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
}

.ai-botkit-video-placeholder {
  cursor: pointer;
}

.ai-botkit-video-placeholder img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.ai-botkit-video-play {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  width: 64px;
  height: 64px;
  background: rgba(0, 0, 0, 0.8);
  border: none;
  border-radius: 50%;
  color: white;
  font-size: 24px;
  cursor: pointer;
  transition: background 0.2s, transform 0.2s;
}

.ai-botkit-video-play:hover {
  background: #1E3A8A;
  transform: translate(-50%, -50%) scale(1.1);
}

.ai-botkit-video-info {
  display: flex;
  justify-content: space-between;
  padding: 8px 12px;
  background: #1a1a1a;
  color: white;
  font-size: 13px;
}
```

### 4.4 File Attachment Cards

#### File Card Component

```html
<div class="ai-botkit-message assistant">
  <div class="ai-botkit-message-avatar">...</div>
  <div class="ai-botkit-message-content">
    <div class="ai-botkit-message-text">
      Here's the documentation you requested:
    </div>
    <div class="ai-botkit-media-file">
      <div class="ai-botkit-file-icon">
        <i class="ti ti-file-type-pdf"></i>
      </div>
      <div class="ai-botkit-file-info">
        <span class="ai-botkit-file-name">User_Guide_v2.pdf</span>
        <span class="ai-botkit-file-meta">PDF - 2.4 MB</span>
      </div>
      <a
        href="download-url"
        class="ai-botkit-file-download"
        download
        aria-label="Download User_Guide_v2.pdf"
      >
        <i class="ti ti-download"></i>
      </a>
    </div>
  </div>
</div>
```

#### File Card Styling

```css
.ai-botkit-media-file {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-top: 8px;
  padding: 12px 16px;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  max-width: 320px;
}

.ai-botkit-file-icon {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 8px;
  font-size: 20px;
}

/* File type colors */
.ai-botkit-file-icon.pdf { background: #FEE2E2; color: #DC2626; }
.ai-botkit-file-icon.doc { background: #DBEAFE; color: #2563EB; }
.ai-botkit-file-icon.xls { background: #D1FAE5; color: #059669; }
.ai-botkit-file-icon.zip { background: #FEF3C7; color: #D97706; }
.ai-botkit-file-icon.default { background: #E5E7EB; color: #6B7280; }

.ai-botkit-file-info {
  flex: 1;
  min-width: 0;
}

.ai-botkit-file-name {
  display: block;
  font-size: 14px;
  font-weight: 500;
  color: #1d2327;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.ai-botkit-file-meta {
  display: block;
  font-size: 12px;
  color: #6b7280;
  margin-top: 2px;
}

.ai-botkit-file-download {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #1E3A8A;
  color: white;
  border-radius: 8px;
  text-decoration: none;
  transition: background 0.2s;
}

.ai-botkit-file-download:hover {
  background: #1E40AF;
}
```

### 4.5 Link Preview Cards

#### Link Preview Component

```html
<div class="ai-botkit-media-link">
  <a href="https://example.com/article" target="_blank" rel="noopener noreferrer">
    <div class="ai-botkit-link-image">
      <img src="og-image.jpg" alt="" loading="lazy" />
    </div>
    <div class="ai-botkit-link-content">
      <span class="ai-botkit-link-domain">example.com</span>
      <span class="ai-botkit-link-title">Article Title Goes Here</span>
      <span class="ai-botkit-link-description">
        A brief description of the linked content that provides context...
      </span>
    </div>
  </a>
</div>
```

#### Link Preview Styling

```css
.ai-botkit-media-link {
  margin-top: 8px;
  max-width: 400px;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  overflow: hidden;
  transition: border-color 0.2s;
}

.ai-botkit-media-link:hover {
  border-color: #1E3A8A;
}

.ai-botkit-media-link a {
  display: flex;
  flex-direction: column;
  text-decoration: none;
  color: inherit;
}

.ai-botkit-link-image {
  height: 160px;
  overflow: hidden;
  background: #f0f0f0;
}

.ai-botkit-link-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.ai-botkit-link-content {
  padding: 12px 16px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.ai-botkit-link-domain {
  font-size: 12px;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.ai-botkit-link-title {
  font-size: 14px;
  font-weight: 600;
  color: #1d2327;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.ai-botkit-link-description {
  font-size: 13px;
  color: #6b7280;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
```

### 4.6 Lightbox/Modal for Media Zoom

#### Lightbox Component

```html
<div class="ai-botkit-lightbox" role="dialog" aria-modal="true" aria-label="Image viewer">
  <div class="ai-botkit-lightbox-backdrop"></div>
  <div class="ai-botkit-lightbox-content">
    <div class="ai-botkit-lightbox-header">
      <span class="ai-botkit-lightbox-title">Product Image</span>
      <div class="ai-botkit-lightbox-actions">
        <button class="ai-botkit-lightbox-btn" aria-label="Download image">
          <i class="ti ti-download"></i>
        </button>
        <button class="ai-botkit-lightbox-btn" aria-label="Open in new tab">
          <i class="ti ti-external-link"></i>
        </button>
        <button class="ai-botkit-lightbox-close" aria-label="Close">
          <i class="ti ti-x"></i>
        </button>
      </div>
    </div>
    <div class="ai-botkit-lightbox-body">
      <img src="full-size-image.jpg" alt="Full size product image" />
    </div>
    <div class="ai-botkit-lightbox-footer">
      <span class="ai-botkit-lightbox-info">1920 x 1080 - 245 KB</span>
    </div>
  </div>

  <!-- Navigation for galleries -->
  <button class="ai-botkit-lightbox-nav prev" aria-label="Previous image">
    <i class="ti ti-chevron-left"></i>
  </button>
  <button class="ai-botkit-lightbox-nav next" aria-label="Next image">
    <i class="ti ti-chevron-right"></i>
  </button>
</div>
```

#### Lightbox Styling

```css
.ai-botkit-lightbox {
  position: fixed;
  inset: 0;
  z-index: 100000;
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  visibility: hidden;
  transition: opacity 0.3s, visibility 0.3s;
}

.ai-botkit-lightbox.open {
  opacity: 1;
  visibility: visible;
}

.ai-botkit-lightbox-backdrop {
  position: absolute;
  inset: 0;
  background: rgba(0, 0, 0, 0.9);
}

.ai-botkit-lightbox-content {
  position: relative;
  max-width: 90vw;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  background: #000;
  border-radius: 8px;
  overflow: hidden;
}

.ai-botkit-lightbox-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  background: rgba(255, 255, 255, 0.1);
}

.ai-botkit-lightbox-title {
  color: white;
  font-size: 14px;
  font-weight: 500;
}

.ai-botkit-lightbox-actions {
  display: flex;
  gap: 8px;
}

.ai-botkit-lightbox-btn,
.ai-botkit-lightbox-close {
  width: 36px;
  height: 36px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(255, 255, 255, 0.1);
  border: none;
  border-radius: 8px;
  color: white;
  cursor: pointer;
  transition: background 0.2s;
}

.ai-botkit-lightbox-btn:hover,
.ai-botkit-lightbox-close:hover {
  background: rgba(255, 255, 255, 0.2);
}

.ai-botkit-lightbox-body {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
}

.ai-botkit-lightbox-body img {
  max-width: 100%;
  max-height: 75vh;
  object-fit: contain;
}

.ai-botkit-lightbox-nav {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  width: 48px;
  height: 48px;
  background: rgba(255, 255, 255, 0.1);
  border: none;
  border-radius: 50%;
  color: white;
  font-size: 24px;
  cursor: pointer;
  transition: background 0.2s;
}

.ai-botkit-lightbox-nav.prev { left: 16px; }
.ai-botkit-lightbox-nav.next { right: 16px; }

.ai-botkit-lightbox-nav:hover {
  background: rgba(255, 255, 255, 0.2);
}
```

---

## 5. Conversation Templates Admin UI

### 5.1 Overview

**Purpose:** Allow administrators to create, edit, and apply conversation templates to chatbots.

**Template Types (per Phase 0.5):**
1. FAQ Bot
2. Customer Support
3. Product Advisor
4. Lead Capture

### 5.2 Template List View

#### Location

WordPress Admin > AI BotKit > Templates

#### List Design

```
+------------------------------------------------------------------+
|  Conversation Templates                        [+ Create Template] |
+------------------------------------------------------------------+
|                                                                    |
|  +--------------------------------------------------------------+ |
|  | [FAQ icon]                                                    | |
|  | FAQ Bot                                       [Built-in]      | |
|  | Direct answers from knowledge base with source citations      | |
|  |                                                               | |
|  | Used by: 3 chatbots                                           | |
|  |                              [Preview] [Duplicate] [Edit]     | |
|  +--------------------------------------------------------------+ |
|                                                                    |
|  +--------------------------------------------------------------+ |
|  | [Support icon]                                                | |
|  | Customer Support                              [Built-in]      | |
|  | Help desk style with ticket reference and escalation          | |
|  |                                                               | |
|  | Used by: 2 chatbots                                           | |
|  |                              [Preview] [Duplicate] [Edit]     | |
|  +--------------------------------------------------------------+ |
|                                                                    |
|  +--------------------------------------------------------------+ |
|  | [Product icon]                                                | |
|  | Product Advisor                               [Built-in]      | |
|  | Product matching with needs assessment and comparison         | |
|  |                                                               | |
|  | Used by: 1 chatbot                                            | |
|  |                              [Preview] [Duplicate] [Edit]     | |
|  +--------------------------------------------------------------+ |
|                                                                    |
|  +--------------------------------------------------------------+ |
|  | [Lead icon]                                                   | |
|  | Lead Capture                                  [Built-in]      | |
|  | Multi-step form with field validation                         | |
|  |                                                               | |
|  | Used by: 0 chatbots                                           | |
|  |                              [Preview] [Duplicate] [Edit]     | |
|  +--------------------------------------------------------------+ |
|                                                                    |
|  +--------------------------------------------------------------+ |
|  | [Custom icon]                                                 | |
|  | My Custom Template                            [Custom]        | |
|  | Custom configuration for special use case                     | |
|  |                                                               | |
|  | Used by: 1 chatbot                                            | |
|  |                        [Preview] [Duplicate] [Edit] [Delete]  | |
|  +--------------------------------------------------------------+ |
|                                                                    |
+------------------------------------------------------------------+
```

#### Template Card Component

```html
<div class="ai-botkit-template-card">
  <div class="ai-botkit-template-icon">
    <i class="ti ti-messages"></i>
  </div>
  <div class="ai-botkit-template-content">
    <div class="ai-botkit-template-header">
      <h3 class="ai-botkit-template-title">FAQ Bot</h3>
      <span class="ai-botkit-badge ai-botkit-badge-outline">Built-in</span>
    </div>
    <p class="ai-botkit-template-description">
      Direct answers from knowledge base with source citations
    </p>
    <div class="ai-botkit-template-meta">
      <span>Used by: <strong>3 chatbots</strong></span>
    </div>
  </div>
  <div class="ai-botkit-template-actions">
    <button class="ai-botkit-btn-outline ai-botkit-btn-sm">
      <i class="ti ti-eye"></i> Preview
    </button>
    <button class="ai-botkit-btn-outline ai-botkit-btn-sm">
      <i class="ti ti-copy"></i> Duplicate
    </button>
    <button class="ai-botkit-btn-outline ai-botkit-btn-sm">
      <i class="ti ti-edit"></i> Edit
    </button>
  </div>
</div>
```

### 5.3 Template Builder/Editor

#### Editor Layout

```
+------------------------------------------------------------------+
|  [<] Back to Templates                                            |
+------------------------------------------------------------------+
|  Edit Template: FAQ Bot                            [Save] [Cancel] |
+------------------------------------------------------------------+
|                                                                    |
|  +-------------------+  +--------------------------------------+   |
|  |                   |  |                                      |   |
|  |  SECTIONS         |  |  CONFIGURATION                       |   |
|  |                   |  |                                      |   |
|  |  > Basic Info     |  |  Template Name                       |   |
|  |    System Prompt  |  |  +--------------------------------+  |   |
|  |    Welcome Flow   |  |  | FAQ Bot                        |  |   |
|  |    Response Style |  |  +--------------------------------+  |   |
|  |    Features       |  |                                      |   |
|  |    Behaviors      |  |  Description                         |   |
|  |                   |  |  +--------------------------------+  |   |
|  |                   |  |  | Direct answers from knowledge  |  |   |
|  |                   |  |  | base with source citations     |  |   |
|  |                   |  |  +--------------------------------+  |   |
|  |                   |  |                                      |   |
|  |                   |  |  Icon                                |   |
|  |                   |  |  [messages] [v]                      |   |
|  |                   |  |                                      |   |
|  |                   |  |  Category                            |   |
|  |                   |  |  ( ) FAQ & Knowledge Base            |   |
|  |                   |  |  ( ) Customer Support                |   |
|  |                   |  |  ( ) Sales & Products                |   |
|  |                   |  |  ( ) Lead Generation                 |   |
|  |                   |  |  ( ) Custom                          |   |
|  |                   |  |                                      |   |
|  +-------------------+  +--------------------------------------+   |
|                                                                    |
+------------------------------------------------------------------+
```

#### Section: System Prompt

```
+--------------------------------------+
|  System Prompt                       |
+--------------------------------------+
|                                      |
|  Base Instructions                   |
|  +--------------------------------+  |
|  | You are a helpful FAQ         |  |
|  | assistant. Answer questions   |  |
|  | directly and cite your        |  |
|  | sources...                    |  |
|  |                               |  |
|  +--------------------------------+  |
|  [Use AI to improve] [Reset to default]
|                                      |
|  Tone                                |
|  [Professional] [v]                  |
|                                      |
|  Knowledge Scope                     |
|  [x] Strict - Only answer from KB    |
|  [ ] Flexible - Allow general knowledge
|                                      |
+--------------------------------------+
```

#### Section: Welcome Flow

```
+--------------------------------------+
|  Welcome Flow                        |
+--------------------------------------+
|                                      |
|  Welcome Message                     |
|  +--------------------------------+  |
|  | Hello! I'm here to help you   |  |
|  | find answers. What would you  |  |
|  | like to know?                 |  |
|  +--------------------------------+  |
|                                      |
|  Quick Actions                       |
|  +--------------------------------+  |
|  | [x] Show suggested questions  |  |
|  |                               |  |
|  |  1. [How do I get started?]   |  |
|  |  2. [What are the pricing...] |  |
|  |  3. [How can I contact sup...]|  |
|  |                               |  |
|  |  [+ Add suggestion]           |  |
|  +--------------------------------+  |
|                                      |
+--------------------------------------+
```

#### Section: Features Toggle

```
+--------------------------------------+
|  Features                            |
+--------------------------------------+
|                                      |
|  [x] Source Citations                |
|      Show links to source documents  |
|                                      |
|  [x] Feedback Buttons                |
|      Allow users to rate responses   |
|                                      |
|  [ ] Follow-up Suggestions           |
|      Suggest related questions       |
|                                      |
|  [ ] Escalation to Human             |
|      Offer to connect with support   |
|                                      |
|  [x] "Did this help?" Prompt         |
|      Ask for confirmation after      |
|      answering                       |
|                                      |
+--------------------------------------+
```

### 5.4 Template Preview

#### Preview Modal

```
+------------------------------------------------------------------+
|  Template Preview: FAQ Bot                                    [X] |
+------------------------------------------------------------------+
|                                                                    |
|  +--------------------------------------------------------------+ |
|  |                                                              | |
|  |  +------------------------------------------+                | |
|  |  |  [Bot Avatar]  FAQ Assistant             |                | |
|  |  +------------------------------------------+                | |
|  |  |                                          |                | |
|  |  |  [Bot]: Hello! I'm here to help you     |                | |
|  |  |  find answers. What would you like to   |                | |
|  |  |  know?                                  |                | |
|  |  |                                          |                | |
|  |  |  +------------------------------------+ |                | |
|  |  |  | How do I get started?             | |                | |
|  |  |  +------------------------------------+ |                | |
|  |  |  | What are the pricing options?     | |                | |
|  |  |  +------------------------------------+ |                | |
|  |  |  | How can I contact support?        | |                | |
|  |  |  +------------------------------------+ |                | |
|  |  |                                          |                | |
|  |  +------------------------------------------+                | |
|  |  | Type a message...                [Send] |                | |
|  |  +------------------------------------------+                | |
|  |                                                              | |
|  +--------------------------------------------------------------+ |
|                                                                    |
|  Note: This is a static preview. Actual responses will vary       |
|  based on your knowledge base content.                            |
|                                                                    |
|                                    [Apply to Chatbot] [Close]      |
+------------------------------------------------------------------+
```

### 5.5 Apply Template to Chatbot

#### Template Selection in Chatbot Wizard

```
+------------------------------------------------------------------+
|  Step 1: Choose a Template                                        |
+------------------------------------------------------------------+
|                                                                    |
|  Start with a template to quickly configure your chatbot          |
|                                                                    |
|  +-------------+  +-------------+  +-------------+  +-------------+|
|  |             |  |             |  |             |  |             ||
|  |  [FAQ]      |  |  [Support]  |  |  [Product]  |  |  [Lead]     ||
|  |             |  |             |  |             |  |             ||
|  |  FAQ Bot    |  |  Customer   |  |  Product    |  |  Lead       ||
|  |             |  |  Support    |  |  Advisor    |  |  Capture    ||
|  |             |  |             |  |             |  |             ||
|  +-------------+  +-------------+  +-------------+  +-------------+|
|                                                                    |
|  +-------------+  +-------------+                                  |
|  |             |  |             |                                  |
|  |  [Custom]   |  |  [Blank]    |                                  |
|  |             |  |             |                                  |
|  |  My Custom  |  |  Start      |                                  |
|  |  Template   |  |  from       |                                  |
|  |             |  |  Scratch    |                                  |
|  +-------------+  +-------------+                                  |
|                                                                    |
|                                        [Skip] [Continue with FAQ]  |
+------------------------------------------------------------------+
```

---

## 6. Export UI

### 6.1 Overview

**Purpose:** Allow admins to export any conversation; allow users to download their own conversations as PDF.

**Format:** PDF only (per Phase 0.5 clarification)

### 6.2 Admin Export Interface

#### Export Options Panel

Located in conversation detail view and search results:

```
+------------------------------------------------------------------+
|  Export Conversation                                          [X] |
+------------------------------------------------------------------+
|                                                                    |
|  Conversation: Support inquiry with John Doe                       |
|  Date: Jan 28, 2026 | Messages: 24                                |
|                                                                    |
+------------------------------------------------------------------+
|                                                                    |
|  Export Options                                                    |
|                                                                    |
|  Include:                                                          |
|  [x] Conversation metadata                                         |
|      (User info, chatbot name, date/time)                         |
|                                                                    |
|  [x] All messages                                                  |
|  [ ] Only selected messages                                        |
|                                                                    |
|  [x] Timestamps                                                    |
|                                                                    |
|  [x] Media attachments                                             |
|      (Images will be embedded in PDF)                             |
|                                                                    |
|  [ ] Source citations                                              |
|                                                                    |
|  Branding:                                                         |
|  [x] Include site logo                                             |
|  [x] Include footer with site URL                                  |
|                                                                    |
+------------------------------------------------------------------+
|                                                                    |
|                                      [Cancel]  [Generate PDF]      |
|                                                                    |
+------------------------------------------------------------------+
```

#### Bulk Export (Admin)

```
+------------------------------------------------------------------+
|  Export Selected Conversations (5 selected)                   [X] |
+------------------------------------------------------------------+
|                                                                    |
|  Export Format:                                                    |
|  ( ) Individual PDFs (ZIP archive)                                 |
|  (x) Combined PDF (all conversations in one file)                  |
|                                                                    |
|  Date Range: Jan 1, 2026 - Jan 28, 2026                           |
|  Total Messages: 156                                               |
|                                                                    |
+------------------------------------------------------------------+
|                                                                    |
|                                      [Cancel]  [Generate Export]   |
|                                                                    |
+------------------------------------------------------------------+
```

### 6.3 User "Download PDF" Button Placement

#### Option A: Chat Header Action (Recommended)

```
+------------------------------------------+
|  [Avatar] Support Bot      [History] [PDF] [X]|
+------------------------------------------+
```

#### Option B: History Panel Context Menu

```
+------------------------------------------+
|  [Conversation Item]              [...]  |
|                                          |
|                            +----------+  |
|                            | View     |  |
|                            | Export   |  |
|                            | Delete   |  |
|                            +----------+  |
+------------------------------------------+
```

#### Download Button Component

```html
<button
  class="ai-botkit-export-btn"
  aria-label="Download conversation as PDF"
  title="Download PDF"
>
  <i class="ti ti-file-download"></i>
</button>
```

#### Styling

```css
.ai-botkit-export-btn {
  background: transparent;
  border: none;
  color: #888888;
  cursor: pointer;
  padding: 5px;
  border-radius: 4px;
  font-size: 1.2rem;
  transition: background-color 0.2s, color 0.2s;
}

.ai-botkit-export-btn:hover {
  background: rgba(0, 0, 0, 0.05);
  color: #1E3A8A;
}

.ai-botkit-export-btn:focus {
  outline: 2px solid #1E3A8A;
  outline-offset: 2px;
}
```

### 6.4 Export Progress Indicator

#### Progress Modal

```
+------------------------------------------------------------------+
|  Generating PDF...                                                |
+------------------------------------------------------------------+
|                                                                    |
|                                                                    |
|  +--------------------------------------------------------------+ |
|  |============================================                  | |
|  +--------------------------------------------------------------+ |
|                                                                    |
|  Processing messages... 18/24                                      |
|                                                                    |
|                                      [Cancel]                      |
|                                                                    |
+------------------------------------------------------------------+
```

#### Inline Progress (for quick exports)

```html
<button class="ai-botkit-export-btn exporting" disabled>
  <span class="ai-botkit-export-spinner"></span>
</button>
```

```css
.ai-botkit-export-spinner {
  width: 16px;
  height: 16px;
  border: 2px solid #e5e7eb;
  border-top-color: #1E3A8A;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}
```

### 6.5 Export Complete State

#### Success Toast

```
+--------------------------------------------------+
|  [Check icon] PDF downloaded successfully         |
|                                                   |
|  conversation_2026-01-28.pdf                      |
|                                      [Open File]  |
+--------------------------------------------------+
```

#### Error State

```
+--------------------------------------------------+
|  [X icon] Export failed                          |
|                                                   |
|  Unable to generate PDF. Please try again.       |
|                                                   |
|                         [Try Again] [Close]       |
+--------------------------------------------------+
```

---

## 7. Product/Course Suggestions UI

### 7.1 Overview

**Purpose:** Display personalized product (WooCommerce) or course (LearnDash) recommendations within chat.

**Triggers (per Phase 0.5):**
- Conversation context analysis
- Browsing history
- Purchase/enrollment history
- Explicit user request

### 7.2 Suggestion Cards Design

#### Single Product Card

```html
<div class="ai-botkit-suggestion-card">
  <div class="ai-botkit-suggestion-image">
    <img src="product.jpg" alt="Product Name" loading="lazy" />
    <span class="ai-botkit-suggestion-badge">Best Match</span>
  </div>
  <div class="ai-botkit-suggestion-content">
    <div class="ai-botkit-suggestion-category">Electronics</div>
    <h4 class="ai-botkit-suggestion-title">Premium Wireless Headphones</h4>
    <div class="ai-botkit-suggestion-rating">
      <span class="ai-botkit-stars" aria-label="4.5 out of 5 stars">
        <i class="ti ti-star-filled"></i>
        <i class="ti ti-star-filled"></i>
        <i class="ti ti-star-filled"></i>
        <i class="ti ti-star-filled"></i>
        <i class="ti ti-star-half-filled"></i>
      </span>
      <span class="ai-botkit-review-count">(128)</span>
    </div>
    <div class="ai-botkit-suggestion-price">
      <span class="ai-botkit-price-current">$149.99</span>
      <span class="ai-botkit-price-original">$199.99</span>
    </div>
  </div>
  <div class="ai-botkit-suggestion-actions">
    <button class="ai-botkit-add-to-cart">
      <i class="ti ti-shopping-cart-plus"></i>
      Add to Cart
    </button>
    <a href="/product/..." class="ai-botkit-view-details">
      View Details
    </a>
  </div>
</div>
```

#### Course Card (LearnDash)

```html
<div class="ai-botkit-suggestion-card ai-botkit-course-card">
  <div class="ai-botkit-suggestion-image">
    <img src="course.jpg" alt="Course Name" loading="lazy" />
    <span class="ai-botkit-suggestion-badge">Recommended</span>
    <div class="ai-botkit-course-duration">
      <i class="ti ti-clock"></i> 12 hours
    </div>
  </div>
  <div class="ai-botkit-suggestion-content">
    <div class="ai-botkit-suggestion-category">Web Development</div>
    <h4 class="ai-botkit-suggestion-title">Advanced JavaScript Mastery</h4>
    <div class="ai-botkit-instructor">
      <img src="instructor.jpg" alt="" class="ai-botkit-instructor-avatar" />
      <span>By John Smith</span>
    </div>
    <div class="ai-botkit-course-meta">
      <span><i class="ti ti-users"></i> 2,340 enrolled</span>
      <span><i class="ti ti-certificate"></i> Certificate</span>
    </div>
    <div class="ai-botkit-suggestion-price">
      <span class="ai-botkit-price-current">$79.00</span>
    </div>
  </div>
  <div class="ai-botkit-suggestion-actions">
    <button class="ai-botkit-enroll-btn">
      <i class="ti ti-school"></i>
      Enroll Now
    </button>
    <a href="/course/..." class="ai-botkit-view-details">
      Learn More
    </a>
  </div>
</div>
```

### 7.3 Suggestion Card Styling

```css
.ai-botkit-suggestion-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  overflow: hidden;
  max-width: 280px;
  transition: box-shadow 0.2s, transform 0.2s;
}

.ai-botkit-suggestion-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
  transform: translateY(-2px);
}

.ai-botkit-suggestion-image {
  position: relative;
  height: 160px;
  overflow: hidden;
}

.ai-botkit-suggestion-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.ai-botkit-suggestion-badge {
  position: absolute;
  top: 8px;
  left: 8px;
  background: #1E3A8A;
  color: white;
  font-size: 11px;
  font-weight: 600;
  padding: 4px 8px;
  border-radius: 4px;
}

.ai-botkit-suggestion-badge.personalized {
  background: #059669;
}

.ai-botkit-suggestion-content {
  padding: 12px 16px;
}

.ai-botkit-suggestion-category {
  font-size: 12px;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 4px;
}

.ai-botkit-suggestion-title {
  font-size: 15px;
  font-weight: 600;
  color: #1d2327;
  margin: 0 0 8px 0;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.ai-botkit-suggestion-rating {
  display: flex;
  align-items: center;
  gap: 4px;
  margin-bottom: 8px;
}

.ai-botkit-stars {
  color: #F59E0B;
  font-size: 14px;
}

.ai-botkit-review-count {
  font-size: 12px;
  color: #6b7280;
}

.ai-botkit-suggestion-price {
  display: flex;
  align-items: baseline;
  gap: 8px;
}

.ai-botkit-price-current {
  font-size: 18px;
  font-weight: 700;
  color: #1d2327;
}

.ai-botkit-price-original {
  font-size: 14px;
  color: #6b7280;
  text-decoration: line-through;
}

.ai-botkit-suggestion-actions {
  padding: 12px 16px;
  border-top: 1px solid #e5e7eb;
  display: flex;
  gap: 8px;
}

.ai-botkit-add-to-cart,
.ai-botkit-enroll-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  background: #1E3A8A;
  color: white;
  border: none;
  padding: 10px 16px;
  border-radius: 6px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.2s;
}

.ai-botkit-add-to-cart:hover,
.ai-botkit-enroll-btn:hover {
  background: #1E40AF;
}

.ai-botkit-view-details {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 10px 12px;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  font-size: 13px;
  color: #1d2327;
  text-decoration: none;
  transition: background 0.2s, border-color 0.2s;
}

.ai-botkit-view-details:hover {
  background: #f9fafb;
  border-color: #1E3A8A;
  color: #1E3A8A;
}
```

### 7.4 Carousel Layout

#### Multiple Suggestions Carousel

```html
<div class="ai-botkit-suggestions-container">
  <div class="ai-botkit-suggestions-header">
    <span class="ai-botkit-suggestions-label">
      <i class="ti ti-sparkles"></i>
      Recommended for you
    </span>
    <div class="ai-botkit-suggestions-nav">
      <button class="ai-botkit-carousel-prev" aria-label="Previous">
        <i class="ti ti-chevron-left"></i>
      </button>
      <button class="ai-botkit-carousel-next" aria-label="Next">
        <i class="ti ti-chevron-right"></i>
      </button>
    </div>
  </div>
  <div class="ai-botkit-suggestions-carousel">
    <div class="ai-botkit-suggestions-track">
      <!-- Suggestion cards here -->
    </div>
  </div>
  <div class="ai-botkit-suggestions-indicators">
    <button class="active" aria-label="Slide 1"></button>
    <button aria-label="Slide 2"></button>
    <button aria-label="Slide 3"></button>
  </div>
</div>
```

#### Carousel Styling

```css
.ai-botkit-suggestions-container {
  margin-top: 12px;
  max-width: 100%;
}

.ai-botkit-suggestions-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
  padding: 0 4px;
}

.ai-botkit-suggestions-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 500;
  color: #1d2327;
}

.ai-botkit-suggestions-label i {
  color: #F59E0B;
}

.ai-botkit-suggestions-nav {
  display: flex;
  gap: 4px;
}

.ai-botkit-carousel-prev,
.ai-botkit-carousel-next {
  width: 28px;
  height: 28px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #f9fafb;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  cursor: pointer;
  transition: background 0.2s, border-color 0.2s;
}

.ai-botkit-carousel-prev:hover,
.ai-botkit-carousel-next:hover {
  background: #1E3A8A;
  border-color: #1E3A8A;
  color: white;
}

.ai-botkit-carousel-prev:disabled,
.ai-botkit-carousel-next:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.ai-botkit-suggestions-carousel {
  overflow: hidden;
}

.ai-botkit-suggestions-track {
  display: flex;
  gap: 12px;
  transition: transform 0.3s ease;
}

.ai-botkit-suggestions-indicators {
  display: flex;
  justify-content: center;
  gap: 6px;
  margin-top: 12px;
}

.ai-botkit-suggestions-indicators button {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #e5e7eb;
  border: none;
  cursor: pointer;
  transition: background 0.2s, transform 0.2s;
}

.ai-botkit-suggestions-indicators button.active {
  background: #1E3A8A;
  transform: scale(1.2);
}
```

### 7.5 Grid Layout (Alternative)

For wider chat containers or full-page displays:

```css
.ai-botkit-suggestions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 16px;
  margin-top: 12px;
}

@media (max-width: 600px) {
  .ai-botkit-suggestions-grid {
    grid-template-columns: 1fr;
  }
}
```

### 7.6 Personalization Indicators

#### Visual Indicators

```html
<!-- Personalization badge on card -->
<span class="ai-botkit-suggestion-badge personalized">
  <i class="ti ti-user-check"></i> For You
</span>

<!-- Reason tooltip -->
<div class="ai-botkit-personalization-reason">
  <i class="ti ti-info-circle"></i>
  <span class="ai-botkit-reason-tooltip">
    Based on your browsing history
  </span>
</div>
```

#### Personalization Reasons

| Trigger | Badge Text | Tooltip |
|---------|-----------|---------|
| Conversation | "Matches Your Interest" | "Based on your conversation" |
| Browsing | "Recently Viewed" | "You viewed similar items" |
| Purchase History | "You Might Like" | "Based on your purchases" |
| Explicit Request | "Best Match" | "Matches your request" |

### 7.7 Add to Cart / Enroll Button States

```css
/* Loading state */
.ai-botkit-add-to-cart.loading {
  pointer-events: none;
  opacity: 0.7;
}

.ai-botkit-add-to-cart.loading::before {
  content: '';
  width: 14px;
  height: 14px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: white;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin-right: 6px;
}

/* Success state */
.ai-botkit-add-to-cart.success {
  background: #059669;
}

.ai-botkit-add-to-cart.success::before {
  content: '\2713'; /* Checkmark */
  margin-right: 6px;
}

/* Already enrolled / in cart */
.ai-botkit-add-to-cart.in-cart,
.ai-botkit-enroll-btn.enrolled {
  background: #059669;
  cursor: default;
}
```

---

## 8. Accessibility Requirements

### 8.1 WCAG 2.1 AA Compliance Checklist

#### Perceivable

| Requirement | Implementation |
|-------------|----------------|
| **1.1.1 Non-text Content** | All images have alt text; decorative icons use `aria-hidden="true"` |
| **1.3.1 Info and Relationships** | Proper heading hierarchy; form labels associated with inputs |
| **1.3.2 Meaningful Sequence** | DOM order matches visual order; logical tab sequence |
| **1.4.1 Use of Color** | Color not sole indicator; icons/text accompany color cues |
| **1.4.3 Contrast (Minimum)** | Text: 4.5:1; Large text: 3:1; UI components: 3:1 |
| **1.4.4 Resize Text** | Text resizable to 200% without loss of content |
| **1.4.10 Reflow** | Content reflows at 320px width without horizontal scroll |
| **1.4.11 Non-text Contrast** | UI components have 3:1 contrast ratio |

#### Operable

| Requirement | Implementation |
|-------------|----------------|
| **2.1.1 Keyboard** | All functionality accessible via keyboard |
| **2.1.2 No Keyboard Trap** | Focus can move away from all components |
| **2.4.1 Bypass Blocks** | Skip links provided for repetitive content |
| **2.4.3 Focus Order** | Logical focus order follows visual layout |
| **2.4.6 Headings and Labels** | Descriptive headings and labels |
| **2.4.7 Focus Visible** | Clear focus indicator (2px outline minimum) |

#### Understandable

| Requirement | Implementation |
|-------------|----------------|
| **3.1.1 Language of Page** | `lang` attribute on HTML element |
| **3.2.1 On Focus** | No context change on focus alone |
| **3.2.2 On Input** | No unexpected context change on input |
| **3.3.1 Error Identification** | Errors clearly identified in text |
| **3.3.2 Labels or Instructions** | Inputs have visible labels or instructions |

#### Robust

| Requirement | Implementation |
|-------------|----------------|
| **4.1.1 Parsing** | Valid HTML; unique IDs |
| **4.1.2 Name, Role, Value** | Custom components have proper ARIA |

### 8.2 Keyboard Navigation Patterns

#### Chat Widget

| Key | Action |
|-----|--------|
| `Tab` | Move to next interactive element |
| `Shift+Tab` | Move to previous interactive element |
| `Enter` | Activate button/link; send message |
| `Escape` | Close panel/modal; clear selection |
| `Arrow Up/Down` | Navigate message history (when input focused) |

#### History Panel

| Key | Action |
|-----|--------|
| `Arrow Up/Down` | Navigate conversation list |
| `Enter` | Select conversation |
| `Delete` | Delete conversation (with confirmation) |
| `Escape` | Close history panel |

#### Carousel

| Key | Action |
|-----|--------|
| `Arrow Left/Right` | Navigate slides |
| `Home` | Go to first slide |
| `End` | Go to last slide |

### 8.3 ARIA Patterns

#### Chat Message

```html
<div
  class="ai-botkit-message assistant"
  role="listitem"
  aria-label="Assistant message"
>
  <div class="ai-botkit-message-content">
    <div class="ai-botkit-message-text" aria-live="polite">
      Message content here
    </div>
  </div>
</div>
```

#### History Panel

```html
<div
  class="ai-botkit-history-panel"
  role="dialog"
  aria-modal="true"
  aria-labelledby="history-title"
>
  <h2 id="history-title">Chat History</h2>
  <div role="listbox" aria-label="Conversations">
    <div role="option" aria-selected="true">...</div>
    <div role="option" aria-selected="false">...</div>
  </div>
</div>
```

#### Search Input

```html
<div role="search">
  <label for="chat-search" class="sr-only">Search conversations</label>
  <input
    type="search"
    id="chat-search"
    aria-describedby="search-hint"
    aria-controls="search-results"
  />
  <div id="search-hint" class="sr-only">
    Type to search your conversation history
  </div>
  <div
    id="search-results"
    role="listbox"
    aria-live="polite"
    aria-label="Search results"
  >
    <!-- Results populated dynamically -->
  </div>
</div>
```

#### Lightbox

```html
<div
  class="ai-botkit-lightbox"
  role="dialog"
  aria-modal="true"
  aria-labelledby="lightbox-title"
>
  <h2 id="lightbox-title" class="sr-only">Image viewer</h2>
  <img src="..." alt="Full description of image" />
  <button aria-label="Close image viewer">Close</button>
</div>
```

### 8.4 Screen Reader Announcements

```javascript
// Announce new messages
function announceMessage(role, content) {
  const announcement = document.createElement('div');
  announcement.setAttribute('role', 'status');
  announcement.setAttribute('aria-live', 'polite');
  announcement.className = 'sr-only';
  announcement.textContent = `${role} says: ${content}`;
  document.body.appendChild(announcement);
  setTimeout(() => announcement.remove(), 1000);
}

// Announce loading states
function announceLoading() {
  const announcement = document.createElement('div');
  announcement.setAttribute('role', 'alert');
  announcement.className = 'sr-only';
  announcement.textContent = 'Loading conversation...';
  document.body.appendChild(announcement);
  setTimeout(() => announcement.remove(), 1000);
}

// Announce search results
function announceSearchResults(count) {
  const announcement = document.createElement('div');
  announcement.setAttribute('role', 'status');
  announcement.setAttribute('aria-live', 'polite');
  announcement.className = 'sr-only';
  announcement.textContent = `Found ${count} ${count === 1 ? 'result' : 'results'}`;
  document.body.appendChild(announcement);
  setTimeout(() => announcement.remove(), 1000);
}
```

### 8.5 Color Contrast Reference

| Element | Foreground | Background | Ratio | Pass |
|---------|------------|------------|-------|------|
| Primary button text | #FFFFFF | #1E3A8A | 8.59:1 | AAA |
| Body text | #1d2327 | #FFFFFF | 14.7:1 | AAA |
| Secondary text | #6b7280 | #FFFFFF | 5.38:1 | AA |
| Muted text | #71717A | #FFFFFF | 4.83:1 | AA |
| Error text | #DE554B | #FFFFFF | 4.63:1 | AA |
| Success badge | #00BFA6 | #DAF9DF | 3.12:1 | AA (large) |
| Link text | #1E3A8A | #FFFFFF | 8.59:1 | AAA |
| Focus outline | #1E3A8A | #FFFFFF | 8.59:1 | AAA |

---

## 9. Responsive Design Guidelines

### 9.1 Breakpoints

| Breakpoint | Width | Target |
|------------|-------|--------|
| Mobile S | 320px | Small phones |
| Mobile M | 375px | Standard phones |
| Mobile L | 425px | Large phones |
| Tablet | 768px | Tablets portrait |
| Laptop | 1024px | Tablets landscape, small laptops |
| Desktop | 1200px | Standard desktops |

### 9.2 Widget Responsive Behavior

#### Desktop (> 768px)

```
Widget position: Fixed, bottom-right
Widget size: 424px x calc(100vh - 120px)
History panel: Slide-out sidebar, 280px
Suggestions: Carousel, 2-3 cards visible
```

#### Tablet (768px - 425px)

```
Widget position: Fixed, bottom-right
Widget size: 90vw x calc(100vh - 100px)
History panel: Slide-out sidebar, 260px
Suggestions: Carousel, 1-2 cards visible
```

#### Mobile (< 425px)

```
Widget position: Fixed, full-screen
Widget size: 100vw x 100vh
History panel: Full-width overlay
Suggestions: Single card carousel
```

### 9.3 Component-Specific Breakpoints

#### Chat History Panel

```css
/* Desktop */
.ai-botkit-history-panel {
  width: 280px;
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
}

/* Mobile */
@media (max-width: 600px) {
  .ai-botkit-history-panel {
    width: 100%;
    position: fixed;
    z-index: 100;
  }
}
```

#### Search Results

```css
/* Desktop */
.ai-botkit-search-results {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
}

/* Tablet */
@media (max-width: 1024px) {
  .ai-botkit-search-results {
    grid-template-columns: 1fr;
  }
}

/* Mobile */
@media (max-width: 600px) {
  .ai-botkit-search-result {
    padding: 12px;
  }

  .ai-botkit-search-result-actions {
    flex-direction: column;
  }
}
```

#### Product/Course Cards

```css
/* Desktop */
.ai-botkit-suggestions-track {
  display: flex;
  gap: 16px;
}

.ai-botkit-suggestion-card {
  min-width: 280px;
  max-width: 280px;
}

/* Mobile */
@media (max-width: 600px) {
  .ai-botkit-suggestion-card {
    min-width: calc(100vw - 64px);
    max-width: calc(100vw - 64px);
  }

  .ai-botkit-suggestion-image {
    height: 140px;
  }
}
```

#### Template Builder

```css
/* Desktop */
.ai-botkit-template-editor {
  display: grid;
  grid-template-columns: 240px 1fr;
  gap: 24px;
}

/* Tablet */
@media (max-width: 1024px) {
  .ai-botkit-template-editor {
    grid-template-columns: 1fr;
  }

  .ai-botkit-template-sections {
    display: flex;
    overflow-x: auto;
    gap: 8px;
    padding-bottom: 8px;
  }
}
```

### 9.4 Touch-Friendly Design

#### Touch Targets

```css
/* Minimum touch target: 44x44px */
.ai-botkit-btn,
.ai-botkit-history-item,
.ai-botkit-carousel-prev,
.ai-botkit-carousel-next {
  min-height: 44px;
  min-width: 44px;
}

/* Adequate spacing between targets */
.ai-botkit-suggestion-actions {
  gap: 8px;
}
```

#### Swipe Gestures

```javascript
// Carousel swipe support
let touchStartX = 0;
let touchEndX = 0;

carousel.addEventListener('touchstart', (e) => {
  touchStartX = e.changedTouches[0].screenX;
});

carousel.addEventListener('touchend', (e) => {
  touchEndX = e.changedTouches[0].screenX;
  handleSwipe();
});

function handleSwipe() {
  const threshold = 50;
  const diff = touchStartX - touchEndX;

  if (Math.abs(diff) > threshold) {
    if (diff > 0) {
      // Swipe left - next slide
      nextSlide();
    } else {
      // Swipe right - previous slide
      prevSlide();
    }
  }
}
```

---

## 10. Implementation Notes

### 10.1 CSS Class Naming Convention

Follow existing `ai-botkit-` prefix pattern:

```
ai-botkit-[component]
ai-botkit-[component]-[element]
ai-botkit-[component]-[modifier]
```

**Examples:**
```css
.ai-botkit-history-panel
.ai-botkit-history-panel-header
.ai-botkit-history-panel.open

.ai-botkit-suggestion-card
.ai-botkit-suggestion-card-image
.ai-botkit-suggestion-card.highlighted
```

### 10.2 JavaScript Module Structure

```javascript
// Suggested module organization
AI_BotKit.History = {
  init: function() {},
  open: function() {},
  close: function() {},
  loadConversations: function() {},
  switchConversation: function(id) {},
  deleteConversation: function(id) {}
};

AI_BotKit.Search = {
  init: function() {},
  search: function(query, filters) {},
  highlightResults: function(text, query) {},
  clearSearch: function() {}
};

AI_BotKit.Media = {
  init: function() {},
  renderImage: function(url, alt) {},
  renderVideo: function(embedUrl) {},
  renderFile: function(file) {},
  renderLinkPreview: function(url) {},
  openLightbox: function(imageUrl) {}
};

AI_BotKit.Suggestions = {
  init: function() {},
  render: function(products) {},
  addToCart: function(productId) {},
  enroll: function(courseId) {},
  initCarousel: function() {}
};

AI_BotKit.Export = {
  init: function() {},
  generatePDF: function(conversationId, options) {},
  showProgress: function() {},
  download: function(blob, filename) {}
};
```

### 10.3 Integration with Existing Components

#### Extending Chat Widget

```javascript
// Add history button to existing header
const headerActions = document.querySelector('.ai-botkit-chat-actions');
if (headerActions) {
  const historyBtn = document.createElement('button');
  historyBtn.className = 'ai-botkit-history-btn';
  historyBtn.setAttribute('aria-label', 'View chat history');
  historyBtn.innerHTML = '<i class="ti ti-history"></i>';
  historyBtn.addEventListener('click', AI_BotKit.History.open);
  headerActions.prepend(historyBtn);
}
```

#### Message Rendering Extension

```javascript
// Extend message rendering for media
AI_BotKit.Chat.renderMessage = function(message) {
  const container = createMessageContainer(message);

  // Check for media attachments
  if (message.media && message.media.length > 0) {
    message.media.forEach(media => {
      switch (media.type) {
        case 'image':
          container.appendChild(AI_BotKit.Media.renderImage(media));
          break;
        case 'video':
          container.appendChild(AI_BotKit.Media.renderVideo(media));
          break;
        case 'file':
          container.appendChild(AI_BotKit.Media.renderFile(media));
          break;
        case 'link':
          container.appendChild(AI_BotKit.Media.renderLinkPreview(media));
          break;
      }
    });
  }

  // Check for product suggestions
  if (message.suggestions && message.suggestions.length > 0) {
    container.appendChild(AI_BotKit.Suggestions.render(message.suggestions));
  }

  return container;
};
```

### 10.4 Performance Considerations

#### Lazy Loading

```javascript
// Lazy load history conversations
const observer = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      loadMoreConversations();
    }
  });
});

observer.observe(document.querySelector('.ai-botkit-history-sentinel'));
```

#### Virtual Scrolling (for large histories)

```javascript
// Consider virtual scrolling for 100+ conversations
// Use libraries like virtual-scroller or implement custom
```

#### Image Optimization

```html
<!-- Use srcset for responsive images -->
<img
  src="product-small.jpg"
  srcset="product-small.jpg 280w,
          product-medium.jpg 560w,
          product-large.jpg 840w"
  sizes="(max-width: 600px) 100vw, 280px"
  loading="lazy"
  alt="Product description"
/>
```

### 10.5 Testing Checklist

#### Functional Testing

- [ ] History panel opens/closes correctly
- [ ] Conversations load and display properly
- [ ] Search returns accurate results
- [ ] Media types render correctly
- [ ] Lightbox opens and navigates
- [ ] Templates can be created/edited/applied
- [ ] PDF export generates correctly
- [ ] Product suggestions display and interact properly
- [ ] Add to cart / Enroll actions work

#### Accessibility Testing

- [ ] Keyboard navigation works for all features
- [ ] Screen reader announces dynamic content
- [ ] Focus management is correct for modals
- [ ] Color contrast meets WCAG AA
- [ ] Touch targets are adequate size

#### Responsive Testing

- [ ] Desktop (1200px+)
- [ ] Laptop (1024px)
- [ ] Tablet (768px)
- [ ] Mobile (375px)
- [ ] Small mobile (320px)

#### Browser Testing

- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)
- [ ] iOS Safari
- [ ] Chrome Mobile

---

## Appendix A: Icon Reference

Using Tabler Icons (already included in the plugin):

| Feature | Icon | Class |
|---------|------|-------|
| History | Clock | `ti ti-history` |
| Search | Magnifying glass | `ti ti-search` |
| Close | X | `ti ti-x` |
| Download | Arrow down | `ti ti-download` |
| File download | File with arrow | `ti ti-file-download` |
| External link | Arrow diagonal | `ti ti-external-link` |
| Expand | Arrows maximize | `ti ti-arrows-maximize` |
| Play video | Play circle | `ti ti-player-play-filled` |
| Previous | Chevron left | `ti ti-chevron-left` |
| Next | Chevron right | `ti ti-chevron-right` |
| Cart | Shopping cart | `ti ti-shopping-cart-plus` |
| School/Enroll | School icon | `ti ti-school` |
| Star filled | Star | `ti ti-star-filled` |
| Star half | Star half | `ti ti-star-half-filled` |
| Info | Info circle | `ti ti-info-circle` |
| User check | User verified | `ti ti-user-check` |
| Sparkles | Magic/AI | `ti ti-sparkles` |
| Messages | Chat bubbles | `ti ti-messages` |
| Dots menu | Three dots | `ti ti-dots-vertical` |
| Edit | Pencil | `ti ti-edit` |
| Copy | Duplicate | `ti ti-copy` |
| Eye/View | Eye | `ti ti-eye` |
| Trash/Delete | Trash can | `ti ti-trash` |
| Clock/Duration | Clock | `ti ti-clock` |
| Users | People | `ti ti-users` |
| Certificate | Award | `ti ti-certificate` |

---

## Appendix B: Animation Specifications

### Slide Animations

```css
/* Panel slide in */
@keyframes slideIn {
  from {
    transform: translateX(-100%);
    opacity: 0;
  }
  to {
    transform: translateX(0);
    opacity: 1;
  }
}

/* Panel slide out */
@keyframes slideOut {
  from {
    transform: translateX(0);
    opacity: 1;
  }
  to {
    transform: translateX(-100%);
    opacity: 0;
  }
}
```

### Fade Animations

```css
/* Fade in */
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

/* Fade in up */
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
```

### Loading Animations

```css
/* Skeleton shimmer */
@keyframes shimmer {
  0% {
    background-position: -200px 0;
  }
  100% {
    background-position: calc(200px + 100%) 0;
  }
}

/* Spinner */
@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* Typing dots */
@keyframes typing {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-4px); }
}
```

### Interaction Feedback

```css
/* Button press */
@keyframes buttonPress {
  0% { transform: scale(1); }
  50% { transform: scale(0.95); }
  100% { transform: scale(1); }
}

/* Success checkmark */
@keyframes checkmark {
  0% {
    stroke-dashoffset: 50;
  }
  100% {
    stroke-dashoffset: 0;
  }
}
```

---

*Document generated for AI BotKit Chatbot Phase 2 development*
*Review and update as implementation progresses*
