# CLAUDE.md

## Project

News Aggregator — self-hosted, AI-enhanced RSS/Atom aggregator. Symfony 8.0 + FrankenPHP + PostgreSQL.

See `PITCH.md` for full project overview.

## Planning Files

This project uses file-based planning. **These rules are mandatory:**

1. **After completing any task**: check off the task in `task_plan.md` (`- [ ]` → `- [x]`)
2. **After completing any task**: log what was done in `progress.md` under the current session
3. **Before starting work**: read `task_plan.md` and `progress.md` to understand current state
4. **When encountering blockers or errors**: log them in the Error Log section of `task_plan.md`
5. **When making architectural decisions**: update `findings.md` with the reasoning
6. **Never skip these updates** — they are as important as the code itself

## Quick Start

```bash
make up          # Start containers
make quality     # Run all quality checks
make test        # Run all tests
make hooks       # Install git hooks
```

## Guidelines

_(Full guidelines will be created in Phase 1.14)_

- `.claude/coding-php.md` — PHP coding rules
- `.claude/coding-typescript.md` — TypeScript conventions
- `.claude/testing.md` — Testing & code quality
- `.claude/architecture.md` — Architecture reference

## Hard Rules

- No `DateTime` — use `DateTimeImmutable` via `ClockInterface` only
- No `var_dump` / `dump` / `dd` / `print_r`
- No `empty()` — use explicit checks
- No `ignoreErrors` in phpstan.neon
- No YAML for Symfony config — PHP format only
- No `time()` / `date()` / `strtotime()` — use `ClockInterface`
- Interface-first: all service boundaries defined by interface
- Conventional Commits: `feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `chore:`
