# Compatibility Assessment: Phase 2 Features

**Generated:** 2026-01-28
**Plugin:** AI BotKit Chatbot (KnowVault)
**Assessment Type:** Backward Compatibility and Breaking Change Analysis

---

## Risk Summary

| Risk Level | Count | Features |
|------------|-------|----------|
| HIGH | 0 | - |
| MEDIUM | 2 | Rich Media Support, Search Functionality |
| LOW | 4 | Chat History, Templates, Export, Suggestions |

---

## Overall Assessment

Phase 2 features are **highly compatible** with Phase 1. Most features are **additive** - they add new functionality without modifying existing behavior. No breaking changes are required for any feature.

| Assessment | Status |
|------------|--------|
| Database Schema Changes | ADDITIVE ONLY |
| API Changes | ADDITIVE ONLY |
| Breaking Changes | NONE REQUIRED |
| Migration Required | MINIMAL |

---

## Feature-by-Feature Compatibility Assessment

---

### 1. Chat History

**Risk Level:** LOW
**Breaking Change Risk:** NONE

#### Database Changes

| Change Type | Table | Change | Risk |
|-------------|-------|--------|------|
| NONE | - | No schema changes required | NONE |

**Notes:** All required data already exists in `ai_botkit_conversations` and `ai_botkit_messages` tables. No schema modifications needed.

#### API Changes

| Change Type | Endpoint | Change | Risk |
|-------------|----------|--------|------|
| MODIFY | `GET /conversations` | Add pagination, preview fields | LOW |
| MODIFY | `ai_botkit_get_history` | Return conversation list option | LOW |

**Backward Compatibility:**
- Existing endpoints continue to work
- New parameters are optional
- Default behavior unchanged

```php
// Current: returns messages for current conversation
// Modified: optionally return conversation list
add_action('wp_ajax_ai_botkit_get_history', function($request) {
    $list_mode = isset($_POST['list_conversations']) && $_POST['list_conversations'];

    if ($list_mode) {
        // NEW: Return conversation list
        return get_user_conversations();
    } else {
        // EXISTING: Return messages (unchanged)
        return get_conversation_messages();
    }
});
```

#### Breaking Changes

| Change | Impact | Mitigation |
|--------|--------|------------|
| None | - | - |

#### Risk Assessment

| Factor | Assessment |
|--------|------------|
| Existing functionality affected | NO |
| Data migration required | NO |
| API contract changed | NO (additive only) |
| Frontend changes required | YES (new UI component) |
| **Overall Risk** | **LOW** |

---

### 2. Search Functionality

**Risk Level:** MEDIUM
**Breaking Change Risk:** NONE

#### Database Changes

| Change Type | Table | Change | Risk |
|-------------|-------|--------|------|
| ADD INDEX | `ai_botkit_messages` | Add FULLTEXT index on `content` | LOW |
| ADD INDEX | `ai_botkit_messages` | Add index on `created_at` | LOW |
| ADD INDEX | `ai_botkit_conversations` | Add index on `user_id, created_at` | LOW |

**Schema Change Script:**
```sql
-- Add fulltext index for message search
ALTER TABLE {prefix}ai_botkit_messages
ADD FULLTEXT INDEX idx_message_content (content);

-- Add index for date range queries
ALTER TABLE {prefix}ai_botkit_messages
ADD INDEX idx_message_date (created_at);

-- Add composite index for user conversation queries
ALTER TABLE {prefix}ai_botkit_conversations
ADD INDEX idx_user_date (user_id, created_at);
```

**Risk Analysis:**
- Adding indexes does NOT affect existing queries
- FULLTEXT index creation may briefly lock table on large datasets
- Recommend running during low-traffic period

#### API Changes

| Change Type | Endpoint | Change | Risk |
|-------------|----------|--------|------|
| ADD | `GET /conversations/search` | New search endpoint | NONE |
| ADD | `ai_botkit_search_messages` | New AJAX action | NONE |

