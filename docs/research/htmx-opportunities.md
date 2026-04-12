# htmx Opportunities Audit

**Date**: 2026-04-07  
**Context**: Symfony 8 + FrankenPHP, htmx 2.0.7 + SSE extension already installed in `importmap.php`, vanilla TypeScript modules, Twig templates, DaisyUI/Tailwind CSS.

---

## Executive Summary

This audit evaluates every user-facing UI page for opportunities to simplify interactions using htmx. The existing evaluation (`htmx-evaluation.md`) identified high-value opportunities; this document details every page and interaction.

**Key findings**:
- **Already implemented**: Dashboard infinite scroll via htmx (pagination-loader component)
- **Immediate candidates**: Source/Alert/Digest/Notification CRUD forms → swap modal or redirect avoidance
- **Search page**: Could benefit from live search via `hx-trigger="keyup"` instead of form submission
- **TypeScript reduction**: Keep most modules (client-side logic), but Mercure SSE integration can use htmx SSE extension
- **No htmx candidates**: Theme toggle, language selector, article filter (all client-side DOM manipulation with no server round-trip)

---

## Page-by-Page Audit

### 1. Dashboard (`/` — `DashboardController`, `dashboard/index.html.twig`)

**Current State**:
- Infinite scroll with pagination ✅ ALREADY USING HTMX
  - `_pagination_loader.html.twig` uses `hx-get`, `hx-trigger="revealed"`, `hx-swap="outerHTML"`
  - Sentinel div scrolls into view → loads next page
- "Mark All Read" button → full page reload via form submission
- "Unread Only" filter → full page reload via navigation link
- Category tabs → full page reload via navigation links
- Client-side article text filter via `article-filter.ts`
- Mercure SSE updates via `mercure-updates.ts` (custom EventSource)
- Mark-as-read on click or scroll via `mark-as-read.ts`
- Language selection via `language-selector.ts` (client-side swaps)
- Relative timestamps via `timeago.ts`

**Htmx Analysis**:

| Interaction | Current | Htmx Opportunity | Complexity | Recommendation |
|---|---|---|---|---|
| **Infinite scroll** | ✅ Already htmx | N/A | - | **DONE** |
| **Mark All Read button** | POST form → redirect to dashboard | `hx-post` to new endpoint returning article feed | Small | **DO** — Replace full redirect with AJAX post + in-place swap |
| **Unread Only filter** | Link → reload dashboard | `hx-get` with filter params, swap article feed | Trivial | **DO** — Current links redirect; use `hx-get` instead |
| **Category tabs** | Links → reload dashboard | `hx-get` with category param, swap article feed | Trivial | **DO** — Replace with `hx-get` |
| **Client text filter** | `article-filter.ts` — client-side DOM hiding | Keep as-is | N/A | **SKIP** — Client-side filter is instant; server search would add latency. Only valuable if SEAL/Meilisearch search should be used. |
| **Mercure new articles** | EventSource + banner + reload on click | `sse-connect` + `sse-swap` to prepend new articles | Small | **DO** — Eliminate `mercure-updates.ts`, use htmx SSE extension |
| **Mercure enrichment updates** | EventSource → manual DOM updates via `mercure-updates.ts` | `sse-connect` + OOB swap (out-of-band) to replace specific article card | Medium | **DO** — Eliminate 150+ lines of DOM manipulation |
| **Mark as read (scroll)** | `mark-as-read.ts` — IntersectionObserver + timer + POST | Keep as-is | N/A | **SKIP** — Custom UX logic (dwell timer); htmx provides no value |
| **Language selector** | `language-selector.ts` — client-side translation swap | Keep as-is | N/A | **SKIP** — Instant client-side; no server round-trip needed |
| **Timeago display** | `timeago.ts` — format relative times | Keep as-is | N/A | **SKIP** — Runs every 60s; client-side only |

**htmx Implementation Details**:

