# OCR Setup Guide for Cyprus Point Scorecards

## Quick Setup with OCR.space (Free)

### Step 1: Get API Key
1. Go to https://ocr.space/ocrapi
2. Sign up for free account
3. Get your API key from the dashboard

### Step 2: Configure Package
Add to your `.env` file:
```bash
OCRSPACE_API_KEY=your_api_key_here
SCORECARD_OCR_PROVIDER=ocrspace
```

### Step 3: Test with Your Scorecards
Run the test to see real OCR results:
```bash
vendor/bin/phpunit --filter test_cyprus_point_front_nine_ocr_extraction tests/Unit/ActualImageOcrTest.php
```

## Alternative: Google Vision API (More Accurate)

### Step 1: Google Cloud Setup
1. Create Google Cloud project
2. Enable Vision API
3. Create service account key
4. Download JSON credentials file

### Step 2: Configure Package
```bash
GOOGLE_CLOUD_CREDENTIALS_PATH=/path/to/your/credentials.json
GOOGLE_CLOUD_PROJECT_ID=your-project-id
SCORECARD_OCR_PROVIDER=google
```

### Step 3: Install Google Client
```bash
composer require google/cloud-vision
```

## Expected Results for Cyprus Point

Once configured with real OCR, you should see:
- ✅ "Cyprus Point" course name extraction
- ✅ Actual hole numbers and par values from your images
- ✅ Any handwritten scores or player names
- ✅ Course layout and yardage information
- ✅ Date and other scorecard details

## Testing Commands

```bash
# Test front nine
vendor/bin/phpunit --filter test_cyprus_point_front_nine_ocr_extraction tests/Unit/ActualImageOcrTest.php

# Test back nine  
vendor/bin/phpunit --filter test_cyprus_point_back_nine_ocr_extraction tests/Unit/ActualImageOcrTest.php

# Test both with preprocessing
vendor/bin/phpunit tests/Unit/ActualImageOcrTest.php
```

## Troubleshooting

If OCR results are poor:
1. Check image quality (your images are good size: 3.57MB and 2.79MB)
2. Try different OCR providers
3. Adjust confidence thresholds in config
4. Use image preprocessing (already enabled)