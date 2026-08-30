export class SearchService {
  constructor(app) {
    // Explicitly save a reference to the main SmartSearch instance
    this.app = app;
  }

  async execute() {
    // No magic 'this'. It's crystal clear where 'state' is coming from.
    const query =
      this.app.state.query.length >= this.app.minimumQueryLength
        ? this.app.state.query
        : "";

    const hasFilters = Object.keys(this.app.state.filters).length > 0;

    if (!query && !hasFilters) {
      this.app.displayService.showAllProducts();
      this.app.loadingService.setLoading(false); // Explicitly passing the app context as an argument
      return;
    }

    // Handle abort controllers openly
    this.app.requestController?.abort();
    this.app.requestController = new AbortController();
    const currentController = this.app.requestController;

    this.app.loadingService.setLoading(true);

    const params = new URLSearchParams({
      q: query,
      filters: JSON.stringify(this.app.state.filters),
    });

    try {
      const response = await fetch(`${window.ESSS.endpoint}?${params}`, {
        signal: currentController.signal,
      });

      if (!response.ok) throw new Error(`Search failed: ${response.status}`);

      const data = await response.json();
      const visibleProductCount = this.app.displayService.renderResults(
        data.matches,
        data.ranking,
      );

      this.logSearch(data, params, response, visibleProductCount);
    } catch (error) {
      if (error.name !== "AbortError") console.error("Search failed:", error);
    } finally {
      if (
        this.app.requestController === currentController &&
        !currentController.signal.aborted
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
    return Boolean(this.state.query || Object.keys(this.state.filters).length);
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
