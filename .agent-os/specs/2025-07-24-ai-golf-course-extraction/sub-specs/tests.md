# Tests Specification

This is the tests coverage details for the spec detailed in @.agent-os/specs/2025-07-24-ai-golf-course-extraction/spec.md

> Created: 2025-07-24
> Version: 1.0.0

## Test Coverage

### Unit Tests

**OcrService Enhanced Prompt Methods**
- Test `getEnhancedGolfScorecardExtractionPrompt()` returns properly formatted JSON schema prompt
- Test enhanced prompt includes all required validation rules and field specifications
- Test prompt maintains golf domain expertise and extraction priorities

**Enhanced Response Processing**
- Test `processEnhancedOpenAIResponse()` correctly parses new JSON schema format
- Test handling of course_information, tee_boxes array, and player_scores array structures
- Test graceful degradation when enhanced JSON parsing fails
- Test confidence scoring calculation for enhanced data extraction

**Multi-Tee Box Processing**
- Test `processTeeBoxConfigurations()` handles multiple tee box objects correctly
- Test proper separation of Men's vs Ladies' tee data
- Test validation of tee-specific par, handicap, and yardage arrays
- Test course rating and slope rating validation per tee box

**Advanced Data Validation**
- Test par values validation (exactly 18 integers, each 3-6)
- Test handicap values validation (exactly 18 unique integers, 1-18 range)
- Test yardages validation (exactly 18 integers, 50-700 range)
- Test course rating validation (67.0-77.0 range)
- Test slope rating validation (55-155 range)
- Test player scores validation (reasonable hole scores 1-15)

### Integration Tests

**Enhanced OCR Processing Workflow**
- Test complete scorecard processing with enhanced prompt using sample Cypress Point Club data
- Test extraction accuracy meets 95%+ target for all required fields
- Test proper creation of UnverifiedCourse records with enhanced data
- Test multi-tee box scorecard processing creates separate course records

**Player Score Integration** 
- Test player score extraction from completed scorecards
- Test creation of Round and RoundScore records from extracted player data
- Test handling of multiple players on single scorecard
- Test score validation against course par values

**Backward Compatibility**
- Test existing OCR functionality remains unaffected when enhanced features are disabled
- Test gradual migration from standard to enhanced prompt processing
- Test configuration flag properly toggles between prompt systems

### Feature Tests

**End-to-End Enhanced Extraction**
- Test complete workflow: upload scorecard image → enhanced OCR processing → structured JSON output → database population
- Test Cypress Point Club scorecard processing matches expected JSON schema output
- Test multi-tee configuration extraction (Championship, Middle, Ladies tees)
- Test player score extraction and round creation workflow

**Data Quality Assurance**
- Test extracted course data meets USGA standards and validation rules
- Test confidence scoring accurately reflects extraction quality
- Test error handling for partial extractions and validation failures

### Mocking Requirements

**OCR Provider Responses**: Mock OpenAI/OpenRouter API responses with sample Cypress Point Club data structure
**Image Processing**: Mock image file handling to focus on prompt and response processing logic
**Database Operations**: Use Laravel's database transactions and factory data for consistent test environments