**New Endpoints (no existing changes):**
```php
// New REST endpoint
register_rest_route('ai-botkit/v1', '/conversations/search', [
    'methods' => 'GET',
    'callback' => 'handle_search',
    'permission_callback' => 'check_history_permission',
    'args' => [
        'query' => ['required' => true, 'type' => 'string'],
        'chatbot_id' => ['required' => false, 'type' => 'integer'],
        'date_from' => ['required' => false, 'type' => 'string'],
        'date_to' => ['required' => false, 'type' => 'string'],
        'page' => ['required' => false, 'type' => 'integer', 'default' => 1],
        'per_page' => ['required' => false, 'type' => 'integer', 'default' => 20],
    ]
]);
```

#### Breaking Changes

| Change | Impact | Mitigation |
|--------|--------|------------|
| None | - | - |

#### Risk Assessment

| Factor | Assessment |
|--------|------------|
| Existing functionality affected | NO |
| Data migration required | NO |
| API contract changed | NO (new endpoints only) |
| Performance impact | POSSIBLE (monitor query times) |
| **Overall Risk** | **MEDIUM** (due to index creation) |

#### Mitigation Strategies

1. **Index Creation:**
   - Run index creation in upgrade routine
   - Use `dbDelta()` for safe index management
   - Log index creation time

2. **Performance:**
   - Implement search result caching
   - Limit search results per query
   - Add query timeout protection

---

### 3. Rich Media Support

**Risk Level:** MEDIUM
**Breaking Change Risk:** NONE

#### Database Changes

| Change Type | Table | Change | Risk |
|-------------|-------|--------|------|
| NONE | `ai_botkit_messages` | Use existing `metadata` JSON column | NONE |
| ADD TABLE (optional) | `ai_botkit_attachments` | Separate attachment tracking | LOW |

**Option 1: Use Existing Metadata Column (Recommended)**
```json
// messages.metadata structure extension
{
  "tokens": 150,
  "model": "gpt-4",
  "attachments": [
    {
      "id": "att_12345",
      "type": "image",
      "url": "/wp-content/uploads/ai-botkit/media/image.jpg",
      "filename": "image.jpg",
      "mime_type": "image/jpeg",
      "size": 102400
    }
  ]
}
```

**Option 2: New Attachments Table (Optional for Scale)**
```sql
CREATE TABLE {prefix}ai_botkit_attachments (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    message_id bigint(20) NOT NULL,
    type varchar(20) NOT NULL,
    url varchar(500) NOT NULL,
    filename varchar(255) NOT NULL,
    mime_type varchar(100),
    size bigint(20),
    metadata json,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY message_id (message_id)
);
```

#### API Changes

| Change Type | Endpoint | Change | Risk |
|-------------|----------|--------|------|
| MODIFY | `POST /chat/message` | Accept file attachments | LOW |
| ADD | `ai_botkit_upload_media` | New AJAX for media upload | NONE |

**Backward Compatibility for Chat Message:**
```php
// Current: text-only messages
// Modified: text + optional attachments
public function handle_chat_message($request) {
    $message = sanitize_textarea_field($request['message']);

    // NEW: Handle attachments if present
    $attachments = [];
    if (!empty($_FILES['attachments'])) {
        $attachments = $this->process_media_uploads($_FILES['attachments']);
    }

    // Existing message processing continues unchanged
    $response = $this->rag_engine->generate_response($message, $context);

    // Return response (with attachment data if present)
    return [
        'response' => $response,
        'attachments' => $attachments  // NEW field, doesn't break existing
    ];
}
```

#### Breaking Changes

| Change | Impact | Mitigation |
|--------|--------|------------|
| None | - | - |

#### Risk Assessment

| Factor | Assessment |
|--------|------------|
| Existing functionality affected | NO |
| Data migration required | NO |
| API contract changed | NO (additive response fields) |
| Security considerations | YES (file upload validation) |
| **Overall Risk** | **MEDIUM** (security review needed) |

#### Security Requirements

| Requirement | Implementation |
|-------------|---------------|
| File type whitelist | Only allow image/video MIME types |
| File size limit | Max 10MB per file |
| Filename sanitization | Use `sanitize_file_name()` |
| Upload directory | Separate from documents |
| Direct access prevention | .htaccess deny, or use WP attachment |

