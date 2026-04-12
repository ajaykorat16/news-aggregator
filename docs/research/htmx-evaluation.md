# htmx Evaluation for News Aggregator

**Date**: 2026-04-06
**Context**: Symfony 8 + FrankenPHP, vanilla TypeScript (6 modules), Twig templates, DaisyUI/Tailwind CSS. No frontend framework. Considering Mercure (SSE) for real-time push + htmx for partial page updates.

## htmx Overview

htmx is a ~14 KB (gzipped) JavaScript library that lets you issue AJAX requests, handle WebSockets, and subscribe to Server-Sent Events directly from HTML attributes -- no custom JavaScript required.

**Core concept**: Instead of returning JSON from endpoints, you return rendered HTML fragments. htmx swaps them into the DOM.

```html
<!-- Clicking this button GETs /articles?page=2 and appends the response to #article-feed -->
<button hx-get="/articles?page=2"
        hx-target="#article-feed"
        hx-swap="beforeend">
    Load More
</button>
```

**Bundle sizes** (gzipped):
| Library | Size |
|---------|------|
| htmx core | ~14 KB |
| htmx SSE extension | ~2 KB |
| **Total** | **~16 KB** |
| Turbo + Stimulus (for comparison) | ~45 KB |

The app currently ships ~0 KB of framework JS (just 6 small vanilla TS modules). Adding htmx would be the first library dependency.

## Symfony Integration

### No Official Bundle Required

There is no `symfony/ux-htmx` package. htmx does not need one -- it is backend-agnostic by design. You include a `<script>` tag (or install via npm/importmap) and add HTML attributes to your Twig templates. That said, community bundles exist:

- **seaworn/symfony-htmx-bundle** -- Provides `HtmxRequest` and `HtmxResponse` classes. Lets you detect `HX-Request` headers and set htmx-specific response headers (`HX-Trigger`, `HX-Redirect`, etc.) cleanly in controllers.
- **tomcri/htmxfony** -- Similar SDK approach.

Neither is required. The integration is simple enough to do manually:

```php
// In a controller -- detect htmx request, return partial
#[Route('/dashboard', name: 'app_dashboard')]
public function index(Request $request): Response
{
    $articles = $this->articleRepository->findLatest(page: $request->query->getInt('page', 1));

    // htmx sends this header on every request
    if ($request->headers->has('HX-Request')) {
        return $this->render('dashboard/_article_list.html.twig', [
            'articles' => $articles,
        ]);
    }

    return $this->render('dashboard/index.html.twig', [
        'articles' => $articles,
        // ... stats, categories, etc.
    ]);
}
```

This pattern is **already half-implemented** in this app. The `infinite-scroll.ts` module fetches `?page=N&_fragment=1` with `X-Requested-With: XMLHttpRequest` and inserts raw HTML. htmx would replace that entire 93-line TypeScript file with two HTML attributes.

### Twig Template Pattern

The existing `_article_list.html.twig` partial and `_article_card.html.twig` component are already structured for fragment rendering. htmx would consume them directly with zero template changes.

## htmx SSE Extension + Mercure

### How It Works

htmx has a first-party SSE extension (`htmx-ext-sse`). It connects to any SSE endpoint and swaps incoming event data as HTML into the DOM:

```html
<div hx-ext="sse"
     sse-connect="/.well-known/mercure?topic=articles"
     sse-swap="article-created">

    <!-- When Mercure publishes an event named "article-created",
         htmx swaps the event's data (HTML) into this div -->
    <div id="article-feed" class="flex flex-col gap-3">
        {% for article in articles %}
            {% include 'components/_article_card.html.twig' %}
        {% endfor %}
    </div>
</div>
```

### Mercure Compatibility

Mercure is built on SSE, so htmx's SSE extension connects to a Mercure hub natively. The key consideration is **what Mercure publishes as event data**:

**Option A -- Mercure publishes rendered HTML** (recommended with htmx):
```php
// In a Symfony Messenger handler, after article creation/enrichment
$update = new Update(
    topics: ['articles'],
    data: $this->twig->render('components/_article_card.html.twig', [
        'article' => $article,
    ]),
    type: 'article-created', // becomes the SSE event name
);
$hub->publish($update);
```

```html
<!-- Client side: htmx swaps the rendered card into the feed -->
<div id="article-feed"
     hx-ext="sse"
     sse-connect="/.well-known/mercure?topic=articles"
     sse-swap="article-created"
     hx-swap="afterbegin">
    <!-- new cards appear at the top automatically -->
</div>
```

