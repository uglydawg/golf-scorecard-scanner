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

- [ ] 2. Implement Service Provider
  - [ ] 2.1 Write tests for service provider functionality
  - [ ] 2.2 Create ScorecardScannerServiceProvider class
  - [ ] 2.3 Register routes, services, and policies in provider
  - [ ] 2.4 Add auto-discovery configuration to composer.json
  - [ ] 2.5 Verify all tests pass with service provider

- [ ] 3. Create Configuration Publishing
  - [ ] 3.1 Write tests for configuration publishing
  - [ ] 3.2 Create publishable config/scorecard-scanner.php file
  - [ ] 3.3 Implement configuration publishing in service provider
  - [ ] 3.4 Update existing services to use published configuration
  - [ ] 3.5 Verify all tests pass with configuration system

- [ ] 4. Implement Migration Publishing
  - [ ] 4.1 Write tests for migration publishing functionality
  - [ ] 4.2 Move database migrations to package structure
  - [ ] 4.3 Implement migration publishing in service provider
  - [ ] 4.4 Create migration publishing command
  - [ ] 4.5 Verify all tests pass with migration publishing

- [ ] 5. Update Package Metadata
  - [ ] 5.1 Write tests for package installation and compatibility
  - [ ] 5.2 Update composer.json with package metadata and requirements
  - [ ] 5.3 Create package README with installation instructions
  - [ ] 5.4 Add package description and keywords for discoverability
  - [ ] 5.5 Verify all tests pass and package can be installed locally