#!/bin/bash

# Install Git Hooks Script
# This script installs the pre-commit hook for Laravel Scorecard Scanner

echo "🔧 Installing Git hooks for Laravel Scorecard Scanner..."

# Check if we're in the project root
if [ ! -f "composer.json" ]; then
    echo "❌ Error: Please run this script from the project root directory"
    exit 1
fi

# Create the pre-commit hook
cat > .git/hooks/pre-commit << 'EOF'
#!/bin/sh

# Laravel Scorecard Scanner Pre-commit Hook
# Runs Pint (code formatting) and PHPStan (static analysis)

echo "🔍 Running pre-commit checks..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if we're in a Laravel project
if [ ! -f "composer.json" ]; then
    echo "${RED}Error: Not in a Laravel project root${NC}"
    exit 1
fi

# Function to check if vendor/bin exists
check_vendor_bin() {
    if [ ! -d "vendor/bin" ]; then
        echo "${RED}Error: vendor/bin directory not found. Please run 'composer install'${NC}"
        exit 1
    fi
}

# Run Pint (Laravel code formatter)
run_pint() {
    echo "${YELLOW}📝 Running Laravel Pint...${NC}"
    
    if [ -f "vendor/bin/pint" ]; then
        vendor/bin/pint --test
        PINT_EXIT_CODE=$?
        
        if [ $PINT_EXIT_CODE -ne 0 ]; then
            echo "${RED}❌ Pint found formatting issues. Running auto-fix...${NC}"
            vendor/bin/pint
            PINT_FIX_EXIT_CODE=$?
            
            if [ $PINT_FIX_EXIT_CODE -eq 0 ]; then
                echo "${GREEN}✅ Pint auto-fixed formatting issues${NC}"
                echo "${YELLOW}⚠️  Please review and stage the formatting changes before committing${NC}"
                exit 1
            else
                echo "${RED}❌ Pint failed to fix formatting issues${NC}"
                exit 1
            fi
        else
            echo "${GREEN}✅ Pint: Code formatting is correct${NC}"
        fi
    else
        echo "${YELLOW}⚠️  Pint not found, skipping code formatting check${NC}"
    fi
}

# Run PHPStan (Static Analysis)
run_phpstan() {
    echo "${YELLOW}🔬 Running PHPStan static analysis...${NC}"
    
    if [ -f "vendor/bin/phpstan" ]; then
        vendor/bin/phpstan analyse --error-format=table
        PHPSTAN_EXIT_CODE=$?
        
        if [ $PHPSTAN_EXIT_CODE -ne 0 ]; then
            echo "${RED}❌ PHPStan found issues that need to be fixed before committing${NC}"
            exit 1
        else
            echo "${GREEN}✅ PHPStan: No issues found${NC}"
        fi
    else
        echo "${YELLOW}⚠️  PHPStan not found, skipping static analysis${NC}"
    fi
}

# Main execution
check_vendor_bin
run_pint
run_phpstan

echo "${GREEN}🎉 All pre-commit checks passed!${NC}"
exit 0
EOF

# Make the hook executable
chmod +x .git/hooks/pre-commit

echo "✅ Pre-commit hook installed successfully!"
echo ""
echo "The hook will now run automatically before each commit and will:"
echo "  📝 Check code formatting with Laravel Pint"
echo "  🔬 Run static analysis with PHPStan"
echo ""
echo "To bypass the hook for a specific commit (not recommended), use:"
echo "  git commit --no-verify"