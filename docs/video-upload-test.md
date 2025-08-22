# Video Upload System Testing Guide

## ✅ What has been implemented:

### Backend (Laravel Admin Panel)
1. **Database Structure**
   - `videos` table - stores video metadata
   - `video_access_logs` table - tracks user access
   - Relationships set up between Lesson ↔ Video

2. **Models Created**
   - `Video.php` - Main video model with HLS support
   - `VideoAccessLog.php` - Tracking model
   - Updated `Lesson.php` with video relationships

3. **API Endpoints** (`/api/video/`)
   - `POST /upload` - Upload new video
   - `GET /status/{id}` - Check processing status
   - `GET /{id}/generate-url` - Get secure streaming URL
   - `GET /stream/{id}` - Stream video content
   - `PUT /access-log/{id}` - Update viewing stats

4. **Admin Panel Integration**
   - Updated LessonResource with file upload field
   - Shows video processing status
   - Displays video information (size, duration)
   - Handles video replacement on edit

5. **Video Processing Job**
   - `ProcessVideoJob` queues video conversion
   - Converts to HLS format (when FFmpeg available)
   - Generates encryption keys
   - Updates status (pending → processing → ready)

## 🧪 How to Test:

### Step 1: Access Admin Panel
1. Go to http://localhost:8001/admin
2. Login with your admin credentials
3. Navigate to "Lessons" in the sidebar

### Step 2: Create/Edit a Lesson with Video
1. Click "New Lesson" or edit existing one
2. Fill in required fields:
   - Course ID
   - Title
   - Duration (minutes)
   - Order Index
3. In "Video Content" section:
   - Click "Upload Video" 
   - Select a video file (MP4, MOV, AVI, WebM)
   - Max size: 2GB
4. Save the lesson

### Step 3: Check Video Processing
- After saving, video status will show:
  - **Yellow "Pending"** - Waiting in queue
  - **Blue "Processing"** - Converting to HLS
  - **Green "Ready"** - Available for streaming
  - **Red "Failed"** - Error occurred

### Step 4: Test API Endpoints
```bash
# Check video status
curl http://localhost:8001/api/video/status/{video-id}

# Generate streaming URL (requires auth)
curl -H "Authorization: Bearer {token}" \
  http://localhost:8001/api/video/{video-id}/generate-url

# Stream video (with signed URL)
curl "http://localhost:8001/api/video/stream/{video-id}?user={user}&expires={timestamp}&token={token}"
```

## ⚠️ Important Notes:

### FFmpeg Required
- Video conversion to HLS requires FFmpeg
- Install with: `brew install ffmpeg` (macOS)
- Without FFmpeg, videos will upload but fail processing

### Queue Worker
For video processing to work, run queue worker:
```bash
php artisan queue:work
```

### Storage Paths
Videos are stored in:
- Temp: `storage/app/temp-videos/`
- Processed: `storage/app/videos/{video-id}/`

## 🚀 Next Steps for Frontend:

### 1. Create Secure Video Player Component
```tsx
// components/SecureVideoPlayer.tsx
- HLS.js integration
- Token-based authentication
- Watermark overlay
- Screenshot prevention
- Watch time tracking
```

### 2. Add to Course/Lesson Pages
- Display video player when lesson has video
- Show processing status if not ready
- Handle expired tokens with refresh

### 3. Security Features to Add
- Device fingerprinting
- Concurrent stream limiting
- Dynamic watermarking with user info
- Screen recording detection

## 📝 Current Limitations:

1. **No FFmpeg** = No HLS conversion (will serve original file)
2. **No CDN** = Videos served directly from server
3. **No DRM** = Basic token protection only
4. **Manual Queue** = Need to run `queue:work` manually

## 🔒 Security Features Already Included:

1. **Signed URLs** - 2-hour expiration
2. **Token Validation** - HMAC-SHA256 signed
3. **Access Logging** - Tracks all video views
4. **Range Requests** - Proper video streaming support
5. **Suspicious Activity Detection** - Excessive seeking/speed changes

## Testing Without FFmpeg:

If FFmpeg is not installed, the system will:
1. Accept video uploads ✅
2. Store original file ✅
3. Mark as "failed" in processing ⚠️
4. Still allow streaming of original file ✅

To test without conversion:
1. Upload a small MP4 file
2. Manually update video status in database to 'ready'
3. Test streaming endpoint

## Database Check Commands:

```sql
-- Check uploaded videos
SELECT * FROM videos;

-- Check video access logs
SELECT * FROM video_access_logs;

-- Update video status manually (for testing)
UPDATE videos SET status = 'ready' WHERE id = 'your-video-id';
```

---

Video upload system is ready for testing! 🎥