# Dependency Manifest - AI BotKit Chatbot Phase 2

**Project:** AI BotKit Chatbot
**Phase:** Phase 2 - Enhanced Features
**Document Version:** 1.0
**Generated:** 2026-01-28
**Status:** Final

---

## Table of Contents

1. [Overview](#1-overview)
2. [Current Dependencies (Phase 1)](#2-current-dependencies-phase-1)
3. [Phase 2 New Dependencies](#3-phase-2-new-dependencies)
4. [Dependency Matrix by Feature](#4-dependency-matrix-by-feature)
5. [WordPress Built-in Dependencies](#5-wordpress-built-in-dependencies)
6. [License Compatibility Analysis](#6-license-compatibility-analysis)
7. [PHP Version Requirements](#7-php-version-requirements)
8. [Installation Instructions](#8-installation-instructions)
9. [Dependency Tree](#9-dependency-tree)
10. [Security Considerations](#10-security-considerations)
11. [Recommendations](#11-recommendations)

---

## 1. Overview

### 1.1 Purpose

This document catalogs all dependencies required for AI BotKit Chatbot Phase 2 development, including:
- Composer PHP packages
- WordPress built-in libraries
- Optional enhancements

### 1.2 Dependency Categories

| Priority | Definition |
|----------|------------|
| **REQUIRED** | Must have for feature to function |
| **RECOMMENDED** | Improves functionality, but has fallback |
| **OPTIONAL** | Nice to have, provides enhancements |

### 1.3 Technical Stack

| Layer | Requirement |
|-------|-------------|
| **Platform** | WordPress 5.8+ |
| **PHP Version** | PHP 7.4+ (8.0+ recommended) |
| **MySQL Version** | MySQL 5.7+ (for FULLTEXT support) |
| **Node.js** | N/A (vanilla JavaScript frontend) |

---

## 2. Current Dependencies (Phase 1)

### 2.1 Existing composer.json

Location: `D:\Claude code projects\AI-Chatbot-Extension\ai-botkit-chatbot\includes\composer.json`

```json
{
    "name": "ishwar/php-rag",
    "description": "PHP RAG System",
    "type": "project",
    "require": {
        "guzzlehttp/guzzle": "^7.9",
        "phpfastcache/phpfastcache": "^9.2",
        "fivefilters/readability.php": ">=3.0",
        "smalot/pdfparser": "*"
    }
}
```

### 2.2 Current Dependency Summary

| Package | Version | Purpose | License | Status |
|---------|---------|---------|---------|--------|
| guzzlehttp/guzzle | ^7.9 | HTTP client for API calls | MIT | INSTALLED |
| phpfastcache/phpfastcache | ^9.2 | Caching layer | MIT | INSTALLED |
| fivefilters/readability.php | >=3.0 | HTML content extraction | Apache-2.0 | INSTALLED |
| smalot/pdfparser | * | PDF document parsing | LGPL-3.0 | INSTALLED |

### 2.3 Transitive Dependencies (Already Installed)

| Package | Required By | Purpose |
|---------|-------------|---------|
| guzzlehttp/psr7 | guzzle | PSR-7 HTTP message implementation |
| guzzlehttp/promises | guzzle | Promise library |
| psr/http-client | guzzle | HTTP client interface |
| psr/http-message | guzzle | HTTP message interface |
| psr/http-factory | guzzle | HTTP factory interface |
| psr/cache | phpfastcache | Cache interface |
| psr/simple-cache | phpfastcache | Simple cache interface |
| psr/log | phpfastcache | Logging interface |
| masterminds/html5 | readability.php | HTML5 parser |
| league/uri | readability.php | URI manipulation |
| league/uri-interfaces | readability.php | URI interfaces |
| symfony/polyfill-mbstring | pdfparser | Multibyte string support |
| symfony/deprecation-contracts | pdfparser | Deprecation handling |
| ralouphie/getallheaders | guzzle | PHP getallheaders polyfill |

---

## 3. Phase 2 New Dependencies

### 3.1 Complete Dependency Table

| Category | Package | Version | Source | Status | Required For | License | PHP Req |
|----------|---------|---------|--------|--------|--------------|---------|---------|
| **PHP Library** | dompdf/dompdf | ^2.0 | Packagist | REQUIRED | FR-241 PDF Export | LGPL-2.1 | PHP 7.1+ |
| **PHP Library** | ext-gd | * | PHP Extension | REQUIRED | dompdf image support | PHP License | N/A |
| **PHP Library** | ext-mbstring | * | PHP Extension | REQUIRED | dompdf unicode support | PHP License | N/A |
| **PHP Library** | ext-dom | * | PHP Extension | REQUIRED | dompdf HTML parsing | PHP License | N/A |
| **PHP Library** | james-heinrich/getid3 | ^1.9 | Packagist | OPTIONAL | FR-220 Media metadata | GPL-2.0+ | PHP 5.4+ |
| **WordPress** | oEmbed API | Built-in | WordPress | REQUIRED | FR-221 Video embeds | GPL-2.0+ | WP 5.8+ |
| **WordPress** | wp_privacy API | Built-in | WordPress | REQUIRED | FR-249 GDPR export | GPL-2.0+ | WP 4.9.6+ |
| **MySQL** | FULLTEXT Index | Built-in | MySQL | REQUIRED | FR-211 Search | N/A | MySQL 5.7+ |
| **PHP Library** | ZipArchive | Built-in | PHP Extension | REQUIRED | FR-246 Batch export | PHP License | PHP 7.0+ |

### 3.2 Detailed Package Analysis

---

#### 3.2.1 dompdf/dompdf (PDF Export)

**Purpose:** Generate PDF transcripts of chat conversations (FR-240 to FR-249)

**Package Details:**
- **Repository:** https://github.com/dompdf/dompdf
- **Packagist:** https://packagist.org/packages/dompdf/dompdf
- **Latest Stable:** 2.0.4 (as of 2025)
- **License:** LGPL-2.1

**Why dompdf over TCPDF:**
| Criteria | dompdf | TCPDF |
|----------|--------|-------|
| HTML/CSS Support | Excellent | Good |
| Ease of Use | High (HTML to PDF) | Medium (programmatic) |
| Modern CSS | CSS 2.1 + limited CSS3 | Limited |
| Font Support | True Type, WOFF | True Type, Core fonts |
| Memory Usage | Moderate | Higher |
| WordPress Integration | Common | Less common |
| Learning Curve | Low | Medium |
| License | LGPL-2.1 | LGPL-3.0 |

**Recommendation:** dompdf - better suited for HTML template-based PDF generation which aligns with our PDF transcript approach.

**Version Constraint:** `^2.0`
- Version 2.0+ requires PHP 7.1+
- Major improvements in CSS support and memory management
- Breaking changes from 1.x (namespace changes)

**Required PHP Extensions:**
```php
// Required for dompdf
ext-gd        // Image processing
ext-mbstring  // Unicode string handling
ext-dom       // HTML DOM parsing
```

**Transitive Dependencies (dompdf):**
| Package | Version | Purpose |
|---------|---------|---------|
| phenx/php-font-lib | ^0.5.4 | Font parsing |
| phenx/php-svg-lib | ^0.5.2 | SVG rendering |
| sabberworm/php-css-parser | ^8.4 | CSS parsing |

**Code Example:**
```php
use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html_content);
$dompdf->setPaper('letter', 'portrait');
$dompdf->render();

// Stream to browser
$dompdf->stream('transcript.pdf', ['Attachment' => true]);
```

---

#### 3.2.2 james-heinrich/getid3 (Media Metadata)

**Purpose:** Extract metadata from uploaded media files (FR-220 to FR-229)

**Package Details:**
- **Repository:** https://github.com/JamesHeinrich/getID3
- **Packagist:** https://packagist.org/packages/james-heinrich/getid3
- **Latest Stable:** 1.9.23
- **License:** GPL-2.0+

**Status:** OPTIONAL - WordPress provides basic media metadata; getID3 adds advanced audio/video metadata extraction.

**Use Cases:**
- Video duration extraction
- Audio bitrate/sample rate
- Image EXIF data
- File format verification

**Version Constraint:** `^1.9`

**Why Optional:**
WordPress's `wp_read_video_metadata()` and `wp_read_audio_metadata()` provide basic metadata. getID3 is only needed for:
- Advanced media analysis
- Format verification beyond MIME type
- Detailed codec information

**Recommendation:** Include in Phase 2.1 or later if advanced media analytics are needed.

---

#### 3.2.3 Alternative PDF Library: tecnickcom/tcpdf

**Package Details:**
- **Repository:** https://github.com/tecnickcom/TCPDF
- **Packagist:** https://packagist.org/packages/tecnickcom/tcpdf
- **License:** LGPL-3.0

**Status:** ALTERNATIVE (Not recommended as primary)

**When to Consider TCPDF:**
- Need barcode generation in PDFs
- Require PDF/A compliance
- Need advanced PDF features (annotations, forms)

**Recommendation:** Keep as fallback option; dompdf better suits HTML-template-based generation.

---

## 4. Dependency Matrix by Feature

### 4.1 Feature 1: Chat History (FR-201 to FR-209)

| Requirement | Package | Type | Notes |
|-------------|---------|------|-------|
| Conversation retrieval | WordPress $wpdb | Built-in | No new dependency |
| Caching | phpfastcache | Existing | Already installed |
| Pagination | WordPress | Built-in | No new dependency |

**New Dependencies Required:** None

---

### 4.2 Feature 2: Search Functionality (FR-210 to FR-219)

| Requirement | Package | Type | Notes |
|-------------|---------|------|-------|
| Fulltext search | MySQL FULLTEXT | Built-in | Requires MySQL 5.7+ |
| Query sanitization | WordPress $wpdb | Built-in | `$wpdb->prepare()` |
| Result caching | phpfastcache | Existing | Already installed |
| Highlighting | Custom PHP | Built-in | No dependency needed |

**New Dependencies Required:** None (MySQL FULLTEXT is built-in)

**Database Requirement:**
```sql
-- Add FULLTEXT index (MySQL 5.7+ required)
ALTER TABLE {prefix}ai_botkit_messages
ADD FULLTEXT INDEX ft_content (content);
```

---

### 4.3 Feature 3: Rich Media Support (FR-220 to FR-229)

| Requirement | Package | Type | Notes |
|-------------|---------|------|-------|
| Image validation | PHP GD/Imagick | Built-in | WordPress requirement |
| Video embeds | WordPress oEmbed | Built-in | `wp_oembed_get()` |
| Link previews | fivefilters/readability.php | Existing | Already installed |
| Media metadata | james-heinrich/getid3 | OPTIONAL | Advanced metadata only |
| File uploads | WordPress | Built-in | `wp_handle_upload()` |

**New Dependencies Required:**
- OPTIONAL: james-heinrich/getid3 ^1.9 (for advanced metadata)

**WordPress Built-in Used:**
- `wp_oembed_get()` - YouTube/Vimeo embed support
- `wp_handle_upload()` - File upload handling
- `wp_check_filetype_and_ext()` - File validation
- `wp_generate_attachment_metadata()` - Image metadata

---

### 4.4 Feature 4: Conversation Templates (FR-230 to FR-239)

| Requirement | Package | Type | Notes |
|-------------|---------|------|-------|
| JSON handling | PHP json | Built-in | Core PHP |
| Template storage | WordPress $wpdb | Built-in | Custom table |
| Import/Export | PHP json | Built-in | `json_encode/decode` |

**New Dependencies Required:** None

---

### 4.5 Feature 5: Chat Transcripts Export (FR-240 to FR-249)

| Requirement | Package | Type | Notes |
|-------------|---------|------|-------|
| PDF generation | **dompdf/dompdf** | REQUIRED | Core PDF feature |
| HTML to PDF | dompdf | REQUIRED | Template rendering |
| Batch export ZIP | PHP ZipArchive | Built-in | PHP extension |
| GDPR export | WordPress Privacy API | Built-in | WP 4.9.6+ |

**New Dependencies Required:**
- **REQUIRED:** dompdf/dompdf ^2.0
- **REQUIRED:** PHP extensions: ext-gd, ext-mbstring, ext-dom

---

### 4.6 Feature 6: LMS/WooCommerce Suggestions (FR-250 to FR-259)

| Requirement | Package | Type | Notes |
|-------------|---------|------|-------|
| WooCommerce integration | WooCommerce | Plugin | External dependency |
| LearnDash integration | LearnDash | Plugin | External dependency |
| Recommendation scoring | Custom PHP | Built-in | No library needed |
| User tracking | WordPress | Built-in | Custom table |

**New Dependencies Required:** None (relies on optional WooCommerce/LearnDash plugins)

---

## 5. WordPress Built-in Dependencies

### 5.1 Core WordPress APIs Used

| API | Purpose | Required For | Minimum WP Version |
|-----|---------|--------------|-------------------|
| `$wpdb` | Database operations | All features | 3.0+ |
| `wp_oembed_get()` | Video embeds | FR-221 | 2.9+ |
| `wp_handle_upload()` | File uploads | FR-224 | 2.0+ |
| `wp_check_filetype_and_ext()` | File validation | FR-228 | 4.7+ |
| `wp_privacy_personal_data_exporters` | GDPR export | FR-249 | 4.9.6+ |
| `wp_privacy_personal_data_erasers` | GDPR deletion | FR-249 | 4.9.6+ |
| `wp_schedule_event()` | Scheduled tasks | FR-247 | 2.1+ |
| `WP_REST_Controller` | REST API | All APIs | 4.7+ |

### 5.2 WordPress oEmbed Providers (Built-in)

| Provider | URL Patterns | Notes |
|----------|--------------|-------|
| YouTube | youtube.com, youtu.be | Full support |
| Vimeo | vimeo.com | Full support |
| Twitter/X | twitter.com, x.com | Full support |
| WordPress.tv | wordpress.tv | Full support |
| TikTok | tiktok.com | WP 5.4+ |
| Spotify | spotify.com | WP 4.0+ |

**No external library needed** - WordPress handles oEmbed natively.

---

## 6. License Compatibility Analysis

### 6.1 License Overview

| License | GPL-2.0+ Compatible | Notes |
|---------|---------------------|-------|
| MIT | Yes | Most permissive |
| Apache-2.0 | Yes | Compatible with attribution |
| LGPL-2.1 | Yes | Can be used in GPL projects |
| LGPL-3.0 | Yes | Can be used in GPL projects |
| GPL-2.0+ | Yes | Native WordPress license |
| PHP License | Yes | PHP core components |

### 6.2 Package License Summary

| Package | License | GPL-2.0+ Compatible |
|---------|---------|---------------------|
| guzzlehttp/guzzle | MIT | YES |
| phpfastcache/phpfastcache | MIT | YES |
| fivefilters/readability.php | Apache-2.0 | YES |
| smalot/pdfparser | LGPL-3.0 | YES |
| **dompdf/dompdf** | **LGPL-2.1** | **YES** |
| james-heinrich/getid3 | GPL-2.0+ | YES |
| phenx/php-font-lib | LGPL-2.1+ | YES |
| phenx/php-svg-lib | LGPL-2.1+ | YES |
| sabberworm/php-css-parser | MIT | YES |

**Conclusion:** All dependencies are GPL-2.0+ compatible. No license conflicts.

---

## 7. PHP Version Requirements

### 7.1 Minimum PHP Version Analysis

| Package | Minimum PHP | Recommended |
|---------|-------------|-------------|
| WordPress 5.8+ | PHP 5.6 | PHP 7.4+ |
| guzzlehttp/guzzle 7.x | PHP 7.2.5 | PHP 8.0+ |
| phpfastcache 9.x | PHP 8.0 | PHP 8.1+ |
| dompdf 2.x | PHP 7.1 | PHP 8.0+ |
| getid3 1.9 | PHP 5.4 | PHP 7.4+ |

### 7.2 Project PHP Requirement

**Minimum:** PHP 7.4
**Recommended:** PHP 8.0+
**Optimal:** PHP 8.1+

**Note:** phpfastcache 9.x requires PHP 8.0+. The project already uses this, so PHP 8.0+ is the effective minimum.

### 7.3 PHP Extension Requirements

| Extension | Required By | Purpose | Default in PHP |
|-----------|-------------|---------|----------------|
| ext-json | All | JSON handling | Yes (PHP 8.0+) |
| ext-mbstring | dompdf | Unicode | Usually yes |
| ext-gd | dompdf | Images | Usually yes |
| ext-dom | dompdf | HTML parsing | Yes |
| ext-xml | readability.php | XML parsing | Yes |
| ext-curl | guzzle | HTTP client | Usually yes |
| ext-zip | Batch export | ZIP creation | Usually yes |
| ext-fileinfo | Media upload | MIME detection | Usually yes |

---

## 8. Installation Instructions

### 8.1 Composer Update

Add the following to `ai-botkit-chatbot/includes/composer.json`:

```json
{
    "name": "ishwar/php-rag",
    "description": "PHP RAG System",
    "type": "project",
    "require": {
        "php": ">=8.0",
        "guzzlehttp/guzzle": "^7.9",
        "phpfastcache/phpfastcache": "^9.2",
        "fivefilters/readability.php": ">=3.0",
        "smalot/pdfparser": "*",
        "dompdf/dompdf": "^2.0"
    },
    "require-dev": {
        "james-heinrich/getid3": "^1.9"
    },
    "config": {
        "platform": {
            "php": "8.0"
        }
    },
    "authors": [
        {
            "name": "Ishwar"
        }
    ]
}
```

### 8.2 Installation Commands

```bash
# Navigate to includes directory
cd D:/Claude\ code\ projects/AI-Chatbot-Extension/ai-botkit-chatbot/includes

# Update composer dependencies
composer update

# Install with dev dependencies (includes getid3)
composer install

# Production install (excludes dev dependencies)
composer install --no-dev
```

### 8.3 Verify PHP Extensions

```bash
# Check required extensions
php -m | grep -E "gd|mbstring|dom|zip|curl|fileinfo|json"

# On Windows
php -m | findstr /i "gd mbstring dom zip curl fileinfo json"
```

### 8.4 Database Migration (Search)

```sql
-- Run after plugin update to add FULLTEXT index
ALTER TABLE {prefix}ai_botkit_messages
ADD FULLTEXT INDEX ft_content (content);

-- Verify index was created
SHOW INDEX FROM {prefix}ai_botkit_messages
WHERE Index_type = 'FULLTEXT';
```

---

## 9. Dependency Tree

### 9.1 Visual Dependency Tree

```
ai-botkit-chatbot
├── guzzlehttp/guzzle: ^7.9 (HTTP Client)
│   ├── guzzlehttp/promises: ^2.0
│   ├── guzzlehttp/psr7: ^2.0
│   ├── psr/http-client: ^1.0
│   ├── psr/http-factory: ^1.0
│   ├── psr/http-message: ^1.0|^2.0
│   └── ralouphie/getallheaders: ^3.0
│
├── phpfastcache/phpfastcache: ^9.2 (Caching)
│   ├── psr/cache: ^1.0|^2.0|^3.0
│   ├── psr/simple-cache: ^1.0|^2.0|^3.0
│   └── psr/log: ^1.0|^2.0|^3.0
│
├── fivefilters/readability.php: >=3.0 (Content Extraction)
│   ├── masterminds/html5: ^2.7
│   ├── league/uri: ^6.0|^7.0
│   └── league/uri-interfaces: ^2.0|^7.0
│
├── smalot/pdfparser: * (PDF Reading)
│   ├── symfony/polyfill-mbstring: ^1.0
│   └── symfony/deprecation-contracts: ^2.0|^3.0
│
└── dompdf/dompdf: ^2.0 (PDF Generation) [NEW - PHASE 2]
    ├── phenx/php-font-lib: ^0.5.4
    ├── phenx/php-svg-lib: ^0.5.2
    └── sabberworm/php-css-parser: ^8.4
```

### 9.2 WordPress/Built-in Dependencies

```
WordPress Core (No installation needed)
├── oEmbed API (Video embeds)
├── Privacy API (GDPR)
├── REST API (Endpoints)
├── Cron API (Scheduling)
└── Media handling

MySQL 5.7+ (Database)
├── FULLTEXT indexes (Search)
└── InnoDB support (Transactions)

PHP Extensions (System)
├── ext-gd (Images)
├── ext-mbstring (Unicode)
├── ext-dom (HTML)
├── ext-zip (Archives)
├── ext-curl (HTTP)
└── ext-fileinfo (MIME)
```

---

## 10. Security Considerations

### 10.1 Package Vulnerabilities

| Package | Known CVEs | Mitigation |
|---------|------------|------------|
| guzzlehttp/guzzle | CVE-2022-31042, CVE-2022-31043 (fixed in 7.4.5) | Use ^7.9 |
| guzzlehttp/psr7 | CVE-2023-29197 (fixed in 2.4.5) | Composer resolves |
| dompdf/dompdf | CVE-2021-3838 (fixed in 1.0.2) | Use ^2.0 |

**All known vulnerabilities are fixed in specified versions.**

### 10.2 Security Best Practices

**File Uploads (Media Handler):**
```php
// Always validate MIME type from content, not extension
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime_type = $finfo->file($file['tmp_name']);

// Use WordPress validation
$validated = wp_check_filetype_and_ext(
    $file['tmp_name'],
    $file['name'],
    ['jpg' => 'image/jpeg', 'png' => 'image/png', ...]
);
```

**PDF Generation (dompdf):**
```php
$options = new Options();
// Disable remote file loading unless absolutely needed
$options->set('isRemoteEnabled', false);
// Use HTML5 parser for better security
$options->set('isHtml5ParserEnabled', true);
// Disable JavaScript in PDFs
$options->set('isJavascriptEnabled', false);
```

### 10.3 Dependency Auditing

```bash
# Check for known vulnerabilities
composer audit

# Update to latest secure versions
composer update --prefer-stable
```

---

## 11. Recommendations

### 11.1 Phase 2 Implementation Order

| Priority | Package | Install When |
|----------|---------|--------------|
| 1 | dompdf/dompdf | Before FR-241 (PDF Export) |
| 2 | MySQL FULLTEXT | Before FR-211 (Search) |
| 3 | james-heinrich/getid3 | Optional, Phase 2.1 |

### 11.2 Final Composer.json for Phase 2

```json
{
    "name": "ishwar/php-rag",
    "description": "PHP RAG System - AI BotKit Chatbot",
    "type": "project",
    "license": "GPL-2.0-or-later",
    "minimum-stability": "stable",
    "prefer-stable": true,
    "require": {
        "php": ">=8.0",
        "ext-gd": "*",
        "ext-mbstring": "*",
        "ext-dom": "*",
        "ext-zip": "*",
        "ext-fileinfo": "*",
        "guzzlehttp/guzzle": "^7.9",
        "phpfastcache/phpfastcache": "^9.2",
        "fivefilters/readability.php": ">=3.0",
        "smalot/pdfparser": "^2.0",
        "dompdf/dompdf": "^2.0"
    },
    "require-dev": {
        "james-heinrich/getid3": "^1.9"
    },
    "config": {
        "optimize-autoloader": true,
        "sort-packages": true,
        "platform": {
            "php": "8.0"
        }
    },
    "authors": [
        {
            "name": "Ishwar"
        }
    ]
}
```

### 11.3 Pre-Installation Checklist

- [ ] Verify PHP 8.0+ installed
- [ ] Verify MySQL 5.7+ with FULLTEXT support
- [ ] Verify ext-gd enabled
- [ ] Verify ext-mbstring enabled
- [ ] Verify ext-dom enabled
- [ ] Verify ext-zip enabled
- [ ] Verify WordPress 5.8+
- [ ] Backup existing vendor directory
- [ ] Run `composer update`
- [ ] Run `composer audit`
- [ ] Test PDF generation
- [ ] Run FULLTEXT index migration

### 11.4 Summary

| Category | Count | Notes |
|----------|-------|-------|
| New Composer Packages | 1 | dompdf/dompdf |
| New Transitive Deps | 3 | php-font-lib, php-svg-lib, php-css-parser |
| PHP Extensions Required | 6 | Most already installed |
| Optional Packages | 1 | james-heinrich/getid3 |
| WordPress Built-ins Used | 8+ | No installation needed |
| License Conflicts | 0 | All GPL-2.0+ compatible |

---

## Appendix A: Quick Reference

### A.1 Package Installation Commands

```bash
# Add dompdf (REQUIRED for Phase 2)
composer require dompdf/dompdf:^2.0

# Add getid3 (OPTIONAL)
composer require --dev james-heinrich/getid3:^1.9

# Full update
composer update
```

### A.2 Version Constraints Explained

| Constraint | Meaning |
|------------|---------|
| ^2.0 | >=2.0.0, <3.0.0 |
| ^7.9 | >=7.9.0, <8.0.0 |
| >=3.0 | Any version 3.0 or higher |
| * | Any version (not recommended) |

### A.3 Related Documents

- `PHASE2_SPECIFICATION.md` - Functional requirements
- `ARCHITECTURE.md` - System architecture
- `composer.json` - Package definitions
- `RECOVERED_DATA_MODEL.md` - Database schema

---

*Document generated for AI BotKit Chatbot Phase 2*
*Last Updated: 2026-01-28*
