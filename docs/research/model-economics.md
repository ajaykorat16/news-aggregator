# OpenRouter Model Economics Research

**Date:** April 2026
**Purpose:** Support decisions for GitHub issue #112 -- improving article throughput with free and low-cost models.

## Current Architecture

The news aggregator performs 4 AI enrichment tasks per article:

1. **Categorization** -- classify into one of 5 categories (~200 input + ~50 output tokens)
2. **Summarization** -- 1-2 sentence summary (~500 input + ~150 output tokens)
3. **Keyword extraction** -- 3-5 entity names (~300 input + ~50 output tokens)
4. **Translation** -- per target language (~400 input + ~200 output tokens)

All services use `openrouter/free` as the primary model with a failover chain:
`openrouter/free` -> `minimax/minimax-m2.5:free` -> `z-ai/glm-4.5-air:free` -> `openai/gpt-oss-120b:free` -> `qwen/qwen3.6-plus:free` -> `nvidia/nemotron-3-super-120b-a12b:free`

Content is truncated before sending: categorization/keywords use first 1,000 chars, summarization uses first 2,000 chars.

---

## 1. Free Tier Analysis

### Available Free Models (28 as of April 2026)

| Model | Provider | Context | Notable |
|---|---|---|---|
| Qwen3.6 Plus | Qwen | 1.0M | Vision, tool use |
| Qwen3 Next 80B | Qwen | 262K | Tool use |
| Qwen3 Coder 480B | Qwen | 262K | Coding specialist |
| Nemotron 3 Super 120B | NVIDIA | 262K | Tool use |
| Step 3.5 Flash | StepFun | 256K | Tool use |
| Nemotron 3 Nano 30B | NVIDIA | 256K | Tool use |
| Free Models Router | OpenRouter | 200K | Auto-routes to best available |
| MiniMax M2.5 | MiniMax | 196K | Tool use |
| GPT-OSS-120B | OpenAI | 131K | Tool use |
| GPT-OSS-20B | OpenAI | 131K | Tool use |
| GLM 4.5 Air | Z.ai | 131K | Tool use |
| Trinity Mini | Arcee AI | 131K | Tool use |
| Gemma 3 27B | Google | 131K | Vision |
| Hermes 3 405B | Nous Research | 131K | Large, general purpose |
| Llama 3.3 70B | Meta | 65K | Solid general purpose |
| Gemma 3 12B | Google | 32K | Vision |
| Gemma 3 4B | Google | 32K | Vision |
| Llama 3.2 3B | Meta | 131K | Lightweight |

(Plus several smaller/specialized models omitted for brevity.)

### Rate Limits

| Condition | Requests/Minute | Requests/Day |
|---|---|---|
| No credits purchased | 20 | **50** |
| Credits purchased (>= 10 credits) | 20 | **1,000** |
| Global throttle (all tiers) | 100/connection | -- |

**Critical finding:** Without purchasing credits, the daily cap is **50 requests** -- barely enough for 12 articles (4 tasks each, no translation). With 10+ credits purchased, this rises to 1,000/day.

### Throughput Ceiling Calculation (Free Tier)

With purchased credits unlocking 1,000 requests/day and 20 RPM:

| Config | Articles/Day | Bottleneck |
|---|---|---|
| No translation | 250 (4 calls/article) | Daily cap |
| 2 languages | 166 (6 calls/article) | Daily cap |
| 3 languages | 125 (8 calls/article) | Daily cap |
| RPM-limited (20 RPM sustained) | 28,800 calls/day theoretical | Not the bottleneck |

**The daily cap of 1,000 requests is the hard ceiling.** At 20 RPM, the minute limit is not the bottleneck for a news aggregator that processes articles in batches.

### Free Tier Risks

- Free models may be removed or rate-adjusted without notice (5 models left free tier since Feb 2026).
- During peak times, free requests are queued behind paid requests.
- Failed attempts count toward daily quota.
- Provider-side rate limiting may be stricter than OpenRouter's own limits.

---