```html
{# dashboard/index.html.twig #}

{# Category tabs — replace with hx-get #}
<div class="tabs tabs-boxed mb-4 overflow-x-auto">
    <a hx-get="{{ path('app_dashboard', {category: null}) }}"
       hx-target="#article-feed"
       hx-swap="innerHTML"
       class="tab {{ currentCategory is null ? 'tab-active' : '' }}">
        All
    </a>
    {% for category in categories %}
        <a hx-get="{{ path('app_dashboard', {category: category.slug}) }}"
           hx-target="#article-feed"
           hx-swap="innerHTML"
           class="tab {{ currentCategory == category.slug ? 'tab-active' : '' }}">
            {{ category.name }}
        </a>
    {% endfor %}
</div>

{# Unread/Show All filter — replace with hx-get #}
<div class="flex items-center gap-3 mb-4">
    {% if unreadOnly %}
        <a hx-get="{{ path('app_dashboard', {category: currentCategory}) }}"
           hx-target="#article-feed"
           hx-swap="innerHTML"
           class="btn btn-sm btn-outline">
            Show All
        </a>
    {% else %}
        <a hx-get="{{ path('app_dashboard', {category: currentCategory, unreadOnly: 1}) }}"
           hx-target="#article-feed"
           hx-swap="innerHTML"
           class="btn btn-sm btn-outline">
            Unread Only
        </a>
    {% endif %}
    
    {# Mark All Read — POST via hx-post #}
    <button hx-post="{{ path('app_articles_read_all') }}"
            hx-target="#article-feed"
            hx-swap="innerHTML"
            class="btn btn-sm btn-ghost">
        Mark All Read
    </button>
</div>

{# Article feed + Mercure SSE — htmx SSE extension #}
<div id="article-feed"
     hx-ext="sse"
     sse-connect="{{ mercure_url_for(['articles', 'articles/enriched']) }}"
     class="flex flex-col gap-3">
    {% for article in articles %}
        {% include 'components/_article_card.html.twig' %}
    {% endfor %}
    {% include 'components/_pagination_loader.html.twig' with {...} %}
</div>
```

**Controller Changes**:
- `DashboardController` already detects `HX-Request` header and returns `_article_list.html.twig`
- Add logic to return just the article feed (not stats) when htmx request
- `MarkAllReadController` should detect htmx request and return feed instead of redirect

**Server-Side Changes Needed**:
1. Create new endpoints or leverage existing ones:
   - `MarkAllReadController` — add htmx detection, return feed partial
   - Ensure `DashboardController` returns correct partial with feed + pagination sentinel
2. Build Mercure SSE URL using `mercure_url_for()` Twig helper
3. Create Mercure publishers for:
   - New articles: publish rendered `_article_card.html.twig`
   - Enrichment updates: publish card with `hx-swap-oob="outerHTML"`

**Expected Result**:
- Category switching: instant filter (no page reload)
- Mark All Read: updates feed in place, no navigation
- Mercure integration: no custom EventSource code, zero JS
- Lines of JS eliminated: ~150 (mercure-updates.ts)

---

### 2. Sources Management (`/sources` — `SourceController`, `source/index.html.twig`)

**Current State**:
- Table of sources with name, category, health, errors, last fetched, enabled status
- Action buttons: Edit (link), Delete (POST form with confirm)
- Create and Edit pages: full-page forms

**Htmx Analysis**:

| Interaction | Current | Htmx Opportunity | Complexity | Recommendation |
|---|---|---|---|---|
| **Edit link** | Navigate to `/sources/{id}/edit` | Modal with form via `hx-get` + `hx-target` | Medium | **DO** — Modal edit avoids navigation |
| **Delete button** | POST form → redirect to sources list | `hx-delete` + `hx-confirm` + row removal via `hx-swap="swap:closest tr"` | Small | **DO** — Inline deletion with confirmation |
| **Create new** | Navigate to `/sources/new` | Modal with form via `hx-get` | Medium | **DO** — Same as edit |

**htmx Implementation Details**:

```html
{# source/index.html.twig #}
<table class="table table-zebra">
    <tbody>
        {% for source in sources %}
            <tr>
                ...
                <td class="flex gap-1">
                    {# Edit — trigger modal #}
                    <button hx-get="{{ path('app_sources_edit', {id: source.id}) }}"
                            hx-target="#modal-form"
                            hx-swap="innerHTML"
                            class="btn btn-ghost btn-xs">
                        Edit
                    </button>
                    
                    {# Delete — inline with confirmation #}
                    <button hx-delete="{{ path('app_sources_delete', {id: source.id}) }}"
                            hx-confirm="Delete this source?"
                            hx-target="closest tr"
                            hx-swap="swap outerHTML swap:1s"
                            class="btn btn-ghost btn-xs text-error">
                        Delete
                    </button>
                </td>
            </tr>
        {% endfor %}
    </tbody>
</table>

{# Modal container #}
<div id="modal-form" class="modal"></div>

{# Create button #}
<a hx-get="{{ path('app_sources_new') }}"
   hx-target="#modal-form"
   hx-swap="innerHTML"
   class="btn btn-primary btn-sm">
    New Source
</a>
```

