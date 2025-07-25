# Technical Specification

This is the technical specification for the spec detailed in @.agent-os/specs/2025-07-24-ai-golf-course-extraction/spec.md

> Created: 2025-07-24
> Version: 1.0.0

## Technical Requirements

- **Enhanced AI Prompt**: Replace existing `getGolfScorecardExtractionPrompt()` method with comprehensive JSON schema-based prompt that matches the provided specification
- **Structured Response Processing**: Update `processOpenAIResponse()` and related methods to handle the new JSON schema format with course_information, tee_boxes array, and player_scores array
- **Multi-Tee Box Support**: Modify data processing logic to handle multiple tee configurations within a single scorecard extraction
- **Advanced Validation**: Implement comprehensive validation rules for par values (3-6), handicap values (1-18 unique), course ratings (67.0-77.0), slope ratings (55-155), and player scores (1-15 per hole)
- **Enhanced Data Mapping**: Update data transformation logic to map extracted JSON to Laravel models (Course, UnverifiedCourse, Round, RoundScore)
- **Confidence Scoring**: Maintain existing confidence scoring while adding field-level confidence tracking for new data types
- **Error Handling**: Enhance error handling to gracefully manage partial extractions and validation failures

## Approach Options

**Option A: Complete Prompt Replacement**
- Pros: Clean implementation, optimal accuracy, direct schema alignment
- Cons: Requires thorough testing of existing functionality, potential breaking changes

**Option B: Dual Prompt System** (Selected)
- Pros: Maintains backward compatibility, allows A/B testing, gradual rollout capability
- Cons: Increased complexity, larger codebase footprint

**Option C: Incremental Enhancement**
- Pros: Minimal risk, easy rollback
- Cons: Suboptimal accuracy gains, technical debt accumulation

**Rationale:** Option B provides the best balance of innovation and stability. We can implement the enhanced prompt as a new method while maintaining the existing prompt for backward compatibility. This allows thorough testing and gradual migration of users to the enhanced system.

## External Dependencies

- **No new Laravel packages required** - Enhancement builds on existing Intervention Image and HTTP client capabilities
- **OCR Provider Requirements** - Enhanced prompts will work with existing OpenAI, OpenRouter, and other Vision API providers
- **Database Schema Compatibility** - Leverages existing Course, UnverifiedCourse, Round, and RoundScore models without modification

## Implementation Architecture

### Enhanced Prompt System
```php
// New method alongside existing prompt
private function getEnhancedGolfScorecardExtractionPrompt(): string

// Configuration flag to enable enhanced processing
private bool $useEnhancedPrompt = false;
```

### Response Processing Pipeline
```php
// Enhanced response processor for new JSON schema
private function processEnhancedOpenAIResponse(string $content): array

// Validation layer for golf-specific data rules
private function validateGolfCourseData(array $extractedData): array

// Multi-tee box processor
private function processTeeBoxConfigurations(array $teeBoxes): array
```

### Data Transformation Layer
```php
// Enhanced data mapper for new schema
private function mapEnhancedDataToModels(array $validatedData): array

// Player score processor for round creation
private function processPlayerScores(array $playerScores, int $courseId): array
```