**Security Checklist:**
```php
private function validate_media_upload($file) {
    // Whitelist allowed MIME types
    $allowed_types = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'video/mp4', 'video/webm',
        'application/pdf'
    ];

    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('File type not allowed');
    }

    // Size limit (10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        throw new Exception('File too large');
    }

    // Verify MIME type matches extension
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $actual_type = finfo_file($finfo, $file['tmp_name']);
    if ($actual_type !== $file['type']) {
        throw new Exception('MIME type mismatch');
    }

    return true;
}
```

---

### 4. Conversation Templates

**Risk Level:** LOW
**Breaking Change Risk:** NONE

#### Database Changes

| Change Type | Table | Change | Risk |
|-------------|-------|--------|------|
| ADD TABLE | `ai_botkit_templates` | New template storage | NONE |

**New Table Schema:**
```sql
CREATE TABLE {prefix}ai_botkit_templates (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    slug varchar(100) NOT NULL,
    description text,
    category varchar(50),
    style json NOT NULL,
    messages_template json,
    system_prompt text,
    settings json,
    is_system tinyint(1) DEFAULT 0,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY slug (slug)
);
```

**Pre-populated System Templates:**
```sql
INSERT INTO {prefix}ai_botkit_templates (name, slug, category, is_system, style, messages_template) VALUES
('Customer Support', 'customer-support', 'support', 1, '{"primary_color": "#2196F3", ...}', '{"welcome": "How can I help you today?"}'),
('Lead Capture', 'lead-capture', 'marketing', 1, '{"primary_color": "#4CAF50", ...}', '{"welcome": "Hi! I can help answer your questions..."}'),
-- ... more templates
```

#### API Changes

| Change Type | Endpoint | Change | Risk |
|-------------|----------|--------|------|
| ADD | `GET /templates` | List available templates | NONE |
| ADD | `GET /templates/{id}` | Get template details | NONE |
| ADD | `POST /templates` | Create custom template | NONE |
| ADD | `POST /chatbots/apply-template` | Apply template to chatbot | NONE |
| ADD | Admin AJAX handlers | Template CRUD operations | NONE |

#### Breaking Changes

| Change | Impact | Mitigation |
|--------|--------|------------|
| None | - | - |

#### Risk Assessment

| Factor | Assessment |
|--------|------------|
| Existing functionality affected | NO |
| Data migration required | NO |
| API contract changed | NO (all new endpoints) |
| **Overall Risk** | **LOW** |

---

### 5. Chat Transcripts Export

**Risk Level:** LOW
**Breaking Change Risk:** NONE

#### Database Changes

| Change Type | Table | Change | Risk |
|-------------|-------|--------|------|
| NONE | - | No schema changes | NONE |

**Notes:** Export uses existing conversation and message data. No new storage required.

#### API Changes

| Change Type | Endpoint | Change | Risk |
|-------------|----------|--------|------|
| ADD | `GET /conversations/export` | Export endpoint | NONE |
| ADD | `ai_botkit_export_transcripts` | Admin AJAX action | NONE |

**New Export Endpoint:**
```php
register_rest_route('ai-botkit/v1', '/conversations/export', [
    'methods' => 'GET',
    'callback' => 'handle_export',
    'permission_callback' => 'check_export_permission',
    'args' => [
        'format' => ['required' => true, 'enum' => ['csv', 'pdf']],
        'conversation_ids' => ['required' => false, 'type' => 'array'],
        'chatbot_id' => ['required' => false, 'type' => 'integer'],
        'date_from' => ['required' => false, 'type' => 'string'],
        'date_to' => ['required' => false, 'type' => 'string'],
    ]
]);
```

#### New Dependencies

| Dependency | Purpose | License |
|------------|---------|---------|
| dompdf/dompdf | PDF generation | LGPL-2.1 |

**Composer Addition:**
```json
{
    "require": {
        "dompdf/dompdf": "^2.0"
    }
}
```

#### Breaking Changes

| Change | Impact | Mitigation |
|--------|--------|------------|
| None | - | - |

