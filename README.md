# ES Smart Search Prototype

## Purpose

ES Smart Search is a WordPress plugin prototype for improving product discovery on Emporio Surfaces grouped product and batch listing pages. It replaces browser-only filtering with a server-backed search over in-stock `batch` products, so customers can find products using natural product terms alongside the existing filter controls.

The original problem is that valid customer searches can return too few, or no, products even when relevant stock exists. The prototype establishes the search and ranking foundation; it is not yet the complete production search and reporting solution.

## Where it runs

The plugin loads on category archives and the `grouped-products.php` and `batches.php` page templates. It expects the theme to provide:

- a `.live-filter` search input;
- a `.product-list` containing rendered product cards, with batch IDs in `data-id` attributes;
- optional `.mixitup-page-stats`, `.no-results`, and `#reset-filters` elements; and
- filter controls inside `fieldset[data-filter-group]`.

Search state is stored in the URL hash so filtered and searched views can be shared and restored with browser navigation.

## Current Prototype Behaviour

### Search index

- Indexes published `batch` posts that are in stock and have a stock quantity above zero.
- Builds a cached, structured index from WooCommerce product data, ACF fields, category/effect taxonomies, and derived usage labels.
- Invalidates the index when batch data, relevant metadata, terms, stock status, or batch posts change.
- Caches the index for up to 30 days, unless an invalidation event rebuilds it first.

### Matching and ranking

- Searches product and batch titles, colour, effect, category, finish, size/dimensions, usage, thickness, slip rating, factory, and product code.
- Gives greater ranking weight to product code, dimensions, usage, colour, effect, category, finish, title, and factory, in that order.
- Treats generic words such as `tile`, `tiles`, `porcelain`, `product`, and `products` as non-restrictive so they do not eliminate otherwise relevant results.
- Normalises case, accents, separators, whitespace, `x`/`by` dimension notation, and centimetre shorthand. For example, `60x60`, `60 x 60`, and indexed `600x600` values can resolve to the same dimension.
- Supports limited fuzzy matching for title, colour, effect, category, and factory terms: words with four or more characters may match an indexed word within Levenshtein distance two. This is intended to help with close misspellings such as `carrera` and `carrara` where the indexed product data supports it.
- Applies selected filter groups as exact structured constraints and combines them with text search.
- Derives `floor`, `wall`, and `outdoor` usage labels from controlled finish values.

### Results-page experience

- Sends live requests after a 300 ms input debounce for queries of at least three characters.
- Cancels a superseded request while the customer keeps typing.
- Reorders rendered product cards by API ranking and hides non-matches.
- Shows a loading indicator, an accessible busy state, a matching-product count, and the theme's existing no-results element.
- Restores all initially rendered products when search and filters are cleared.

### Diagnostics

When `ESSS.debug` is enabled, the browser console records the query, active filters, REST request, index source/count, displayed product count, matching batch count, and ranking rationale. This is developer diagnostics only; it is not customer-search reporting.

## REST Interface

`GET /wp-json/emporio-search/v1/search`

Parameters:

- `q`: optional free-text query.
- `filters`: optional JSON object whose keys are filter groups and whose values are selected filter values.

The response includes the normalised query, matching batch IDs, match count, and ranked result diagnostics. The endpoint currently permits public read access because it only exposes product IDs and matching metadata needed by the page.

## Required Production Outcomes

### Search reporting

The completed feature must persist each meaningful search event and make it reportable. At a minimum, retain:

- search term as entered and its normalised form;
- selected filters;
- result count returned by the search service and product-card count shown on the page;
- timestamp, page/template, and a non-identifying session or request identifier;
- zero-result status; and
- implementation/version metadata where useful for comparing ranking changes.

Reporting must allow the team to find frequent searches, searches producing no results, searches producing unexpectedly few results, and changes in result count over time. It must avoid storing personal data unless a separate privacy decision and retention policy approve it.

### Better matching

The ranking and synonym rules need to be evaluated against the real catalogue and extended until familiar shopper language maps to the relevant structured attributes. This includes:

- colour vocabulary and multi-word colours such as `dark grey` and `off white`;
- material/effect vocabulary, including marble;
- product/category intent such as `floor tiles`;
- dimensions entered with or without spacing and in millimetres or centimetres;
- factory/product-code searches such as `K greige`; and
- common spelling mistakes, including `carrera` for `carrara`.

Rules should favour precise structured matches where available, then broaden intentionally through maintained synonyms and bounded typo matching. Generic words must not artificially reduce a valid result set.

### Results-page clarity