**Option B -- Mercure publishes JSON, htmx triggers a re-fetch**:
```html
<!-- On receiving the SSE event, htmx GETs the latest articles -->
<div hx-ext="sse"
     sse-connect="/.well-known/mercure?topic=articles"
     hx-trigger="sse:article-created"
     hx-get="/dashboard?_fragment=1&limit=1"
     hx-target="#article-feed"
     hx-swap="afterbegin">
</div>
```

Option A is simpler and eliminates the extra HTTP round-trip.

### In-Place Enrichment Updates

When an article's enrichment completes (category, summary, keywords added), Mercure can push the updated card HTML. htmx can target a specific article card by ID:

```html
<!-- Each article card has an ID -->
<div id="article-{{ article.id }}" class="card ...">
    ...
</div>
```

```php
// After enrichment completes, publish the updated card
$update = new Update(
    topics: ['articles'],
    data: $this->twig->render('components/_article_card.html.twig', [
        'article' => $enrichedArticle,
    ]),
    type: 'article-updated',
);
```

htmx can use `hx-swap="outerHTML"` with an out-of-band swap to replace the specific card:

```html
<!-- The Mercure event data includes the OOB attribute -->
<div id="article-42" hx-swap-oob="outerHTML" class="card ...">
    <!-- full updated card with category badge, summary, keywords -->
</div>
```

This is the **killer feature** for this use case: zero custom JavaScript for in-place enrichment updates.

## Dashboard Use Case Analysis

### Current State (vanilla TypeScript)

| Feature | Implementation | Lines of TS |
|---------|---------------|-------------|
| Infinite scroll | `infinite-scroll.ts` -- IntersectionObserver + fetch + insertAdjacentHTML | 93 |
| Mark as read (scroll) | `mark-as-read.ts` -- IntersectionObserver + dwell timer + fetch | 102 |
| Article filter | `article-filter.ts` -- client-side text filter | 33 |
| Language selector | `language-selector.ts` -- client-side JSON swap | 129 |
| Theme toggle | `theme-toggle.ts` | small |
| Timeago | `timeago.ts` | small |

**Total**: ~400 lines of custom TypeScript across 6 modules.

### With htmx

| Feature | htmx approach | TS needed? |
|---------|--------------|------------|
| Infinite scroll | `hx-get` + `hx-trigger="revealed"` + `hx-swap="afterbegin"` on sentinel | **No** -- 0 lines, 2 attributes |
| Mark as read (scroll) | Keep existing TS -- htmx does not help here (dwell timer + IntersectionObserver is custom UX logic) | **Yes** -- keep as-is |
| Article filter | `hx-get="/dashboard?q=..."` + `hx-trigger="keyup changed delay:200ms"` for server-side search, OR keep client-side filter | **Optional** -- could move to server |
| Language selector | Keep existing TS -- client-side JSON swap is more efficient than re-rendering | **Yes** -- keep as-is |
| New articles (Mercure) | `sse-connect` + `sse-swap` | **No** -- 0 lines |
| Enrichment updates (Mercure) | `sse-connect` + OOB swap | **No** -- 0 lines |

**Result**: htmx would eliminate `infinite-scroll.ts` entirely (93 lines) and make the Mercure real-time features achievable with zero additional TypeScript. The mark-as-read and language-selector modules stay -- they handle client-side-only logic that htmx is not designed for.

### Infinite Scroll with htmx (concrete example)

Replace the entire `infinite-scroll.ts` with:

```html
{# In dashboard/index.html.twig -- replace the pagination loader #}
<div id="article-feed" class="flex flex-col gap-3">
    {% for article in articles %}
        {% include 'components/_article_card.html.twig' %}
    {% endfor %}
</div>

{# This div loads the next page when it scrolls into view #}
<div hx-get="{{ path('app_dashboard', {category: currentCategory, unreadOnly: unreadOnly, page: currentPage + 1}) }}"
     hx-trigger="revealed"
     hx-target="#article-feed"
     hx-swap="beforeend"
     hx-indicator="#scroll-spinner">
</div>
<span id="scroll-spinner" class="loading loading-spinner htmx-indicator"></span>
```

The controller returns `_article_list.html.twig` when it detects `HX-Request`. The last page returns an empty response, and htmx naturally stops (no content to swap).

## Complexity Impact

### Pros

