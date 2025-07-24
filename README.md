# Laravel Golf Scorecard Scanner

[![Latest Version on Packagist](https://img.shields.io/packagist/v/scorecard-scanner/laravel-golf-ocr.svg?style=flat-square)](https://packagist.org/packages/scorecard-scanner/laravel-golf-ocr)
[![Total Downloads](https://img.shields.io/packagist/dt/scorecard-scanner/laravel-golf-ocr.svg?style=flat-square)](https://packagist.org/packages/scorecard-scanner/laravel-golf-ocr)
[![PHP Version](https://img.shields.io/packagist/php-v/scorecard-scanner/laravel-golf-ocr.svg?style=flat-square)](https://packagist.org/packages/scorecard-scanner/laravel-golf-ocr)
[![Laravel Version](https://img.shields.io/badge/Laravel-11.x%20|%2012.x-FF2D20?style=flat-square&logo=laravel)](https://laravel.com)
[![License](https://img.shields.io/packagist/l/scorecard-scanner/laravel-golf-ocr.svg?style=flat-square)](https://packagist.org/packages/scorecard-scanner/laravel-golf-ocr)

A powerful Laravel package for automated golf scorecard scanning and data extraction using OCR (Optical Character Recognition) technology. Transform uploaded scorecard images into structured golf data with support for multiple OCR providers and comprehensive course database management.

## Features

🏌️ **Golf-Optimized OCR Processing** - Specialized parsing for golf scorecards with 85%+ accuracy  
🔄 **Multiple OCR Providers** - Support for OCR.space, Google Vision, AWS Textract, and mock data  
🏌️‍♂️ **Course Database Management** - Automated course matching and crowdsourced verification  
🔐 **Laravel Sanctum Integration** - Secure API authentication with policy-based authorization  
📊 **Comprehensive Data Extraction** - Scores, course info, player data, and confidence scoring  
🧪 **Full Test Coverage** - Complete test suite with TDD approach and factory patterns  

## Requirements

- PHP 8.2 or higher
- Laravel 11.x or 12.x
- GD or Imagick extension for image processing

## Installation

Install the package via Composer:

```bash
composer require scorecard-scanner/laravel-golf-ocr
```

### Publish Configuration

Publish the configuration file to customize OCR providers and processing settings:

```bash
php artisan vendor:publish --tag=scorecard-scanner-config
```

This creates `config/scorecard-scanner.php` where you can configure:
- OCR providers (OCR.space, Google Vision, AWS Textract)
- Storage settings and cleanup policies
- Processing parameters and confidence thresholds
- Database table names and API settings

### Publish Migrations

Publish and run the database migrations:

```bash
php artisan vendor:publish --tag=scorecard-scanner-migrations
php artisan migrate
```

Or use the custom command for sequential timestamps:

```bash
php artisan scorecard-scanner:publish-migrations --force
php artisan migrate
```

## Configuration

### OCR Provider Setup

#### OCR.space (Recommended for Development)
```php
// config/scorecard-scanner.php
'ocr' => [
    'default' => 'ocrspace',
    'providers' => [
        'ocrspace' => [
            'api_key' => env('OCRSPACE_API_KEY'),
            'language' => 'eng',
            'timeout' => 30,
        ],
    ],
],
```

Set your environment variable:
```bash
OCRSPACE_API_KEY=your_api_key_here
```

#### Google Vision API
```php
'google' => [
    'credentials_path' => env('GOOGLE_CLOUD_CREDENTIALS_PATH'),
    'project_id' => env('GOOGLE_CLOUD_PROJECT_ID'),
],
```

#### AWS Textract
```php
'aws' => [
    'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    'access_key_id' => env('AWS_ACCESS_KEY_ID'),
    'secret_access_key' => env('AWS_SECRET_ACCESS_KEY'),
],
```

### Storage Configuration

Configure where scorecard images are stored:

```php
'storage' => [
    'disk' => env('SCORECARD_STORAGE_DISK', 'local'),
    'path' => env('SCORECARD_STORAGE_PATH', 'scorecards'),
    'cleanup_after_days' => env('SCORECARD_CLEANUP_DAYS', 30),
],
```

## Usage

### Basic API Usage

The package provides RESTful API endpoints for scorecard scanning:

```php
// POST /api/scorecard-scans
// Upload and process a scorecard image

$response = Http::withToken($authToken)
    ->attach('image', $imageContent, 'scorecard.jpg')
    ->post('/api/scorecard-scans');

$scan = $response->json();
```

### Service Integration

Use the services directly in your Laravel application:

```php
use ScorecardScanner\Services\ScorecardProcessingService;

class YourController extends Controller
{
    public function __construct(
        private ScorecardProcessingService $processor
    ) {}
    
    public function processScorecard(Request $request)
    {
        $result = $this->processor->processScorecard(
            $request->file('scorecard'),
            $request->user()
        );
        
        return response()->json($result);
    }
}
```

### Model Usage

Access the extracted data using Eloquent models:

```php
use ScorecardScanner\Models\ScorecardScan;
use ScorecardScanner\Models\GolfCourse;

// Get user's scorecard scans
$scans = ScorecardScan::where('user_id', auth()->id())
    ->with(['players.scores'])
    ->latest()
    ->get();

// Find golf courses
$courses = GolfCourse::where('is_verified', true)
    ->where('name', 'LIKE', '%Pebble Beach%')
    ->get();
```

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/scorecard-scans` | List scorecard scans |
| `POST` | `/api/scorecard-scans` | Upload and process scorecard |
| `GET` | `/api/scorecard-scans/{id}` | Get specific scan details |
| `DELETE` | `/api/scorecard-scans/{id}` | Delete a scan |

All endpoints require authentication via Laravel Sanctum.

## Database Schema

The package creates six database tables:

- **golf_courses** - Verified course data with par/handicap information
- **golf_holes** - Individual hole details for each course  
- **scorecard_scans** - OCR processing results and metadata
- **scorecard_players** - Player information extracted from scans
- **player_scores** - Individual hole scores for each player
- **unverified_courses** - Crowdsourced course submissions for admin review

## Testing

Run the package tests:

```bash
composer test
```

Run with coverage:

```bash
composer test-coverage
```

The package includes comprehensive tests covering:
- Unit tests for all services and models
- Integration tests for API endpoints
- Package structure and metadata validation
- Configuration and migration publishing

## Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

### Development Setup

1. Clone the repository
2. Install dependencies: `composer install`
3. Copy environment file: `cp .env.example .env`
4. Run tests: `composer test`

## Security

If you discover any security vulnerabilities, please send an email to security@scorecard-scanner.com instead of using the issue tracker.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).

## Credits

- [Golf Scorecard Scanner Team](https://github.com/scorecard-scanner)
- [All Contributors](../../contributors)

## Support

- **Documentation**: [https://scorecard-scanner.github.io/laravel-golf-ocr](https://scorecard-scanner.github.io/laravel-golf-ocr)
- **Issues**: [GitHub Issues](https://github.com/scorecard-scanner/laravel-golf-ocr/issues)
- **Discussions**: [GitHub Discussions](https://github.com/scorecard-scanner/laravel-golf-ocr/discussions)

---

Made with ❤️ for the golf community