```html
{# source/new.html.twig and edit.html.twig — wrapped in modal dialog #}
<dialog class="modal" open>
    <div class="modal-box max-w-lg">
        <form hx-post="{{ path('app_sources_new') }}" {# or app_sources_edit #}
              hx-target="#sources-table"
              hx-swap="innerHTML">
            {# Form fields #}
            <div class="modal-action">
                <button type="submit" class="btn btn-primary">Create</button>
                <button type="button" hx-on:click="htmx.ajax('GET', window.location, '#modal-form')" class="btn">Close</button>
            </div>
        </form>
    </div>
</dialog>
```

**Controller Changes**:
- Detect htmx request in `CreateSourceController`, `EditSourceController`, `DeleteSourceController`
- On POST success, return updated sources table instead of redirect
- Add htmx header detection to return modal vs full page

**Server-Side Changes Needed**:
1. Add partial response endpoint for sources table
2. Modify delete controller to support DELETE method (or POST with header detection)
3. Form submission in modal should return table contents (not modal)

**Complexity**: Medium  
**Recommendation**: **DO** — Eliminates 3 page navigations

---

### 3. Alert Rules (`/alerts` — `AlertRuleController`, `alert/index.html.twig`)

**Current State**:
- Table of alert rules with name, type, keywords, urgency, cooldown, enabled, matches, last triggered
- Action buttons: Edit (link), Delete (POST form with confirm)
- Create and Edit pages: full-page forms

**Htmx Analysis**: Identical to Sources page.

| Interaction | Current | Htmx Opportunity | Complexity | Recommendation |
|---|---|---|---|---|
| **Edit link** | Navigate to `/alerts/{id}/edit` | Modal via `hx-get` | Medium | **DO** |
| **Delete button** | POST form → redirect | `hx-delete` + row removal | Small | **DO** |
| **Create new** | Navigate to `/alerts/new` | Modal via `hx-get` | Medium | **DO** |

**Implementation**: Identical to Sources (use same modal pattern).

---

### 4. Digest Configuration (`/digests` — `DigestController`, `digest/index.html.twig`)

**Current State**:
- Table of digest configs with name, schedule, categories, limit, last run
- Action buttons: Edit (link), Run Now (POST), Delete (POST with confirm)
- Recent digest history: collapsible list of logs
- Create and Edit pages: full-page forms
- View Log page: detail view

**Htmx Analysis**:

| Interaction | Current | Htmx Opportunity | Complexity | Recommendation |
|---|---|---|---|---|
| **Edit link** | Navigate to `/digests/{id}/edit` | Modal via `hx-get` | Medium | **DO** |
| **Delete button** | POST form → redirect | `hx-delete` + row removal | Small | **DO** |
| **Run Now button** | POST form → redirect | `hx-post` + status update in row | Small | **DO** |
| **Create new** | Navigate to `/digests/new` | Modal via `hx-get` | Medium | **DO** |
| **View Log** | Navigate to `/digests/{id}/log` | Modal or side panel via `hx-get` | Medium | **OPTIONAL** |

**htmx Implementation Details**:

```html
{# digest/index.html.twig #}
<table class="table table-zebra">
    <tbody>
        {% for config in configs %}
            <tr>
                ...
                <td class="flex gap-1">
                    <button hx-get="{{ path('app_digests_edit', {id: config.id}) }}"
                            hx-target="#modal-form"
                            hx-swap="innerHTML"
                            class="btn btn-ghost btn-xs">
                        Edit
                    </button>
                    <button hx-post="{{ path('app_digests_trigger', {id: config.id}) }}"
                            hx-target="closest tr"
                            hx-swap="outerHTML"
                            class="btn btn-ghost btn-xs text-info">
                        Run Now
                    </button>
                    <button hx-delete="{{ path('app_digests_delete', {id: config.id}) }}"
                            hx-confirm="Delete this digest configuration?"
                            hx-target="closest tr"
                            hx-swap="outerHTML"
                            class="btn btn-ghost btn-xs text-error">
                        Delete
                    </button>
                </td>
            </tr>
        {% endfor %}
    </tbody>
</table>
```

