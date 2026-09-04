export class DisplayService {
  constructor(app) {
    // Explicitly save a reference to the main SmartSearch instance
    this.app = app;
  }

  /**
   * Update the input value and filter button states to reflect the current search state.
   * This ensures that the UI accurately represents the active query and filters.
   */
  renderFilterState() {
    // Update the input value and active class based on the current query state
    if (this.app.input) {
      // Set the input value to the current query state
      this.app.input.value = this.app.state.query;
      this.updateQueryDisplay();

      // Toggle the "mixitup-control-active" class on the input based on whether there is a query
      this.app.input.classList.toggle(
        "mixitup-control-active",
        Boolean(this.app.state.query),
      );
    }

    // Update the active state of filter buttons based on the current filter state
    document
      .querySelectorAll("fieldset[data-filter-group] .control")
      .forEach((button) => {
        const group = button.closest("fieldset[data-filter-group]")?.dataset
          .filterGroup;
        const selected =
          this.app.state.filters[group]?.includes(button.dataset.toggle) ||
          false;
        button.classList.toggle("mixitup-control-active", selected);
      });
  }

  /**
   * Update the query display element with the current search query.
   * @returns {void}
   */
  updateQueryDisplay() {
    // If the query display element is not available, exit
    if (!this.app.queryDisplay) return;

    // Update the query display text based on the current search query
    this.app.queryDisplay.textContent = this.app.state.query
      ? `Search Results for "${this.app.state.query}"`
      : "";
  }

  /**
   * Dedicated coordinator to route zero-result screens based on active backend payloads.
   * @param {string} type The type of zero-result fallback.
   * @param {string[]} terms Flat array of successful trend queries from your logs.
   */
  handleZeroResultsFallback(type, terms = []) {
    // Clear out any stale element items from your container first
    if (this.app.noResults) {
      this.app.noResults.innerHTML = "";
    }
    console.log("Handling zero results fallback with terms:", terms);

    // If there are popular fallback terms, display them as clickable trend pills.
    if (Array.isArray(terms) && terms.length > 0) {
      const trendsContainer = document.createElement("div");
      trendsContainer.classList.add("esss-popular-fallback-panel");

      // Set message based on the type of zero-result fallback.
      if (type === "popular") {
        const message = document.createElement("p");
        message.classList.add("esss-fallback-title");
        message.textContent =
          "No exact matches found. Try exploring our most popular searches:";
        trendsContainer.appendChild(message);
      } else if (type === "usage") {
        const message = document.createElement("p");
        message.classList.add("esss-fallback-title");
        message.textContent =
          "No exact matches found. Try exploring our products by usage:";
        trendsContainer.appendChild(message);
      }

      const pillsRow = document.createElement("div");
      pillsRow.classList.add("esss-pills-row");
      trendsContainer.appendChild(pillsRow);

      terms.forEach((term) => {
        const pill = document.createElement("a");
        pill.classList.add("esss-trend-pill");
        pill.href = `${window.location.origin}/collections/search-results/#textsearch=${encodeURIComponent(term)}`;
        pill.textContent = term;

        pill.addEventListener("click", (event) => {
          event.preventDefault();
          this.app.suggestionService.loadSuggestion(term);
        });

        pillsRow.appendChild(pill);
      });

      // Append the complete trends container panel straight to your DOM reference wrapper
      if (this.app.noResults) {
        this.app.noResults.style.display = "block";
        this.app.noResults.appendChild(trendsContainer);
      }
    }
  }

  /**
   * Update the reset button's active state based on whether there is an active query or any active filters.
   * This provides visual feedback to the user that they can reset the search state.
   */
  renderResetState() {
    this.app.resetButton?.classList.toggle(
      "active",
      this.app.searchService.hasActiveState(),
    );
  }

  /** Clear query, filters, URL state, and product visibility. */
  reset() {
    this.app.state = {
      query: "",
      filters: {},
      page: 1,
      pagination: {
        pages: {},
      },
    };
    this.updateQueryDisplay();
    this.renderFilterState();
    this.renderResetState();
    this.app.urlService.writeUrlState();
    this.app.searchService.execute();
    this.app.loadingService.setLoading(false);
    this.app.paginationService.resetToAllProducts();
    this.app.paginationService.updatePaginationCount();
    this.showAllProducts();
  }

  /**
   * Apply ranked visibility and order to the rendered product cards.
   *
   * @param {Array<number|string>} matchIds Batch IDs returned by the API.
   * @param {Array<object>} ranking Ranked API results with scores and IDs.
   */
  renderResults(matchIds, ranking = []) {
    // Create a Set of matching batch IDs for quick lookup
    const matches = new Set(
      (Array.isArray(matchIds) ? matchIds : []).map(Number),
    );

    // Create an array of ranked batch IDs from the ranking results
    const rankedIds = (Array.isArray(ranking) ? ranking : []).map((result) =>
      Number(result.id),
    );

    // Create a Map to store the rank of each batch ID for sorting purposes
    const rankById = new Map(rankedIds.map((id, index) => [id, index]));

    // Initialize a counter for the number of visible products after applying the search results
    let visibleCount = 0;

    // Get all product list items (li elements) that are direct children of the product list
    const products = Array.from(
      this.app.productList.querySelectorAll(":scope > li"),
    );

    // Get all product list items (li elements) that are direct children of the product list
    const matchingParents = this.app.paginationService.getMatchingParents(
      products,
      matches,
    );

    // Paginate the matching parent product cards and get the items for the current page
    this.app.paginationService.paginateMatchingProducts(matchingParents);

    // Sort the product cards based on their rank in the API results and their original order
    products.sort((left, right) => {
      // Extract the product IDs for the left and right product cards for ranking comparison
      const leftIds = (this.app.helpers.getProductIds(left) || []).map(Number);
      const rightIds = (this.app.helpers.getProductIds(right) || []).map(
        Number,
      );

      // Handle empty arrays cleanly by defaulting to MAX_SAFE_INTEGER instead of Infinity
      const leftRank =
        leftIds.length > 0
          ? Math.min(
              ...leftIds.map((id) =>
                rankById.has(id) ? rankById.get(id) : Number.MAX_SAFE_INTEGER,
              ),
            )
          : Number.MAX_SAFE_INTEGER;

      const rightRank =
        rightIds.length > 0
          ? Math.min(
              ...rightIds.map((id) =>
                rankById.has(id) ? rankById.get(id) : Number.MAX_SAFE_INTEGER,
              ),
            )
          : Number.MAX_SAFE_INTEGER;

      // If the ranks are different, sort by your API ranking scores accurately
      if (leftRank !== rightRank) {
        return leftRank - rightRank;
      }

      // If neither product has a scored rank, maintain original template DOM ordering configurations
      return (
        this.app.originalProductOrder.indexOf(left) -
        this.app.originalProductOrder.indexOf(right)
      );
    });

    console.log("Sorted products collection layout mapping:", products);

    // Append the sorted product cards back to the product list in the new order
    products.forEach((product) => this.app.productList.appendChild(product));

    // Extract the matching parents again, preserving your new sorted products array order
    const sortedMatchingParents = this.app.paginationService.getMatchingParents(
      products,
      matches,
    );

    // Force your pagination service to update its internal collection memory arrays
    // using your clean, ranked layout sequence
    this.app.paginationService.paginateMatchingProducts(sortedMatchingParents);

    // Render the current page of parent product cards based on the current state and pagination
    this.app.paginationService.renderCurrentPage();

    // Get the count of visible products after applying the search results
    visibleCount = this.app.paginationService.getCurrentPageItems().length;

    // Update the pagination buttons to reflect the current page and total pages
    this.app.paginationService.addPaginationButtons();

    // Update the stats element to show the number of visible products and the current page range
    if (this.app.stats) {
      this.app.stats.style.display = this.app.parentProducts.length
        ? ""
        : "none";
      this.app.paginationService.updatePaginationCount();
    }

    // If there are no visible products, show the "no results" message
    if (this.app.noResults) {
      this.app.noResults.style.display = visibleCount ? "none" : "block";
    }

    // Return the count of visible products after applying the search results
    return visibleCount;
  }

  /**
   * Restore the original product order and visibility, hiding any "no results" message and showing the stats.
   * This is called when the search query and filters are cleared, allowing the user to see all products again.
   */
  showAllProducts() {
    // Append the original product order back to the product list in the original order
    this.app.originalProductOrder.forEach((product) =>
      this.app.productList.appendChild(product),
    );

    // Remove the "es-smart-search-hidden" class from all products to make them visible again
    this.app.originalProductOrder.forEach((product) => {
      product.classList.remove("es-smart-search-hidden");
    });

    // Hide the "no results" message if it is currently displayed
    if (this.app.noResults) this.app.noResults.style.display = "none";

    // Show the stats element if it is currently hidden
    if (this.app.stats) this.app.stats.style.display = "";
  }
}
