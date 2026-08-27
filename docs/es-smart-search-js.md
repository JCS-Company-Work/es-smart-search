# ES Smart Search JavaScript

This document explains how `assets/js/es-smart-search.js` works. The file contains one class, `ESSmartSearch`, which controls searching and filtering on the product results page.

The class does not decide which products match. It sends the search to the plugin's PHP REST endpoint, then displays the response.

## The complete flow

```text
Page loads
    -> Find the search controls and product list
    -> Read any search or filter values from the URL
    -> Show those values in the page
    -> Search if a query or filter is already active

Customer changes the search
    -> Update the current state
    -> Update the URL
    -> Wait briefly for typing to stop
    -> Ask the PHP endpoint for matching products
    -> Show the returned products
```

## Stage 1: The page loads

The file waits for the page to finish loading, then creates an `ESSmartSearch` object and calls `init()`.

`init()` finds the parts of the page it needs:

- `.live-filter`: the search input;
- `.product-list`: the list of product cards;
- `.mixitup-page-stats`: the result count;
- `.no-results`: the message shown when nothing matches; and
- `#reset-filters`: the button that clears the search.

If the search input, product list, or `window.ESSS` configuration is missing, the class stops. This allows the script to be loaded safely on pages that do not contain the search interface.

The class then remembers the original order of the product cards. This is used later when the customer clears the search.

## Stage 2: The starting search is restored

`readUrlState()` checks the current URL.

There are two ways to arrive at the results page:

- The top site-wide search uses `?q=grey+marble`.
- The search and filter controls on the results page use the URL hash, such as `#textsearch=grey%20marble&colour=grey&page=1`.

The class converts either format into one internal state:

```javascript
{
  query: "grey marble",
  filters: {
    colour: ["grey"]
  },
  page: 1
}
```

Once the state has been restored, `renderFilterState()` puts the query into the search input and marks the selected filter buttons as active.

If a query or filter is already present, `init()` immediately starts a search so the page displays the correct products.

## Stage 3: The page is connected to user actions

`bindEvents()` listens for changes to the search interface.

### Typing in the search box

When the customer types:

1. The current query is read from the input.
2. The page number is reset to page 1.
3. The new state is written to the URL.
4. The reset button is updated.
5. A search is scheduled.

The search is delayed by 300 milliseconds. This prevents a request being sent for every individual key press.

### Clicking a filter

When a filter is clicked:

1. The filter button is marked active or inactive.
2. All active values in that filter group are collected.
3. The current filter state is updated.
4. Empty filter groups are removed.
5. The page number is reset to page 1.
6. The URL is updated.
7. A new search starts immediately.

### Clicking reset

The reset button clears the query and filters, updates the input and buttons, removes the active state, and restores every product card to its original order.

### Using browser navigation

When the customer uses the browser Back or Forward button, the class reads the URL again, updates the controls, and searches using the restored state.

### Scrolling or resizing

The loading indicator is repositioned when the page is scrolled or the browser window is resized. This keeps it over the visible product area.

## Stage 4: A search request is made

`search()` prepares the request for the current state.

Queries shorter than three characters are not sent to the server. If there are no filters either, all products are shown instead.

For a valid search, the class sends:

```text
GET /wp-json/emporio-search/v1/search?q=grey%20marble&filters={...}
```

The request includes:

- the text query; and
- the selected filters as JSON.

While waiting, the loading indicator is shown and the search input is marked as busy.

If the customer starts another search before the first one finishes, the earlier request is cancelled. This prevents an older response from replacing a newer one.

## Stage 5: The response is displayed

When the PHP endpoint responds, `search()` passes the returned IDs and ranking information to `renderResults()`.

`renderResults()`:

1. Creates a list of matching product IDs.
2. Checks each product card and any batch cards inside it.
3. Hides cards that do not match.
4. Keeps matching cards visible.
5. Reorders cards according to the ranking from the server.
6. Updates the visible product count.
7. Shows or hides the no-results message.

The server returns batch matches, while the page may display grouped product cards. That is why the browser calculates its own visible-card count instead of relying only on the server count.

## Stage 6: The URL remains shareable

`writeUrlState()` stores the current in-page search state in the URL hash. This means a customer can refresh the page, use browser navigation, or share the URL without losing the search and filters.

The top-bar `q` parameter is kept in the page URL when the first search is opened. Once the customer changes the in-page controls, the current state is also represented in the hash.

## Stage 7: The search is cleared

`showAllProducts()` restores the original product order, removes the hidden class from every card, hides the no-results message, and restores the normal count display.

`reset()` calls this after clearing the internal state and updating the controls.

## Loading behavior

The class creates one loading element in `createLoadingIndicator()`.

`setLoading()` controls whether it is visible and sets `aria-busy` on the search input. `positionLoading()` sizes the indicator over the visible product list without changing the product layout.

## Debug information

`logSearch()` only writes information when `window.ESSS.debug` is enabled. It records the query, filters, request, index information, result counts, and ranking details in the browser console.

This is developer information only. It is not the permanent search reporting required for production.

## Important page requirements

For the class to work, the results page must contain:

- one `.live-filter` input;
- one `.product-list` element;
- product cards inside the list with `data-id` values for their batch IDs;
- `window.ESSS.endpoint` containing the REST endpoint; and
- filter controls inside `fieldset[data-filter-group]` when filters are used.

The page may also provide the stats, no-results, and reset elements. Without those optional elements, searching still works, but the related visual feedback will not be available.
