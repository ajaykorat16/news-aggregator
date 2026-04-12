# Token Usage Deep Analysis -- Agent Workflow Optimization

Date: 2026-04-07
Scope: Claude Code agent workflow patterns in news-aggregator (Apr 4-7, 212 commits across all branches)

---

## 1. Session Token Waste Breakdown (Quantified)

### Commit profile (Apr 4-7)

| Type | Count | % of Total |
|------|-------|------------|
| `feat:` | 67 | 31.6% |
| `fix:` | 15 | 7.1% |
| `test:` | 2 | 0.9% |
| `docs:` / `chore:` / merge | 128 | 60.4% |
| **Total** | **212** | |

**Fix commits are 7.1% of all commits.** Each fix commit represents a full round-trip: agent detects issue, reads files, edits, runs quality, commits, pushes, waits for CI. Estimated cost: ~5-15K tokens per fix round.

### Top token waste categories

| Category | Occurrences | Est. Token Cost | Root Cause |
|----------|-------------|----------------|------------|
| **Screenshot retakes** | 3-4 commits | ~30-50K total | Senior dev agent lacks Playwright MCP tools |
| **CSRF test failures** | 2 fix commits | ~20-30K total | Fragile DOM extraction pattern |
| **Mutation testing fix rounds** | 3 PRs needed extra commits | ~40-60K total | Tests not written mutation-resistant upfront |
| **Duplicate quality runs** | Every PR (dev + CI) | ~10K per PR | Dev runs quality locally, CI re-runs identically |
| **Handoff file overhead** | ~15K bytes per feature | ~4-5K tokens per feature | 3 structured markdown files read by each agent |

**Estimated total waste: ~150-250K tokens over this 4-day period** (conservative).

---

## 2. Root Causes (Ranked by Impact)

### #1: Senior developer agent cannot take screenshots (~50K tokens wasted)

The senior developer agent (`.claude/agents/senior-developer.md`) does NOT have Playwright MCP tools in its tool list. Its tools are:

```
Read, Write, Edit, Glob, Grep, Bash, Agent, Skill,
mcp__code-review-graph__*, mcp__plugin_context7_context7__*
```

No `mcp__playwright__*` tools. The senior dev instructions at line 63 say: *"if this changes UI, capture with Playwright MCP tools only"* -- but the agent **cannot execute this instruction**. Every screenshot attempt either:
- Fails silently (agent uses raw CLI instead, producing transparent/broken images)
- Requires a follow-up `fix: retake screenshot` commit from the orchestrator

Evidence: 3 screenshot retake commits in 4 days:
- `57c3184e fix: retake card-enhancements screenshot with Playwright MCP`
- `fb5846e0 fix: retake screenshots without transparent areas`
- `d245ecb5 fix: retake screenshots without transparent areas (#132)`

### #2: Mutation testing not run before push (~50K tokens wasted)

Three PRs needed extra commits to fix mutation coverage:
- `0a0fdd79 test: strengthen batch enrichment tests to hit 90% mutation coverage`
- `c79ca043 test: strengthen handler tests to hit 90% mutation coverage threshold`
- `5663041e fix: use CoversNothing for DashboardControllerTest`

Each mutation fix cycle costs: read CI failure output (~2K tokens) + analyze tests (~3K) + edit tests (~2K) + run infection locally (~5K) + commit + push + wait for CI (~5K) = ~15-20K tokens per round.

The senior dev agent instructions already say "run `make infection` before submitting" (line 5 of Token Efficiency Rules), but this is not enforced.

### #3: CSRF token extraction fragility (~25K tokens wasted)

Two fix commits for CSRF extraction failures:
- `883ee3b6 fix: TriggerFetchSourceControllerTest creates source before CSRF extraction`
- `79ff4be0 fix: TriggerDigestControllerTest gets CSRF token from container`

The pattern: functional tests navigate to a page, parse the DOM for a CSRF token from an htmx button's `hx-headers` attribute. If the page has no buttons (e.g., no sources created yet), the DOM filter returns an empty node list and the test fails.

### #4: Duplicate quality/test runs (~10K tokens per PR)

Current flow:
1. Senior dev runs `make quality` + `make test` + `make infection` locally
2. CI runs the exact same checks on push

The local run produces output that costs ~2-5K tokens to process. For 16 agent-driven PRs, that is ~32-80K tokens spent processing output the agent already confirmed was green.

### #5: Handoff file overhead (~5K tokens per feature, low individual impact)

Three handoff files total ~15KB / ~4.5K tokens. Each agent reads at least 2 of the 3 files. For the 16 agent-driven PRs, that is ~70-90K tokens total on handoff file I/O. However, this overhead is justified: the handoff files replaced ad-hoc context passing that was even more expensive.

---

## 3. Concrete Fixes (Actionable)

### Fix 1: Add Playwright MCP tools to senior developer agent

**File**: `.claude/agents/senior-developer.md`
**Change**: Add Playwright tools to the tool list

