# Phase 2 E2E Tests for AI BotKit

This directory contains Playwright E2E tests for AI BotKit Phase 2 features.

## Features Covered

| Feature | Spec File | Test Cases |
|---------|-----------|------------|
| Chat History (FR-201 to FR-209) | `chat-history.spec.js` | TC-201-xxx to TC-209-xxx |
| Search Functionality (FR-210 to FR-219) | `search-functionality.spec.js` | TC-210-xxx to TC-219-xxx |
| Rich Media Support (FR-220 to FR-229) | `rich-media.spec.js` | TC-220-xxx to TC-229-xxx |
| Conversation Templates (FR-230 to FR-239) | `conversation-templates.spec.js` | TC-230-xxx to TC-239-xxx |
| Chat Export (FR-240 to FR-249) | `chat-export.spec.js` | TC-240-xxx to TC-249-xxx |
| LMS/WooCommerce Suggestions (FR-250 to FR-259) | `lms-suggestions.spec.js` | TC-250-xxx to TC-259-xxx |

## Directory Structure

```
tests/e2e/phase2/
├── playwright.config.js       # Playwright configuration
├── README.md                  # This file
├── fixtures/
│   ├── auth.fixture.js        # Authentication helpers
│   ├── global-setup.js        # Global setup before all tests
│   ├── global-teardown.js     # Global teardown after all tests
│   └── test-data.fixture.js   # Test data generators
├── pages/
│   ├── ChatWidgetPage.js      # Chat widget page object
│   ├── HistoryPanelPage.js    # History panel page object
│   ├── SearchPanelPage.js     # Search panel page object
│   └── AdminTemplatePage.js   # Admin template page object
└── specs/
    ├── chat-history.spec.js          # Chat history tests
    ├── search-functionality.spec.js  # Search functionality tests
    ├── rich-media.spec.js            # Rich media support tests
    ├── conversation-templates.spec.js # Template management tests
    ├── chat-export.spec.js           # Chat export tests
    └── lms-suggestions.spec.js       # LMS/WooCommerce suggestion tests
```

## Prerequisites

1. **Node.js** >= 18.x
2. **Playwright** installed:
   ```bash
   npm install @playwright/test
   npx playwright install
   ```
3. **WordPress site** running with:
   - AI BotKit plugin activated
   - Test users created (admin, subscriber)
   - Sample conversations and data

## Environment Variables

Create a `.env.test` file in the project root:

```env
# WordPress Site URL
WP_BASE_URL=http://localhost:8080

# Admin User
WP_ADMIN_USER=admin
WP_ADMIN_PASS=password

# Subscriber User
WP_SUBSCRIBER_USER=subscriber
WP_SUBSCRIBER_PASS=password

# Editor User (optional)
WP_EDITOR_USER=editor
WP_EDITOR_PASS=password
```

## Running Tests

### Run All Tests

```bash
npx playwright test --config=tests/e2e/phase2/playwright.config.js
```

### Run Specific Feature

```bash
# Chat History tests only
npx playwright test chat-history --config=tests/e2e/phase2/playwright.config.js

# Search Functionality tests only
npx playwright test search-functionality --config=tests/e2e/phase2/playwright.config.js

# Security tests only
npx playwright test --grep "Security" --config=tests/e2e/phase2/playwright.config.js
```

### Run by Priority

```bash
# P0 Critical tests
npx playwright test --grep "P0|Critical" --config=tests/e2e/phase2/playwright.config.js

# P1 High priority tests
npx playwright test --grep "P1|High" --config=tests/e2e/phase2/playwright.config.js
```

### Run in Headed Mode (visible browser)

```bash
npx playwright test --headed --config=tests/e2e/phase2/playwright.config.js
```

### Run with UI Mode

```bash
npx playwright test --ui --config=tests/e2e/phase2/playwright.config.js
```

### Debug a Specific Test

```bash
npx playwright test --debug "TC-201-001" --config=tests/e2e/phase2/playwright.config.js
```

## Test Reports

After running tests, reports are generated in:

