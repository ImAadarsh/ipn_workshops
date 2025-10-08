# Joining Links Management Feature

## Overview
This feature allows administrators to prepare joining links for all enrolled users for a workshop and track the progress. It populates the workshops_emails table with is_sent = 0 to indicate emails are ready for sending. It also includes the ability to send actual workshop reminder emails with professional templates and unique joining links.

## Files Added/Modified

### Modified Files:
- `workshop-details.php` - Added the UI components and JavaScript functionality

### New Files:
- `send_joining_links.php` - Handles preparing joining links and populating the workshops_emails table with is_sent = 0
- `send_workshop_reminder_emails.php` - Handles sending actual workshop reminder emails to prepared users
- `get_joining_links_status.php` - Returns the current status of prepared joining links for a workshop

## Database Schema

### workshops_emails Table
The system automatically creates this table if it doesn't exist:

```sql
CREATE TABLE workshops_emails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    workshop_id INT NOT NULL,
    trainer_id INT,
    user_id INT NOT NULL,
    payment_id INT NOT NULL,
    user_email VARCHAR(255) NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sending_email_id INT,
    is_sent TINYINT(1) DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_workshop_payment_user (workshop_id, payment_id, user_id),
    INDEX idx_workshop_id (workshop_id),
    INDEX idx_user_id (user_id),
    INDEX idx_payment_id (payment_id)
);
```

## How It Works

### 1. Prepare Joining Links
- Fetches all users with `payment_status = 1` for the workshop
- Creates entries in the `workshops_emails` table with `is_sent = 0`
- Uses NOT EXISTS clause to only insert new entries (prevents duplicates)
- Updates progress bar and UI in real-time
- Shows count of new entries added vs. total entries

### 2. Progress Tracking
- Shows total entries in workshops_emails table vs. actually sent emails (is_sent = 1)
- Displays progress percentage based on sent/total ratio
- Updates status messages
- Shows completion state

### 3. Loading States
- Blur effect applied to the entire card immediately on page load
- Shows "Loading..." text while fetching initial data
- Prevents user interaction during API calls
- Smooth transitions for better user experience

## UI Components

### Progress Bar
- Animated progress bar with shimmer effect during sending
- Shows percentage completion
- Color-coded (green gradient)

### Buttons
- **Prepare Joining Links**: Initiates the preparation process
- **Send Reminder Emails**: Sends actual workshop reminder emails to prepared users

### Status Display
- Real-time status updates
- Count of sent emails (is_sent = 1) vs. total entries in workshops_emails table
- Informational messages

## Workshop Reminder Email Features

### Email Template
- Professional HTML email template with IPN Academy branding
- Includes workshop details, trainer information, and meeting credentials
- Features unique joining links for each user
- Mobile-responsive design with modern styling
- Includes certificate download instructions and disclaimer

### Email Content
- Workshop name, trainer name, and timing
- Meeting ID and passcode
- User's registered email address
- Unique joining link with user and payment tracking
- Steps to download certificate
- Professional disclaimer and contact information

### Email Delivery
- Uses multiple email configurations for reliability
- Supports both PHPMailer and built-in PHP mail functions
- Includes error handling and retry mechanisms
- Tracks which email address was used for sending
- Updates database with sent status and timestamp

## Security Features
- Session-based authentication required
- SQL injection protection with prepared statements
- Transaction-based operations for data integrity
- Error handling and logging

## Usage
1. Navigate to any workshop details page
2. Find the "Joining Link Management" section
3. Click "Prepare Joining Links" to populate the workshops_emails table with is_sent = 0
4. Monitor progress in real-time
5. The system will show 0% progress initially after preparing (since is_sent = 0)
6. Click "Send Reminder Emails" to send actual workshop reminder emails to prepared users
7. Monitor email sending progress and view detailed results

## Technical Notes
- Uses AJAX for seamless user experience
- Implements proper error handling
- Includes loading states and user feedback with blur effects
- Responsive design for mobile devices
- Follows existing code patterns and styling
- Optimized with single INSERT query using NOT EXISTS for better performance
- No table creation on every request - assumes table exists
- Sets is_sent = 0 to indicate emails are ready for sending
- Progress shows 0% after preparing emails (since they're not sent yet)
- No revert functionality - once prepared, emails stay in the system