## 2. Low-Cost Model Comparison

All prices are per million tokens on OpenRouter as of April 2026. OpenRouter does not mark up provider pricing.

| Model | Input $/M | Output $/M | Context | Latency | Quality Tier |
|---|---|---|---|---|---|
| **Free tier** | | | | | |
| `openrouter/free` (router) | $0.00 | $0.00 | 200K | Variable | Mixed (routes to best available) |
| `qwen/qwen3.6-plus:free` | $0.00 | $0.00 | 1.0M | Medium | Good |
| `nvidia/nemotron-3-super-120b:free` | $0.00 | $0.00 | 262K | Medium | Good |
| `openai/gpt-oss-120b:free` | $0.00 | $0.00 | 131K | Medium | Good |
| **Ultra-low cost (< $0.10/M input)** | | | | | |
| Qwen3.5-9B (paid) | $0.05 | $0.15 | 32K | Fast | Adequate |
| Gemini 2.5 Flash-Lite | $0.10 | $0.40 | 1.0M | Very fast | Good |
| Mistral Small Creative | $0.10 | $0.30 | 32K | Fast | Good (text tasks) |
| **Low cost ($0.10-0.30/M input)** | | | | | |
| GPT-4o-mini | $0.15 | $0.60 | 128K | Fast | Very good |
| Mistral Small 4 | $0.15 | $0.60 | 32K | Fast | Good |
| DeepSeek V3.1 | $0.15 | $0.75 | 64K | Medium | Very good |
| DeepSeek V3.2 | $0.26 | $0.38 | 64K | Medium | Very good |
| **Mid cost ($0.30-1.00/M input)** | | | | | |
| Claude 3.5 Haiku | $0.80 | $4.00 | 200K | Fast | Excellent |
| DeepSeek V3 (original) | $0.32 | $0.89 | 64K | Medium | Good |

### Model Notes

- **Gemini 2.0 Flash**: Deprecated, shuts down June 1, 2026. Use Gemini 2.5 Flash-Lite instead.
- **Gemini 2.5 Flash-Lite**: Best price-to-performance for simple text tasks. Ultra-low latency design, reasoning disabled by default (ideal for categorization/keywords).
- **GPT-4o-mini**: Strong all-rounder, excellent at classification and summarization. Slightly pricier but very reliable.
- **DeepSeek V3.1/V3.2**: Excellent value. V3.2 has cheaper output tokens ($0.38/M vs $0.75/M). Strong at multilingual tasks.
- **Mistral Small Creative**: Cheapest paid option for output tokens ($0.30/M). Good for translation/summarization.
- **Claude 3.5 Haiku**: Best quality but 5-8x more expensive. Overkill for simple classification/extraction tasks.
- **Qwen3.5-9B**: Cheapest paid model overall. Adequate for simple tasks but may struggle with nuanced summarization.

---

## 3. Cost Per Article Breakdown

### Token Estimates Per Article

| Task | Input Tokens | Output Tokens | Total Tokens |
|---|---|---|---|
| Categorization | 200 | 50 | 250 |
| Summarization | 500 | 150 | 650 |
| Keyword extraction | 300 | 50 | 350 |
| Translation (per language) | 400 | 200 | 600 |
| **Total (no translation)** | **1,000** | **250** | **1,250** |
| **Total (2 extra languages)** | **1,800** | **650** | **2,450** |
| **Total (3 extra languages)** | **2,200** | **850** | **3,050** |

### Cost Per Article (2 Extra Languages = 6 API Calls)

| Model | Input Cost | Output Cost | **Total/Article** |
|---|---|---|---|
| Free tier | $0.00 | $0.00 | **$0.000** |
| Qwen3.5-9B | $0.000090 | $0.000098 | **$0.000188** |
| Gemini 2.5 Flash-Lite | $0.000180 | $0.000260 | **$0.000440** |
| Mistral Small Creative | $0.000180 | $0.000195 | **$0.000375** |
| GPT-4o-mini | $0.000270 | $0.000390 | **$0.000660** |
| Mistral Small 4 | $0.000270 | $0.000390 | **$0.000660** |
| DeepSeek V3.1 | $0.000270 | $0.000488 | **$0.000758** |
| DeepSeek V3.2 | $0.000468 | $0.000247 | **$0.000715** |
| Claude 3.5 Haiku | $0.001440 | $0.002600 | **$0.004040** |

