# News Aggregator

## What

A self-hosted, AI-enhanced news aggregator that pulls from RSS/Atom feeds, deduplicates content, categorizes and summarizes articles, scores them by relevance, and alerts you when something important happens.

## Why

- **Existing aggregators** (Feedly, Inoreader) are SaaS, closed-source, and expensive for premium features
- **Self-hosted options** (Miniflux, FreshRSS) are great RSS readers but lack AI enrichment, smart alerting, and configurable scoring
- **No aggregator** combines rule-based reliability with AI enhancement as a fallback-safe architecture

## For Whom

- News-heavy professionals who need to track multiple domains (tech, finance, politics)
- Self-hosters who want full control over their data and infrastructure
- Developers who want an opinionated Symfony 8 reference architecture

## Key Features

**Feed Management**
- 16+ preconfigured sources across 5 categories (Politics, Business, Tech, Science, Sports)
- Add any RSS/Atom feed, configurable fetch intervals and reliability weights
- Source health monitoring with auto-disable on persistent failures

**Smart Content Processing**
- Deduplication: URL, title similarity, content fingerprinting
- AI categorization and summarization via OpenRouter free models (zero cost)
- Rule-based fallback ensures the system works even when AI is unavailable
- Quality gates validate AI output before acceptance

**Unified Alert System**
- Keyword-based alerts (instant, free, rule-based)
- AI-powered severity evaluation with context prompts ("I hold Tesla stock")
- Keyword match first, AI only on matches — cuts API calls by ~90%
- Configurable cooldown per alert rule, transport-agnostic notifications (Pushover, Telegram, Slack, etc.)

**Periodic Digests**
- Configurable daily/weekly editorial summaries with AI-generated takeaways
- Category filters, article limits, risk flags
- Rule-based fallback produces structured article lists

**Scoring & Ranking**
- Category weight, recency decay, source reliability, multi-source coverage bonus
- AI confidence boost for enriched articles
- Periodic rescoring keeps rankings fresh

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Runtime | FrankenPHP + Caddy (auto HTTPS, HTTP/3) |
| Framework | Symfony 8.0, PHP 8.4 |
| Database | PostgreSQL 17 + PgBouncer |
| Search | SEAL + Loupe (SQLite-based, zero infrastructure) |
| AI | Symfony AI bundle + OpenRouter free models + FailoverPlatform |
| Frontend | Twig + DaisyUI + plain TypeScript (via Bun + AssetMapper) |
| Async | Symfony Messenger (Doctrine transport, no Redis) |
| Monitoring | Ember (Caddy/FrankenPHP metrics dashboard) |
| Notifications | Symfony Notifier (transport-agnostic) |

## Architecture Highlights

- **DDD**: 6 bounded contexts + Shared (Article, Enrichment, Source, Notification, Digest, User)
- **Interface-first**: all service boundaries defined by interfaces, implementations swappable
- **Multi-user ready**: single-user MVP with `user_id` FKs for painless multi-user migration
- **AI as enhancement, not dependency**: rule-based fallback for everything, AI is a decorator layer
- **Zero-maintenance AI models**: `openrouter/free` auto-routes to best available model, dynamic discovery as backup
- **Template-extractable**: Phase 1+2 designed for future Symfony+Docker+Claude Code template

## Quality Standards

- PHPStan level max with 10 extensions (zero ignoreErrors)
- 80% mutation score (Infection), 90% covered code MSI
- Xdebug path coverage, PHPat architecture tests
- ECS + Rector auto-enforcement
- E2E tests via Symfony Panther
- Docker-based CI (same containers as local dev)

## Status

Planning complete. Implementation starting with PR #1 (scaffold + quality tooling).

## License

MIT
