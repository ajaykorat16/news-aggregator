# Architecture Audit Report

**Date:** 2026-04-05
**Scope:** `src/` -- 108 PHP files, 7 domain modules + Shared kernel
**Level:** Standard

## Executive Summary

**Overall Health Score: 6.5/10 -- Solid Foundation, Key Gaps**

The project has an excellent interface-first design (18 service interfaces) and clean modular structure. The AI enrichment layer with decorator/fallback is architecturally sound. Three systemic gaps hold the architecture back: no repository interfaces (30+ direct EntityManager usages), a God Handler orchestrating 4 bounded contexts, and a circuit breaker that can never trip in stateless PHP.

| Metric | Count |
|--------|-------|
| High issues | 3 |
| Medium issues | 12 |
| Low issues | 7 |
| Patterns detected | 14/20 |

## Pattern Detection Matrix

### Architecture Patterns

| Pattern | Detected | Compliance | Notes |
|---------|----------|------------|-------|
| DDD | Partial | Medium | Good module boundaries, but no aggregates/domain events/repo interfaces |
| Clean Architecture | Partial | Medium | Good dependency direction, EntityManager coupling |
| Hexagonal (Ports & Adapters) | Partial | Medium | 18 service interfaces, missing persistence ports |
| Layered Architecture | Yes | Medium | Clear layers per module, controllers contain queries |
| Event-Driven | Yes | Medium | Symfony Messenger async, no domain events |
| CQRS | No | N/A | Not needed at this scale |
| Outbox Pattern | No | N/A | Not needed for monolith |
| Saga Pattern | No | N/A | Not needed for monolith |

### Design Patterns

| Pattern | Detected | Quality | Notes |
|---------|----------|---------|-------|
| Circuit Breaker | Partial | Needs improvement | In-memory failure counter won't work in stateless PHP |
| Retry (Failover) | Yes | Well-implemented | Clean model failover chain |
| Rate Limiter | Yes | Well-implemented | Symfony sliding_window, 20 req/min |
| Bulkhead | No | -- | Not critical at current scale |
| Strategy | Partial | Needs improvement | AI services hardcode concrete fallback |
| State | Yes | Well-implemented | SourceHealth state machine |
| Decorator | Yes | Well-implemented | ModelFailoverPlatform, AiDeduplicationService |
| Null Object | Partial | Well-implemented | RuleBasedTranslationService as no-op |
| Adapter | Yes | Well-implemented | LaminasFeedParser, HttpFeedFetcher, SealSearch |
| Facade | Partial | Needs improvement | FetchSourceHandler too large |
| Factory (Method) | Partial | Well-implemented | Named constructors on value objects |
| Policy | Yes | Well-implemented | Quality gate, matcher, scoring |
| Iterator | Yes | Well-implemented | Collection value objects |
| Builder | No | -- | Optional improvement |
| Chain of Responsibility | No | -- | Optional for enrichment pipeline |
| Read Model | No | -- | Optional for dashboard queries |

## Strengths

1. **Excellent interface-first design**: 18 service interfaces, all with implementations.
2. **Good modular structure**: 6 domain modules + Shared kernel with consistent internal organization.
3. **Strong value object usage**: 18+ value objects/enums, mostly `final readonly`.
4. **Clean message DTOs**: All Messenger messages are `final readonly` with zero framework coupling.
5. **Source entity has real behavior**: `recordSuccess()`/`recordFailure()` health state machine.
6. **Empty top-level directories**: `Controller/`, `Entity/`, `Repository/` at `src/` root are empty.

## Cross-Pattern Analysis

### Interface-First vs Missing Persistence Ports
18 service interfaces (excellent) but zero repository interfaces. Domain logic is cleanly abstracted but persistence is tightly coupled. Closing this gap is the highest ROI improvement.

### Decorator Documentation vs Implementation
CLAUDE.md describes Enrichment as using "decorator pattern." The AI services are better characterized as Strategy-with-fallback: they hold a concrete rule-based class reference, not the interface.

### State + Health Tracking Synergy
The `Source` entity's `SourceHealth` state machine is well-integrated with fetch scheduling. Strongest DDD modeling in the codebase.

### Circuit Breaker + Quality Tracker Disconnected
Both present but neither functions correctly -- the breaker can't trip and the tracker is never called. The AI resilience layer looks complete but has no working feedback loop.

## Remaining Findings (Low Severity)

These items are informational and not tracked as issues:

| # | Finding | Location |
|---|---------|----------|
| L1 | Entities depend on Doctrine ORM annotations (pragmatic Symfony convention) | All entity files |
| L2 | User entity depends on Symfony Security interfaces (framework-mandated) | `User/Entity/User.php` |
| L3 | Collections extend Doctrine ArrayCollection (couples domain to Doctrine) | 5 collection files |
| L4 | SeedDataCommand in Source module seeds User and DigestConfig entities | `Source/Command/SeedDataCommand.php` |
| L5 | No failover delay/backoff between model attempts | `ModelFailoverPlatform.php` |
| L6 | No Bulkhead -- all AI operations share single rate limiter | Not critical at current scale |
| L7 | No Builder pattern for Article construction | Optional improvement |

## Entity Richness Assessment

| Entity | Assessment |
|--------|------------|
| `Source` | Good -- `recordSuccess()` and `recordFailure()` with health state machine |
| `AlertRule` | Adequate -- `requiresAiEvaluation()` domain method |
| `Article` | Anemic -- 277 lines of pure getters/setters, zero domain behavior |
| `DigestConfig` | Anemic -- pure getters/setters |
| `DigestLog` | Anemic -- data container |
| `NotificationLog` | Anemic -- data container |
| `Category` | Anemic -- pure getters/setters |
| `User` | Anemic -- delegates to Symfony Security |

## Adapter Inventory

| Port (Interface) | Adapter(s) |
|-------------------|-----------|
| `DeduplicationServiceInterface` | `DeduplicationService`, `AiDeduplicationService` |
| `ScoringServiceInterface` | `ScoringService` |
| `CategorizationServiceInterface` | `RuleBasedCategorizationService`, `AiCategorizationService` |
| `SummarizationServiceInterface` | `RuleBasedSummarizationService`, `AiSummarizationService` |
| `TranslationServiceInterface` | `RuleBasedTranslationService`, `AiTranslationService` |
| `KeywordExtractionServiceInterface` | `RuleBasedKeywordExtractionService`, `AiKeywordExtractionService` |
| `AiQualityGateServiceInterface` | `AiQualityGateService` |
| `FeedFetcherServiceInterface` | `HttpFeedFetcherService` |
| `FeedParserServiceInterface` | `LaminasFeedParserService` |
| `ArticleMatcherServiceInterface` | `ArticleMatcherService` |
| `AiAlertEvaluationServiceInterface` | `AiAlertEvaluationService` |
| `NotificationDispatchServiceInterface` | `NotificationDispatchService` |
| `ArticleSearchServiceInterface` | `SealArticleSearchService` |
| `DigestGeneratorServiceInterface` | `DigestGeneratorService` |
| `DigestSummaryServiceInterface` | `DigestSummaryService` |
| `ModelDiscoveryServiceInterface` | `ModelDiscoveryService` |
| `ModelQualityTrackerInterface` | `ModelQualityTracker` |
| `AlertRuleFixtureLoaderInterface` | `AlertRuleFixtureLoader` |
