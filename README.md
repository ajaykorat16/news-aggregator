# News Aggregator

Self-hosted, AI-enhanced RSS/Atom news aggregator built with Symfony 8 + FrankenPHP.

## Features

- **RSS/Atom feed aggregation** from configurable sources
- **AI-powered categorization & summarization** via OpenRouter free models (with rule-based fallback)
- **Smart alerts** with keyword and AI-based evaluation
- **Periodic digests** with AI-generated editorial summaries
- **Full-text search** via SEAL + Loupe (zero infrastructure)
- **Article scoring & ranking** based on recency, source reliability, and category weights
- **Deduplication** across sources (URL, title similarity, content fingerprint)
- Single-user auth, multi-user ready architecture

## Tech Stack

- **Backend**: Symfony 8.0, PHP 8.4, Doctrine ORM
- **Server**: FrankenPHP + Caddy (automatic HTTPS, HTTP/3)
- **Database**: PostgreSQL 17 + PgBouncer (connection pooling)
- **Frontend**: Twig + DaisyUI + plain TypeScript (via Bun + AssetMapper)
- **AI**: Symfony AI Bundle + OpenRouter (free models, FailoverPlatform)
- **Search**: SEAL + Loupe (SQLite-based, swap to Meilisearch later)
- **Async**: Symfony Messenger (Doctrine transport)
- **Monitoring**: Ember (Caddy/FrankenPHP metrics TUI)

## Requirements

- Docker & Docker Compose v2
- (Optional) OpenRouter API key for AI features

## Quick Start

```bash
# Clone and start
git clone https://github.com/tony-stark-eth/news-aggregator.git
cd news-aggregator
make start

# Access at https://localhost:8443
# (Accept the self-signed certificate)
```

## Development

```bash
make up              # Start containers
make down            # Stop containers
make sh              # Shell into PHP container
make quality         # Run all quality checks (ECS + PHPStan + Rector)
make test            # Run all tests
make test-unit       # Run unit tests
make test-integration # Run integration tests
make infection       # Run mutation testing
make coverage        # Generate coverage report
make hooks           # Install git hooks
make ts-build        # Compile TypeScript
```

## Configuration

Copy `.env.example` to `.env.local` and adjust:

- `ADMIN_EMAIL` / `ADMIN_PASSWORD_HASH` — admin credentials
- `OPENROUTER_API_KEY` — for AI features (optional, rule-based fallback works without it)
- `NOTIFIER_CHATTER_DSN` — notification transport (Pushover, Slack, Telegram, etc.)

## Architecture

Domain-driven design with bounded contexts:

- **Article** — core articles, scoring, deduplication
- **Source** — feed management, fetching, health tracking
- **Enrichment** — rule-based + AI categorization/summarization
- **Notification** — unified alert rules + dispatch
- **Digest** — periodic AI-generated editorial summaries
- **User** — authentication, per-user read state

See [PITCH.md](PITCH.md) for the full project overview.

## License

[MIT](LICENSE)
