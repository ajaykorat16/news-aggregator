# Article Processing Throughput Analysis

Issue: #112
Date: 2026-04-06
Author: Architect agent

## Current Architecture

### Pipeline Path (per article)

```
Scheduler (FetchScheduleProvider)
  → FetchSourceMessage [async transport, Doctrine]
    → FetchSourceHandler
      → FeedFetcher.fetch() .............. HTTP call to RSS feed
      → FeedParser.parse() ............... local XML parsing
      → FOR EACH feed item:
          → DeduplicationService ......... DB lookup (+ optional AI call)
          → Article entity creation ...... local
          → ArticleEnrichmentService.enrich() ... SEQUENTIAL AI CALLS:
              1. categorize()  ........... 1 AI call
              2. summarize()   ........... 1 AI call
              3. extract()     ........... 1 AI call
              4. translate()   ........... 3 AI calls × (N-1) languages
              5. score()       ........... local computation
          → Repository.save(flush:true) .. DB write per article
      → dispatchArticleEvents()
        → ArticleCreated (sync EventDispatcher)
          → ArticleMatcherService.match() ... DB reads (keyword matching, no AI)
          → SendNotificationMessage [async] .. dispatched to Messenger
```

### AI Call Count Per Article

| Step | Calls | Notes |
|------|-------|-------|
| Categorization | 1 | Single prompt, single response |
| Summarization | 1 | Single prompt, single response |
| Keyword extraction | 1 | Single prompt, single response |
| Translation (per extra lang) | 3 | title + summary + keywords, each a separate call |
| **Total (1 display language)** | **3** | Source language matches display |
| **Total (2 display languages)** | **6** | 3 base + 3 translation |
| **Total (3 display languages)** | **9** | 3 base + 6 translation (2 extra langs) |

### Worker Configuration

- **1 worker container** (`compose.override.yaml`, `compose.prod.yaml`)
- Consumes: `async` + `scheduler_fetch` transports
- Transport: `doctrine://default` (PostgreSQL polling)
- No parallel workers, no separate transport for enrichment
- Time limit: 3600s, memory limit: 128M

### Failover Chain

Each AI call goes through `ModelFailoverPlatform`:
1. `openrouter/free` (auto-routed)
2. `minimax/minimax-m2.5:free`
3. `z-ai/glm-4.5-air:free`
4. `openai/gpt-oss-120b:free`
5. `qwen/qwen3.6-plus:free`
6. `nvidia/nemotron-3-super-120b-a12b:free`

Each model is tried sequentially. On success, returns immediately. On failure, tries next. Rate limit errors short-circuit (break the loop).

**Eager evaluation**: `ModelFailoverPlatform` calls `$result->asText()` eagerly to detect deferred failures. This means each model attempt is fully blocking.

## Bottleneck Analysis (ranked by impact)

### 1. Translation Dominance (HIGH)

Translation is the single largest contributor to per-article latency. With 3 display languages, translation accounts for **6 of 9 AI calls (67%)**. Each `translate()` call is a separate HTTP round-trip to OpenRouter.

Worse: `translateToLanguage()` makes 3 separate calls (title, summary, keywords) that could be batched into a single prompt.

### 2. Fully Sequential Enrichment (HIGH)

All enrichment steps in `ArticleEnrichmentService::enrich()` run sequentially. Categorization, summarization, and keyword extraction are independent of each other and could theoretically run in parallel. However, PHP's synchronous execution model makes this non-trivial without Fibers or separate processes.

### 3. Single Worker (HIGH)

One worker process handles everything: feed fetching, article enrichment (blocking AI calls), notification dispatch, digest generation, and scheduled fetches. A single slow AI call blocks all other work.

### 4. Per-Article Flush (MEDIUM)

`$this->articleRepository->save($article, flush: true)` inside the loop means one DB flush per article instead of batching. This adds DB round-trips but is not the primary bottleneck compared to AI latency.

