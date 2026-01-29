/**
 * Test Data Fixture
 *
 * Provides test data generators and helpers for E2E tests.
 *
 * @package AI_BotKit
 * @since 2.0.0
 */

/**
 * Generate a unique test identifier
 *
 * @returns {string}
 */
function generateTestId() {
    return `test_${Date.now()}_${Math.random().toString(36).substring(7)}`;
}

/**
 * Generate test conversation data
 *
 * @param {Object} overrides Override default values
 * @returns {Object}
 */
function generateConversationData(overrides = {}) {
    return {
        title: `Test Conversation ${generateTestId()}`,
        messages: [
            { role: 'user', content: 'Hello, I need help with my order.' },
            { role: 'assistant', content: 'Of course! I\'d be happy to help. Could you please provide your order number?' },
            { role: 'user', content: 'My order number is #12345.' },
            { role: 'assistant', content: 'Thank you! I can see your order #12345. How can I assist you with it?' },
        ],
        ...overrides,
    };
}

/**
 * Generate test template data
 *
 * @param {Object} overrides Override default values
 * @returns {Object}
 */
function generateTemplateData(overrides = {}) {
    return {
        name: `Test Template ${generateTestId()}`,
        description: 'A test template created for E2E testing',
        category: 'general',
        is_active: true,
        messages_template: {
            personality: 'You are a helpful test assistant.',
            greeting: 'Hello! This is a test greeting.',
            fallback: 'I\'m sorry, I didn\'t understand that.',
        },
        style: {
            primary_color: '#4F46E5',
            header_bg_color: '#4F46E5',
            header_color: '#FFFFFF',
            body_bg_color: '#FFFFFF',
            ai_msg_bg_color: '#F3F4F6',
            user_msg_bg_color: '#4F46E5',
            ai_msg_font_color: '#1F2937',
            user_msg_font_color: '#FFFFFF',
            font_family: 'system-ui, -apple-system, sans-serif',
            position: 'bottom-right',
        },
        model_config: {
            model: 'gpt-4o-mini',
            temperature: 0.5,
            max_tokens: 800,
            context_length: 5,
            tone: 'professional',
        },
        conversation_starters: [
            { text: 'How can I help you?', icon: 'help-circle' },
            { text: 'Tell me about your products', icon: 'shopping-bag' },
        ],
        ...overrides,
    };
}

/**
 * Generate test product data (for WooCommerce tests)
 *
 * @param {Object} overrides Override default values
 * @returns {Object}
 */
function generateProductData(overrides = {}) {
    return {
        id: Math.floor(Math.random() * 10000),
        title: `Test Product ${generateTestId()}`,
        description: 'A test product for E2E testing',
        price: '$29.99',
        image: 'https://via.placeholder.com/300x300',
        url: '/product/test-product/',
        type: 'product',
        rating: 4.5,
        review_count: 12,
        stock_status: 'instock',
        action: {
            type: 'add_to_cart',
            label: 'Add to Cart',
        },
        ...overrides,
    };
}

/**
 * Generate test course data (for LMS tests)
 *
 * @param {Object} overrides Override default values
 * @returns {Object}
 */
function generateCourseData(overrides = {}) {
    return {
        id: Math.floor(Math.random() * 10000),
        title: `Test Course ${generateTestId()}`,
        description: 'A test course for E2E testing',
        price: '$99.99',
        image: 'https://via.placeholder.com/300x200',
        url: '/course/test-course/',
        type: 'course',
        rating: 4.8,
        review_count: 45,
        lesson_count: 12,
        progress: 0,
        action: {
            type: 'enroll',
            label: 'Enroll Now',
        },
        ...overrides,
    };
}

/**
 * Generate test search result data
 *
 * @param {Object} overrides Override default values
 * @returns {Object}
 */
function generateSearchResultData(overrides = {}) {
    return {
        conversation_id: Math.floor(Math.random() * 10000),
        message_id: Math.floor(Math.random() * 100000),
        role: 'user',
        content: 'Test search result content with <mark>highlighted</mark> terms.',
        date: new Date().toISOString(),
        chatbot: 'Test Bot',
        relevance: 0.95,
        ...overrides,
    };
}

/**
 * Generate SQL injection test strings
 *
 * @returns {string[]}
 */
function getSqlInjectionStrings() {
    return [
        "' OR '1'='1",
        "'; DROP TABLE messages;--",
        "1; SELECT * FROM users",
        "' UNION SELECT * FROM users--",
        "admin'--",
        "1' OR '1' = '1",
        "' OR 1=1#",
        "' OR 'x'='x",
    ];
}

/**
 * Generate XSS test strings
 *
 * @returns {string[]}
 */
function getXssTestStrings() {
    return [
        '<script>alert("XSS")</script>',
        '<img src="x" onerror="alert(1)">',
        '<svg onload="alert(1)">',
        'javascript:alert(1)',
        '<iframe src="javascript:alert(1)">',
        '"><script>alert(1)</script>',
        "'-alert(1)-'",
        '<body onload="alert(1)">',
    ];
}

/**
 * Generate path traversal test strings
 *
 * @returns {string[]}
 */
function getPathTraversalStrings() {
    return [
        '../../../etc/passwd',
        '..\\..\\..\\windows\\system32\\config\\sam',
        '....//....//....//etc/passwd',
        '%2e%2e%2f%2e%2e%2f%2e%2e%2fetc/passwd',
        '..%c0%af..%c0%af..%c0%afetc/passwd',
        'file:///etc/passwd',
    ];
}

/**
 * Test user data
 */
const TEST_USERS = {
    admin: {
        username: 'admin',
        role: 'administrator',
        capabilities: ['manage_ai_botkit', 'edit_posts', 'manage_options'],
    },
    editor: {
        username: 'editor',
        role: 'editor',
        capabilities: ['edit_posts', 'delete_posts'],
    },
    subscriber: {
        username: 'subscriber',
        role: 'subscriber',
        capabilities: ['read'],
    },
};

/**
 * Test chatbot data
 */
const TEST_CHATBOTS = [
    {
        id: 1,
        name: 'Support Bot',
        category: 'support',
    },
    {
        id: 2,
        name: 'Sales Bot',
        category: 'sales',
    },
    {
        id: 3,
        name: 'FAQ Bot',
        category: 'general',
    },
];

/**
 * Wait helper for async operations
 *
 * @param {number} ms Milliseconds to wait
 * @returns {Promise<void>}
 */
function wait(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
}

module.exports = {
    generateTestId,
    generateConversationData,
    generateTemplateData,
    generateProductData,
    generateCourseData,
    generateSearchResultData,
    getSqlInjectionStrings,
    getXssTestStrings,
    getPathTraversalStrings,
    TEST_USERS,
    TEST_CHATBOTS,
    wait,
};