- **HTML Report**: `tests/e2e/phase2/reports/html/index.html`
- **JSON Results**: `tests/e2e/phase2/reports/test-results.json`
- **Screenshots**: `tests/e2e/phase2/test-results/` (on failure)
- **Videos**: `tests/e2e/phase2/test-results/` (on retry)

### View HTML Report

```bash
npx playwright show-report tests/e2e/phase2/reports/html
```

## Test Categories

### Functional Tests
Core functionality verification for each feature.

### Security Tests
- **Authentication**: Verify login/logout flows work correctly
- **Authorization**: Verify users can only access their own data
- **Input Validation**: SQL injection, XSS prevention
- **API Security**: Nonce validation, capability checks

### Accessibility Tests
- Keyboard navigation
- ARIA labels and roles
- Focus management

### Performance Tests
- Response time verification
- Large data handling

## Page Objects

### ChatWidgetPage
Methods for interacting with the chat widget:
- `openWidget()` / `closeWidget()`
- `sendMessage(text)`
- `waitForBotResponse()`
- `getMessageCounts()`
- `clearConversation()`

### HistoryPanelPage
Methods for the chat history panel:
- `openPanel()` / `closePanel()`
- `selectConversation(index)`
- `deleteConversation(index)`
- `toggleFavorite(index)`
- `applyQuickFilter(type)`

### SearchPanelPage
Methods for search functionality:
- `search(query)`
- `getResultCount()`
- `clickResult(index)`
- `applyDateFilter(value)`
- `clearSearch()`

### AdminTemplatePage
Methods for template management:
- `goto()` - Navigate to templates admin page
- `openNewTemplateModal()`
- `fillTemplateForm(data)`
- `saveTemplate()`
- `deleteTemplate(index)`

## Writing New Tests

### Test Structure

```javascript
test.describe('Feature Name', () => {
    /**
     * TC-XXX-001: Test name from test case document
     * Priority: P0 (Critical)
     */
    test('TC-XXX-001: descriptive test name', async ({ page }) => {
        // Arrange
        await loginAs(page, 'subscriber');

        // Act
        await doSomething();

        // Assert - MUST use specific assertions that can FAIL
        expect(actualValue).toBe(expectedValue);
        await expect(element).toBeVisible();
    });
});
```

### Assertion Rules

**BANNED Patterns** (tests that always pass):
```javascript
// DON'T DO THIS
expect(count >= 0).toBeTruthy();           // Always passes
expect(a || b || c).toBeTruthy();          // Too many fallbacks
expect([200, 401, 403]).toContain(status); // Multiple success states
.catch(() => false);                        // Hides failures
```

**REQUIRED Patterns** (specific assertions):
```javascript
// DO THIS
expect(count).toBe(5);                     // Exact value
expect(count).toBeGreaterThan(0);          // At least one
expect(text).toContain('Welcome');         // Specific text
await expect(element).toBeVisible();       // Element exists
expect(response.status()).toBe(200);       // Exact status
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
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: 18
      - run: npm ci
      - run: npx playwright install --with-deps
      - run: npx playwright test --config=tests/e2e/phase2/playwright.config.js
        env:
          WP_BASE_URL: ${{ secrets.WP_BASE_URL }}
          WP_ADMIN_USER: ${{ secrets.WP_ADMIN_USER }}
          WP_ADMIN_PASS: ${{ secrets.WP_ADMIN_PASS }}
      - uses: actions/upload-artifact@v4
        if: failure()
        with:
          name: playwright-report
          path: tests/e2e/phase2/reports/
```

## Troubleshooting

### Tests Fail to Connect

1. Verify WordPress is running at the configured URL
2. Check `.env.test` has correct credentials
3. Ensure AI BotKit plugin is activated

### Authentication Issues

1. Verify test users exist in WordPress
2. Check user passwords match `.env.test`
3. Ensure users have correct roles/capabilities

### Flaky Tests

1. Increase timeouts in `playwright.config.js`
2. Add explicit waits for async operations
3. Use `test.retry(2)` for network-dependent tests

### Element Not Found

1. Check selectors match current implementation
2. Verify element is visible (not hidden by CSS)
3. Wait for page load with `waitForLoadState('networkidle')`
