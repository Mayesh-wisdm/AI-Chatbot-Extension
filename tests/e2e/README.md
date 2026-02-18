# AI BotKit E2E Regression Test Suite

## Overview

This directory contains comprehensive E2E regression tests for the AI BotKit (KnowVault) WordPress plugin. These tests are designed to protect Phase 1 functionality during Phase 2 development.

**Priority:** All tests in this suite are marked as **P0 (Critical)** and must pass before any release.

## Test Coverage

### Phase 1 Functionality Protected

| Test File | Coverage | Functional Requirements |
|-----------|----------|------------------------|
| `core-chatbot.spec.js` | Chat widget, messaging, conversation | FR-006, FR-007 |
| `knowledge-base.spec.js` | RAG, document management, vector storage | FR-001 to FR-004, FR-013 |
| `provider-integration.spec.js` | LLM providers, API configuration | FR-005 |
| `admin-settings.spec.js` | Admin UI, permissions, rate limiting | FR-008 to FR-010, FR-013, FR-014 |

### Test Categories

1. **Core Functionality Tests (TC-CORE)**
   - Chat widget loading
   - Message send/receive
   - Conversation persistence
   - UI components

2. **Knowledge Base Tests (TC-KB)**
   - Document upload/import
   - WordPress content sync
   - RAG query integration
   - Vector database

3. **Provider Tests (TC-PROV)**
   - API key configuration
   - Model selection
   - Fallback order
   - Streaming responses

4. **Admin Tests (TC-ADMIN)**
   - Menu navigation
   - Settings persistence
   - Analytics dashboard
   - Security settings

5. **State Matrix Tests (TC-SM)**
   - Chatbot enabled/disabled states
   - Provider configured/unconfigured states
   - User logged-in/guest states

6. **Permission Matrix Tests (TC-PM)**
   - Administrator access
   - Editor access
   - Subscriber access
   - Guest access

## Prerequisites

### Environment Setup

1. **WordPress Installation**
   - WordPress 5.8+
   - AI BotKit plugin installed and activated
   - Test users created (admin, editor, subscriber)
   - Test page with chatbot shortcode

2. **Environment Variables**
   ```bash
   export WP_SITE_URL=http://localhost:8080
   export WP_ADMIN_USER=admin
   export WP_ADMIN_PASS=password
   export WP_EDITOR_USER=editor
   export WP_EDITOR_PASS=password
   export WP_SUBSCRIBER_USER=subscriber
   export WP_SUBSCRIBER_PASS=password
   ```

3. **Node.js Dependencies**
   ```bash
   npm install @playwright/test
   npx playwright install
   ```

## Running Tests

### Run All Regression Tests

```bash
npx playwright test --config=tests/e2e/playwright.config.js
```

### Run Specific Test File

```bash
npx playwright test tests/e2e/regression/core-chatbot.spec.js
```

### Run Tests by Tag

```bash
# Run only P0 tests (all tests in this suite)
npx playwright test --grep "P0"

# Run state matrix tests
npx playwright test --grep "TC-SM"

# Run permission matrix tests
npx playwright test --grep "TC-PM"
```

### Run with UI Mode

```bash
npx playwright test --ui
```

### Run in Debug Mode

```bash
npx playwright test --debug
```

### Run on Specific Browser

```bash
npx playwright test --project=chromium
npx playwright test --project=firefox
npx playwright test --project=webkit
```

## Test Reports

After running tests, reports are generated in:

- **HTML Report:** `tests/reports/html/index.html`
- **JSON Report:** `tests/reports/test-results.json`
- **JUnit Report:** `tests/reports/junit.xml`

Open HTML report:
```bash
npx playwright show-report tests/reports/html
```

## Directory Structure

```
tests/e2e/
├── regression/                 # Regression test specs
│   ├── core-chatbot.spec.js   # Chat widget tests
│   ├── knowledge-base.spec.js # Knowledge base tests
│   ├── provider-integration.spec.js # Provider tests
│   └── admin-settings.spec.js # Admin tests
├── pages/                      # Page Object Models
│   ├── admin-settings.page.js # Settings page object
│   └── chat-widget.page.js    # Chat widget page object
├── fixtures/                   # Test fixtures
│   └── wordpress.fixture.js   # WordPress helpers
├── playwright.config.js       # Playwright configuration
└── README.md                  # This file
```

## Writing New Tests

### Test ID Convention

Tests follow this naming convention:
```
TC-{CATEGORY}-{NUMBER}.{SUB}: [PRIORITY] Description
```

Examples:
- `TC-CORE-001.1: [P0] Chat widget container renders on page load`
- `TC-KB-002.1: [P0] PDF upload triggers AJAX request`
- `TC-SM-001.1: [P0] Enabled chatbot + Logged-in user = Full functionality`

### Using Page Objects

```javascript
const { ChatWidgetPage } = require('../pages/chat-widget.page');

test('example test', async ({ page }) => {
  const chatWidget = new ChatWidgetPage(page);
  await chatWidget.goto();
  await chatWidget.sendMessage('Hello');
  await chatWidget.waitForResponse();
  const response = await chatWidget.getLastAssistantMessageText();
  expect(response.length).toBeGreaterThan(0);
});
```

### Using Fixtures

```javascript
const { test, expect, AuthHelper } = require('../fixtures/wordpress.fixture');

test('example with auth', async ({ page, auth }) => {
  await auth.loginAsAdmin();
  // ... test code
});
```

## CI/CD Integration

### GitHub Actions Example

```yaml
name: E2E Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: actions/setup-node@v3
        with:
          node-version: 18
      - name: Install dependencies
        run: npm ci
      - name: Install Playwright browsers
        run: npx playwright install --with-deps
      - name: Run tests
        run: npx playwright test --config=tests/e2e/playwright.config.js
        env:
          WP_SITE_URL: ${{ secrets.WP_SITE_URL }}
          WP_ADMIN_USER: ${{ secrets.WP_ADMIN_USER }}
          WP_ADMIN_PASS: ${{ secrets.WP_ADMIN_PASS }}
      - uses: actions/upload-artifact@v3
        if: always()
        with:
          name: playwright-report
          path: tests/reports/
```

## Troubleshooting

### Common Issues

1. **Tests timing out**
   - Increase `timeout` in config
   - Check WordPress site is running
   - Verify API keys are configured

2. **Authentication failures**
   - Verify user credentials
   - Check user exists in WordPress
   - Clear browser cookies between tests

3. **Element not found**
   - Check selector matches current UI
   - Page may have changed structure
   - Wait for element to load

### Debug Tips

1. Use `page.pause()` to stop execution and inspect
2. Use `--headed` flag to see browser during test
3. Check screenshots in `tests/reports/test-results/`
4. Review trace files for detailed debugging

## Maintenance

### Updating Tests

When Phase 1 functionality changes:
1. Run affected tests
2. Update selectors if UI changed
3. Update assertions if behavior changed
4. Add new tests for new functionality

### Adding Coverage

For new Phase 1 features:
1. Add test cases to appropriate spec file
2. Follow test ID convention
3. Mark as P0 priority
4. Include state/permission matrix coverage

## Support

For issues with this test suite:
1. Check existing test failures in CI
2. Review test reports for details
3. Contact the QA team

---

*Generated by Regression Test Generator Agent*
*Last Updated: 2026-01-29*
