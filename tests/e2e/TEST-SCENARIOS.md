Use Playwright Codegen to create executable tests from these scenarios.

## 1. Text Search

- Open `/collections/search-results/`.
- Enter `marble`.
- Confirm results update.
- Confirm the URL contains `#textsearch=marble&page=1`.
- Confirm visible product cards are reduced.
- Confirm a `POST` request is sent to `/es-smart-search/v1/report`.
- Confirm the report payload includes:
  - `query_raw: "marble"`
  - `query_normalised: "marble"`
  - `has_results: 1`

## 2. Fuzzy Search

- Search for `carrera`.
- Confirm exact `carrera` products appear.
- Confirm fuzzy `carrara` products also appear.
- Confirm the result count includes both exact and fuzzy matches.
- Confirm the fuzzy result reports a `matched_fields` key ending in `_fuzzy`.

## 3. Multi-Word AND Search

- Search for `grey marble`.
- Confirm every visible result matches both terms exactly or fuzzily.
- Confirm a product matching only `grey` is hidden.
- Confirm a product matching only `marble` is hidden.

## 4. Size Filter

- Open the Size or Format filter.
- Select `60 x 60mm`.
- Confirm a search request is sent.
- Inspect its `filters` query parameter.
- Confirm it contains:

```json
{
  "size": ["60x60"]
}
```

- Confirm it does not contain `dimensions`.
- Confirm matching product cards remain visible.

## 5. Combined Filters

- Select a size filter.
- Select a colour filter.
- Confirm the request contains both active filters:

```json
{
  "size": ["60x60"],
  "colour": ["grey"]
}
```

- Confirm the URL hash contains both filters.
- Confirm results satisfy both filters.

## 6. Category Filter

- Select a Category filter such as `Marble`.
- Confirm the request contains:

```json
{
  "category": ["marble effect"]
}
```

- Confirm it is not renamed to `effect`.
- Confirm matching category results remain visible.

## 7. Reset

- Apply a text search and one or more filters.
- Click Reset.
- Confirm the search input is empty.
- Confirm filter controls are inactive.
- Confirm the URL returns to the unfiltered state.
- Confirm the original product cards are visible.
- Confirm pagination resets.

## 8. Null Fallback Regression

Intercept the search response and return:

```json
{
  "query": "unknown-term",
  "matches": [],
  "count": 0,
  "ranking": [],
  "suggestion": null,
  "fallback": null
}
```

- Enter `unknown-term`.
- Confirm no `pageerror` occurs.
- Confirm the search does not crash while reading `fallback.type`.

## 9. Request Cancellation

- Type one search term.
- Immediately replace it with another before the first request completes.
- Confirm the first request is aborted.
- Confirm only the second response controls the visible results.
