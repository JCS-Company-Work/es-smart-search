# Testing

## PHP Unit Tests

PHPUnit tests are in `tests/php` and use Brain Monkey for isolated WordPress hook tests.

Install dependencies:

```sh
composer install
```

Run tests:

```sh
composer test
```

Current tests cover registration, search responses, and index invalidation hooks.

## Tests To Add

Add focused PHP tests for `SearchMatcher` covering:

- exact field-weight ranking and matched-field reporting;
- ignored terms and strict exclusions such as `floor`, `wall`, `outdoor`, and dimensions;
- permitted fuzzy fields, edit-distance limits, short-word rejection, and the fuzzy score penalty;
- filter aliases, all-filter matching, and quantity bands such as `sqm-10-20` and `sqm-20+`;
- query normalisation, especially `60x60`, `60 x 60`, and `600x600`;
- synonyms and misspellings, including `carrera` and `carrara`;
- reporting table creation and event validation.

## Browser Tests

Use Playwright for the complete customer journey:

- top-bar search reaches the Smart Search results page;
- live search updates products, title, count, and URL;
- parent-card pagination works across screen sizes;
- Reset restores unfiltered products and counts;
- browser Back/Forward restores state;
- zero-result state is useful;
- completed text searches are reported once.
