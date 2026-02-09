# KnowVault - AI Chatbot for WordPress

## User Documentation

### Table of Contents

1. [Introduction](#introduction)
2. [Getting Started](#getting-started)
3. [Settings & Configuration](#settings--configuration)
4. [Knowledge Base Management](#knowledge-base-management)
5. [Chatbot Creation & Management](#chatbot-creation--management)
6. [Analytics & Monitoring](#analytics--monitoring)
7. [Features & Capabilities](#features--capabilities)
8. [Best Practices](#best-practices)
9. [Troubleshooting](#troubleshooting)
10. [Warnings & Important Notes](#warnings--important-notes)

---

## Introduction

KnowVault is an advanced RAG (Retrieval-Augmented Generation) based AI chatbot plugin for WordPress that enables you to create intelligent, context-aware chatbots trained on your business content. The plugin supports multiple AI providers (OpenAI, Anthropic Claude, Google Gemini, and open-source models via Together.ai) and stores all data locally on your WordPress installation.

### Key Benefits

- **WordPress Native Training**: No external vector database required - trains directly on your website content
- **Cost-Effective**: Bring Your Own API Key (BYO API) model gives you full control over costs
- **Unlimited Chatbots**: Create as many chatbots as needed for different purposes
- **Data Privacy**: All chatbot data stored locally, GDPR-compliant
- **Multilingual Support**: Communicate in 50+ languages
- **No Coding Required**: Intuitive visual builder for all configurations

---

## Getting Started

### Installation Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher
- API key from at least one AI provider (OpenAI, Anthropic, Google, or Together.ai)

### Initial Setup Steps

1. **Install and Activate the Plugin**

   - Upload the plugin to `/wp-content/plugins/ai-botkit` or install via WordPress admin
   - Activate through the Plugins screen

2. **Configure API Keys**

   - Navigate to **KnowVault → Settings**
   - Select your preferred AI Engine
   - Enter and verify your API key(s)
   - Click "Save Changes"

3. **Add Content to Knowledge Base**

   - Go to **KnowVault → Knowledge Base**
   - Upload documents, add URLs, or import WordPress content
   - Wait for content processing to complete

4. **Create Your First Chatbot**
   - Navigate to **KnowVault → My Bots**
   - Click "Create New Bot"
   - Follow the 6-step wizard to configure your chatbot

---

## Settings & Configuration

### Location

**KnowVault → Settings**

### API Keys Section

#### AI Engine Selection

Choose your primary AI provider:

- **OpenAI**: For GPT-3.5, GPT-4, and GPT-4 Turbo models
- **Anthropic**: For Claude models (requires VoyageAI for embeddings)
- **Google**: For Gemini models
- **Together AI**: For open-source models like Llama, DeepSeek

**How to Configure:**

1. Select your desired engine from the dropdown
2. Enter the corresponding API key
3. Optionally add Organization ID (for OpenAI)
4. Click "Verify" to test the connection
5. Save settings

**Important:**

- You must have a valid API key for your chosen engine
- For Anthropic, you also need a VoyageAI API key for embeddings
- API keys are stored securely in WordPress options
- Test your API keys before proceeding to ensure functionality

#### Chat Model Selection

Select the language model for chatbot responses:

- **OpenAI Options**: gpt-4-turbo-preview, gpt-4, gpt-3.5-turbo, etc.
- **Anthropic Options**: claude-3-5-sonnet, claude-3-5-haiku, etc.
- **Google Options**: gemini-pro, gemini-pro-vision, etc.
- **Together AI Options**: llama-3.3-70b-instruct, deepseek-chat, etc.

**Recommendations:**

- Use GPT-4 or Claude 3.5 Sonnet for best quality
- Use GPT-3.5-turbo or Claude Haiku for cost efficiency
- GPT-4 Turbo balances quality and cost

#### Embedding Model Selection

Select the model for generating embeddings (vector representations):

- **OpenAI**: text-embedding-3-small, text-embedding-3-large, text-embedding-ada-002
- **Anthropic**: Requires VoyageAI models (voyage-3, voyage-lite-03, etc.)
- **Google**: text-embedding-004, text-multilingual-embedding-004
- **Together AI**: BGE models (BGE-base-en-v1.5, BGE-large-en-v1.5)

**Best Practice:** Use smaller embedding models (like text-embedding-3-small) for cost efficiency unless you need higher accuracy.

#### Pinecone Vector Database (Optional)

The plugin can use either:

- **Local WordPress Database** (default, recommended for most users)
- **Pinecone Cloud Database** (for larger scale deployments)

**When to Use Pinecone:**

- You have more than 10,000 documents
- You need faster search performance
- You're running multiple WordPress sites with shared knowledge

**Pinecone Configuration:**

1. Sign up at [pinecone.io](https://pinecone.io)
2. Create an index with **1536 dimensions** (critical requirement)
3. Enable Pinecone in settings
4. Enter your API key and host URL (format: `https://your-index.pinecone.io`)
5. Test connection before saving

**WARNING:**

- Your Pinecone index MUST be configured with 1536 dimensions
- Clear vector data operation clears only embeddings, not document metadata
- Migration between local and Pinecone is available in Knowledge Base page

### AI Parameters Section

#### Chunk Size

- **Default:** 1000 characters
- **Range:** 100-2000 characters
- **Purpose:** Determines how content is split into pieces for processing
- **Best Practice:**
  - Smaller chunks (500-800) for precise answers
  - Larger chunks (1200-1500) for comprehensive context
  - Start with default and adjust based on response quality

#### Chunk Overlap

- **Default:** 200 characters
- **Range:** 0-200 characters
- **Purpose:** Ensures important context isn't lost at chunk boundaries
- **Best Practice:** Keep at default (200) for best results

### Rate Limiting Settings

Configure limits for logged-in users to control API costs and usage.

#### Max Tokens per Conversation (Token Bucket)

- **Default:** 100,000 tokens
- **Range:** 10,000 - 1,000,000 tokens
- **Purpose:** Maximum tokens a user can consume in 24 hours
- **How It Works:**
  - Tracks token usage across all conversations
  - Resets every 24 hours
  - Blocks chat input when limit reached
- **Calculation:** Tokens count for both user messages and AI responses

#### Max Messages in 24 Hours

- **Default:** 60 messages
- **Range:** 1-1000 messages
- **Purpose:** Maximum number of messages a user can send per day
- **How It Works:**
  - Counts only user messages (not AI responses)
  - Resets every 24 hours
  - Blocks chat input when limit reached

**Rate Limiting Behavior:**

- When either limit is reached, users see a warning message
- Chat input is disabled until the 24-hour window resets
- Limits apply only to logged-in users (anonymous users not restricted)
- Rate limits are enforced in real-time

**Recommended Settings:**

- **Light Use:** 30,000 tokens, 30 messages
- **Moderate Use:** 100,000 tokens, 60 messages (default)
- **Heavy Use:** 500,000 tokens, 200 messages

---

## Knowledge Base Management

### Location

**KnowVault → Knowledge Base**

### Overview

The Knowledge Base is where you add all content that your chatbots will learn from. It's a centralized repository shared across all your chatbots.

### Adding Content

#### 1. Upload Documents (PDF)

- Click **"Upload Document"** button
- Select PDF file(s) (currently only PDF format supported)
- Files are automatically processed
- **Note:** Only text from PDFs is extracted; images and GIFs are not used

**Processing Status:**

- **Pending:** Waiting to be processed
- **Processing:** Currently being chunked and embedded
- **Completed:** Ready for use by chatbots
- **Failed:** Check error details by clicking the status badge

**Supported File Formats:**

- PDF (.pdf) only currently

#### 2. Add URLs

- Click **"Add URL"** button
- Enter the URL you want to train on
- Optionally provide a custom title (auto-detected if left empty)
- The plugin will fetch and process the webpage content

**Best Practices:**

- Use URLs from your own website for accurate content
- Ensure URLs are publicly accessible (no login required)
- Large pages may take longer to process

#### 3. Import from WordPress

- Click **"Import from WordPress"** button
- Select post types to import (posts, pages, custom post types)
- Choose specific items or select all
- Click **"Add Data"** to process

**What Gets Imported:**

- Page/post titles
- Main content
- Custom fields (if applicable)

**What Doesn't Get Imported:**

- Comments
- Metadata (categories, tags - only content)

### Managing Knowledge Base

#### Filtering

- Use tabs to filter by type: All Resources, Documents, URLs
- Use search box to find specific resources
- Filtering is case-insensitive

#### Reprocessing Content

- Click the refresh icon (↻) on any completed item
- Useful when:
  - Content has been updated on your site
  - Initial processing failed
  - You want to re-index with new settings

#### Deleting Content

- Click the trash icon (🗑) on any item
- **Warning:** This permanently removes the content from knowledge base
- All chatbots using this content will lose access to it

### Database Management (Pinecone Users Only)

If you have Pinecone configured, you'll see a Database Management section.

#### Migration Tools

**Migrate Data:**

- **Local to Pinecone:** Moves all vector data to Pinecone cloud
- **Pinecone to Local:** Moves all vector data back to WordPress database
- Use migration wizard for guided process
- Migration is incremental and can be paused/resumed

**Clear Operations:**

- **Clear Vector Data:** Removes embeddings but keeps document metadata (knowledge base display remains)
- **Clear Pinecone:** Removes all data from Pinecone database
- **Clear Knowledge Base:** Permanently removes everything including documents, chunks, and embeddings

**Status Monitoring:**

- Real-time status of local and Pinecone databases
- Connection status
- Migration progress and history

**Important Warnings:**

- ⚠️ **Clear operations are permanent and cannot be undone**
- ⚠️ **Backup your data before performing clear operations**
- ⚠️ **Clearing Knowledge Base affects all chatbots**
- ⚠️ **Migration can take time for large knowledge bases**

---

## Chatbot Creation & Management

### Location

**KnowVault → My Bots**

### Conversation Templates

**KnowVault → Conversation Templates**

Templates let you save and reuse chatbot configurations (personality, greeting, style, model settings, conversation starters).

- **Export:** Downloads the template as a `.json` file. This format is used so you can re-import the template on this or another site via **Import Template**.
- **Import:** Use **Import Template** (top of the page) to upload a previously exported `.json` file. You can choose to skip or replace existing templates with the same name.
- **Edit and Save:** For **custom** templates, click **Edit**, change any fields, then click **Save Template** to update the template in place. For **system** (pre-built) templates, only **Save as Copy** is shown; use it to create a new custom template based on the system one.
- **Delete:** The **Delete** button appears only on **custom** templates. System templates cannot be deleted (by design). To remove a custom template, open it and click the trash icon.

### Creating a New Chatbot

Click **"Create New Bot"** to start the 6-step wizard:

#### Step 1: General Settings

**Bot Name:**

- Choose a descriptive name (visible to users)
- Example: "Customer Support Bot", "Product Assistant"

**Bot Introduction (Personality):**

- Define the bot's purpose and personality
- Default: "helpful website assistant"
- Example: "friendly customer support specialist focused on helping users find answers quickly"

**Bot Tone:**

- **Professional:** Formal, business-like (recommended for B2B)
- **Friendly:** Warm and approachable (recommended for most sites)
- **Casual:** Relaxed and conversational
- **Formal:** Very formal, reserved

#### Step 2: Training (Knowledge Base Selection)

**Import from Knowledge Base:**

1. Click to expand knowledge base selector
2. Select documents, URLs, or WordPress content you want this bot to use
3. You can select all items or be specific
4. Different bots can use different knowledge sources
5. Click **"Add Data"** then **"Next"**

**Best Practices:**

- Start with core content (main pages, key documents)
- Add specialized bots with focused knowledge sets
- Regularly update knowledge base and reprocess content

#### Step 3: Interface

**Initial Greeting Message:**

- First message users see when opening chat
- Default: "Hi there! How can I help you today?"
- Keep it short, friendly, and action-oriented

**Fallback Message:**

- Message shown when bot doesn't have answer
- Default: "I'm sorry, I don't have that information."
- Provide helpful alternatives like contact info or related topics

#### Step 4: Model Configuration

**Max Context Chunks:**

- Default: 3
- Range: 1-10
- **What it means:** How many pieces of knowledge base the bot reviews before answering
- **Lower (1-2):** Faster, more focused answers
- **Higher (5-10):** More comprehensive, contextually rich answers
- **Recommended:** Start with 3, increase if answers lack context

**Max Tokens:**

- Default: 1000 tokens
- **What it means:** Maximum length of bot's response
- 1000 tokens ≈ 750 words
- **Lower:** Shorter, concise answers (good for quick support)
- **Higher:** Longer, detailed answers (good for explanations)
- **Recommended:** 1000 for most use cases

**Temperature:**

- Default: 0.7
- Range: 0.0-2.0
- **What it means:** Controls creativity vs. factualness
- **Lower (0.0-0.3):** More factual, deterministic
- **Medium (0.7):** Balanced (recommended)
- **Higher (1.0-2.0):** More creative, varied responses
- **Recommended:** 0.7 for most chatbots

**Advanced Settings:**

- **Top P:** Nucleus sampling (usually left at default)
- **Frequency Penalty:** Reduce repetition (0 = no penalty)
- **Presence Penalty:** Encourage new topics (0 = no penalty)

#### Step 5: Styles

**Position:**

- **Bottom Right:** Most common, unobtrusive
- **Bottom Left:** Alternative placement
- Choose based on your site layout

**Primary Color:**

- Select color matching your brand
- Used for header, buttons, and accents
- Use color picker or enter hex code

**Avatar:**

- Upload custom bot avatar image
- Recommended size: 64x64px or larger (square)
- Supported formats: PNG, JPG
- Leave empty to use default bot icon

**Font & Size:**

- Customize chat widget fonts
- Adjust font size for readability

**Widget Size:**

- Adjust chat bubble and window dimensions
- Test on mobile devices for responsiveness

#### Step 6: Publish

**Activate Chatbot:**

- Toggle ON to make chatbot live
- Toggle OFF to keep inactive (for testing/editing)

**Deployment Options:**

- **Enable Sitewide:** Shows on all pages automatically
- **Shortcode Only:** Use shortcode `[ai_botkit_chat id="X"]` on specific pages

**Final Steps:**

1. Review all settings
2. Click **"Create Bot"**
3. Test the chatbot on your site

### Managing Existing Chatbots

#### Viewing Chatbots

The "My Bots" page displays:

- Bot name and status (Active/Inactive)
- Sitewide badge (if enabled sitewide)
- Tone badge
- Message count and session statistics
- Action buttons

#### Editing Chatbots

- Click the edit icon (✏️) on any bot card
- Modify any settings from the creation wizard
- Changes take effect immediately for active bots

#### Activating/Deactivating

- Edit the chatbot
- Toggle "Activate Chatbot" in Step 6
- Save changes

#### Setting Sitewide Chatbot

- Only one chatbot can be sitewide at a time
- Enable "Sitewide" option in Step 6 (Publish section)
- This automatically shows on all pages without shortcodes

#### Copying Shortcodes

**Floating Widget Shortcode:**

```
[ai_botkit_chat id="1"]
```

- Place in pages/posts/widgets where you want the chatbot
- Replace `1` with your chatbot ID

**Widget Code (Alternative):**

- Use widget code for more customization
- Copy from bot card actions
- Paste into HTML blocks or theme files

#### Viewing Conversations

- Click **"View Conversations"** on any bot card
- See all chat sessions and messages
- Review user interactions and bot responses
- Analyze conversation quality

#### Deleting Chatbots

- Click the delete icon (🗑) on bot card
- Confirm deletion
- **Warning:** This permanently removes the chatbot and all its conversations

---

## Analytics & Monitoring

### Location

**KnowVault → Analytics**

### Overview Dashboard

The Analytics page provides comprehensive insights into chatbot performance and usage.

#### Key Metrics Display

**Total Interactions:**

- Total number of user messages and bot responses
- Indicates overall chatbot engagement

**Total Conversations:**

- Number of unique chat sessions started
- Measures how many users interact with the bot

**Unique Users:**

- Count of distinct users who have chatted
- Helps understand reach

**Total Tokens Used:**

- Cumulative token consumption
- Useful for tracking API costs

### Time Range Selection

- **7 Days:** Default view for recent activity
- **30 Days:** Monthly trends
- **90 Days:** Quarterly analysis
- **1 Year:** Long-term patterns

### Charts & Visualizations

#### Daily Usage Chart

- Line graph showing daily interaction counts
- Identify peak usage times
- Spot trends and patterns

#### Response Times Chart

- Average response time per day
- Lower is better (indicates faster AI responses)
- Helps identify performance issues

#### Error Rates Chart

- Percentage of failed interactions
- Monitor system health
- Investigate spikes in errors

#### Token Usage Chart

- Daily token consumption
- Track cost trends
- Plan for budget management

### Data Tables

#### Top Query Types

Shows:

- Query categories (product questions, support, general)
- Frequency of each type
- Average quality score
- Average response time

**Use Cases:**

- Identify most common user needs
- Optimize knowledge base based on popular queries
- Improve responses for frequent question types

#### Recent Errors

Displays:

- Error types (API failures, timeouts, etc.)
- Error count
- Component causing error
- Last occurrence timestamp

**Action Items:**

- Investigate recurring errors
- Check API key validity
- Review system health
- Contact support if persistent

### Best Practices for Analytics

1. **Regular Monitoring:** Check analytics weekly
2. **Trend Analysis:** Compare periods to identify patterns
3. **Optimization:** Use insights to improve chatbot knowledge base
4. **Cost Management:** Monitor token usage to control API costs
5. **Error Resolution:** Address errors promptly to maintain quality

---

## Features & Capabilities

### Core Features

#### 1. RAG (Retrieval-Augmented Generation)

- **What it is:** Advanced AI that combines retrieval of relevant information with language generation
- **How it works:**
  1. User asks question
  2. System searches knowledge base for relevant content
  3. AI generates answer based on retrieved context
  4. Response is accurate and contextually relevant

#### 2. Vector Search

- Semantic search using embeddings
- Finds relevant content even if keywords don't match exactly
- More accurate than traditional keyword search
- Works with both local database and Pinecone

#### 3. Multilingual Support

- Supports 50+ languages automatically
- Detects user language from their messages
- Responds in the same language
- No additional configuration needed

#### 4. Rate Limiting

- Prevents API cost overruns
- Protects against abuse
- Configurable per-user limits
- Real-time enforcement

#### 5. Conversation History

- Tracks all conversations
- Maintains context within sessions
- Enables personalized responses
- Accessible in admin dashboard

### Advanced Features

#### Custom Bot Instructions

- Define specific behavior rules
- Control response style and format
- Set constraints and boundaries
- Customize personality traits

#### Feedback Collection

- Users can rate responses (thumbs up/down)
- Helps identify quality issues
- Enables continuous improvement
- Feedback visible in analytics

#### Context-Aware Responses

- Bot remembers conversation context
- Provides follow-up answers
- Handles multi-turn conversations
- Maintains session state

#### Flexible Deployment

- **Floating Widget:** Bottom corner chat bubble
- **Inline Widget:** Embedded in page content
- **Shortcode:** Place anywhere with `[ai_botkit_chat id="X"]`
- **Sitewide:** Automatic display on all pages

---

## Best Practices

### Knowledge Base Management

1. **Start Small:** Begin with core content, expand gradually
2. **Keep Updated:** Reprocess content when you update your website
3. **Organize:** Use descriptive titles for easy management
4. **Review Failures:** Check and fix failed document processing
5. **Quality Over Quantity:** Better to have focused, accurate content than everything

### Chatbot Configuration

1. **Clear Greeting:** Set expectations in greeting message
2. **Helpful Fallback:** Provide alternatives when bot doesn't know answer
3. **Appropriate Tone:** Match tone to your brand voice
4. **Test Thoroughly:** Test chatbot before going live
5. **Monitor Performance:** Regularly check analytics for improvements

### Cost Management

1. **Choose Right Model:** Use cheaper models for simple tasks
2. **Set Reasonable Limits:** Configure rate limits based on expected usage
3. **Monitor Token Usage:** Track consumption in analytics
4. **Optimize Chunk Size:** Balance between context and cost
5. **Use Local Database:** Only use Pinecone if you have scale needs

### Performance Optimization

1. **Optimal Chunk Size:** Test different sizes for your content type
2. **Context Chunks:** Start with 3, adjust based on response quality
3. **Cache Settings:** Use default cache TTL unless you have specific needs
4. **Database Choice:** Local database is sufficient for most sites
5. **Regular Cleanup:** Remove unused documents from knowledge base

### User Experience

1. **Mobile Responsive:** Test chatbot on mobile devices
2. **Fast Loading:** Keep knowledge base size reasonable
3. **Clear Instructions:** Help users understand how to interact
4. **Fallback Options:** Always provide alternative contact methods
5. **Feedback Loop:** Encourage users to provide feedback

---

## Troubleshooting

### Common Issues & Solutions

#### API Key Issues

**Problem:** "API key invalid" or connection errors
**Solutions:**

- Verify API key is entered correctly (no extra spaces)
- Check API key hasn't expired or been revoked
- Ensure you have sufficient API credits/quota
- Test API key directly with provider's dashboard
- For Anthropic: Ensure VoyageAI key is also set

#### Chatbot Not Responding

**Problem:** Chatbot shows but doesn't respond to messages
**Solutions:**

- Check chatbot is activated in settings
- Verify API keys are configured and valid
- Check rate limits haven't been exceeded
- Review error logs in Analytics
- Ensure knowledge base has processed content

#### Slow Response Times

**Problem:** Chatbot takes too long to respond
**Solutions:**

- Reduce Max Context Chunks (try 2 instead of 3)
- Check your server performance
- Consider using Pinecone for faster search
- Reduce chunk size if very large
- Check API provider status/rate limits

#### Poor Answer Quality

**Problem:** Chatbot gives inaccurate or irrelevant answers
**Solutions:**

- Increase Max Context Chunks (try 5-7)
- Ensure relevant content is in knowledge base
- Reprocess knowledge base content
- Adjust bot instructions for clarity
- Test with different chunk sizes

#### Content Not Processing

**Problem:** Documents stay in "Pending" or "Processing" status
**Solutions:**

- Check for PHP errors in WordPress debug log
- Verify API keys are valid
- Check server memory limits (should be 256MB+)
- Review timeout settings
- Try reprocessing individual items
- Clear WordPress cache

#### Migration Issues (Pinecone)

**Problem:** Migration fails or gets stuck
**Solutions:**

- Verify Pinecone credentials are correct
- Ensure Pinecone index has 1536 dimensions
- Check Pinecone API quota/limits
- Try smaller batches
- Review migration logs
- Clear and restart migration

#### Shortcode Not Working

**Problem:** Shortcode doesn't display chatbot
**Solutions:**

- Verify chatbot ID is correct
- Ensure chatbot is activated
- Check for JavaScript errors in browser console
- Verify shortcode is on public-facing page (not admin)
- Test with sitewide deployment first
- Clear all caches (browser, WordPress, CDN)

### Getting Help

1. **Check Analytics:** Review error logs for specific issues
2. **Test API Keys:** Use verify buttons in settings
3. **Review Documentation:** Check plugin readme files
4. **WordPress Debug:** Enable WP_DEBUG to see errors
5. **Contact Support:** Email contact@aibotkit.io with details

---

## Warnings & Important Notes

### Critical Warnings

⚠️ **API Costs**

- You are responsible for all API costs from your provider
- Monitor token usage regularly in Analytics
- Set appropriate rate limits to control costs
- Different AI models have different pricing
- Usage can increase significantly with high traffic

⚠️ **Data Backup**

- Always backup your WordPress database before:
  - Major knowledge base operations
  - Migrations between databases
  - Clear operations
  - Plugin updates

⚠️ **Pinecone Configuration**

- Your Pinecone index MUST have exactly 1536 dimensions
- Incorrect dimensions will cause search failures
- Migration will fail if dimensions don't match
- Double-check configuration before enabling

⚠️ **Clear Operations**

- Clearing vector data is PERMANENT
- Clearing knowledge base removes ALL data
- Cannot be undone
- Backup before clearing

⚠️ **Content Processing**

- Large documents may take time to process
- Very large knowledge bases may impact performance
- Keep total knowledge base size reasonable
- Monitor processing times

### Important Notes

📝 **Rate Limits**

- Apply only to logged-in WordPress users
- Anonymous users are not restricted
- Consider this in your usage planning
- Adjust limits based on your user base

📝 **Multilingual Support**

- Automatic language detection
- No manual language selection needed
- Works best with well-translated content
- Some languages may have slightly lower quality

📝 **WordPress Compatibility**

- Tested with WordPress 5.8+
- Some caching plugins may require configuration
- Check compatibility with other active plugins
- Keep WordPress and PHP versions updated

📝 **Performance Considerations**

- Local database is sufficient for most sites
- Pinecone recommended for 10,000+ documents
- Large knowledge bases may slow down queries
- Optimize chunk sizes for your use case

📝 **Privacy & GDPR**

- All data stored locally on your WordPress site
- No external data transmission (except API calls)
- You control all user data
- Ensure your privacy policy covers chatbot usage

### Best Security Practices

1. **Protect API Keys:** Never share API keys publicly
2. **Regular Updates:** Keep plugin updated for security patches
3. **User Permissions:** Only grant admin access to trusted users
4. **Monitor Usage:** Regularly check for unusual activity
5. **Backup Regularly:** Maintain recent backups

### Plugin Limitations

- **PDF Only:** Currently only supports PDF documents for uploads
- **Images Not Processed:** Images in PDFs are not extracted
- **Single Sitewide Bot:** Only one bot can be sitewide at a time
- **Rate Limits for Logged-in Users:** Anonymous users not restricted
- **Local Database Default:** Pinecone requires separate setup

---

## Conclusion

KnowVault provides a powerful, flexible solution for adding intelligent chatbots to your WordPress site. By following this documentation, you can successfully configure, manage, and optimize your chatbots for maximum effectiveness.

For additional support, visit:

- **Documentation:** [aibotkit.gitbook.io/documentation](https://aibotkit.gitbook.io/documentation)
- **Support Forum:** [wordpress.org/support/plugin/ai-botkit-for-lead-generation](https://wordpress.org/support/plugin/ai-botkit-for-lead-generation)
- **Email Support:** contact@aibotkit.io

---

**Document Version:** 1.0  
**Last Updated:** Based on KnowVault v1.0.3  
**Plugin Maintained By:** WisdmLabs