```yaml
tools:
  - Read
  - Write
  - Edit
  - Glob
  - Grep
  - Bash
  - Agent
  - Skill
  - mcp__code-review-graph__query_graph_tool
  - mcp__code-review-graph__get_impact_radius_tool
  - mcp__code-review-graph__refactor_tool
  - mcp__code-review-graph__semantic_search_nodes_tool
  - mcp__plugin_context7_context7__resolve-library-id
  - mcp__plugin_context7_context7__query-docs
  # ADD THESE:
  - mcp__playwright__browser_navigate
  - mcp__playwright__browser_snapshot
  - mcp__playwright__browser_take_screenshot
  - mcp__playwright__browser_click
  - mcp__playwright__browser_wait_for
```

**Impact**: Eliminates screenshot retake commits entirely. Saves ~15K tokens per UI feature.

### Fix 2: Add pre-push hook that runs `make infection`

**File**: `.githooks/pre-push` (new)
**Change**: Run infection before push to catch MSI failures locally

```bash
#!/bin/bash
echo "Running mutation tests before push..."
make infection || { echo "Mutation tests failed. Push aborted."; exit 1; }
```

**Impact**: Catches MSI failures before CI, saving ~15-20K tokens per occurrence.

**Alternative** (lighter weight): Add `make infection` to the existing `make quality` target so it runs as part of the standard quality gate.

### Fix 3: Use container CSRF token service instead of DOM extraction

**File**: Functional tests using CSRF tokens
**Change**: Get CSRF tokens from the container's `CsrfTokenManagerInterface` instead of parsing page HTML.

Current (fragile):
```php
$crawler = $client->request('GET', '/sources');
$button = $crawler->filter('[hx-headers]');
// Fails if no button exists on page
```

Better:
```php
$csrfManager = self::getContainer()->get('security.csrf.token_manager');
$token = $csrfManager->getToken('fetch_source')->getValue();
```

**Impact**: Eliminates the "empty node list" failure pattern. Saves ~10-15K tokens per occurrence.

Note: `TriggerDigestControllerTest` already migrated to this pattern (commit `79ff4be0`). The approach works. Remaining tests in `TriggerFetchSourceControllerTest` and `FetchAllSourcesControllerTest` still use DOM extraction but now create a source first to ensure buttons exist -- a workaround, not a fix.

### Fix 4: Skip local CI-duplicate runs for agent workflow

**Approach A**: Agent instructions say "run `make quality` and `make test-unit` during dev, but skip `make test` (full suite) before push -- CI will catch integration issues."

**Approach B**: Add a `make quick-check` target that runs ECS + PHPStan + unit tests only (~15s), leaving the full suite + infection to CI.

**Impact**: Saves ~5K tokens per PR by not processing full test + infection output locally when CI will verify anyway.

### Fix 5: Condense handoff files for simple features

For small features (single entity, no cross-module changes), the architect could write a 10-line brief instead of a full 120-line template. Add a "complexity" field:

```markdown
## Complexity: Simple
## Brief: Add reading time field to Article. Display on card. Unit test.
## Branch: feat/135-reading-time
```

**Impact**: Saves ~2-3K tokens per simple feature. Not worth it for complex features where the full brief prevents scope creep.

---

## 4. Estimated Savings Per Fix

| Fix | Implementation Effort | Savings Per PR | Annual Estimate |
|-----|----------------------|----------------|-----------------|
| #1 Playwright tools for senior dev | 5 min (edit YAML) | ~15K tokens/UI PR | ~150K tokens |
| #2 Pre-push infection hook | 10 min | ~15K tokens/MSI failure | ~90K tokens |
| #3 Container CSRF tokens | 30 min (refactor tests) | ~10K per CSRF failure | ~40K tokens |
| #4 Skip duplicate runs | 10 min (edit agent docs) | ~5K per PR | ~80K tokens |
| #5 Condensed briefs | 15 min (add template) | ~2K per simple feature | ~30K tokens |
| **Total** | **~70 min** | | **~390K tokens/year** |

---

## 5. RTK Proxy Findings (Supplementary)

RTK is saving 84.9% of tokens across 4,362 commands (7.2M tokens saved). Top savers:
- `make worker-logs`: 1.5M saved (99.1%) -- long-running log output
- `make test`: 654K saved (25.2%) -- test output filtering
- `make build`: 597K saved (99.2%) -- Docker build output

`rtk discover` found **477K additional tokens saveable** from 3,623 commands not yet routed through RTK hooks:
- `make quality` (899 invocations, ~161K saveable)
- `git add` (797 invocations, ~88K saveable)
- `gh run` (710 invocations, ~74K saveable)

These are hook-level improvements (RTK configuration), not workflow improvements.

---

## 6. Summary of Priority Actions

1. **Immediate** (today): Add Playwright MCP tools to senior-developer.md
2. **This week**: Refactor CSRF tests to use container token manager
3. **This week**: Add `make infection` to pre-push hook or quality target
4. **Next sprint**: Add `make quick-check` target for agent dev loop
5. **Backlog**: Condensed brief template for simple features
