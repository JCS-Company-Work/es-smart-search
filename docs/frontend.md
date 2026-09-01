# Front End

`assets/js/SmartSearch.js` is the browser entry point. It owns shared search state and creates the services below.

| Module           | Responsibility                                                 |
| ---------------- | -------------------------------------------------------------- |
| `SmartSearch.js` | Starts the search interface and holds shared state.            |
| `search.js`      | Calls the search endpoint and manages active requests.         |
| `display.js`     | Updates product cards, result counts, titles, and reset state. |
| `filters.js`     | Reads filter clicks and updates search state.                  |
| `url.js`         | Reads and writes the URL hash.                                 |
| `pagination.js`  | Splits parent cards into pages and builds page navigation.     |
| `loading.js`     | Shows and positions the loading overlay.                       |
| `events.js`      | Binds browser events.                                          |
| `helpers.js`     | Holds small shared browser helpers.                            |

## Shared State

`SmartSearch` owns the query, selected filters, current page, and pagination pages. Services receive the `SmartSearch` instance so they operate on the same state.

Do not create separate copies of query, filter, or page state inside a service.

## URLs

The site-wide search form sends a `textsearch` value to the results page. Smart Search uses the hash for its live state:

```text
#textsearch=grey%20marble&page=1
```

The hash is used by the live controls, page navigation, refresh, and browser Back/Forward behavior.

## Display Unit

The browser displays parent product cards. A parent remains visible if its own batch ID, or any child batch ID, is returned by the PHP search.

Pagination and customer-facing counts are based on parent cards, not individual batch records.