### 5. Failover Cascade Latency (MEDIUM)

When `openrouter/free` fails, the failover chain tries up to 5 additional models sequentially. If multiple models are down, a single enrichment call could take 6 timeouts before falling back to rule-based. The rate-limit short-circuit helps but only for rate-limit errors.

### 6. Doctrine Transport Polling (LOW)

`doctrine://default` polls PostgreSQL for new messages. This adds polling latency (default 1s) but is negligible compared to AI call latency.

## Parallelization Opportunities

### A. Multiple Workers (easy, high impact)

Scale the worker container to N replicas. The Doctrine transport supports concurrent consumers out of the box. Each `FetchSourceMessage` is independent.

**Constraint**: OpenRouter free tier rate limits apply across all workers. With aggressive parallelism, rate limits hit sooner, triggering failover cascades or rule-based fallback for all workers simultaneously.

**Implementation**: Add `deploy.replicas: N` to compose or run multiple worker commands. No code changes needed.

### B. Split Enrichment Into Phases (medium effort, high impact)

Decouple enrichment from fetching by introducing a new message type:

```
FetchSourceMessage → FetchSourceHandler (saves article with rule-based enrichment)
  → EnrichArticleMessage [async]
    → EnrichArticleHandler (runs AI enrichment, updates article)
```

Benefits:
- Articles appear immediately with rule-based data
- AI enrichment runs asynchronously without blocking fetch
- Enrichment messages can be consumed by dedicated workers
- Failed AI enrichment doesn't prevent article creation

### C. Batch Translation Prompts (easy, high impact)

Combine title + summary + keywords into a single translation prompt per language instead of 3 separate calls. This reduces translation from 3 calls per language to 1.

**With 3 display languages**: 9 calls drops to 5 (3 base + 1 per extra language).

### D. Combine Base Enrichment Into Single Prompt (medium effort, medium impact)

Merge categorize + summarize + keywords into one structured prompt. Ask the model to return JSON with all three fields. This reduces 3 calls to 1.

**Risk**: Structured output is harder to validate. One malformed response loses all three enrichments instead of just one.

### E. Separate Transports (easy, medium impact)

Split the `async` transport into `async_fetch` and `async_enrich`:
- `async_fetch` consumed by fetch workers (fast, I/O-bound on HTTP)
- `async_enrich` consumed by enrichment workers (slow, I/O-bound on AI API)

This prevents enrichment backlog from blocking fetches.

## Async Enrichment Pattern

### Design

```
Phase 1 (immediate): FetchSourceHandler
  - Fetch feed, parse, deduplicate
  - Create Article with rule-based enrichment only
  - Save + flush
  - Dispatch EnrichArticleMessage [async_enrich transport]
  - Dispatch ArticleCreated event (for notifications based on rule-based data)

Phase 2 (async): EnrichArticleHandler
  - Load article from DB
  - Run AI categorization (upgrade from rule-based if better)
  - Run AI summarization (upgrade from rule-based if better)
  - Run AI keyword extraction (upgrade)
  - Run AI translation
  - Update article, flush
  - Optionally re-evaluate notifications with improved data
```

### Architectural Requirements

1. **New message + handler**: `EnrichArticleMessage` / `EnrichArticleHandler`
2. **New transport**: `async_enrich` with separate retry strategy (longer delays for rate limits)
3. **Enrichment service refactor**: Split `enrich()` into `enrichRuleBased()` and `enrichAi()` — or make AI services skip if rule-based result is already good enough
4. **Article entity**: Add `enrichmentStatus` field (pending/partial/complete) so UI can show enrichment state
5. **Idempotency**: EnrichArticleHandler must handle re-processing safely (article may already have AI data from a retry)

### Trade-offs

