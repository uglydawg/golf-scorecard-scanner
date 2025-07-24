# Technical Stack

> Last Updated: 2025-07-24
> Version: 1.0.0

## Application Framework
- **Laravel:** 12.21 with PHP 8.4
- **Authentication:** Laravel Sanctum for API authentication
- **Database ORM:** Eloquent with strict typing

## Database System
- **Development:** SQLite (default, zero configuration)
- **Production Support:** SQLite, PostgreSQL, MySQL (full Laravel compatibility)
- **Migration Design:** Cross-database compatible schema definitions
- **Queue/Cache:** Database driver (Redis recommended for production)

## JavaScript Framework
- **Build Tool:** Vite 7.0.4
- **Import Strategy:** Node modules with Laravel Vite plugin

## CSS Framework
- **Tailwind CSS:** 4.0.0 with Vite integration
- **Responsive Design:** Mobile-first approach

## UI Component Library
- **None specified** - Pure Tailwind implementation
- **Package Ready:** Can be integrated into any Laravel frontend

## Fonts Provider
- **System fonts** - No external font dependencies
- **Configurable** - Easy to add custom fonts

## Icon Library
- **Not specified** - Framework agnostic
- **Recommendation:** Heroicons or Lucide for Tailwind compatibility

## Application Hosting
- **Laravel Sail:** Docker development environment
- **Production:** Any Laravel-compatible hosting (AWS, DigitalOcean, etc.)

## Database Hosting
- **Development:** Local SQLite (included in repository)
- **Production Options:** 
  - SQLite (simple deployments)
  - PostgreSQL (AWS RDS, DigitalOcean, PlanetScale)
  - MySQL (AWS RDS, DigitalOcean, shared hosting)

## Asset Hosting
- **Local Storage:** File system with public disk
- **Cloud Ready:** AWS S3, Cloudinary integration prepared

## Deployment Solution
- **Development:** Laravel Sail with Composer scripts
- **Production:** Framework agnostic (Laravel Forge, Deployer, etc.)

## Code Repository
- **Not specified** - Framework agnostic
- **Git Ready:** Proper .gitignore and version control setup

## Image Processing
- **Intervention Image Laravel:** 1.5 for image manipulation
- **OCR Integration:** Multi-provider support (OCR.space, Google Vision, AWS Textract)

## Testing Framework
- **PHPUnit:** 11.5.3 with Laravel testing utilities
- **Database:** Transaction-based test isolation
- **Factories:** Faker integration for test data generation

## Package Dependencies
- **Core:** Laravel Framework, Sanctum, Tinker
- **Image Processing:** Intervention Image Laravel
- **Development:** Pint (code style), Sail (Docker), Pail (log viewer)
- **Frontend:** Tailwind CSS, Vite, Laravel Vite Plugin