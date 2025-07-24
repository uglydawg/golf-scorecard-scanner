# Technical Specification

This is the technical specification for the spec detailed in @.agent-os/specs/2025-07-24-package-structure/spec.md

> Created: 2025-07-24
> Version: 1.0.0

## Technical Requirements

- Convert existing `app/` directory structure to `src/` package structure
- Implement Laravel service provider with auto-discovery support
- Create publishable configuration files for all OCR and storage settings
- Enable migration publishing without breaking existing database structure
- Maintain 100% backward compatibility with existing API endpoints and service interfaces
- Support Laravel 11+ with PHP 8.2+ requirements
- Implement proper PSR-4 autoloading for all package classes
- Preserve all existing functionality including authentication, file handling, and OCR processing

## Approach Options

**Option A: Gradual Migration with Symlinks**
- Pros: Minimal disruption, can be done incrementally, easy to rollback
- Cons: Complex file structure during transition, potential for confusion

**Option B: Complete Restructure** (Selected)
- Pros: Clean package structure, follows Laravel package conventions exactly, easier to maintain
- Cons: Requires moving all files at once, more complex initial migration

**Option C: Dual Structure Support**
- Pros: Supports both app and package usage simultaneously
- Cons: Code duplication, complex maintenance, violates single responsibility

**Rationale:** Option B provides the cleanest long-term solution and aligns with Laravel package development best practices. Since the codebase is already complete and tested, a one-time restructure is less risky than maintaining dual structures.

## External Dependencies

- **No new dependencies required** - All current Composer dependencies remain the same
- **Laravel Package Discovery** - Built into Laravel 5.5+, no additional packages needed
- **PSR-4 Autoloading** - Standard Composer feature, already configured

## Implementation Details

### Directory Structure Transformation
```
Current Structure:           Target Package Structure:
app/                        src/
├── Http/                   ├── Http/
│   ├── Controllers/        │   ├── Controllers/
│   ├── Requests/           │   ├── Requests/
│   └── Resources/          │   └── Resources/
├── Models/                 ├── Models/
├── Services/               ├── Services/
└── Policies/               ├── Policies/
                           └── Providers/
config/                        └── ScorecardScannerServiceProvider.php
database/                   config/
routes/                     ├── scorecard-scanner.php
tests/                      database/
                           ├── migrations/
                           routes/
                           ├── api.php
                           tests/
```

### Service Provider Features
- Route registration for API endpoints
- Configuration file publishing
- Migration publishing
- View publishing (if needed for future admin interface)
- Service binding for dependency injection
- Policy registration for authorization

### Configuration Structure
```php
// config/scorecard-scanner.php
return [
    'ocr' => [
        'default' => env('SCORECARD_OCR_PROVIDER', 'mock'),
        'providers' => [
            'ocrspace' => [
                'api_key' => env('OCR_SPACE_API_KEY'),
                'endpoint' => 'https://api.ocr.space/parse/image',
            ],
            'google' => [
                'credentials' => env('GOOGLE_VISION_CREDENTIALS'),
            ],
            'aws' => [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
                'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            ],
        ],
    ],
    'storage' => [
        'disk' => env('SCORECARD_STORAGE_DISK', 'public'),
        'path' => env('SCORECARD_STORAGE_PATH', 'scorecard-scans'),
        'cleanup_after_days' => env('SCORECARD_CLEANUP_DAYS', 30),
    ],
    'processing' => [
        'confidence_threshold' => env('SCORECARD_CONFIDENCE_THRESHOLD', 0.85),
        'max_file_size' => env('SCORECARD_MAX_FILE_SIZE', 10240), // KB
        'allowed_types' => ['jpg', 'jpeg', 'png', 'gif'],
    ],
];
```

### Namespace Organization
- Root namespace: `YourVendor\ScorecardScanner`
- Controllers: `YourVendor\ScorecardScanner\Http\Controllers`
- Models: `YourVendor\ScorecardScanner\Models`
- Services: `YourVendor\ScorecardScanner\Services`
- Requests: `YourVendor\ScorecardScanner\Http\Requests`
- Resources: `YourVendor\ScorecardScanner\Http\Resources`
- Policies: `YourVendor\ScorecardScanner\Policies`