---

## 4. Monthly Cost Projections

Assumes 2 extra translation languages (6 API calls per article, ~1,800 input + ~650 output tokens).

| Model | 100/day | 500/day | 1,000/day |
|---|---|---|---|
| Free tier | **$0.00** | **$0.00** | **$0.00** |
| Qwen3.5-9B | **$0.56** | **$2.82** | **$5.64** |
| Gemini 2.5 Flash-Lite | **$1.32** | **$6.60** | **$13.20** |
| Mistral Small Creative | **$1.13** | **$5.63** | **$11.25** |
| GPT-4o-mini | **$1.98** | **$9.90** | **$19.80** |
| DeepSeek V3.1 | **$2.27** | **$11.37** | **$22.74** |
| DeepSeek V3.2 | **$2.15** | **$10.73** | **$21.45** |
| Claude 3.5 Haiku | **$12.12** | **$60.60** | **$121.20** |

### Key Takeaway

Even at 1,000 articles/day, the low-cost models are all under $25/month. The free tier handles this volume at $0 but is constrained by the 1,000 requests/day cap (requires credits purchase to unlock), which maxes out at ~166 articles/day with 2 translations.

---

## 5. Hybrid Strategy Options

### Option A: Free-First with Paid Overflow

Use free models for bulk processing, fall back to a cheap paid model when rate-limited.

```
openrouter/free -> qwen/qwen3.6-plus:free -> [other :free models]
   |-- on rate limit --> gemini-2.5-flash-lite (paid, $0.10/M input)
```

**Pros:** $0 for first ~166 articles/day (2 languages), pennies for overflow.
**Cons:** Inconsistent latency, free model quality varies day-to-day.

### Option B: Cheap Paid Model as Primary

Use Gemini 2.5 Flash-Lite or Mistral Small Creative as the primary model, free as fallback.

**Pros:** Consistent latency and quality, predictable costs ($6-13/mo at 500/day).
**Cons:** Requires credits on account.

### Option C: Task-Specific Model Routing

Route different enrichment tasks to different models based on task complexity:

| Task | Model | Rationale |
|---|---|---|
| Categorization | Free tier | Simple classification, quality acceptable |
| Keyword extraction | Free tier | Simple extraction, quality acceptable |
| Summarization | GPT-4o-mini or Gemini 2.5 Flash-Lite | Quality matters more, still cheap |
| Translation | DeepSeek V3.2 or Mistral Small Creative | Strong multilingual, cheap output tokens |

**Pros:** Best quality/cost ratio per task, ~$3-5/mo at 500/day.
**Cons:** More complex routing logic, multiple model configurations.

### Option D: BYOK (Bring Your Own Key)

Use your own Google API key (for Gemini) or DeepSeek key directly through OpenRouter.

- First 1M BYOK requests/month are free through OpenRouter.
- After that, 5% surcharge on provider pricing.
- Your own rate limits from the provider (typically much higher than OpenRouter free tier).
- Google Gemini API has a generous free tier directly (1,500 req/day for Flash models).

**Pros:** Potentially free for moderate volumes, higher rate limits, consistent model access.
**Cons:** Requires managing external API keys, provider-specific rate limits apply.

### Option E: Direct Provider API (Bypass OpenRouter)

For maximum throughput, call Google Gemini or DeepSeek APIs directly instead of through OpenRouter.

- Google Gemini API free tier: 1,500 requests/day, 15 RPM for Flash models.
- DeepSeek API: No free tier but very cheap ($0.15-0.26/M input).

**Pros:** Higher free-tier limits than OpenRouter, no middleman latency.
**Cons:** Loses multi-model failover, requires code changes to support multiple platforms.