**Complexity**: Medium  
**Recommendation**: **DO** — Eliminates 4 page navigations, improves UX with in-place updates

---

### 5. Notification Log (`/notifications` — `NotificationLogController`, `notification/index.html.twig`)

**Current State**:
- Read-only table of alert matches with time, rule, article, type, severity, status
- No interactive elements

**Htmx Analysis**:

| Interaction | Current | Htmx Opportunity | Complexity | Recommendation |
|---|---|---|---|---|
| **Pagination** | None visible; assume server-rendered list | Could add `hx-get` pagination if limit needed | Small | **SKIP** — No pagination UI shown; add if list grows |

**Recommendation**: **SKIP** — No interactions to improve.

---

### 6. Search (`/search` — `SearchController`, `search/index.html.twig`)

**Current State**:
- Form with text input and submit button
- On submit: GET request with `?q=...` parameter
- Results rendered on same page

**Htmx Analysis**:

| Interaction | Current | Htmx Opportunity | Complexity | Recommendation |
|---|---|---|---|---|
| **Search form** | Submit → reload with results | `hx-get` with `hx-trigger="keyup changed delay:500ms"` for live search | Small | **DO** — Live search without form submission |

**htmx Implementation Details**:

```html
{# search/index.html.twig #}
<div class="join w-full max-w-lg">
    <input type="text"
           name="q"
           value="{{ query }}"
           placeholder="Search articles..."
           hx-get="{{ path('app_search') }}"
           hx-trigger="keyup changed delay:500ms"
           hx-target="#search-results"
           hx-swap="innerHTML"
           class="input input-bordered join-item w-full"
           autofocus>
    <button type="submit" class="btn btn-primary join-item">Search</button>
</div>

<div id="search-results">
    {# Results rendered here #}
    {% if query %}
        {% if results is empty %}
            {% include 'components/_empty_state.html.twig' with {...} %}
        {% else %}
            <p class="text-base-content/50 mb-4">{{ results|length }} result(s)</p>
            <div class="flex flex-col gap-3">
                {% for article in results %}
                    {% include 'components/_article_card.html.twig' %}
                {% endfor %}
            </div>
        {% endif %}
    {% endif %}
</div>
```

**Controller Changes**:
- Detect htmx request; return just results div (not full page)
- Implement `HX-Request` header detection

**Complexity**: Trivial  
**Recommendation**: **DO** — Live search improves UX

---

### 7. Settings (`/settings` — `SettingsController`, `settings/index.html.twig`)

**Current State**:
- Read-only display of configuration status (OpenRouter, Notifier DSN, retention days)
- Quick links to other pages

**Htmx Analysis**:

| Interaction | Current | Htmx Opportunity | Complexity | Recommendation |
|---|---|---|---|---|
| **None** | Static page | N/A | - | **SKIP** |

**Recommendation**: **SKIP** — No interactions to improve.

---

### 8. AI Stats (`/ai-stats` — `AiStatsController`, `stats/ai.html.twig`)

**Current State**:
- Table of AI model stats (accepted, rejected, acceptance rate)
- List of available free models

**Htmx Analysis**:

| Interaction | Current | Htmx Opportunity | Complexity | Recommendation |
|---|---|---|---|---|
| **None** | Static page; could add refresh button | `hx-get` to refresh stats table | Trivial | **OPTIONAL** — Add "Refresh Stats" button if stats should be live |

**Recommendation**: **SKIP** — Stats are static; no real-time updates needed.

---

## TypeScript Module Assessment

### Modules to Keep (No htmx Alternative)

1. **`theme-toggle.ts`** (small)
   - Toggles `data-theme` attribute on `<html>`
   - Persists in localStorage
   - No server interaction
   - htmx provides no value

2. **`language-selector.ts`** (129 lines)
   - Swaps article card text (title, summary, keywords) based on `data-translations` JSON
   - Instant client-side, no server round-trip
   - htmx would add latency
   - Keep as-is

3. **`mark-as-read.ts`** (102 lines)
   - IntersectionObserver + dwell timer + POST on scroll
   - Custom UX logic (dwell timer prevents accidental marks)
   - htmx provides no value
   - Keep as-is; already works with htmx-added content via MutationObserver

