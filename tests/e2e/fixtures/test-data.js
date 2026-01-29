/**
 * Test Data Fixtures
 *
 * Predefined test data for E2E tests including messages,
 * settings configurations, and expected values.
 *
 * @phase 1
 */

/**
 * Test messages for chat interactions
 */
const TEST_MESSAGES = {
  simple: [
    'Hello',
    'Hi there',
    'How are you?',
    'Thank you',
    'Goodbye',
  ],
  questions: [
    'What can you help me with?',
    'How do I get started?',
    'Can you explain this?',
    'What are your capabilities?',
    'Where can I find more information?',
  ],
  complex: [
    'I need help understanding the features of this product. Can you provide a detailed explanation?',
    'Please compare the different options available and help me make a decision.',
    'I have a technical question about implementation. How does this work under the hood?',
  ],
  edgeCases: [
    '', // Empty
    '   ', // Whitespace only
    'a', // Single character
    'a'.repeat(1000), // Very long message
    '<script>alert("XSS")</script>', // XSS attempt
    '"; DROP TABLE users; --', // SQL injection attempt
    'Hello\nWorld', // Newline
    'Test with emoji: \'', // Special characters
  ],
  unicode: [
    'Hello World', // Chinese
    'Hello World', // Arabic
    'Hello World', // Hebrew
    'Hello World', // Russian
    'Cafe Resume', // Accented characters
  ],
};

/**
 * Test configurations for provider settings
 */
const PROVIDER_CONFIGS = {
  openai: {
    models: ['gpt-4-turbo', 'gpt-4o-mini', 'gpt-3.5-turbo'],
    defaultModel: 'gpt-4-turbo',
    temperature: { min: 0, max: 2, default: 0.7 },
    maxTokens: { min: 1, max: 4096, default: 1000 },
  },
  anthropic: {
    models: ['claude-3-opus', 'claude-3-sonnet'],
    defaultModel: 'claude-3-sonnet',
    requiresVoyageAI: true,
  },
  google: {
    models: ['gemini-1.5-flash'],
    defaultModel: 'gemini-1.5-flash',
  },
  together: {
    models: ['various open models'],
    supportsOpenAIFormat: true,
  },
};

/**
 * Rate limit test configurations
 */
const RATE_LIMIT_CONFIGS = {
  default: {
    tokenBucket: 100000,
    maxRequestsPerDay: 60,
  },
  restrictive: {
    tokenBucket: 1000,
    maxRequestsPerDay: 5,
  },
  permissive: {
    tokenBucket: 1000000,
    maxRequestsPerDay: 1000,
  },
};

/**
 * WordPress user roles and expected permissions
 */
const USER_PERMISSIONS = {
  administrator: {
    canManageAIBotkit: true,
    canEditSettings: true,
    canViewAnalytics: true,
    canManageDocuments: true,
    canUseChat: true,
    canViewHistory: true,
  },
  editor: {
    canManageAIBotkit: false,
    canEditSettings: false,
    canViewAnalytics: false,
    canManageDocuments: true,
    canUseChat: true,
    canViewHistory: true,
  },
  author: {
    canManageAIBotkit: false,
    canEditSettings: false,
    canViewAnalytics: false,
    canManageDocuments: false,
    canUseChat: true,
    canViewHistory: true,
  },
  subscriber: {
    canManageAIBotkit: false,
    canEditSettings: false,
    canViewAnalytics: false,
    canManageDocuments: false,
    canUseChat: true,
    canViewHistory: false,
  },
  guest: {
    canManageAIBotkit: false,
    canEditSettings: false,
    canViewAnalytics: false,
    canManageDocuments: false,
    canUseChat: true, // If enabled in settings
    canViewHistory: false,
  },
};

/**
 * State matrix configurations
 */
