# Spec Tasks

These are the tasks to be completed for the spec detailed in @.agent-os/specs/2025-07-24-package-structure/spec.md

> Created: 2025-07-24
> Status: Ready for Implementation

## Tasks

- [x] 1. Create Package Directory Structure
  - [x] 1.1 Write tests for package structure validation
  - [x] 1.2 Create src/ directory with proper PSR-4 organization
  - [x] 1.3 Move all app/ classes to src/ with updated namespaces
  - [x] 1.4 Update composer.json autoload configuration
  - [x] 1.5 Verify all tests pass with new structure

- [x] 2. Implement Service Provider
  - [x] 2.1 Write tests for service provider functionality
  - [x] 2.2 Create ScorecardScannerServiceProvider class
  - [x] 2.3 Register routes, services, and policies in provider
  - [x] 2.4 Add auto-discovery configuration to composer.json
  - [x] 2.5 Verify all tests pass with service provider

- [x] 3. Create Configuration Publishing
  - [x] 3.1 Write tests for configuration publishing
  - [x] 3.2 Create publishable config/scorecard-scanner.php file
  - [x] 3.3 Implement configuration publishing in service provider
  - [x] 3.4 Update existing services to use published configuration
  - [x] 3.5 Verify all tests pass with configuration system

- [x] 4. Implement Migration Publishing
  - [x] 4.1 Write tests for migration publishing functionality
  - [x] 4.2 Move database migrations to package structure
  - [x] 4.3 Implement migration publishing in service provider
  - [x] 4.4 Create migration publishing command
  - [x] 4.5 Verify all tests pass with migration publishing

- [x] 5. Update Package Metadata
  - [x] 5.1 Write tests for package installation and compatibility
  - [x] 5.2 Update composer.json with package metadata and requirements
  - [x] 5.3 Create package README with installation instructions
  - [x] 5.4 Add package description and keywords for discoverability
  - [x] 5.5 Verify all tests pass and package can be installed locally