---

## 6. Recommendations

### Immediate (Zero Cost)

1. **Purchase $10 of OpenRouter credits** to unlock the 1,000/day free model cap (vs 50/day without). This is the single highest-impact change.
2. **Update the failover chain** to prioritize the strongest current free models:
   - `openrouter/free` -> `qwen/qwen3.6-plus:free` -> `nvidia/nemotron-3-super-120b-a12b:free` -> `openai/gpt-oss-120b:free` -> `z-ai/glm-4.5-air:free` -> `minimax/minimax-m2.5:free`
   (Already close to this; minor reorder for current model quality rankings.)

### Short-Term (< $15/month)

3. **Implement hybrid free+paid routing (Option A or C).** When free models are rate-limited or fail quality gates, overflow to Gemini 2.5 Flash-Lite ($0.10/$0.40) or Mistral Small Creative ($0.10/$0.30). At 500 articles/day, overflow cost is negligible.
4. **Consider task-specific routing (Option C)** if quality variance from free models is a problem. Use free for categorization/keywords, paid for summarization/translation.

### Medium-Term (Architectural)

5. **Add BYOK support for Google Gemini.** Google's direct Gemini API free tier offers 1,500 req/day (vs OpenRouter's 1,000/day for free models) and the paid tier is extremely cheap. Combined with OpenRouter's 1M free BYOK requests/month, this could keep costs near zero even at high volume.
6. **Implement request batching/queuing.** Spread API calls across the day to stay within RPM limits rather than bursting during fetch cycles. A Symfony Messenger-based queue with rate-limiting middleware would handle this naturally.
7. **Track per-model cost in ModelQualityTracker.** Add token usage tracking to inform model selection decisions with real data rather than estimates.

### Models to Avoid

- **Claude 3.5 Haiku**: 5-8x more expensive than alternatives with marginal quality gains for these simple tasks.
- **Gemini 2.0 Flash**: Deprecated, shutting down June 2026.
- **Very small models** (< 4B parameters like LFM2.5-1.2B, Gemma 3n 2B): Insufficient quality for summarization and translation.

---

## Sources

- [OpenRouter Rate Limits Documentation](https://openrouter.ai/docs/api/reference/limits)
- [OpenRouter Free Models Collection](https://openrouter.ai/collections/free-models)
- [OpenRouter Free Models List (CostGoat, Apr 2026)](https://costgoat.com/pricing/openrouter-free-models)
- [OpenRouter Free Models Router](https://openrouter.ai/docs/guides/routing/routers/free-models-router)
- [OpenRouter BYOK Documentation](https://openrouter.ai/docs/guides/overview/auth/byok)
- [OpenRouter 1M Free BYOK Announcement](https://openrouter.ai/announcements/1-million-free-byok-requests-per-month)
- [OpenRouter Pricing](https://openrouter.ai/pricing)
- [Gemini 2.5 Flash-Lite on OpenRouter](https://openrouter.ai/google/gemini-2.5-flash-lite)
- [GPT-4o-mini on OpenRouter](https://openrouter.ai/openai/gpt-4o-mini)
- [DeepSeek V3.1 on OpenRouter](https://openrouter.ai/deepseek/deepseek-chat-v3.1)
- [Claude 3.5 Haiku on OpenRouter](https://openrouter.ai/anthropic/claude-3.5-haiku)
- [Mistral Small 4 on OpenRouter](https://openrouter.ai/mistralai/mistral-small-2603)
- [Mistral Small Creative on OpenRouter](https://openrouter.ai/mistralai/mistral-small-creative)
- [Qwen Models on OpenRouter](https://openrouter.ai/qwen)
- [AI Model Benchmarks (Artificial Analysis)](https://artificialanalysis.ai/leaderboards/models)
- [OpenRouter Rankings April 2026](https://www.digitalapplied.com/blog/openrouter-rankings-april-2026-top-ai-models-data)
- [Gemini API Pricing (Google)](https://ai.google.dev/gemini-api/docs/pricing)
