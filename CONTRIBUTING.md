# Contributing

## Development Setup

1. Clone the repository
2. Run `make start` to build and start Docker containers
3. Run `make hooks` to install git hooks

## Code Quality

All code must pass quality checks before merging:

```bash
make quality   # ECS + PHPStan + Rector
make test      # All tests
```

### Standards

- **PHPStan**: Level max with strict extensions (zero ignoreErrors)
- **ECS**: PSR-12 + strict + cleanCode
- **Rector**: PHP 8.4 + Symfony 8 + Doctrine sets
- **PHPUnit**: Unit + integration suites, Xdebug path coverage
- **Infection**: MSI >= 80%, covered MSI >= 90% (unit suite)

### PHP Conventions

- `declare(strict_types=1)` in every file
- `final readonly class` by default
- Interface-first: all service boundaries defined by interface
- `ClockInterface` for all time access
- Max 20 lines/method, max 3 params, max ~150 lines/class
- `find*` returns nullable, `get*` throws on not found
- No `DateTime`, `var_dump`, `dump`, `dd`, `empty()`

## Commit Messages

We use [Conventional Commits](https://www.conventionalcommits.org/):

```
feat: add feed health monitoring
fix: correct UTC timezone handling in digest scheduler
refactor: extract deduplication into service interface
test: add integration tests for feed parser
docs: update architecture documentation
chore: bump PHPStan to 2.1.x
```

## Pull Requests

- One feature/fix per PR
- All quality checks must pass
- Include tests for new functionality
- Update documentation if applicable
