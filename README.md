# SimpleAttribute

> **Alpha — v0.1.0.** This module is in early testing. The API may change before a stable release. `pw-find`, `pw-switch`, and `pw-case` are planned but not yet active. Feedback and bug reports are welcome.

A powerful template preprocessor for ProcessWire that extends HTML with custom attributes and template syntax. Write cleaner, more expressive templates using **pw-\*** attributes for loops, conditionals, includes, and variable output with filters — all compiled to optimized PHP with intelligent caching.

## Installation

Install as a standalone ProcessWire module:

1. Copy the `SimpleAttribute` folder to `/site/modules/`
2. Go to **Modules → Refresh** in the ProcessWire admin
3. Install **SimpleAttribute**

Once installed, `.attr.phtml` files are processed automatically — no extra configuration needed.

## Quick Access

```php
// The hook into $files->render() is registered automatically — just use .attr.phtml files

// Manual processing (if needed)
attribute('/absolute/path/to/file.attr.phtml'); // returns cached file path

// Module instance
wire()->simpleattribute->process($filename);

// Via module function
simpleattribute()->process($filename);
```

> **Dependency:** SimpleAttribute requires the [`rct567/dom-query`](https://github.com/rct567/dom-query) library. Run `composer require rct567/dom-query` in the site root before installing. If the library is missing, the module throws `\ProcessWire\WireException` on first use.

## Features

- **HTML Attribute Syntax:** Use `pw-repeat-me`, `pw-repeat`, `pw-if`, `pw-include` and more as HTML attributes
- **Template Variables:** Output variables with `{{ variable }}` and filter them with `{{ text<filter> }}`
- **40+ Filters:** Transform output with `truncate`, `upper`, `dollar`, `date`, and more
- **Smart Variable Parsing:** Dot notation, method calls, null coalescing, array/object access
- **Intelligent Caching:** File modification time-based cache invalidation
- **Automatic Processing:** Hooks into `$files->render()` and template compilation via SimpleWire
- **Security First:** Auto-escape variables by default
- **Fragment Caching:** Cache expensive template sections with `pw-cache` (two-layer: memory + database)
- **Hypermedia Attributes:** Declare HTMX behaviour declaratively with `pw-handle`

## File Extension

Only files with the `.attr.phtml` extension are processed:

```
detail.attr.phtml
product-card.attr.phtml
```

Regular `.php` and `.phtml` files are ignored. No directory configuration needed.

---

## Quick Start

### Basic Variable Output

```html
<h1>{{ page.title }}</h1>
<p>{{ page.summary<truncate=100> }}</p>
<time>{{ page.created<date=M j Y> }}</time>
```

### Simple Loop

```html
<!-- pw-repeat-me: repeats the element itself -->
<ul>
    <li pw-repeat-me="$items">
        <a href="{{ url }}">{{ title }}</a>
    </li>
</ul>

<!-- pw-repeat: repeats only the inner content -->
<ul pw-repeat="$items">
    <li><a href="{{ url }}">{{ title }}</a></li>
</ul>
```

### Conditional Display

```html
<div pw-if="page.numChildren">
    <p>This page has {{ page.numChildren }} children</p>
</div>
<div pw-else>
    <p>No child pages found</p>
</div>
```

### Include Partials

```html
<header pw-include="layouts/header"></header>
<main>
    <section pw-include="components/hero" pw-data="$heroData"></section>
</main>
<footer pw-include="layouts/footer"></footer>
```

---

## Variable Output

### Basic Syntax

```html
{{ variable }}              <!-- Simple variable -->
{{ name ?? "Guest" }}       <!-- Null coalescing -->
{{ 'Hello World' }}         <!-- String literal -->
{{ "Hello {$name}" }}       <!-- String with interpolation -->
{{ 123 }}                   <!-- Numbers -->
```

### Dot Notation

```html
<!-- ProcessWire API objects (automatic object access) -->
{{ page.title }}            <!-- = $page->title -->
{{ user.email }}            <!-- = $user->email -->
{{ config.debug }}          <!-- = $config->debug -->

<!-- Regular variables (automatic array access) -->
{{ data.name }}             <!-- = $data['name'] -->
{{ product.price }}         <!-- = $product['price'] -->
```

### Method Calls

```html
{{ user.getName() }}                            <!-- Simple method call -->
{{ page.children("limit=5") }}                 <!-- Method with arguments -->
{{ users.get("role=admin").first.title }}       <!-- Chained methods -->
{{ page.children().count }}                    <!-- Method then property -->
```

### Filters

Filters use angle-bracket syntax appended directly to the expression:

```html
{{ text<upper> }}                   <!-- Single filter -->
{{ text<truncate=100> }}            <!-- Filter with argument -->
{{ price<float=2, dollar> }}         <!-- Chain multiple filters (comma-separated) -->
{{ html<raw> }}                     <!-- Disable auto-escaping -->
{{ data<json> }}                    <!-- JSON encode -->
{{ date<date=Y-m-d> }}              <!-- Date formatting -->
```

---

## Quick Reference

### Variable Output

| Syntax | Description |
| --- | --- |
| `{{ name }}` | Auto-detect variable type |
| `{{ user.name }}` | Dot notation for properties / array keys |
| `{{ name ?? "Guest" }}` | Null coalescing fallback |
| `{{ user.getName() }}` | Method calls |
| `{{ page.children("limit=5") }}` | Method with arguments |

### Filters

| Syntax | Description |
| --- | --- |
| `{{ name<upper> }}` | Single filter |
| `{{ text<truncate=100> }}` | Filter with argument (`=` introduces the argument) |
| `{{ price<float=2, dollar> }}` | Multiple filters (comma-separated, space optional) |
| `{{ html<raw> }}` | Disable auto-escaping |
| `{{ data<json> }}` | JSON encode |
| `{{ date<date=Y-m-d> }}` | Date formatting |

### Conditionals

| Attribute | Usage |
| --- | --- |
| `pw-if` | `pw-if="status == 'active'"` |
| `pw-else` | `pw-else="status == 'pending'"` — acts as `elseif` when a value is given |
| `pw-else` | `pw-else` — acts as `else` when no value is given |
| `pw-switch` | `pw-switch="field"` *(coming soon — not yet active)* |
| `pw-case` | `pw-case="value"` *(coming soon — not yet active)* |

### Loops & Iteration

| Attribute | Usage |
| --- | --- |
| `pw-repeat-me` | `pw-repeat-me="$items"` — repeats the entire element |
| `pw-repeat` | `pw-repeat="$items"` — repeats inner content only |
| `pw-children` | `pw-children` — iterate page children |
| `pw-children` | `pw-children="template=basic-page"` — with selector |
| `pw-find` | `pw-find="template=product"` — find and iterate pages *(coming soon — not yet active)* |
| `pw-get` | `pw-get="id=123"` — render a single page |

### Includes & Rendering

| Attribute | Usage |
| --- | --- |
| `pw-include` | `pw-include="layouts/header"` — include template file (relative to `/site/templates/`) |
| `pw-import` | `pw-import="components/card"` — import and process file inline |
| `pw-cache` | `pw-cache="key" pw-duration="3600"` — cache fragment |
| `pw-handle` | `pw-handle="get=/path/, trigger=load, target=#el"` — declare HTMX behaviour |

---

## Available Filters

### Text Filters

| Filter | Description |
| --- | --- |
| `upper` | Uppercase |
| `lower` | Lowercase |
| `title` | Title Case |
| `sentence` | Sentence case |
| `truncate=n` | Limit length |
| `clean` | Trim whitespace |
| `reverse` | Reverse string |
| `shuffle` | Randomize characters |
| `wordwrap` | Wrap text |
| `linebreaks` | Convert newlines to `<br>` |

### Number Filters

| Filter | Description |
| --- | --- |
| `number` | Format number |
| `float=n` | Float with decimals |
| `integer` | Convert to int |
| `ceil` | Round up |
| `floor` | Round down |
| `dollar` | Format as USD ($1,234.56) |
| `money:CUR` | Format currency |

### Security Filters

| Filter | Description |
| --- | --- |
| `escape` | HTML escape (auto-applied by default) |
| `html` | Same as `escape` |
| `raw` | Disable auto-escaping |
| `slashes` | Add slashes |

### Format Filters

| Filter | Description |
| --- | --- |
| `date=format` | Date format (e.g. `date=Y-m-d`) |
| `json` | JSON encode |

> Filters can be chained with comma-separated syntax inside the angle brackets: `{{ text<clean, truncate=100, upper> }}`

---

## Loops & Iteration

### pw-repeat-me (Entire Element)

Wraps the element itself in the loop — the element is repeated once per item:

```html
<div pw-repeat-me="$products" class="product-card">
    <h3>{{ title }}</h3>
    <p>{{ description<truncate=100> }}</p>
    <span>{{ price<dollar> }}</span>
</div>
```

### pw-repeat (Inner Content)

Repeats only the inner content — the element itself is rendered once as a container:

```html
<ul pw-repeat="$items">
    <li><a href="{{ url }}">{{ title }}</a></li>
</ul>
```

### pw-children (Page Children)

```html
<ul pw-children>
    <li>{{ title }} ({{ numChildren }} items)</li>
</ul>

<!-- With selector -->
<ul pw-children="template=basic-page, limit=10">
    <li>{{ title }}</li>
</ul>
```

### pw-find (Find Pages) *(coming soon — not yet active)*

> `pw-find` is planned but not yet implemented. The attribute is accepted without error but has no effect in v0.1.0.

```html
<div pw-find="template=product, featured=1">
    <article>
        <h3>{{ title }}</h3>
        <p>{{ summary }}</p>
    </article>
</div>
```

### pw-get (Single Page)

Fetches one page with `$pages->get()` and renders the element's content in its context.
The element is skipped entirely if the page does not exist (id = 0):

```html
<div pw-get="id=1234">
    <h3>{{ title }}</h3>
    <p>{{ summary }}</p>
    <a href="{{ url }}">Read more</a>
</div>

<!-- Works with any selector -->
<div pw-get="template=settings, name=global-settings">
    <p>Site name: {{ site_name }}</p>
</div>
```

### Loop Control

```html
<!-- Skip items -->
<div pw-repeat-me="$items" pw-continue="status != 'active'">
    <p>{{ title }}</p>
</div>

<!-- Break loop -->
<div pw-repeat-me="$items" pw-break="index > 10">
    <p>{{ title }}</p>
</div>
```

---

## Conditionals

### pw-if / pw-else

There are only two conditional attributes. `pw-else` doubles as `elseif` when given a value:

```html
<div class="stock">
    <span pw-if="stock > 10">In Stock</span>
    <span pw-else="stock > 0">Only {{ stock }} left!</span>
    <span pw-else>Out of Stock</span>
</div>
```

The chain compiles to a single `if / elseif / else / endif` block. Bare `pw-else` is always last.

```html
<!-- Standalone pw-if (no else) -->
<div pw-if="page.numChildren > 0">
    <p>Has {{ page.numChildren }} children</p>
</div>

<!-- pw-if + pw-else -->
<div pw-if="user.isLoggedIn">
    <p>Welcome, {{ user.name }}</p>
</div>
<div pw-else>
    <p>Please log in</p>
</div>

<!-- pw-if + multiple pw-else="condition" + final pw-else -->
<span pw-if="status == 'active'">Active</span>
<span pw-else="status == 'pending'">Pending</span>
<span pw-else="status == 'suspended'">Suspended</span>
<span pw-else>Unknown</span>
```

> `pw-else` elements must be **immediate siblings** of `pw-if` or of each other. Any element
> between them breaks the chain. An orphan `pw-else` with no preceding `pw-if` is removed from
> the output entirely.

### Supported Condition Operators

| Keyword | Equivalent |
| --- | --- |
| `is` | `==` |
| `isnt` | `!=` |
| `like` | `===` |
| `diff` | `!==` |
| `and` | `&&` |
| `or` | `\|\|` |
| `not` | `!` |
| `defined` | `isset(...)` |
| `in` | `in_array(...)` |

---

## Includes & Rendering

### pw-include

Paths are relative to `/site/templates/`. Extension is optional — `.attr.phtml`, `.phtml`, then `.php` are tried in order.

```html
<!-- Include a file -->
<header pw-include="layouts/header"></header>

<!-- With data passed as a variable -->
<section pw-include="components/hero" pw-data="$heroData"></section>

<!-- Extension optional -->
<div pw-include="components/card"></div>  <!-- = templates/components/card.attr.phtml -->
<div pw-include="layouts/main.php"></div> <!-- = templates/layouts/main.php (explicit) -->
```

### pw-import (Inline Content)

```html
<!-- Imports, processes and embeds content directly in the DOM -->
<div pw-import="components/notification"></div>
```

---

## Fragment Caching

### pw-cache

Caches the rendered HTML of any element. Uses a **two-layer** strategy:

1. **In-memory** (`$_sw_mem`) — a per-request global array. If the same cache key appears more than once during a single request (e.g. a component inside a loop), the second hit is served entirely from memory with no database round-trip.
2. **ProcessWire WireCache** (database-backed) — persists the fragment across requests for the configured duration.

```html
<!-- Cache for 1 hour (3600 seconds) -->
<section pw-cache="product-list" pw-duration="3600">
    <div pw-repeat="$products">
        <h3>{{ title }}</h3>
        <p>{{ description }}</p>
    </div>
</section>

<!-- Use static, descriptive keys — they are evaluated at compile time -->
<div pw-cache="sidebar-about" pw-duration="1800">
    <!-- Expensive sidebar content -->
</div>
```

`pw-duration` is optional and defaults to `3600` seconds.

> **Cache keys are static strings.** The key value is extracted at compile time — `{{ page.id }}` and similar expressions inside a key do not evaluate at runtime. Use descriptive static keys (`hero-section`, `sidebar-about`). Per-page dynamic keys are planned for a future version.

---

## Hypermedia Attributes

### pw-handle

Declares HTMX behaviour on any element using a compact comma-separated `key=value` syntax. Each pair is compiled to the corresponding `hx-*` attribute — no PHP is emitted, the conversion happens at **compile time**.

```html
<div pw-handle="get=/customers/, trigger=load, target=#list"></div>
```

Compiles to:

```html
<div hx-get="/customers/" hx-trigger="load" hx-target="#list"></div>
```

#### Syntax

```
pw-handle="key=value, key=value, ..."
```

Every `key` maps to `hx-{key}`. Values may contain `/`, `#`, `-`, and `=` signs (the split happens only on `, ` between pairs, and on the first `=` within each pair).

#### Common attributes

| `pw-handle` key | HTMX attribute | Example value |
| --- | --- | --- |
| `get` | `hx-get` | `/customers/` |
| `post` | `hx-post` | `/orders/create` |
| `put` | `hx-put` | `/orders/42` |
| `delete` | `hx-delete` | `/orders/42` |
| `trigger` | `hx-trigger` | `load`, `click`, `revealed` |
| `target` | `hx-target` | `#drawerView`, `closest .card` |
| `swap` | `hx-swap` | `innerHTML`, `outerHTML`, `afterend` |
| `push-url` | `hx-push-url` | `true`, `/customers/` |
| `indicator` | `hx-indicator` | `#spinner` |
| `boost` | `hx-boost` | `true` |

#### Examples

```html
<!-- Load content into a drawer on page load -->
<div pw-handle="get=/customers/, trigger=load, target=#drawer, swap=innerHTML"></div>

<!-- Submit a form via POST and replace the form element -->
<form pw-handle="post=/orders/create, target=this, swap=outerHTML"></form>

<!-- Infinite scroll: load next page when element is revealed -->
<div pw-handle="get=/products/?page=2, trigger=revealed, target=#product-list, swap=beforeend"></div>

<!-- Boost all links on a nav to use HTMX transitions -->
<nav pw-handle="boost=true">
    <a href="/home">Home</a>
    <a href="/about">About</a>
</nav>

<!-- Delete with confirmation via hx-confirm -->
<button pw-handle="delete=/orders/42, target=#order-42, swap=outerHTML, confirm=Are you sure?">
    Delete
</button>
```

> `pw-handle` is driver-agnostic by design — the attribute name intentionally does not reference HTMX. A future driver system could compile to Alpine.js, Unpoly, or any other hypermedia library. For now, HTMX is the default and only driver.

---

## Advanced Features

### pw-with (Context Binding)

```html
<div pw-with="$page">
    <h1>{{ title }}</h1>
    <p>{{ summary }}</p>
    <time>{{ created<date> }}</time>
</div>
```

### Mixing with PHP

```html
<?php
$products = $pages->find("template=product, limit=10");
$featured = $products->filter("featured=1");
?>

<div pw-repeat-me="$featured" class="product">
    <h3>{{ title<upper> }}</h3>
    <p>{{ summary ?? "No description" }}</p>
    <?= $item->customMethod() ?>
    <span>{{ price<dollar> }}</span>
</div>
```

### Escaping Variables Inside Loops

```html
<div pw-repeat-me="$items">
    <!-- Prefix with backslash to escape from loop scope -->
    <p>Item: {{ title }} of {{ \total_items }}</p>
</div>
```

---

## ProcessWire API Variables

The following variables are automatically treated as **objects** (using `->` notation):

`page`, `pages`, `config`, `user`, `users`, `input`, `session`, `database`, `modules`, `templates`, `fields`, `sanitizer`, `datetime`, `files`, `mail`, `cache`, `log`, `permissions`, `roles`, `languages`

All other variables use **array access**: `{{ variable.key }}` → `$variable['key']`

---

## Configuration

Settings are managed in **Admin → Modules → SimpleAttribute**.

| Setting | Description | Default |
| --- | --- | --- |
| **Auto-escape Variables** | Automatically escape all variables for HTML output | Enabled |
| **Strip Attributes** | Remove `pw-*` attributes from final output | Enabled |
| **Debug Mode** | Enable compilation debugging and logging | Disabled |
| **Skip Files** | Files to skip during compilation (one per line) | `_init.php`, `_main.php`, etc. |

---

## Global Function

```php
// Manual processing (if needed)
$cachedPath = attribute('/path/to/file.attr.phtml');

// Or via the API variable directly
$cachedPath = wire()->simpleattribute->process('/path/to/file.attr.phtml');
```

Automatic processing via hooks is recommended — the `attribute()` function is for edge cases.

---

## Complete Examples

### Product Listing

```html
<?php
$products = $pages->find("template=product, limit=20, sort=-created");
?>

<div class="products-grid">
    <article pw-repeat-me="$products" class="product-card">
        <div pw-if="images.count" class="image">
            <img src="{{ images.first.url }}" alt="{{ title<escape> }}">
            <span pw-if="on_sale" class="badge">{{ discount_percent }}% OFF</span>
        </div>

        <div class="info">
            <h3>{{ title<truncate=50> }}</h3>
            <p>{{ description<truncate=100> }}</p>

            <div class="price">
                <span pw-if="on_sale" class="original">{{ regular_price<dollar> }}</span>
                <span class="current">{{ sale_price ?? regular_price<dollar> }}</span>
            </div>

            <div class="stock">
                <span pw-if="stock > 10" class="in-stock">In Stock</span>
                <span pw-else="stock > 0" class="low">Only {{ stock }} left!</span>
                <span pw-else class="out">Out of Stock</span>
            </div>

            <button pw-if="stock > 0">Add to Cart</button>
            <button pw-else disabled>Unavailable</button>
        </div>
    </article>
</div>
```

### Blog with Pagination

```html
<?php
$posts = $pages->find("template=blog-post, limit=10, sort=-created");
?>

<div class="blog-list">
    <article pw-repeat-me="$posts" class="post">
        <header>
            <h2><a href="{{ url }}">{{ title }}</a></h2>
            <time datetime="{{ created<date=c> }}">{{ created<date=M j Y> }}</time>
        </header>

        <div class="content">
            {{ summary<truncate=300> ?? body<truncate=300> }}
        </div>

        <footer>
            <a href="{{ url }}">Read More
                <span pw-if="comments.count">({{ comments.count }} comments)</span>
            </a>
        </footer>
    </article>
</div>

<div pw-if="$posts.count" class="pagination">
    {{ $posts->renderPager() }}
</div>
```

### Integration with SimpleRouter + SimpleResponse

```php
// Route handler
$page->route("get:products/{id}", function($id) {
    $product = pages()->get($id);
    return response()->view('products/detail', ['product' => $product]);
});
```

```html
<!-- templates/views/products/detail.attr.phtml -->
<article>
    <h1>{{ product.title }}</h1>
    <div class="content">{{ product.body<raw> }}</div>
    <span class="price">{{ product.price<dollar> }}</span>
</article>
```

---

## Best Practices

### Performance

- Use `pw-cache` for expensive operations (database queries, API calls)
- Cache duration is in seconds (`3600` = 1 hour, `86400` = 1 day)
- Use unique, descriptive static keys: `pw-cache="products-listing"`, `pw-cache="sidebar-about"` (keys are compile-time strings — expressions like `{{ page.id }}` do not evaluate inside them)
- The same cache key encountered multiple times in one request is served from memory — no extra DB hits
- Compiled templates are automatically cached based on file modification time

### Security

- Variables are auto-escaped by default
- Only use `<raw>` filter for trusted/pre-sanitized content
- Always escape user input: `{{ input.post.name<escape> }}`

### Code Organization

- Use PHP for complex logic and data preparation
- Use `.attr.phtml` files for display and simple loops
- Keep components small and reusable
- Organize files in logical folders (`layouts/`, `components/`, `partials/`)

### Debugging

- Enable **Debug Mode** in module settings for detailed logs
- Check `/site/assets/logs/attribute-debug.txt`
- Use `{{ variable<json> }}` to inspect data structures
- Clear cache when making changes: delete `/site/assets/cache/SimpleWire/Attribute/`

---

## Troubleshooting

### Templates Not Processing

- Verify the file uses the `.attr.phtml` extension
- Check the file is not in the **Skip Files** list
- Enable Debug Mode and check logs
- Clear cache: delete `/site/assets/cache/SimpleWire/Attribute/`

### Variables Not Displaying

- Verify variable exists: `{{ variable ?? "NOT SET" }}`
- Use `{{ variable<json> }}` to inspect the data
- API variables (`page`, `pages`, `user`, etc.) use object notation automatically
- Regular variables use array notation with dot syntax

### Cache Not Updating

- Clear the Attribute cache: `/site/assets/cache/SimpleWire/Attribute/`
- Clear ProcessWire FileCompiler cache via **Setup → Files → Compiler**
- Check file modification time is updating correctly

### Syntax Errors After Compilation

- Enable Debug Mode to see compilation process
- Check for unclosed tags or malformed attributes
- Verify filter syntax: `{{ var<filter:arg> }}`
- Look for conflicting `pw-*` attributes on the same element

---

## Migration Guide

### From Plain PHP

```html
<!-- Before -->
<?php foreach ($products as $product): ?>
    <div class="product">
        <h3><?= $product->title ?></h3>
        <p><?= htmlspecialchars(substr($product->description, 0, 100)) ?></p>
    </div>
<?php endforeach; ?>

<!-- After -->
<div pw-repeat-me="$products" class="product">
    <h3>{{ title }}</h3>
    <p>{{ description<truncate=100> }}</p>
</div>
```

### From Template Engines (Twig, Blade)

```html
<!-- Twig/Blade -->
@foreach ($products as $product)
    <div>{{ product.title }}</div>
@endforeach

<!-- Attribute -->
<div pw-repeat-me="$products">{{ title }}</div>
```

| Feature | Twig/Blade | Attribute |
| --- | --- | --- |
| Loops | `@foreach` / `{% for %}` | `pw-repeat-me` / `pw-repeat` |
| Conditionals | `@if` / `@elseif` / `@else` | `pw-if` + `pw-else` (optional condition) |
| Variables | `{{ var }}` | `{{ var }}` (same) |
| Filters | `{{ var\|filter }}` | `{{ var<filter> }}` |
| Includes | `@include` / `{% include %}` | `pw-include` attribute |
| Escaping | Manual with filters | Auto-escape by default |