#### Risk Assessment

| Factor | Assessment |
|--------|------------|
| Existing functionality affected | NO |
| Data migration required | NO |
| API contract changed | NO (new endpoint) |
| New dependency | YES (dompdf) |
| **Overall Risk** | **LOW** |

---

### 6. LMS/WooCommerce Product Suggestions

**Risk Level:** LOW
**Breaking Change Risk:** NONE

#### Database Changes

| Change Type | Table | Change | Risk |
|-------------|-------|--------|------|
| ADD TABLE (optional) | `ai_botkit_user_interactions` | Track user behavior | LOW |
| ADD COLUMN (optional) | `ai_botkit_analytics` | Add `suggestion_data` | LOW |

**User Interactions Table (for Recommendations):**
```sql
CREATE TABLE {prefix}ai_botkit_user_interactions (
    id bigint(20) NOT NULL AUTO_INCREMENT,
    user_id bigint(20),
    guest_id varchar(64),
    interaction_type enum('view', 'click', 'add_to_cart', 'purchase', 'enroll'),
    content_type enum('product', 'course'),
    content_id bigint(20) NOT NULL,
    chatbot_id bigint(20),
    metadata json,
    created_at datetime DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY content (content_type, content_id),
    KEY created (created_at)
);
```

#### API Changes

| Change Type | Endpoint | Change | Risk |
|-------------|----------|--------|------|
| MODIFY | `POST /chat/message` | Response includes suggestions | LOW |
| ADD | `POST /suggestions/track` | Track suggestion clicks | NONE |

**Backward Compatible Response Enhancement:**
```php
// Current response
{
    "response": "Here are some courses you might like...",
    "metadata": { "tokens": 150 }
}

// Enhanced response (additive)
{
    "response": "Here are some courses you might like...",
    "metadata": { "tokens": 150 },
    "suggestions": [  // NEW field
        {
            "type": "course",
            "id": 123,
            "title": "Advanced PHP",
            "price": "$49",
            "action_url": "/enroll/123",
            "action_text": "Enroll Now"
        }
    ]
}
```

#### Breaking Changes

| Change | Impact | Mitigation |
|--------|--------|------------|
| None | - | - |

#### Integration Points

| Integration | Current Status | Changes Needed |
|-------------|---------------|----------------|
| WooCommerce_Assistant | EXISTS | Extend with recommendation logic |
| LearnDash integration | EXISTS | Add course recommendation method |
| ai_botkit_pre_response filter | EXISTS | Use for injecting suggestions |

#### Risk Assessment

| Factor | Assessment |
|--------|------------|
| Existing functionality affected | NO |
| Data migration required | NO |
| API contract changed | NO (additive response fields) |
| Third-party API dependency | WooCommerce, LearnDash (already integrated) |
| **Overall Risk** | **LOW** |

---

## Migration Requirements

### Upgrade Routine

All Phase 2 database changes should be handled in a single upgrade routine:

```php
function ai_botkit_upgrade_to_phase2() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    // 1. Add search indexes
    $wpdb->query("ALTER TABLE {$wpdb->prefix}ai_botkit_messages
                  ADD FULLTEXT INDEX idx_message_content (content)");

    // 2. Create templates table
    $sql_templates = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ai_botkit_templates (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        slug varchar(100) NOT NULL,
        description text,
        category varchar(50),
        style json NOT NULL,
        messages_template json,
        system_prompt text,
        settings json,
        is_system tinyint(1) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY slug (slug)
    ) $charset_collate;";

    // 3. Create user interactions table (optional)
    $sql_interactions = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ai_botkit_user_interactions (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20),
        guest_id varchar(64),
        interaction_type varchar(20) NOT NULL,
        content_type varchar(20) NOT NULL,
        content_id bigint(20) NOT NULL,
        chatbot_id bigint(20),
        metadata json,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY content (content_type, content_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_templates);
    dbDelta($sql_interactions);

    // 4. Insert system templates
    ai_botkit_insert_system_templates();

    // 5. Update version
    update_option('ai_botkit_version', '2.0.0');
}
```

