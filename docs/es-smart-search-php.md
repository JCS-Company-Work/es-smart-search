# ES Smart Search PHP Classes

This document explains the PHP classes in `src/` and how they work together.

The plugin uses a small central boot class. Each feature class owns one area of work and registers its own WordPress hooks.

## How the plugin starts

The main plugin file, `es-smart-search.php`, does three things:

1. Defines the plugin settings and constants.
2. Loads Composer's autoloader.
3. Creates `Plugin` and calls `boot()`.

```text
es-smart-search.php
    -> Plugin::boot()
        -> Assets::register()
        -> Search::register()
        -> SearchIndex::register()
```

Constructors do not register hooks. The explicit `register()` calls make startup easy to see and prevent object creation from changing WordPress behavior unexpectedly.

## `Plugin`

`Plugin` is the central starting point for the plugin.

Its `boot()` method starts the three feature areas:

- `Assets`, which loads the front-end files;
- `Search`, which provides the search endpoint and handles the top-bar search hand-off; and
- `SearchIndex`, which clears the cached index when product data changes.

This class does not contain search rules. Its job is only to start the other classes.

## `Assets`

`Assets` controls the JavaScript and CSS used by Smart Search.

When WordPress prepares front-end files, it checks whether the current page is a category, `grouped-products.php`, or `batches.php` page. On those pages it loads:

- `assets/js/es-smart-search.js`; and
- `assets/css/es-smart-search.css`.

It also passes the REST endpoint and debug setting to the JavaScript as `window.ESSS`.

## `Search`

`Search` controls the search request and the route used by the browser.

### Top-bar search flow

The top-bar form sends a `textsearch` value to the batches page using `GET`:

```text
/collections/search-results/?textsearch=grey+marble
```

Before the page is rendered, `Search` converts this to the hash format already used by the on-page search:

```text
/collections/search-results/#textsearch=grey%20marble&page=1
```

This server redirect prevents the temporary query-string URL from appearing briefly in the browser.

`redirect_query_search()` checks the request, builds the clean URL, performs the redirect, and stops the original request.

### Search request flow

`register_routes()` adds the public REST route:

```text
GET /wp-json/emporio-search/v1/search
```

`esss_search()` then:

1. Reads the query and selected filters.
2. Gets the cached batch index.
3. Removes batches that fail the selected filters.
4. Scores the remaining batches.
5. Sorts them from strongest to weakest match.
6. Returns matching IDs, the count, and ranking details.

The detailed helper methods currently remain in this class so the search behavior is in one place while the feature is being developed.

## `SearchIndex`

`SearchIndex` keeps the cached index current.

It listens for changes that can affect search results:

- batch saves;
- ACF saves;
- metadata changes;
- taxonomy changes;
- stock changes; and
- deleted posts.

When one of these events affects a `batch` post, the class deletes the `ESSS_INDEX_TRANSIENT` transient. The next search then rebuilds the index with the latest product data.

Changes to unrelated post types are ignored, so they do not cause unnecessary index rebuilds.

## Testing the classes

PHPUnit tests are in `tests/php`.

Brain Monkey provides test versions of WordPress functions, allowing the hook registration and redirect decision to be tested without loading the full WordPress application.

Run the tests from the plugin folder:

```sh
composer test
```
