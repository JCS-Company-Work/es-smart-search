export class SearchService {
  constructor(app) {
    // Explicitly save a reference to the main SmartSearch instance
    this.app = app;
  }

  /**
   * Execute a search request based on the current query and filters.
   * If the query is empty and no filters are applied, all products will be displayed.
   * The search results will be rendered, and the search query will be logged for debugging.
   * @returns {Promise<void>} A promise that resolves when the search is complete.
   */
  async execute() {
    // If query is three characters or more set the query, otherwise set it to an empty string
    const query =
      this.app.state.query.length >= this.app.minimumQueryLength
        ? this.app.state.query
        : "";

    // Check if there are any active filters
    const hasFilters = Object.keys(this.app.state.filters).length > 0;

    // If there is no query and no filters, show all products and exit early
    if (!this.app.state.query && !hasFilters) {
      this.app.displayService.showAllProducts();
      this.app.loadingService.setLoading(false);
      return;
    }

    // Cancel any ongoing search request and get a new abort controller for the current search
    const abortController = this.cancelActiveSearch();

    // Set the loading state to true while the search request is in progress
    this.app.loadingService.setLoading(true);

    // Prepare the query parameters for the search request
    const params = new URLSearchParams({
      q: query,
      filters: JSON.stringify(this.app.state.filters),
    });

    try {
      // Send the search request to the server with the current query and filters
      const response = await fetch(`${window.ESSS.endpoint}?${params}`, {
        signal: abortController.signal,
      });

      // Throw an error if the response is not successful
      if (!response.ok) throw new Error(`Search failed: ${response.status}`);

      // Parse the JSON response from the server
      const data = await response.json();

      // Render the search results and get the count of visible products
      const visibleProductCount = this.app.displayService.renderResults(
        data.matches,
        data.ranking,
      );

      // If visibleProductCount is zero, show suggestion links if there are any
      if (visibleProductCount === 0 && data.suggestion) {
        this.app.suggestionService.showSuggestion(data.suggestion);
      }

      // Update the URL state to reflect the current search query and filters
      this.app.urlService.writeUrlState();

      // Record the search query and results for reporting purposes
      this.app.reportingService.record(data, visibleProductCount);

      // Log the search details for debugging purposes
      this.logSearch(data, params, response, visibleProductCount);
    } catch (error) {
      // Handle errors that occur during the search request
      if (error.name !== "AbortError") console.error("Search failed:", error);
    } finally {
      // Reset the loading state to false if the current request is still active and not aborted
      if (
        this.abortController === abortController &&
        !abortController.signal.aborted
      ) {
        this.app.loadingService.setLoading(false);
      }
    }
  }

  /**
   * Check if there is an active search query or any active filters.
   *
   * @returns {boolean} True if a query or filter is active, false otherwise.
   */
  hasActiveState() {
    return Boolean(
      this.app.state.query || Object.keys(this.app.state.filters).length,
    );
  }

  /**
   * Cancel any ongoing search request by aborting the current AbortController and creating a new one.
   * @returns {AbortController} The new abort controller for the active search.
   */
  cancelActiveSearch() {
    // Abort the current active search request if it exists.
    this.abortController?.abort();

    // Create a new abort controller for the next active search request.
    this.abortController = new AbortController();

    // Return the new abort controller for the active search.
    return this.abortController;
  }

  /**
   * Log the search query, parameters, response, and visible product count for debugging purposes.
   * @param {object} data The API response data.
   * @param {URLSearchParams} params The query parameters used in the request.
   * @param {Response} response The fetch response object.
   * @param {number} visibleProductCount The count of visible products after rendering.
   * @returns {void}
   */
  logSearch(data, params, response, visibleProductCount) {
    // If debugging is not enabled, exit early without logging.
    if (!window.ESSS.debug) return;

    // Prepare the ranking data for logging, including position, ID, score, matched fields, and matched filters.
    const ranking = (data.ranking || []).map((result, position) => ({
      position: position + 1,
      id: result.id,
      score: result.score,
      matchedField: Object.entries(result.matched_fields || {})
        .map(([field, weight]) => `${field} (+${weight})`)
        .join(", "),
      matchedFilter: Object.values(result.matched_values || {}).join(", "),
    }));

    // Log the search details in a collapsed console group for better organization.
    console.groupCollapsed(
      `[ES Smart Search] ${data.query || "(filters only)"}`,
    );
    console.log("Query:", data.query);
    console.log("Filters:", this.app.state.filters);
    console.log("Request:", `${ESSS.endpoint}?${params}`);
    console.log("Index:", response.headers.get("X-ESSS-Index-Source"));
    console.log("Indexed batches:", response.headers.get("X-ESSS-Index-Count"));
    console.log("Matching products on page:", visibleProductCount);
    console.log("Matching batches:", data.count);
    console.table(ranking);
    console.groupEnd();
  }
}