4. **`timeago.ts`** (59 lines)
   - Formats relative time (`1m ago`, `3h ago`)
   - Runs every 60 seconds
   - Already listens to `htmx:afterSwap` event for new content
   - Keep as-is

5. **`article-filter.ts`** (33 lines)
   - Client-side text filter on visible articles
   - Instant, no server latency
   - Only replace if SEAL/Meilisearch server-side search should be used
   - Recommendation: **KEEP** unless full-text search is implemented

### Module to Eliminate (htmx Alternative)

**`mercure-updates.ts`** (224 lines)
- Current: Custom EventSource subscription + manual DOM manipulation
- htmx Alternative: Use SSE extension + Mercure push rendered HTML
- Elimination: ~224 lines of TypeScript
- Replacement: ~10 lines of htmx attributes + server-side Mercure publishing

**Specific changes**:
```typescript
// REMOVE: mercure-updates.ts entirely

// Modify: assets/app.js — remove import of mercure-updates
// Before:
import './js/mercure-updates.js';

// After:
// (remove line)

// Add: Server-side Mercure publisher for new articles and enrichment updates
// See Symfony/Mercure documentation
```

---

## Search Strategy Analysis

The current `article-filter.ts` provides client-side text filtering. Three options:

### Option A: Keep Client-Side Filter (Current)
- ✅ Instant (no server round-trip)
- ✅ No server load
- ❌ Limited to visible articles, no full-text search
- Complexity: None

### Option B: Server-Side Search with htmx (Proposed)
```html
<input id="article-filter"
       name="q"
       type="text"
       placeholder="Filter articles..."
       hx-get="/dashboard/search"
       hx-trigger="keyup changed delay:200ms"
       hx-target="#article-feed"
       hx-swap="innerHTML"
       class="input input-bordered input-sm flex-1" />
```
- ✅ Full-text search (if SEAL/Meilisearch used)
- ✅ Searches all articles, not just visible
- ❌ Requires server round-trip (200ms+ latency)
- ❌ Requires new endpoint
- Complexity: Small

### Option C: Hybrid (Best)
- Keep client-side filter for instant UX
- Allow toggle to "Search All Articles" via server-side endpoint
- Complexity: Medium

**Recommendation**: **KEEP client-side filter** for dashboard. If full-text search needed, implement as separate feature (Option B on search page, not dashboard).

---

## Implementation Priority & Effort Summary

### Tier 1: Quick Wins (Trivial → Small, High Value)

| Feature | Current | Change | TS Eliminated | Server Changes | Recommendation |
|---------|---------|--------|---------------|-----------------|---|
| **Dashboard category tabs** | Link reload | `hx-get` filter | 0 | Minor (htmx detection) | **DO** |
| **Dashboard unread toggle** | Link reload | `hx-get` filter | 0 | Minor (htmx detection) | **DO** |
| **Dashboard Mark All Read** | POST redirect | `hx-post` + feed swap | 0 | Minor (return partial) | **DO** |
| **Infinite scroll pagination** | ✅ Already htmx | N/A | - | - | **DONE** |
| **Search live search** | Form submit | `hx-trigger="keyup"` | 0 | Minor (htmx detection) | **DO** |

**Effort**: 1-2 hours  
**Impact**: Eliminates 3 page reloads on dashboard, 1 on search

---

### Tier 2: Medium Value (Small → Medium, Moderate Effort)

| Feature | Current | Change | TS Eliminated | Server Changes | Recommendation |
|---------|---------|--------|---------------|-----------------|---|
| **Source/Alert/Digest CRUD** | Full-page forms | Modal forms via `hx-get` | 0 | New partial endpoints | **DO** |
| **Source/Alert/Digest delete** | POST redirect | `hx-delete` + row removal | 0 | Minor (detect method) | **DO** |
| **Digest Run Now** | POST redirect | `hx-post` + row update | 0 | Minor (return row) | **DO** |

**Effort**: 4-6 hours  
**Impact**: Eliminates 6+ page navigations, improves CRUD UX

---

### Tier 3: High Value, Moderate Effort (Medium)

