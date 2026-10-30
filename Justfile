default:
    @just --list

# Run PHPStan static analysis (level max)
analyse:
    composer analyse

# Run Pest test suite
test *args:
    composer test -- {{args}}

# Run Pest test suite with coverage
test-coverage:
    composer test:coverage

# Run full check suite (PHP syntax, PHPStan, Pest)
check:
    php -l wp-plugin-radier.php
    php -l uninstall.php
    composer analyse
    composer test

# Local CI verification gate
ci: check
