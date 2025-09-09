# AI BotKit Rate Limiting Feature

## Overview

The AI BotKit plugin now includes a robust rate limiting system for logged-in users, with two configurable control metrics:

1. **Token Bucket Limit**: Maximum number of tokens a user can consume in a 24-hour period
2. **Max Messages**: Maximum number of messages a user can send in a 24-hour period

## Configuration

Administrators can configure these limits in the plugin settings:

1. Navigate to **AI BotKit → Settings**
2. In the **Rate Limiting Settings** section, you'll find:
   - **Max Tokens per Conversation (Token Bucket)**: Default is 100,000 tokens
   - **Max Messages in 24 Hours**: Default is 60 messages

## How It Works

- The system tracks token usage and message count for each logged-in user
- When a user reaches either limit, they will see a rate limit warning message
- The chat input will be disabled until the 24-hour period resets
- Token usage is calculated based on the total tokens used across all conversations
- Message count is based on user messages only (not assistant responses)

## Implementation Details

- Centralized rate limit checking in the RAG_Engine for consistent enforcement
- Rate limits are enforced in real-time as users interact with chatbots
- The system uses WordPress options to store configuration settings
- Token and message usage is tracked in the database using existing tables
- The UI provides clear feedback when users approach or exceed their limits
- Graceful error handling prevents 500 errors when limits are reached

## Future Enhancements

- Role-based rate limits (different limits for different user roles)
- Per-chatbot rate limiting
- Rate limit override capabilities for administrators
- Usage analytics and reporting 