The page must visibly state the active search term together with the result count, for example: `Results for "Grey porcelain tiles" (17 products)`. It should continue to make selected filters apparent and provide a clear reset action.

### Zero-result experience

When no products match, replace the bare no-results message with a useful recovery state. It should acknowledge the term searched, retain the search input, offer a reset action, and provide curated quick links to high-value browsing routes such as tiles by colour, marble-effect tiles, floor tiles, and the main product categories. The final destinations and copy require content-owner approval.

## Baseline Evaluation Cases

The following observations from the original brief are the starting evaluation set, not permanent expected counts. Counts depend on live stock and catalogue data, so automated tests should assert intended inclusion/exclusion and compare counts against current structured filters rather than hard-code these historic values.

| Search or filter       | Reported observation | Desired interpretation                                                                       |
| ---------------------- | -------------------: | -------------------------------------------------------------------------------------------- |
| `Grey porcelain tiles` |          17 products | Broad grey porcelain tile intent should not be weaker than the generic wording alone.        |
| `Grey tiles`           |          24 products | Generic `tiles` should not exclude valid grey products.                                      |
| `Grey marble`          |          42 products | Grey plus marble-effect intent should be recognised.                                         |
| `60x60`                |          11 products | Must match the full 60 x 60 catalogue.                                                       |
| `60 x 60`              |         204 products | Must be equivalent to `60x60`, subject to live stock.                                        |
| `White marble`         |          77 products | Should align with an equivalent white-and-marble structured filter result where appropriate. |
| White + marble filters |          60 products | Provides a structured-data comparison point.                                                 |
| `Floor tiles`          |          31 products | Should find tiles suitable for floor use.                                                    |
| `Dark grey`            |           0 products | Requires colour synonyms/compound-colour handling.                                           |
| `Off white`            |           0 products | Requires colour synonyms/compound-colour handling.                                           |
| `K greige`             |           0 products | Requires catalogue-data and product-code/factory matching review.                            |
| `Porcelain tiles`      |          49 products | Generic category/material wording should return the relevant catalogue.                      |
| `carrera`              |         not supplied | Should return relevant `carrara` products when present.                                      |

## Known Gaps

- Search events are not persisted or reportable; browser-console diagnostics are the only logging.
- The active text term is not rendered in the results-page summary.
- The no-results state is the existing theme message and has no curated quick links.
- Synonyms for compound colours and broader product-language mapping have not been implemented.
- Fuzzy matching is limited, is token based, and has not been validated against a maintained spelling/synonym dictionary.
- The current UI counts rendered product cards while the API returns matching batch records; these may differ where a card represents multiple batches. Reporting should retain both values.
- The current PHPUnit tests cover class hook registration only. The catalogue evaluation cases do not yet have automated regression tests.

## Suggested Delivery Sequence

1. Add persistent, privacy-reviewed search-event storage and an admin/export reporting view.
2. Expose the active term and improve the no-results recovery UI in the theme integration.
3. Define and implement maintained synonym, colour, material, and product-intent mappings.
4. Validate dimension normalisation, typo matching, and structured filter parity against a known catalogue snapshot.
5. Add automated API and browser regression tests for the baseline evaluation cases and newly discovered high-volume searches.

## Code Structure

The plugin uses Composer to load PHP classes from the `EsSmartSearch` namespace.

- `es-smart-search.php` is the WordPress plugin entry point. It defines plugin constants, loads Composer, and starts the plugin.
- `src/Plugin.php` is the central starting point. Its `boot()` method starts each plugin feature and keeps startup in one place.
- `src/Assets.php` loads the front-end JavaScript and stylesheet on supported pages.
- `src/Search.php` registers the REST route and contains the current search, filtering, indexing, and ranking code.
- `src/SearchIndex.php` owns the hooks that invalidate the cached search index when batch data changes.
- `assets/js/es-smart-search.js` manages the search page in the browser.
- `assets/css/es-smart-search.css` contains search-specific styles.

The plugin depends on WordPress, WooCommerce, Advanced Custom Fields (`get_fields`), the site's `batch` post type, and the category/effect taxonomies.

## Tests

PHPUnit tests live in `tests/php`. They use Brain Monkey to test WordPress hook registration without loading a full WordPress site.

The current tests cover the `Assets`, `Search`, and `SearchIndex` registration hooks. The `SearchIndex` tests also check that batch changes clear the cache while changes to other post types do not.

Install development dependencies after cloning the plugin:

```sh
composer install
```

Run the PHP test suite:

```sh
composer test
```

The `vendor` directory is not committed. It is generated by Composer and is required when running the plugin or its tests.
