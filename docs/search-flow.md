# Search Flow

## Site-Wide Search

```text
Top search form
  -> /collections/search-results/?textsearch=grey+marble
  -> Search redirects to the clean hash URL
  -> /collections/search-results/#textsearch=grey%20marble&page=1
  -> SmartSearch reads the hash and calls the search API
```

## Live Search

```text
Customer types in .live-filter
  -> SmartSearch updates state.query
  -> URL hash is updated
  -> Wait 300 ms
  -> SearchService calls the API
  -> DisplayService updates cards and counts
```

## Result Handling

```text
PHP returns matching batch IDs and ranking
  -> browser finds matching parent cards
  -> parent cards are ordered by ranking
  -> PaginationService creates pages from parent cards
  -> DisplayService shows the current page
```

## Reset

Reset clears query and filters, returns to page 1, restores the original product cards, and rebuilds the unfiltered pagination count.

## Important Rule

PHP ranks batch records. The browser groups the result into customer-facing parent cards. Keep both counts when diagnosing search quality.
