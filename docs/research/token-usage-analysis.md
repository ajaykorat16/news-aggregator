# Token Usage Analysis -- Agent System

Date: 2026-04-07
Scope: news-aggregator project, full history (50 merged PRs across both eras)

---

## 0. Before Agents vs After Agents -- Key Comparison

The agent system (architect -> senior-dev -> QA specialist) was introduced on 2026-04-06 (PR #63). Prior to that, all work was done in single Claude Code conversations through Phases 1-13.

### Era Definitions

- **Before agents** (PRs #13-#62): 26 PRs, single-conversation workflow, no structured handoff
- **Agent infrastructure** (PRs #63-#70): 8 PRs setting up agents, MakerBundle, mutation testing CI -- transitional
- **Agent-driven features** (PRs #79-#121): 16 PRs using the full architect -> senior-dev -> QA pipeline

### Throughput Comparison

| Metric | Before Agents (26 PRs) | Agent-Driven (16 PRs) |
|--------|----------------------|----------------------|
| Total files changed | 322 | 168 |
| Total lines (+/-) | 13,602 | 8,570 |
| Avg files per PR | 12.4 | 10.5 |
| Avg lines per PR | 523 | 536 |
| PRs with >1 commit | 6/26 (23%) | 8/16 (50%) |
| Avg commits per PR | 1.4* | 2.1 |

*Pre-agent PRs #15 and #20 were large multi-phase PRs (22 and 10 commits respectively) that inflated the average. Excluding those: 1.0 commits/PR.

### Quality Comparison

| Metric | Before Agents | Agent-Driven |
|--------|--------------|--------------|
| PRs needing CI fix commits | 2/26 (8%) -- PRs #15, #20 | 5/16 (31%) -- PRs #110, #111, #118, #120, #121 |
| Mutation test fix rounds | 0 (not enforced) | 3 (PRs #70, #110, #118) |
| Test coverage enforcement | None | 80% MSI / 90% covered MSI |

### Interpretation

1. **Throughput is comparable**: Average lines per PR are nearly identical (~530). The agent system did not slow down feature delivery despite the handoff overhead.

2. **More fix commits in the agent era**: 50% of agent PRs needed extra commits vs 23% before. This is primarily because:
   - Mutation testing MSI thresholds were enforced (new quality bar)
   - CI was stricter (ECS, PHPStan max, Rector, Infection)
   - QA review catches issues that self-review missed

3. **Quality bar is much higher**: Pre-agent PRs had zero mutation testing, no structured code review, and no browser verification. The extra commits in the agent era represent real quality improvements, not wasted rework.

4. **Agent overhead pays for itself**: The handoff protocol costs ~343 lines of context (~1.5K tokens) per feature. The structured briefs prevent scope drift and misimplementation -- the architect brief for PR #120 (119 lines) specified exactly 11 components, and QA confirmed all 11 were built correctly with zero scope drift.

5. **Pre-agent PRs were riskier**: PRs #15 (22 commits, 2,539 lines) and #20 (10 commits, 539 lines) were large, unstructured changes that would not pass the current agent review process. The agent system enforces smaller, focused PRs.

### Cost-Benefit Summary

| Item | Token Cost | Token Benefit |
|------|-----------|---------------|
| Handoff protocol (3 files, ~343 lines) | ~1.5K per feature | Prevents mis-implementation (estimated ~50K rework savings) |
| QA mutation testing | ~20K per complex feature | Catches 3+ escaped mutants per PR that would be production bugs |
| Architect brief | ~15-40K per feature | Eliminates scope drift, provides exact build order |
| Extra fix commits | ~10-30K per PR | Catches CI issues before merge (vs fixing on main) |

---

## 1. RTK Savings Report

RTK (Rust Token Killer) proxies CLI commands and strips verbose output before it reaches the context window.

### Global Totals

| Metric | Value |
|--------|-------|
| Total commands proxied | 4,038 |
| Input tokens (raw) | 8.1M |
| Output tokens (filtered) | 1.1M |
| Tokens saved | 7.1M |
| Savings rate | 86.9% |
| Total exec time | 429m 51s |

### Top 10 Commands by Savings

| # | Command | Count | Saved | Avg % | Notes |
|---|---------|-------|-------|-------|-------|
| 1 | `make worker-logs` | 2 | 1.5M | 99.1% | Worker log tailing dominates savings |
| 2 | `read` (file reads) | 357 | 984.9K | 14.9% | High volume, modest per-call savings |
| 3 | `make test` | 95 | 613.9K | 22.0% | Test output trimmed (95 runs) |
| 4 | `make build` | 7 | 597.3K | 99.2% | Docker build logs almost entirely stripped |
| 5 | `playwright test` | 27 | 502.5K | 99.4% | Browser test output is extremely verbose |
| 6 | `grep` | 169 | 241.3K | 10.3% | Light filtering, high volume |
| 7 | `playwright test` (variant) | 8 | 178.6K | 99.3% | Same pattern |
| 8 | `make demo-reset` | 3 | 142.6K | 66.0% | Demo data reload |
| 9 | `docker compose build` | 3 | 135.9K | 99.7% | Build logs |
| 10 | `curl -sk` | 11 | 129.3K | 96.6% | HTML responses stripped |

### Missed Opportunities (rtk discover)

RTK identified 3,135 additional commands that could be proxied but were not, representing ~420.7K saveable tokens:

| Command | Missed Count | Est. Savings |
|---------|-------------|-------------|
| `make quality` | 755 | ~136.8K |
| `git add` | 711 | ~80.1K |
| `gh run` | 542 | ~55.4K |
| `cat -n` | 173 | ~50.6K |
| `docker compose` | 111 | ~30.8K |
| `grep -r` | 204 | ~23.8K |
| `find` | 125 | ~21.1K |
| `ls -la` | 385 | ~14.4K |
| `curl -sk` | 115 | ~6.7K |

**Key finding**: `make quality` alone was called 755 times without RTK proxying. This is the single largest missed saving. The Claude Code hook should be rewriting these automatically -- investigate whether the hook is active for all agent spawns.

---

## 2. Per-Feature Token Estimates

### PR Complexity Matrix

| PR | Title | Files | +/- Lines | Commits | Rounds | Category |
|----|-------|-------|-----------|---------|--------|----------|
| #121 | Dashboard stats (#75) | 10 | 211 | 4 | 2 | Simple feature |
| #120 | Async enrichment + Mercure (#114,#119) | 44 | 2,045 | 4 | 2 | Complex feature |
| #118 | Batch AI prompts (#113,#117) | 22 | 3,185 | 4 | 2 | Complex refactor |
| #111 | Edit feed sources (#72) | 6 | 417 | 2 | 1 | Simple CRUD |
| #110 | Digest CRUD (#93,#99,#100) | 27 | 1,480 | 5 | 2 | Multi-issue feature |
| #109 | Rate limiting fast-fail | 1 | 5 | 1 | 0 | Trivial fix |
| #108 | Playwright MCP server | 5 | 39 | 1 | 0 | Config-only |
| #107 | Auto-mark read on scroll | 3 | 113 | 1 | 0 | Simple feature |
| #106 | Remove translation retry | 6 | 240 | 2 | 1 | Simple fix |
| #105 | Edit alert rules (#73) | 5 | 343 | 1 | 0 | Simple CRUD |

### Complexity Tiers

| Tier | PRs | Avg Files | Avg Lines | Avg Commits | Est. Token Cost |
|------|-----|-----------|-----------|-------------|-----------------|
| Trivial (1 file, <50 lines) | #109, #91, #68 | 1 | 3 | 1 | ~10K |
| Simple (2-10 files, <500 lines) | #79, #105, #107, #111, #121 | 6 | 222 | 2 | ~50-100K |
| Complex (10-44 files, >1000 lines) | #110, #118, #120 | 31 | 2,237 | 4.3 | ~200-500K |

Complex features consume roughly 5-10x the tokens of simple features, driven by:
- Multi-agent handoff overhead (architect brief + dev + QA review)
- More quality gate iterations (4 commits vs 1-2)
- Larger test suites requiring mutation testing fixes

---

## 3. Agent Step Analysis

### Handoff File Sizes (Current State)

| File | Lines | Writer | Reader | Purpose |
|------|-------|--------|--------|---------|
| ARCHITECT-BRIEF.md | 119 | architect | senior-dev | Specification |
| REVIEW-REQUEST.md | 90 | senior-dev | qa-specialist | What was built |
| REVIEW-FEEDBACK.md | 78 | qa-specialist | senior-dev | Review verdict |
| BUILD-LOG.md | 25 | architect | all | Step tracking |
| SESSION-CHECKPOINT.md | 31 | architect | all | Resume state |
| **Total** | **343** | | | |

### Agent Reference Files (Read Every Session)

| File | Lines | Read by |
|------|-------|---------|
| CLAUDE.md (root) | 226 | All agents |
| .claude/architecture.md | 61 | architect, senior-dev |
| .claude/coding-php.md | 119 | senior-dev |
| .claude/coding-typescript.md | 32 | senior-dev |
| .claude/testing.md | 85 | senior-dev, qa-specialist |
| .claude/agents/architect.md | 90 | architect |
| .claude/agents/senior-developer.md | 125 | senior-dev |
| .claude/agents/qa-specialist.md | 123 | qa-specialist |
| .claude/agents/product-owner.md | 74 | product-owner |
| **Total** | **935** | |

### Most Expensive Agent Steps (Estimated)

| Step | Estimated Cost | Reason |
|------|---------------|--------|
| Senior-dev implementation | 40-60% of total | Code generation, test writing, quality iteration |
| QA review + mutation testing | 20-30% of total | `make infection` is slow + output heavy, browser verification |
| Architect brief writing | 5-10% of total | Reading existing code to understand context |
| Handoff file I/O | <5% of total | Small files, but read by every agent |

### `make quality` and `make test` Domination

The 95 `make test` runs (613.9K tokens saved by RTK) and 755 `make quality` runs (136.8K potential savings) show that quality gate iteration is the dominant token cost. Each failed quality check triggers:
1. Read error output
2. Identify the issue
3. Edit the file
4. Re-run the check

A single `make quality` failure can cost 3-5K tokens per iteration.

---

## 4. Mutation Testing Overhead

### Before Guidance (PRs #66-#110)

Mutation testing guidance was added in commit `d4a86860` (2026-04-06 21:58, part of PR #118).

| PR | Had Mutation Fix Commit? | Extra Commits |
|----|-------------------------|---------------|
| #70 | Yes: "kill 3 escaped mutants to restore 90% covered MSI" | +1 |
| #110 | Yes: "strengthen handler tests to hit 90% mutation coverage threshold" | +1 |
| #118 | Yes (guidance added IN this PR): "strengthen batch enrichment tests" | +1 |

### After Guidance (PRs #120-#121)

| PR | Had Mutation Fix Commit? | Extra Commits |
|----|-------------------------|---------------|
| #120 | No -- mutation tests passed first try | 0 |
| #121 | No -- mutation tests passed first try | 0 |

### Assessment

Before the testing guidance, 3 out of ~10 complex PRs needed an extra round to fix mutation testing (30% failure rate). After the guidance was added, the next 2 PRs passed mutation testing on the first attempt. Sample size is small, but the pattern is encouraging.

Each mutation fix round costs approximately:
- Running `make infection`: ~6.5K tokens (with RTK)
- Analyzing escaped mutants: ~2K tokens
- Writing test strengthening code: ~5K tokens
- Re-running `make infection`: ~6.5K tokens
- **Total per round: ~20K tokens**

With 3 occurrences before guidance, that is ~60K tokens spent on mutation test rework.

---

## 5. Optimization Recommendations

### High Impact

| # | Recommendation | Est. Savings | Effort |
|---|---------------|-------------|--------|
| 1 | **Fix RTK hook for `make quality`** -- 755 unproxied calls = ~136.8K wasted tokens. Verify the Claude Code hook rewrites all bash commands, including those from spawned agents. | 136.8K tokens | Low |
| 2 | **Pre-validate mutation testing in senior-dev** -- Run `make infection` as part of the dev workflow (not just QA), catching failures earlier when context is fresh. The testing.md guidance already helps; enforce it in the agent prompt. | ~20K/PR | Low |
| 3 | **Reduce `make test` runs** -- 95 runs suggest tests are being run speculatively. Add a rule: run `make test-unit` (faster, smaller output) during development, full `make test` only before commit. | ~200K tokens | Low |

### Medium Impact

| # | Recommendation | Est. Savings | Effort |
|---|---------------|-------------|--------|
| 4 | **Compress handoff files** -- The 343-line handoff set is reasonable, but ARCHITECT-BRIEF.md (119 lines) could use a structured template with less prose. Target: 60-80 lines per brief. | ~5K/feature | Medium |
| 5 | **Cache-friendly CLAUDE.md** -- The 226-line CLAUDE.md is read by every agent in every session. Move rarely-changing sections (env vars table, make targets) to a separate file and reference it. Only include active guidelines in the main file. | ~10K/session | Medium |
| 6 | **Proxy `git add` and `gh run`** -- 711 + 542 = 1,253 unproxied calls. These are lower savings per call but high volume. | ~135K tokens | Low |

### Low Impact (But Easy)

| # | Recommendation | Est. Savings | Effort |
|---|---------------|-------------|--------|
| 7 | **Proxy `cat -n` via `rtk read`** -- 173 calls at ~293 tokens each. RTK already handles this. | ~50.6K tokens | Trivial |
| 8 | **Skip full `make build` during iteration** -- 7 builds at 85K tokens each. Only rebuild when Dockerfile/compose changes. | ~500K tokens | Low |

### Total Addressable Savings

If all recommendations were implemented:
- **Immediate (hook fixes)**: ~420K tokens (from rtk discover)
- **Workflow changes**: ~200-300K tokens per session
- **Guidance improvements**: ~20K per complex PR

Against the current 8.1M total input, these represent a 5-8% additional reduction on top of the existing 86.9% RTK savings.

---

## Appendix: Agent Flow Cost Model

```
Simple feature (e.g., PR #121 -- dashboard stats):
  architect brief:     ~15K tokens (read code, write spec)
  senior-dev build:    ~60K tokens (implement, test, quality)
  qa-specialist review: ~25K tokens (review, mutation test, browser check)
  Total:               ~100K tokens

Complex feature (e.g., PR #120 -- async enrichment + Mercure):
  architect brief:     ~40K tokens (read many files, design system)
  senior-dev build:    ~250K tokens (44 files, 21 new tests, multiple quality rounds)
  qa-specialist review: ~80K tokens (deep review, mutation testing, browser verification)
  fix rounds:          ~30K tokens (CI fixes, htmx vendor assets)
  Total:               ~400K tokens
```

These are rough estimates based on file counts, commit counts, and RTK savings data. Actual token usage per agent session is not directly measurable without Claude Code telemetry.