| Feature | Current | Change | TS Eliminated | Server Changes | Recommendation |
|---------|---------|--------|---------------|-----------------|---|
| **Mercure new article notifications** | EventSource + banner | `sse-connect` + prepend | 50 lines | Publish rendered card | **DO** |
| **Mercure enrichment updates** | EventSource + DOM manipulation | `sse-connect` + OOB swap | 150 lines | Publish rendered card with OOB | **DO** |

**Effort**: 4-8 hours (mostly Mercure setup)  
**Impact**: Eliminates 200 lines of TS, enables real-time UI updates

---

### Tier 4: Not Recommended (Minimal Value)

| Feature | Current | Reason | Recommendation |
|---------|---------|--------|---|
| **Language selector** | Client-side swap | Instant; server round-trip adds latency | **SKIP** |
| **Mark as read** | Custom dwell timer | UX-critical logic; htmx provides no value | **SKIP** |
| **Theme toggle** | Client-side localStorage | No server interaction needed | **SKIP** |
| **Timeago display** | Client-side formatting | Runs every 60s; instant client-side | **SKIP** |
| **Article text filter** | Client-side DOM hiding | Instant; server search only if needed for full-text | **SKIP** |

---

## Controller Compatibility

**Good news**: Controllers already support htmx patterns where needed.

✅ **DashboardController**:
- Already detects `HX-Request` header
- Returns `_article_list.html.twig` partial
- Ready for category/filter `hx-get` requests

✅ **Form Controllers** (Source, Alert, Digest):
- Could detect htmx and return updated table/list instead of redirect
- No breaking changes needed

⚠️ **Delete Controllers**:
- All POST-only; need to support DELETE method for htmx
- Add `#[Route(..., methods: ['POST', 'DELETE'])]` or separate DELETE handler

---

## Mercure Integration with htmx

### Current Setup
```typescript
// mercure-updates.ts
const eventSource = new EventSource(hubUrl);
eventSource.onmessage = (event) => {
    const data = JSON.parse(event.data);
    if (data.type === 'created') {
        handleArticleCreated(); // show banner
    } else if (data.type === 'enriched') {
        handleEnrichmentComplete(data); // update card manually
    }
};
```

### htmx + Mercure Integration
```html
{# dashboard/index.html.twig #}

{# SSE connection to Mercure hub #}
<div id="article-feed"
     hx-ext="sse"
     sse-connect="{{ mercure_url }}"
     class="flex flex-col gap-3">
    
    {# Existing articles #}
    {% for article in articles %}
        {% include 'components/_article_card.html.twig' %}
    {% endfor %}
</div>

{# Banner that htmx can target #}
<div id="new-articles-banner" class="alert alert-info hidden">
    <span id="new-articles-count">1 new article available. Click to refresh.</span>
</div>
```

### Server-side: Publish rendered HTML
```php
// After article creation (in message handler)
use Symfony\Component\Mercure\Update;

$update = new Update(
    topics: ['/articles'],
    data: $twig->render('components/_article_card.html.twig', [
        'article' => $article,
    ]),
    type: 'article-created',
);
$hub->publish($update);

// After enrichment completes
$update = new Update(
    topics: ['/articles'],
    data: sprintf(
        '<div id="article-%d" hx-swap-oob="outerHTML">%s</div>',
        $article->getId(),
        $twig->render('components/_article_card.html.twig', [
            'article' => $enrichedArticle,
        ])
    ),
    type: 'article-updated',
);
$hub->publish($update);
```

### htmx SSE Extension Configuration
```html
<!-- In base.html.twig or dashboard/index.html.twig -->
<script>
    // Configure Mercure URL (ensure it's accessible from browser)
    window.mercureUrl = "{{ mercure_url() }}";
</script>

<!-- Article feed with SSE extension -->
<div id="article-feed"
     hx-ext="sse"
     sse-connect="{{ mercure_url() }}?topic=/articles"
     sse-swap="article-created:afterbegin, article-updated"
     class="flex flex-col gap-3">
    ...
</div>
```

---

## Migration Path & Timeline

### Phase 1: Preparation (0.5 hours)
1. Ensure Mercure bundle is installed
2. Verify htmx 2.0.7 + SSE extension in `importmap.php`
3. Audit controller htmx header detection

### Phase 2: Dashboard & Search (2-3 hours)
1. Add `hx-get` to category tabs, unread toggle
2. Replace "Mark All Read" form with `hx-post`
3. Add live search to search page with `hx-trigger="keyup"`
4. Update controllers to detect htmx and return partials

