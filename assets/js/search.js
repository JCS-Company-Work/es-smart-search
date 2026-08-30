/**
 * Perform a search based on the current query and filter state.
 * @returns {Promise<void>} Resolves when the search is complete.
 */
export async function search() {
  // Determine the query to use for the search, ensuring it meets the minimum length requirement
  const query =
    this.state.query.length >= this.minimumQueryLength ? this.state.query : "";

  // Determine if there are any active filters in the current state
  const hasFilters = Object.keys(this.state.filters).length > 0;

  // If there is no query and no filters, show all products and stop loading
  if (!query && !hasFilters) {
    // If debugging is enabled and there is a query that was skipped, log the skipped query and minimum length
    if (window.ESSS.debug && this.state.query) {
      console.info("[ES Smart Search] query skipped", {
        query: this.state.query,
        minimumQueryLength: this.minimumQueryLength,
      });
    }

    // If there is no query and no filters, show all products and stop loading
    this.showAllProducts();

    // Stop the loading indicator since there is no search to perform
    this.setLoading(false);

    // Exit the search function early as there is nothing to search for
    return;
  }

  // If there is an ongoing request, abort it before starting a new search
  this.requestController?.abort();

  // Create a new AbortController for the current search request
  this.requestController = new AbortController();

  // Store a reference to the current request controller for use in the fetch request
  const currentController = this.requestController;

  // Show the loading indicator while the search request is in progress
  this.setLoading(true);

  // Build the query parameters for the API request, including the search query and filters
  const params = new URLSearchParams({
    q: query,
    filters: JSON.stringify(this.state.filters),
  });

  // Perform the API request to the ESSS endpoint with the constructed parameters
  try {
    const response = await fetch(`${ESSS.endpoint}?${params}`, {
      signal: currentController.signal,
    });

    if (!response.ok) {
      throw new Error(`Smart search request failed: ${response.status}`);
    }

    // Parse the JSON response from the API
    const data = await response.json();

    // Render the search results and get the count of visible products
    const visibleProductCount = this.renderResults(data.matches, data.ranking);

    // Log the search query, parameters, response, and visible product count for debugging purposes
    logSearch.call(this, data, params, response, visibleProductCount);
  } catch (error) {
    // If the error is not an AbortError, log the error to the console
    if (error.name !== "AbortError") {
      console.error("Smart search failed:", error);
    }
  } finally {
    // If the current request controller is still the same and has not been aborted, stop the loading indicator
    if (
      this.requestController === currentController &&
      !currentController.signal.aborted
    ) {
      this.setLoading(false);
    }
  }
}

/**
 * Log the search query, parameters, response, and visible product count for debugging purposes.
 * @param {object} data The API response data.
 * @param {URLSearchParams} params The query parameters used in the request.
 * @param {Response} response The fetch response object.
 * @param {number} visibleProductCount The count of visible products after rendering.
 * @returns {void}
 */
function logSearch(data, params, response, visibleProductCount) {
  if (!window.ESSS.debug) {
    return;
  }

  const ranking = (data.ranking || []).map((result, position) => ({
    position: position + 1,
    id: result.id,
    score: result.score,
    matchedField: Object.entries(result.matched_fields || {})
      .map(([field, weight]) => `${field} (+${weight})`)
      .join(", "),
    matchedFilter: Object.values(result.matched_values || {}).join(", "),
  }));

  console.groupCollapsed(`[ES Smart Search] ${data.query || "(filters only)"}`);
  console.log("Query:", data.query);
  console.log("Filters:", this.state.filters);
  console.log("Request:", `${ESSS.endpoint}?${params}`);
  console.log("Index:", response.headers.get("X-ESSS-Index-Source"));
  console.log("Indexed batches:", response.headers.get("X-ESSS-Index-Count"));
  console.log("Matching products on page:", visibleProductCount);
  console.log("Matching batches:", data.count);
  console.table(ranking);
  console.groupEnd();
}
