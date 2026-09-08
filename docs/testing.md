# Testing

## Isolated PHP Tests

PHPUnit tests are in `tests/php` and use Brain Monkey for isolated WordPress hook tests.

Install dependencies:

```sh
composer install
```

Run tests:

```sh
composer test
```

Current unit tests cover plugin registration, index rebuild hook registration,
normalisation, matcher scoring and filters, dynamic weight sanitisation, and
suggestions. WordPress-backed index construction, option persistence, reporting
validation, and most REST response behavior belong in the integration suite.

These tests do not load WordPress or connect to a database. Database-backed behaviour, including `Dictionary::rebuild()` and search reporting, belongs in the integration suite.

## WordPress Integration Tests

Integration tests are in `tests/integration` and run against the official WordPress test library and a disposable database. They are deliberately opt-in.

Before running them, install the WordPress test library and configure its `wp-tests-config.php` to use a dedicated database. Set `WP_TESTS_DIR` to that library and `ESSS_TEST_DB_NAME` to the same non-local database name.

```sh
export WP_TESTS_DIR=/path/to/wordpress-tests-lib
export ESSS_TEST_DB_NAME=emporio_search_test
composer test:integration
```

The command refuses to start unless `ESSS_INTEGRATION_TESTS=1` is set by the Composer script, `WP_TESTS_DIR` points to a WordPress test-library installation, and `ESSS_TEST_DB_NAME` names a non-local database. Never point the test configuration at the site's `local` database.

Current integration tests cover:

- `DictionaryIntegrationTest`: rebuilds the suggestion vocabulary from a published, in-stock `batch` post, metadata, and an effect taxonomy term;
- `SearchMatcherOptionsIntegrationTest`: verifies saved `wp_options` weights, canonical `size` scoring, and the `dimensions` filter alias.

The search-index builder also depends on WooCommerce products and ACF fields. Full
`SearchIndex::rebuild()` coverage should run in an environment where those
plugins are loaded into the WordPress test bootstrap.

## Tests To Add

Add focused PHP tests for `SearchMatcher` covering:

- exact field-weight ranking and matched-field reporting;
- ignored terms and strict exclusions such as `floor`, `wall`, `outdoor`, and dimensions;
- permitted fuzzy fields, edit-distance limits, short-word rejection, and the fuzzy score penalty;
- filter aliases, all-filter matching, and quantity bands such as `sqm-10-20` and `sqm-20+`;
- query normalisation, especially `60x60`, `60 x 60`, and `600x600`;
- canonical `size` weighting and the legacy `dimensions` filter alias;
- separate `category` and `effect` indexed fields;
- synonyms and misspellings, including `carrera` and `carrara`;
- single-word and multi-word AND matching with exact and fuzzy terms;
- reporting table creation and event validation.

## Browser Tests

Playwright tests are maintained with this plugin in `tests/e2e`; their artifacts are written to `test-results/playwright`.

Install the plugin's Node dependencies, then run the Smart Search browser coverage:

```sh
npm install
npm run test:e2e:smart-search
```

The default target is `https://emporiosurfaces.local`. Set `PLAYWRIGHT_BASE_URL` to run against another non-production environment. The tests stub Smart Search API responses so results and suggestions remain deterministic while exercising the rendered page and browser state.

Current browser coverage verifies:

- Smart Search renders an API match, updates the URL, and sends the completed-search report payload;
- zero-result searches display and apply a suggested replacement;
- size filters are sent with the canonical `size` key rather than `dimensions`;
- multiple active filters are sent together;
- a `null` fallback response does not cause a browser exception.
