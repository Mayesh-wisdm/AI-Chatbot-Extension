# KnowVault Phase 2 Deployment Checklist

**Version:** 2.0.0
**Release Date:** [To be determined]
**Document Version:** 1.0

---

## Table of Contents

1. [Pre-Deployment Verification](#pre-deployment-verification)
2. [Database Migration](#database-migration)
3. [Configuration Requirements](#configuration-requirements)
4. [Deployment Steps](#deployment-steps)
5. [Post-Deployment Verification](#post-deployment-verification)
6. [Rollback Procedure](#rollback-procedure)
7. [Monitoring](#monitoring)

---

## Pre-Deployment Verification

### Code Quality Gates

- [ ] All PHPCS checks pass (WordPress Coding Standards)
- [ ] PHPStan static analysis passes (Level 5)
- [ ] PHP Compatibility check passes (7.4-8.2)
- [ ] Security scan completes with no critical issues

### Test Suite Results

- [ ] **PHPUnit Integration Tests**
  - [ ] Database tests pass
  - [ ] AJAX handler tests pass
  - [ ] REST API tests pass
  - [ ] Hook integration tests pass

- [ ] **E2E Tests (Playwright)**
  - [ ] Admin interface tests pass
  - [ ] Chatbot widget tests pass
  - [ ] Knowledge base management tests pass

- [ ] **Manual Testing**
  - [ ] Fresh installation tested
  - [ ] Upgrade from 1.x tested
  - [ ] Multisite compatibility verified
  - [ ] Cross-browser testing (Chrome, Firefox, Safari)

### Version Verification

- [ ] Plugin header version updated to 2.0.0
- [ ] `AI_BOTKIT_VERSION` constant set to '2.0.0'
- [ ] readme.txt stable tag updated to 2.0.0
- [ ] CHANGELOG.md updated with Phase 2 changes

### Security Review

- [ ] Security audit report reviewed (reports/PHASE2_SECURITY_AUDIT.md)
- [ ] All SQL queries use prepared statements
- [ ] All user inputs sanitized
- [ ] All outputs properly escaped
- [ ] Nonce verification on all forms
- [ ] Capability checks on all admin actions
- [ ] Rate limiting configured for API endpoints

---

## Database Migration

### Phase 2 Database Changes

The following database changes are introduced in Phase 2:

#### New Tables
| Table | Purpose | Migration Status |
|-------|---------|------------------|
| `{prefix}ai_botkit_migrations` | Track migration versions | [ ] Verified |
| `{prefix}ai_botkit_cache_stats` | Cache performance metrics | [ ] Verified |

#### Modified Tables
| Table | Changes | Migration Status |
|-------|---------|------------------|
| `{prefix}ai_botkit_conversations` | New indexes for performance | [ ] Verified |
| `{prefix}ai_botkit_knowledge_base` | Additional metadata columns | [ ] Verified |

### Migration Verification Steps

1. **Pre-Migration Backup**
   ```bash
   # Export current database
   wp db export backup-pre-phase2-$(date +%Y%m%d).sql
   ```

2. **Run Migration**
   - [ ] Migration runs automatically on plugin activation
   - [ ] Migration version recorded in database
   - [ ] No errors in PHP error log

3. **Verify Migration**
   ```sql
   -- Check migration table exists
   SHOW TABLES LIKE '%ai_botkit_migrations%';

   -- Verify migration version
   SELECT * FROM wp_ai_botkit_migrations ORDER BY version DESC LIMIT 5;

   -- Check new indexes
   SHOW INDEX FROM wp_ai_botkit_conversations;
   ```

### Data Integrity Checks

- [ ] Existing chatbot configurations preserved
- [ ] Conversation history intact
- [ ] Knowledge base embeddings accessible
- [ ] User settings maintained

---

## Configuration Requirements

### Required Environment Variables

| Variable | Description | Required |
|----------|-------------|----------|
| `AI_BOTKIT_DEBUG` | Enable debug logging | No (default: false) |
| `AI_BOTKIT_CACHE_DRIVER` | Cache backend (file/redis) | No (default: file) |

### API Keys Configuration

Verify the following API configurations in WordPress admin:

- [ ] **OpenAI API Key** - Valid and has sufficient quota
- [ ] **Anthropic API Key** - Valid (if using Claude)
- [ ] **Google Gemini API Key** - Valid (if using Gemini)
- [ ] **Pinecone API Key** - Valid (if using Pinecone)

### Server Requirements

| Requirement | Minimum | Recommended | Status |
|-------------|---------|-------------|--------|
| PHP Version | 7.4 | 8.2 | [ ] Verified |
| WordPress | 5.8 | 6.4+ | [ ] Verified |
| MySQL | 5.7 | 8.0 | [ ] Verified |
| Memory Limit | 128MB | 256MB | [ ] Verified |
| Max Execution Time | 60s | 120s | [ ] Verified |

### WordPress Configuration

```php
// Recommended wp-config.php settings for Phase 2

// Increase memory for knowledge base processing
define('WP_MEMORY_LIMIT', '256M');

// Enable debug logging (staging only)
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// Optional: Custom cache directory
// define('AI_BOTKIT_CACHE_DIR', '/path/to/cache');
```

---

## Deployment Steps

### Step 1: Create Release Branch

```bash
# From develop branch
git checkout develop
git pull origin develop
git checkout -b release/2.0.0
```

### Step 2: Update Version Numbers

Files to update:
- [ ] `ai-botkit-chatbot/knowVault.php` - Plugin header and constant
- [ ] `ai-botkit-chatbot/readme.txt` - Stable tag
- [ ] `ai-botkit-chatbot/CHANGELOG.md` - Add release notes

### Step 3: Run Final Tests

```bash
# Run all tests
cd tests
phpunit --configuration phpunit.xml

# Run E2E tests
cd e2e
npx playwright test
```

### Step 4: Create GitHub Release

```bash
# Tag the release
git tag -a v2.0.0 -m "Release version 2.0.0 - Phase 2"
git push origin v2.0.0

# Create release on GitHub
gh release create v2.0.0 \
  --title "KnowVault v2.0.0 - Phase 2" \
  --notes-file RELEASE_NOTES.md \
  --prerelease  # Remove for production release
```

### Step 5: GitHub Actions Deployment

The following workflows will run automatically:

1. **phpcs.yml** - Code standards verification
2. **phpunit.yml** - Unit and integration tests
3. **e2e.yml** - End-to-end tests
4. **security.yml** - Security scanning
5. **deploy.yml** - Build and release package

### Step 6: Manual Deployment (if needed)

```bash
# Download release package
wget https://github.com/wisdmlabs/knowvault/releases/download/v2.0.0/knowvault-2.0.0.zip

# Verify checksum
sha256sum -c knowvault-2.0.0.zip.sha256

# Deploy to WordPress
wp plugin install knowvault-2.0.0.zip --force
wp plugin activate knowvault
```

---

## Post-Deployment Verification

### Functional Verification

- [ ] Plugin activates without errors
- [ ] Admin menu appears correctly
- [ ] Dashboard loads and displays data
- [ ] Knowledge base management functional
- [ ] Chatbot creation/editing works
- [ ] Chat widget renders on frontend
- [ ] API integrations respond correctly

### Database Verification

```sql
-- Verify all tables exist
SELECT table_name
FROM information_schema.tables
WHERE table_name LIKE '%ai_botkit%';

-- Check for orphaned records
SELECT COUNT(*) FROM wp_ai_botkit_conversations
WHERE chatbot_id NOT IN (SELECT id FROM wp_ai_botkit_chatbots);
```

### Performance Verification

- [ ] Page load time < 3 seconds
- [ ] Admin AJAX responses < 1 second
- [ ] Knowledge base queries < 500ms
- [ ] No PHP memory warnings

### Error Log Check

```bash
# Check WordPress error log
tail -100 /path/to/wordpress/wp-content/debug.log | grep -i "ai_botkit\|knowvault"

# Check PHP error log
tail -100 /var/log/php/error.log | grep -i "ai_botkit\|knowvault"
```

---

## Rollback Procedure

### Immediate Rollback (< 1 hour after deployment)

1. **Restore Previous Plugin Version**
   ```bash
   # Deactivate current version
   wp plugin deactivate knowvault

   # Install previous version
   wp plugin install knowvault-1.1.0.zip --force
   wp plugin activate knowvault
   ```

2. **Restore Database (if needed)**
   ```bash
   # Restore from backup
   wp db import backup-pre-phase2-YYYYMMDD.sql
   ```

### Planned Rollback (> 1 hour after deployment)

1. **Assess Impact**
   - Document all issues encountered
   - Identify affected users/data
   - Determine if partial rollback is possible

2. **Create Rollback Plan**
   - Export current data that should be preserved
   - Plan communication to users
   - Schedule maintenance window

3. **Execute Rollback**
   ```bash
   # Backup current state
   wp db export backup-pre-rollback-$(date +%Y%m%d%H%M).sql

   # Deactivate and remove current version
   wp plugin deactivate knowvault

   # Install previous version
   wp plugin install knowvault-1.1.0.zip --force
   wp plugin activate knowvault

   # Run database rollback if needed
   wp eval "do_action('ai_botkit_rollback_database', '2.0.0', '1.1.0');"
   ```

4. **Verify Rollback**
   - [ ] Previous version active and functional
   - [ ] Data integrity verified
   - [ ] User access restored

### Rollback Contact

| Role | Contact | Escalation Time |
|------|---------|-----------------|
| Primary | Dev Team Lead | Immediate |
| Secondary | Technical Support | 15 minutes |
| Escalation | Project Manager | 30 minutes |

---

## Monitoring

### Key Metrics to Monitor

| Metric | Normal Range | Alert Threshold |
|--------|--------------|-----------------|
| Error Rate | < 0.1% | > 1% |
| Response Time | < 500ms | > 2000ms |
| Memory Usage | < 70% | > 90% |
| Database Queries | < 50/page | > 100/page |

### Monitoring Dashboard

- [ ] Set up error tracking (if using external service)
- [ ] Configure uptime monitoring
- [ ] Enable performance monitoring

### Post-Deployment Monitoring Schedule

| Timeframe | Action |
|-----------|--------|
| 0-1 hour | Monitor continuously for errors |
| 1-4 hours | Check every 15 minutes |
| 4-24 hours | Check every hour |
| 24-72 hours | Check every 4 hours |
| 72+ hours | Normal monitoring schedule |

### Health Check Endpoints

```bash
# Check plugin status via WP-CLI
wp option get ai_botkit_version
wp transient get ai_botkit_health_status

# Check database tables
wp db query "SELECT COUNT(*) FROM wp_ai_botkit_chatbots"
```

---

## Sign-Off

### Pre-Deployment Approval

| Role | Name | Date | Signature |
|------|------|------|-----------|
| Developer | | | |
| QA Lead | | | |
| Security | | | |
| Product Owner | | | |

### Post-Deployment Verification

| Role | Name | Date | Status |
|------|------|------|--------|
| DevOps | | | [ ] Verified |
| QA | | | [ ] Verified |
| Support | | | [ ] Verified |

---

**Document maintained by:** WisdmLabs Development Team
**Last updated:** 2026-01-29