- Articles initially appear with rule-based categories/summaries (lower quality but instant)
- UI needs to handle articles in "enrichment pending" state (minor — just show what's available)
- Notification matching runs on rule-based data first; could miss some AI-only triggers (acceptable — keyword matching is already rule-based)

## Throughput Estimates

### Assumptions

- AI API latency: 1.5s average per call (OpenRouter free tier)
- Failover adds 3s per failed model attempt
- DB operations: 10ms per flush
- Feed fetch + parse: 500ms per source
- Happy path (first model succeeds)

### Current: 1 Worker, 3 Display Languages

| Component | Time per article |
|-----------|-----------------|
| Enrichment (9 AI calls × 1.5s) | 13.5s |
| DB flush | 0.01s |
| Event dispatch + matching | 0.02s |
| **Total per article** | **~13.5s** |

**Throughput: ~0.07 articles/second (4.4 articles/minute)**

With 2 display languages (6 calls): ~9s per article, 0.11 articles/s.
With 1 display language (3 calls): ~4.5s per article, 0.22 articles/s.

### Optimized: Batch Translation + Combined Prompt

| Optimization | Calls (3 langs) | Time per article |
|-------------|-----------------|-----------------|
| Current | 9 | 13.5s |
| Batch translation (3→1 per lang) | 5 | 7.5s |
| Combined base prompt (3→1) | 3 | 4.5s |
| Both combined | 3 | 4.5s |

### Optimized: Multiple Workers

Linear scaling up to rate limit ceiling:

| Workers | Articles/min (3 langs, current) | Articles/min (optimized) |
|---------|-------------------------------|-------------------------|
| 1 | 4.4 | 13.3 |
| 2 | 8.8 | 26.6 |
| 4 | 17.6 | 53.2 |
| 8 | 35.2 | 106.4 |

**Rate limit ceiling**: OpenRouter free tier limits are not publicly documented per-key but are approximately 10-20 requests/minute for free models. With 4 workers making 9 calls each per article, rate limits would likely trigger within 1-2 articles per worker, causing cascading failovers.

**Realistic ceiling with rate limits**: ~10-20 AI calls/minute shared across all workers. With current 9 calls/article, that means ~1-2 articles/minute regardless of worker count unless using paid tier.

### Optimized: Async Enrichment Pattern

| Metric | Before | After |
|--------|--------|-------|
| Time to article visible | 13.5s | <0.1s |
| Time to full enrichment | 13.5s | 13.5s (background) |
| Fetch worker throughput | 4.4/min | ~600/min (no AI blocking) |

## Recommendations (ranked)

### 1. Batch Translation Into Single Prompt Per Language
**Effort**: Small (modify `translateToLanguage()`)
**Impact**: High — cuts translation calls by 67% (from 3 per language to 1)
**Risk**: Low — single prompt with structured output for title/summary/keywords

### 2. Async Enrichment (Two-Phase Processing)
**Effort**: Medium (new message/handler, transport split, article status field)
**Impact**: High — articles visible instantly, AI processing non-blocking
**Risk**: Low — rule-based fallback already works, this just makes it the default initial state

### 3. Scale Workers
**Effort**: Trivial (compose config change)
**Impact**: Medium — linear scaling but bounded by rate limits
**Risk**: Low — Doctrine transport handles concurrent consumers
**Note**: Most effective combined with async enrichment (separate fetch workers from enrichment workers)

### 4. Combined Base Enrichment Prompt
**Effort**: Medium (new structured prompt, JSON parsing, validation)
**Impact**: Medium — reduces 3 calls to 1 for categorize+summarize+keywords
**Risk**: Medium — structured output reliability varies by model; one failure loses all three results

### 5. Separate Transports
**Effort**: Small (messenger.php config + new transport)
**Impact**: Medium — prevents enrichment backlog from blocking fetches
**Risk**: Low — standard Messenger feature

### 6. Dedicated Enrichment Workers With Rate-Limit Backoff
**Effort**: Medium (custom retry strategy, rate-limit detection)
**Impact**: Medium — prevents wasted API calls during rate limiting
**Risk**: Low — builds on existing circuit breaker pattern