1. **Eliminates custom fetch/DOM code** -- Infinite scroll goes from 93 lines of TS to 3 HTML attributes. Real-time Mercure integration goes from "write EventSource + DOM manipulation" to `sse-connect` + `sse-swap`.
2. **Server-rendered partials** -- The app already has `_article_card.html.twig` and `_article_list.html.twig`. htmx consumes them directly. No new template layer.
3. **Tiny bundle** -- 14 KB gzipped for core, ~16 KB with SSE extension. Smaller than adding Turbo + Stimulus (~45 KB).
4. **No build pipeline dependency** -- htmx can be loaded via `<script>` tag or Symfony AssetMapper. Works alongside the existing Bun/TS setup without conflict.
5. **Progressive enhancement** -- Links and forms still work without JS. htmx enhances them.
6. **Backend-agnostic** -- No lock-in to Symfony UX ecosystem. No version coupling with Symfony releases.
7. **SymfonyLive 2025 featured htmx** -- Growing community adoption in the Symfony ecosystem (JoliCode demo, SymfonyLive Paris 2025 talk).

### Cons

1. **Another dependency** -- The app currently has zero frontend library dependencies. htmx would be the first.
2. **Two interaction models** -- Some features stay as vanilla TS (mark-as-read, language selector), while others use htmx. Developers need to know when to use which.
3. **Server load for partials** -- Every htmx interaction is a server round-trip. For the dashboard's use case (fetching articles, receiving SSE), this is fine. For highly interactive UIs, it adds latency.
4. **No official Symfony bundle** -- Community bundles exist but are not deeply maintained. The integration is simple enough that this is a minor concern.
5. **Learning curve** -- Modest. The attribute-based API is intuitive, but `hx-swap` modes, OOB swaps, and extension configuration have nuances.

## htmx vs Turbo for This App

| Criterion | htmx | Symfony UX Turbo |
|-----------|------|-----------------|
| Bundle size | ~14 KB | ~45 KB (Turbo + Stimulus) |
| SSE/Mercure | First-party SSE extension, works with Mercure directly | Turbo Streams over Mercure (requires `symfony/ux-turbo-mercure`) |
| Learning curve | Lower -- HTML attributes, no frame/stream concepts | Higher -- frames, streams, drive, morphing |
| Symfony integration | Manual (trivial) or community bundle | Official `symfony/ux-turbo` package |
| Partial rendering | `hx-get` + `hx-target` -- explicit, fine-grained | `<turbo-frame>` -- convention-based |
| Already in project? | No | No |
| Ecosystem lock-in | None | Tied to Symfony UX release cycle |

**For this app**, htmx wins because:
- The app does not use Stimulus controllers. Adopting Turbo would pull in Stimulus as well, adding complexity for no benefit.
- The SSE extension is simpler than Turbo Streams for the "push rendered HTML on article creation" use case.
- The app already has vanilla TS modules that will remain. htmx coexists more naturally with vanilla TS than Turbo does.

## Recommendation

**Use both htmx and Mercure together.**

### Implementation plan

1. **Add htmx** (~14 KB) via AssetMapper or `<script>` tag. Add the SSE extension (~2 KB).
2. **Replace `infinite-scroll.ts`** with `hx-get` + `hx-trigger="revealed"`. Delete the file.
3. **Add Mercure** (`symfony/mercure-bundle`) for real-time push.
4. **Wire SSE to dashboard** -- `sse-connect` to Mercure hub, `sse-swap` for new articles (prepend) and enrichment updates (OOB outerHTML swap).
5. **Keep** `mark-as-read.ts`, `language-selector.ts`, `article-filter.ts`, `theme-toggle.ts`, `timeago.ts` -- these handle client-only logic where htmx adds no value.
6. **Optionally** convert `article-filter.ts` to server-side search via `hx-get` with debounced `hx-trigger` (only worthwhile if the SEAL search index should be used instead of client-side text matching).

### What NOT to do

- Do not adopt Turbo/Stimulus -- it would be a larger footprint for no additional benefit given the existing vanilla TS approach.
- Do not rewrite `mark-as-read.ts` or `language-selector.ts` with htmx -- they are client-side-only logic that htmx cannot simplify.
- Do not use htmx for the language selector (it would cause unnecessary server round-trips for what is currently an instant client-side swap).

### Expected outcome

- **~93 lines of TypeScript deleted** (infinite-scroll.ts)
- **~0 lines of TypeScript written** for Mercure real-time features (new articles + enrichment updates)
- **~16 KB added** to page weight (htmx + SSE extension)
- Real-time dashboard updates with no custom EventSource code
- In-place enrichment updates (category badge, summary, keywords appear on cards) via OOB swaps
