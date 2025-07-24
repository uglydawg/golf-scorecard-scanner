# OpenAI Vision Setup for Cyprus Point Scorecards

## 🎯 Quick Setup (2 minutes)

### Step 1: Configure OpenAI in your .env file
```bash
# Add these lines to your .env file:
OPENAI_API_KEY=your_openai_api_key_here
SCORECARD_OCR_PROVIDER=openai
OPENAI_OCR_MODEL=gpt-4o-mini
```

## 🤖 **Recommended Models:**

### **gpt-4o-mini** (Recommended for testing)
- ✅ **Best value** - Great accuracy at low cost
- ✅ **Fast processing** (5-10 seconds per image)
- ✅ **Cost**: ~$0.01 per image
- ✅ **Perfect for development and testing**

### **gpt-4o** (Best accuracy)
- ✅ **Highest accuracy** for complex layouts
- ✅ **Better handwriting recognition**
- 💰 **Higher cost**: ~$0.05 per image
- 🔄 **Slower processing** (10-20 seconds)

### ❌ **gpt-3.5-turbo** (No vision)
- Cannot process images - text only

## 🧪 Test Your Cyprus Point Scorecards

Once configured, run these commands to see real OCR results:

```bash
# Test front nine
vendor/bin/phpunit --filter test_cyprus_point_front_nine_ocr_extraction tests/Unit/ActualImageOcrTest.php

# Test back nine
vendor/bin/phpunit --filter test_cyprus_point_back_nine_ocr_extraction tests/Unit/ActualImageOcrTest.php
```

## 🎯 **What You'll Get with OpenAI Vision:**

### **Structured JSON Output:**
```json
{
  "course_name": "Cyprus Point Golf Club",
  "date": "2024-07-24",
  "players": ["John Smith", "Jane Doe"],
  "holes": [
    {
      "number": 1,
      "par": 4,
      "yardage": 385,
      "handicap": 10,
      "scores": [4, 5]
    }
  ],
  "totals": {
    "front_nine": [38, 40],
    "back_nine": [35, 37],
    "total": [73, 77]
  },
  "course_info": {
    "rating": "72.1",
    "slope": "113"
  }
}
```

### **Advantages of OpenAI Vision:**
- 🏌️ **Golf-aware**: Understands scorecard layouts
- 📊 **Structured data**: Returns organized JSON
- ✍️ **Handwriting**: Can read handwritten scores
- 🎯 **Context-aware**: Understands what each number represents
- 🔧 **Self-correcting**: Can fix obvious errors

## 💰 **Cost Estimate:**
- **gpt-4o-mini**: ~$0.01 per scorecard image
- **gpt-4o**: ~$0.05 per scorecard image
- Your Cyprus Point images are perfect size (2-4MB)

## 🔧 **Alternative Model Configurations:**

```bash
# For maximum accuracy (higher cost)
OPENAI_OCR_MODEL=gpt-4o

# For testing/development (lower cost)
OPENAI_OCR_MODEL=gpt-4o-mini

# Increase token limit for complex scorecards
OPENAI_MAX_TOKENS=4000

# Increase timeout for slower processing
OPENAI_TIMEOUT=90
```

Ready to test? Just add your OpenAI API key and run the tests!