### Phase 3: CRUD Pages (4-6 hours)
1. Create modal component for forms
2. Update Source/Alert/Digest pages to use modals
3. Update Create/Edit controllers to return form partials
4. Update Delete controllers to support DELETE + return row

### Phase 4: Mercure Integration (4-8 hours)
1. Set up Mercure publishers for article creation
2. Set up Mercure publishers for enrichment updates
3. Add `sse-connect` + `sse-swap` to dashboard article feed
4. Delete `mercure-updates.ts`
5. Test real-time updates

### Phase 5: Cleanup (1 hour)
1. Remove `mercure-updates.ts` import from `app.js`
2. Verify all interactions work
3. Test on mobile/slow networks

---

## Checklist for Implementation

### Dashboard
- [ ] Add `hx-get` to category tabs
- [ ] Add `hx-get` to unread/show-all toggle
- [ ] Replace "Mark All Read" form with `hx-post`
- [ ] Update `DashboardController` to detect htmx for filters
- [ ] Add `sse-connect` + `sse-swap` to article feed
- [ ] Delete `mercure-updates.ts`
- [ ] Test infinite scroll with filters
- [ ] Test Mark All Read via htmx
- [ ] Test Mercure new articles via SSE

### Search
- [ ] Add `hx-get` + `hx-trigger="keyup"` to search input
- [ ] Update `SearchController` to detect htmx
- [ ] Test live search

### Sources
- [ ] Create modal component for forms
- [ ] Update `source/index.html.twig` to use modal for Edit/Create
- [ ] Add `hx-delete` to delete button
- [ ] Update `CreateSourceController` to return table partial on htmx
- [ ] Update `EditSourceController` to return table partial on htmx
- [ ] Update `DeleteSourceController` to support DELETE + return row

### Alerts
- [ ] Duplicate Sources pattern for alerts

### Digests
- [ ] Duplicate Sources pattern for digests
- [ ] Add `hx-post` for "Run Now" button to update row

---

## Risk Assessment

### Low Risk
- Category/filter tabs: No data mutation
- Live search: Read-only operation
- Infinite scroll: Already implemented

### Medium Risk
- Modal forms: New interaction pattern; ensure form validation works
- Delete via htmx: Ensure CSRF tokens work with htmx

### High Risk
- Mercure integration: Depends on external server; test thoroughly
- Enrichment OOB swaps: Requires correct HTML structure in event data

**Mitigation**:
1. Implement in phases (dashboard first)
2. Test each feature in staging
3. Use htmx debugging: Add `hx-trigger="click"` to visible trigger for manual testing
4. Monitor browser console for errors

---

## Summary Table

| Page | Total Opportunities | Recommended | Skip | Effort | Value |
|------|-----|----------|------|--------|-------|
| Dashboard | 8 | 4 | 4 | Medium | High |
| Search | 1 | 1 | 0 | Trivial | Medium |
| Sources | 3 | 3 | 0 | Medium | Medium |
| Alerts | 3 | 3 | 0 | Medium | Medium |
| Digests | 5 | 4 | 1 | Medium | Medium |
| Notifications | 1 | 0 | 1 | - | Low |
| Settings | 0 | 0 | 0 | - | - |
| AI Stats | 1 | 0 | 1 | - | Low |
| **TOTAL** | **22** | **15** | **7** | **~12-16 hrs** | **High** |

---

## Conclusion

htmx is **already installed and ready**. This audit identifies **15 concrete opportunities** across the UI that would:

1. **Eliminate 3 full-page reloads on dashboard** (category filter, unread toggle, mark all)
2. **Eliminate 6+ page navigations in CRUD pages** (Source/Alert/Digest forms)
3. **Enable real-time Mercure integration** (new articles + enrichment updates) with zero custom EventSource code
4. **Reduce TypeScript by ~200 lines** (mercure-updates.ts)
5. **Improve perceived performance** (modal forms, live search, in-place updates)

**Estimated total effort**: 12-16 hours  
**High-priority items**: Dashboard filters + search (2-3 hours, high impact)  
**Medium-priority items**: CRUD modals + Mercure (8-12 hours, high value)

**Next steps**: Start with Phase 2 (Dashboard & Search) for quick wins, then Phase 3-4 for sustained value.