### Rollback Plan

```php
function ai_botkit_rollback_phase2() {
    global $wpdb;

    // Remove Phase 2 tables (careful - data loss)
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ai_botkit_templates");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ai_botkit_user_interactions");

    // Remove indexes (optional, doesn't affect functionality)
    $wpdb->query("ALTER TABLE {$wpdb->prefix}ai_botkit_messages
                  DROP INDEX idx_message_content");

    // Revert version
    update_option('ai_botkit_version', '1.0.0');
}
```

---

## Regression Test Requirements

Phase 2 must include regression tests for all Phase 1 functionality:

### Critical Phase 1 Paths to Test

| Test Area | Test Cases |
|-----------|------------|
| **Chat Functionality** | Send message, receive response, streaming works |
| **Conversation Persistence** | Messages saved, history retrievable |
| **RAG Pipeline** | Document processing, embedding, retrieval |
| **Rate Limiting** | Limits enforced, usage tracked |
| **Authentication** | Capabilities enforced, guest access works |
| **Integrations** | LearnDash sync, WooCommerce sync |

### Test Checklist

```markdown
## Phase 1 Regression Tests

### Chat Core
- [ ] Send text message via shortcode
- [ ] Receive AI response
- [ ] Streaming response displays correctly
- [ ] Conversation persists across page loads
- [ ] Guest users can chat (IP tracked)
- [ ] Logged-in users have history

### Document Processing
- [ ] PDF upload works
- [ ] URL import works
- [ ] WordPress content import works
- [ ] Embeddings generated
- [ ] Vector search returns results

### Rate Limiting
- [ ] Token limit enforced
- [ ] Message limit enforced
- [ ] Limits reset daily
- [ ] Usage stats accurate

### Admin Functions
- [ ] Create chatbot
- [ ] Edit chatbot
- [ ] Delete chatbot
- [ ] Manage knowledge base
- [ ] View analytics

### Integrations
- [ ] LearnDash content syncs on save
- [ ] WooCommerce products sync on save
- [ ] Enrollment-aware context works
- [ ] Shopping assistant intent detection works
```

---

## Compatibility Matrix

### WordPress Compatibility

| Component | WP 5.8 | WP 6.0 | WP 6.1+ |
|-----------|--------|--------|---------|
| Chat History | Yes | Yes | Yes |
| Search | Yes | Yes | Yes |
| Rich Media | Yes | Yes | Yes |
| Templates | Yes | Yes | Yes |
| Export | Yes | Yes | Yes |
| Suggestions | Yes | Yes | Yes |

### PHP Compatibility

| Component | PHP 7.4 | PHP 8.0 | PHP 8.1+ |
|-----------|---------|---------|----------|
| Chat History | Yes | Yes | Yes |
| Search | Yes | Yes | Yes |
| Rich Media | Yes | Yes | Yes |
| Templates | Yes | Yes | Yes |
| Export (dompdf) | Yes | Yes | Yes |
| Suggestions | Yes | Yes | Yes |

### Plugin Compatibility

| Integration | LearnDash 4.x | WooCommerce 7.x | WooCommerce 8.x |
|-------------|---------------|-----------------|-----------------|
| Suggestions | Yes | Yes | Yes |
| Content Sync | Yes | Yes | Yes |

---

## Summary

| Feature | DB Changes | API Changes | Breaking Changes | Risk Level |
|---------|------------|-------------|------------------|------------|
| Chat History | None | Modify existing (additive) | None | LOW |
| Search | Add indexes | New endpoints | None | MEDIUM |
| Rich Media | None (use metadata) | Modify + new | None | MEDIUM |
| Templates | New table | New endpoints | None | LOW |
| Export | None | New endpoints | None | LOW |
| Suggestions | New table (optional) | Modify (additive) | None | LOW |

**Overall Assessment:** Phase 2 features can be implemented with **zero breaking changes**. All modifications are additive or use existing extension points. Backward compatibility is maintained throughout.

---

*Report generated by Gap Analyzer agent*
*All changes should be reviewed before implementation*
