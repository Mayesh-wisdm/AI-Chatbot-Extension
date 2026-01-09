# WDM AI BotKit Extension with License
## User Documentation

### Table of Contents
1. [Introduction](#introduction)
2. [Getting Started](#getting-started)
3. [License Management](#license-management)
4. [LearnDash Integration](#learndash-integration)
5. [Features Overview](#features-overview)
6. [Settings & Configuration](#settings--configuration)
7. [Best Practices](#best-practices)
8. [Troubleshooting](#troubleshooting)
9. [Warnings & Important Notes](#warnings--important-notes)

---

## Introduction

The WDM AI BotKit Extension is a premium add-on for AI BotKit that enhances chatbots with LearnDash course awareness and advanced content integration. This extension enables your AI chatbots to:

- Understand which LearnDash courses users are enrolled in
- Provide personalized responses based on user's learning progress
- Access comprehensive LearnDash course content (lessons, topics, quizzes)
- Deliver context-aware answers that consider user's enrollment status

### Key Benefits
- **Personalized Learning Experience:** Chatbot responses adapt based on user's enrolled courses
- **Enhanced Content Access:** Comprehensive LearnDash integration with lessons, topics, and quizzes
- **User Course Awareness:** Bot knows what courses users are taking
- **Intelligent Responses:** Bot provides course information and enrollment links for relevant courses
- **Seamless Integration:** Works automatically with existing AI BotKit chatbots

### Requirements
- **AI BotKit Core Plugin:** Must be installed and activated
- **LearnDash:** LearnDash LMS plugin must be installed and active
- **Valid License Key:** Premium extension requires active license

---

## Getting Started

### Installation Steps

1. **Ensure Prerequisites**
   - AI BotKit core plugin is installed and activated
   - LearnDash LMS plugin is installed and activated
   - Both plugins are configured and working

2. **Install Extension**
   - Upload the WDM AI BotKit Extension plugin
   - Activate through WordPress Plugins screen

3. **Access Extension Settings**
   - Navigate to **AI BotKit → Extension License** (new tab in sidebar)
   - Extension integrates seamlessly into AI BotKit admin interface

4. **Activate License**
   - Enter your license key
   - Click "Activate License"
   - Wait for activation confirmation

5. **Verify Integration**
   - Check that "Extension License" tab appears in AI BotKit sidebar
   - Confirm license status shows as "Valid"

---

## License Management

### Location
**AI BotKit → Extension License**

### License Activation

#### Activating Your License

1. **Obtain License Key**
   - Purchase extension from WisdmLabs
   - Receive license key via email
   - Keep license key secure

2. **Enter License Key**
   - Go to Extension License page
   - Paste license key in "License Key" field
   - Click **"Activate License"**

3. **Verify Activation**
   - License status should show as "Valid"
   - Green checkmark indicator appears
   - Features become available immediately

#### License Status

The extension displays current license status:
- **Valid:** License is active, all features enabled ✅
- **Invalid:** License expired or invalid ❌
- **Expired:** License period has ended ⚠️

#### Deactivating License

To deactivate license (e.g., moving to different site):
1. Go to Extension License page
2. Click **"Deactivate License"**
3. Confirm deactivation
4. License is released for use on another site

**Important:** Deactivating license will disable premium features but won't remove data.

#### Checking License Status

**Manual Check:**
- Click **"Check License Status"** button
- System contacts license server
- Updates status display
- Shows any changes in status

**Automatic Checks:**
- License status verified periodically
- Expired licenses automatically disabled
- Status updates reflected in real-time

### License Troubleshooting

**If License Shows Invalid:**
- Verify license key is correct (no extra spaces)
- Check license hasn't expired
- Ensure license hasn't been used on maximum allowed sites
- Contact support if issues persist

**If License Won't Activate:**
- Check internet connection
- Verify license server is accessible
- Ensure no firewall blocking license server
- Try deactivating and reactivating

**Support:** Contact WisdmLabs support for license-related issues

---

## LearnDash Integration

### Overview

The extension enhances AI BotKit with deep LearnDash integration, enabling chatbots to understand and use LearnDash course content intelligently.

### User Course Awareness

#### What It Does
- **Detects User Enrollments:** Knows which courses each user is enrolled in
- **Context-Aware Responses:** Adjusts answers based on user's learning status
- **Personalized Recommendations:** Suggests relevant courses based on queries
- **Progress Awareness:** Understands user's learning progress

#### How It Works

1. **User Authentication**
   - Extension detects logged-in WordPress users
   - Identifies user's LearnDash course enrollments
   - Passes enrollment information to chatbot

2. **Content Retrieval**
   - When user asks about courses, bot includes enrollment status
   - Retrieves full course content if user is enrolled
   - Provides course information and enrollment links if not enrolled

3. **Response Generation**
   - Bot uses enrollment context in responses
   - Enrolled users get full course details
   - Non-enrolled users get course info and enrollment options

### LearnDash Course Sync

#### Purpose
Upgrades existing LearnDash courses in your chatbot's knowledge base from basic post data to comprehensive content including lessons, topics, quizzes, and metadata.

#### When to Use
- You've already added LearnDash courses to knowledge base
- Courses were imported before installing extension
- You want to enhance existing course data with full LearnDash content
- You need comprehensive course information in chatbot responses

#### How to Sync

1. **Access Sync Tool**
   - Go to **AI BotKit → Extension License**
   - Scroll to "LearnDash Course Sync" section
   - Ensure license is active (required)

2. **Start Content Upgrade**
   - Click **"Upgrade LearnDash Content in Knowledge Base"** button
   - System identifies courses already in knowledge base
   - Only processes courses that exist in knowledge base (targeted sync)

3. **Monitor Progress**
   - Progress bar shows completion status
   - Real-time updates: "Processing course X of Y"
   - Status messages indicate current action

4. **Review Results**
   - Summary shows total courses processed
   - Any errors are listed with details
   - Successfully upgraded courses available immediately

#### What Gets Synced

**For Each Course:**
- Course title and description
- All lessons with full content
- All topics with content
- All quizzes with questions and answers
- Course metadata (categories, tags)
- Course prerequisites
- Enrollment requirements

**Content Structure:**
```
Course
├── Lessons
│   ├── Lesson Content
│   ├── Associated Topics
│   │   └── Topic Content
│   └── Associated Quizzes
│       └── Quiz Questions
└── Course Metadata
```

#### Sync Behavior

**Targeted Processing:**
- Only processes courses already in knowledge base
- Doesn't add new courses automatically
- Respects existing knowledge base configuration
- Preserves document associations

**Batch Processing:**
- Handles large numbers of courses efficiently
- Processes in batches to avoid timeouts
- Progress saved between batches
- Can resume if interrupted

**Error Handling:**
- Failed courses are logged with details
- Other courses continue processing
- Error summary provided at completion
- Individual courses can be reprocessed

#### Upgrade Availability

**Content Upgrade Available:**
- Shown when courses exist but need upgrading
- Appears when license was expired and now reactivated
- Click to re-enable comprehensive content sync

**Upgrade Completed:**
- Displayed after successful sync
- Indicates courses now have comprehensive content
- Courses ready for enhanced chatbot responses

### Content Transformation

The extension transforms basic WordPress post data into rich LearnDash course content.

**Before Transformation:**
- Basic course title
- Simple course description
- Limited content context

**After Transformation:**
- Complete course structure
- All lessons with full content
- All topics with descriptions
- Quiz questions and answers
- Course relationships and prerequisites
- Enrollment information

---

## Features Overview

### 1. User Course Awareness

**What It Provides:**
- Real-time enrollment detection
- Context-aware response generation
- Personalized course recommendations
- Progress-based answer adaptation

**How Users Benefit:**
- Get answers relevant to their enrolled courses
- Receive personalized learning guidance
- Access course-specific information easily
- Find enrollment links for relevant courses

### 2. Comprehensive LearnDash Content

**Content Types Included:**
- Course descriptions and metadata
- Lesson content and materials
- Topic details and resources
- Quiz questions and answers
- Course prerequisites and requirements

**Benefits:**
- More accurate chatbot responses
- Complete course information in knowledge base
- Better context for AI understanding
- Enhanced user experience

### 3. Intelligent Response Processing

**Smart Behavior:**
- Different responses for enrolled vs. non-enrolled users
- Course recommendations based on queries
- Enrollment link provision for relevant courses
- Full content access for enrolled users

**Examples:**
- **Enrolled User:** Gets full lesson content and quiz details
- **Non-Enrolled User:** Receives course information and enrollment link
- **General Query:** Bot provides relevant course suggestions

### 4. Seamless Integration

**Admin Interface:**
- Integrated into AI BotKit admin panel
- Accessible via "Extension License" tab
- Consistent with core plugin design
- Easy to use and navigate

**Functionality:**
- Works automatically with existing chatbots
- No additional chatbot configuration needed
- Transparent to end users
- Operates in background

---

## Settings & Configuration

### Extension Settings Location

**AI BotKit → Extension License**

### Available Settings

#### License Key Management
- **License Key Field:** Enter or update license key
- **Activate Button:** Activate new or updated license
- **Deactivate Button:** Release license for use elsewhere
- **Check Status Button:** Verify current license status

#### LearnDash Sync Controls
- **Upgrade Button:** Start content upgrade process
- **Progress Display:** Real-time sync progress
- **Results Summary:** Completion status and errors

### Configuration Requirements

#### Required Settings
1. **Active License:** Premium features require valid license
2. **LearnDash Active:** Extension needs LearnDash plugin active
3. **AI BotKit Active:** Core plugin must be installed and active

#### Optional Actions
1. **Content Upgrade:** Run sync to enhance existing courses
2. **Status Checks:** Periodically verify license status
3. **Content Reprocessing:** Reprocess courses after LearnDash updates

### Integration Points

#### With AI BotKit Core
- Uses existing knowledge base structure
- Leverages chatbot configuration
- Works with all AI BotKit features
- Respects core plugin settings

#### With LearnDash
- Detects LearnDash courses automatically
- Uses LearnDash enrollment data
- Accesses LearnDash content structure
- Respects LearnDash permissions

---

## Best Practices

### License Management

1. **Keep License Active:**
   - Monitor license expiration dates
   - Renew before expiration to avoid downtime
   - Check status regularly

2. **Secure License Key:**
   - Don't share license key publicly
   - Store in secure location
   - Use environment variables if possible

3. **Site-Specific Usage:**
   - Deactivate before moving to new site
   - One license per active site
   - Follow license terms

### LearnDash Sync

1. **Sync After Updates:**
   - Run sync after major LearnDash updates
   - Sync new courses added to knowledge base
   - Update after course content changes

2. **Monitor Sync Progress:**
   - Watch for errors during sync
   - Review error messages for issues
   - Reprocess failed courses individually

3. **Optimize Knowledge Base:**
   - Only sync courses relevant to chatbots
   - Don't sync private or draft courses
   - Keep knowledge base focused

### User Experience

1. **Test with Different Users:**
   - Test as enrolled student
   - Test as non-enrolled visitor
   - Verify personalized responses

2. **Monitor Chatbot Responses:**
   - Check that enrollment awareness works
   - Verify course recommendations
   - Ensure enrollment links function

3. **Content Quality:**
   - Ensure LearnDash courses have good content
   - Keep course information updated
   - Review chatbot responses regularly

### Performance

1. **Batch Processing:**
   - Sync processes in batches automatically
   - Large knowledge bases handled efficiently
   - Patience during large syncs

2. **Cache Considerations:**
   - Clear caches after sync
   - User awareness updates in real-time
   - No special cache configuration needed

3. **Server Resources:**
   - Sync may be resource-intensive
   - Run during low-traffic periods
   - Monitor server load

---

## Troubleshooting

### License Issues

#### License Won't Activate

**Symptoms:**
- Activation button doesn't work
- Error message on activation
- Status stays "Invalid"

**Solutions:**
- Verify license key is correct (no spaces)
- Check internet connection
- Ensure license server is accessible
- Verify license hasn't reached site limit
- Try deactivating and reactivating
- Contact support with license key (securely)

#### License Shows Expired

**Symptoms:**
- Status displays as expired
- Features disabled
- Need to renew

**Solutions:**
- Renew license through WisdmLabs
- Enter new license key
- Reactivate license
- Verify renewal confirmation

#### Features Not Available

**Symptoms:**
- License shows valid but features disabled
- LearnDash sync not accessible

**Solutions:**
- Verify AI BotKit core is active
- Check LearnDash plugin is installed and active
- Refresh admin page
- Check for plugin conflicts
- Verify extension is latest version

### LearnDash Sync Issues

#### Sync Fails to Start

**Symptoms:**
- Button doesn't respond
- No progress displayed
- Error message appears

**Solutions:**
- Ensure license is active
- Verify LearnDash plugin is active
- Check knowledge base has courses
- Review browser console for errors
- Try refreshing page

#### Sync Gets Stuck

**Symptoms:**
- Progress bar doesn't advance
- Processing message stuck
- No completion after long time

**Solutions:**
- Refresh page (progress may have completed)
- Check if courses were already processed
- Review error logs
- Try smaller batches
- Contact support with details

#### Errors During Sync

**Symptoms:**
- Error summary shows failures
- Some courses not processed
- Error messages in results

**Solutions:**
- Review error messages for specifics
- Check course permissions in LearnDash
- Verify courses exist and are published
- Try reprocessing individual courses
- Ensure courses are in knowledge base

#### Courses Not Showing Enhanced Content

**Symptoms:**
- Sync completes but content unchanged
- Chatbot responses still basic

**Solutions:**
- Verify sync actually processed courses
- Check courses are in knowledge base
- Ensure chatbot uses updated knowledge base
- Clear WordPress cache
- Reprocess specific courses

### User Awareness Issues

#### Enrollment Not Detected

**Symptoms:**
- Bot doesn't recognize user enrollments
- Responses don't consider enrollment status

**Solutions:**
- Ensure user is logged in (awareness requires authentication)
- Verify LearnDash enrollment is active
- Check LearnDash user data is correct
- Test with different enrolled users
- Review chatbot knowledge base includes courses

#### Incorrect Responses

**Symptoms:**
- Bot gives wrong enrollment information
- Responses don't match user's courses

**Solutions:**
- Verify LearnDash enrollment data is accurate
- Check course sync completed successfully
- Ensure chatbot knowledge base is current
- Review bot instructions for clarity
- Test with fresh chat session

### Integration Issues

#### Extension Tab Not Visible

**Symptoms:**
- "Extension License" tab missing from sidebar
- Can't access extension settings

**Solutions:**
- Verify extension is activated
- Ensure AI BotKit core is active
- Check for plugin conflicts
- Clear browser cache
- Re-activate extension

#### Conflicts with Other Plugins

**Symptoms:**
- Features not working properly
- Errors in admin area
- Unexpected behavior

**Solutions:**
- Deactivate other plugins temporarily
- Identify conflicting plugin
- Check plugin compatibility
- Update all plugins to latest versions
- Contact support with plugin list

---

## Warnings & Important Notes

### Critical Warnings

⚠️ **License Requirements**
- Premium features require active license
- Expired license disables extension features
- One license per active installation
- License terms must be followed

⚠️ **LearnDash Dependency**
- Extension requires LearnDash LMS plugin
- Won't function without LearnDash installed
- Ensure LearnDash is updated and compatible
- Extension features depend on LearnDash data

⚠️ **AI BotKit Dependency**
- Extension requires AI BotKit core plugin
- Must have active AI BotKit installation
- Extension integrates with core plugin
- Both plugins must be compatible versions

⚠️ **Data Sync Operations**
- Large course syncs may take significant time
- Don't interrupt sync process
- Sync processes in background batches
- Review results after completion

⚠️ **Content Updates**
- Course updates in LearnDash require re-sync
- Old content remains until synced
- Chatbot uses knowledge base version
- Keep knowledge base updated

### Important Notes

📝 **User Authentication**
- Course awareness requires logged-in users
- Anonymous users don't get enrollment context
- Extension uses WordPress user authentication
- LearnDash enrollment data is source of truth

📝 **Knowledge Base Integration**
- Extension enhances existing knowledge base
- Doesn't replace knowledge base management
- Courses must be added to knowledge base first
- Sync only upgrades existing course content

📝 **Targeted Sync Behavior**
- Sync only processes courses in knowledge base
- Doesn't automatically add all LearnDash courses
- Respects existing knowledge base structure
- Selective processing for efficiency

📝 **Response Personalization**
- Responses vary based on enrollment status
- Enrolled users get full course content
- Non-enrolled users get course info and links
- General content (non-LearnDash) always accessible

📝 **Performance Considerations**
- Course sync may be resource-intensive
- Large knowledge bases take longer
- Process during low-traffic periods
- Monitor server performance during sync

### Security & Privacy

1. **User Data:**
   - Extension accesses LearnDash enrollment data
   - Uses WordPress user authentication
   - Respects LearnDash permissions
   - No external data transmission (except license)

2. **License Verification:**
   - Connects to license server for verification
   - Transmits site URL and license key
   - Doesn't transmit user data
   - Secure connection (HTTPS)

3. **Content Access:**
   - Respects LearnDash course permissions
   - Uses existing knowledge base access controls
   - No additional security exposure
   - Follows WordPress security standards

### Plugin Limitations

- **LearnDash Only:** Extension works only with LearnDash LMS
- **Logged-in Users:** Enrollment awareness requires user login
- **Knowledge Base Dependency:** Courses must be in knowledge base
- **License Required:** Premium features need active license
- **Single License:** One license per WordPress installation

### Upgrade Considerations

**When Upgrading Extension:**
- Backup WordPress database
- Verify compatibility with AI BotKit core
- Test in staging environment first
- Review changelog for changes
- Update LearnDash if needed

**When Upgrading LearnDash:**
- Run course sync after LearnDash update
- Verify enrollment detection still works
- Test chatbot responses
- Update knowledge base content

---

## Support & Resources

### Getting Help

**Documentation:**
- Review this user documentation
- Check AI BotKit core documentation
- Refer to LearnDash documentation
- Visit WisdmLabs knowledge base

**Support Channels:**
- **WisdmLabs Support:** Available for license holders
- **Support Email:** Contact through WisdmLabs website
- **Support Forum:** WordPress.org support forums
- **Documentation:** Online documentation and guides

### Useful Information to Provide

When requesting support, include:
- Extension version number
- AI BotKit core version
- LearnDash version
- WordPress version
- PHP version
- License status
- Specific error messages
- Steps to reproduce issue

---

## Conclusion

The WDM AI BotKit Extension enhances your AI BotKit chatbots with powerful LearnDash integration and user course awareness. By following this documentation, you can successfully configure and use the extension to provide personalized, context-aware chatbot experiences for your LearnDash students.

For additional support:
- **Documentation:** Check this guide and AI BotKit core docs
- **Support:** Contact WisdmLabs support for premium assistance
- **Updates:** Keep extension updated for latest features

---

**Document Version:** 1.0  
**Last Updated:** Based on WDM AI BotKit Extension v1.0.2  
**Extension Maintained By:** WisdmLabs