const STATE_MATRIX = {
  chatbot: {
    enabled: {
      active: 1,
      sitewide: true,
    },
    disabled: {
      active: 0,
      sitewide: false,
    },
  },
  provider: {
    configured: {
      hasApiKey: true,
      connectionValid: true,
    },
    unconfigured: {
      hasApiKey: false,
      connectionValid: false,
    },
  },
  user: {
    loggedIn: {
      isAuthenticated: true,
      hasUserId: true,
    },
    guest: {
      isAuthenticated: false,
      hasUserId: false,
      trackedByIP: true,
    },
  },
};

/**
 * Expected error messages
 */
const ERROR_MESSAGES = {
  emptyMessage: 'Message cannot be empty',
  securityFailed: 'Security check failed',
  rateLimited: 'Rate limit exceeded',
  ipBlocked: 'Your IP address has been blocked',
  noProvider: 'No LLM provider configured',
  apiError: 'API connection failed',
  permissionDenied: 'Insufficient permissions',
  notLoggedIn: 'You must be logged in',
};

/**
 * CSS selectors for common elements
 */
const SELECTORS = {
  chat: {
    container: '[id*="ai-botkit"], .ai-botkit-chat',
    input: '.ai-botkit-input, #ai-botkit-chat-input',
    sendButton: '.ai-botkit-send-button',
    message: '.ai-botkit-message',
    userMessage: '.ai-botkit-message.user',
    assistantMessage: '.ai-botkit-message.assistant',
    typingIndicator: '.ai-botkit-typing',
    errorMessage: '.ai-botkit-error',
    clearButton: '.ai-botkit-clear',
  },
  admin: {
    menu: '#adminmenu a:has-text("AI BotKit"), #adminmenu a:has-text("KnowVault")',
    saveButton: 'input[type="submit"], button:has-text("Save"), #submit',
    successNotice: '.notice-success, .updated',
    errorNotice: '.notice-error, .error',
  },
};

/**
 * Timeout configurations
 */
const TIMEOUTS = {
  navigation: 30000,
  ajax: 10000,
  chatResponse: 30000,
  upload: 30000,
  processing: 60000,
  animation: 500,
};

/**
 * Generate unique test data
 */
const generateTestData = {
  /**
   * Generate unique chatbot name
   * @returns {string}
   */
  chatbotName: () => `Test Bot ${Date.now()}`,

  /**
   * Generate unique document name
   * @returns {string}
   */
  documentName: () => `Test Document ${Date.now()}.pdf`,

  /**
   * Generate unique conversation ID
   * @returns {string}
   */
  conversationId: () => `conv_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`,

  /**
   * Generate test URL
   * @returns {string}
   */
  testUrl: () => `https://example.com/test-${Date.now()}`,

  /**
   * Generate random message
   * @returns {string}
   */
  randomMessage: () => {
    const messages = TEST_MESSAGES.questions;
    return messages[Math.floor(Math.random() * messages.length)];
  },
};

/**
 * Validation helpers
 */
const validators = {
  /**
   * Check if value is valid temperature
   * @param {number} value
   * @returns {boolean}
   */
  isValidTemperature: (value) => value >= 0 && value <= 2,

  /**
   * Check if value is valid max tokens
   * @param {number} value
   * @returns {boolean}
   */
  isValidMaxTokens: (value) => value >= 1 && value <= 100000,

  /**
   * Check if string is valid API key format
   * @param {string} key
   * @returns {boolean}
   */
  isValidApiKeyFormat: (key) => key && key.length >= 20,

  /**
   * Check if URL is valid
   * @param {string} url
   * @returns {boolean}
   */
  isValidUrl: (url) => {
    try {
      new URL(url);
      return true;
    } catch {
      return false;
    }
  },
};

module.exports = {
  TEST_MESSAGES,
  PROVIDER_CONFIGS,
  RATE_LIMIT_CONFIGS,
  USER_PERMISSIONS,
  STATE_MATRIX,
  ERROR_MESSAGES,
  SELECTORS,
  TIMEOUTS,
  generateTestData,
  validators,
};
