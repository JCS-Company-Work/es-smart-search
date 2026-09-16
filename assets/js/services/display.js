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
      this.app.productList.querySelectorAll(":scope > li:not(.gap)"),
    );

    // Get all product list items (li elements) that are direct children of the product list
    const matchingParents = this.app.paginationService.getMatchingParents(
      products,
      matches,
    );

    // Paginate the matching parent product cards and get the items for the current page
    this.app.paginationService.paginateMatchingProducts(matchingParents);

    // Sort the product cards based on their rank in the API results and their original order
    this.sortProducts(products, rankById);

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
   * Sort products based on the current sort state or search ranking.
   * @param {array<HTMLLIElement>} products The list of product elements to sort.
   * @param {Map<number, number>} rankById A map of product IDs to their search ranking.
   * @returns {array<HTMLLIElement>} The sorted list of product elements.
   */
  sortProducts(products, rankById) {
    // If a sort state is defined, sort by the selected attribute first. Otherwise, sort by search ranking.
    if (this.app.state.sort) {
      return this.sortBySelectedAttribute(products, this.app.state.sort);
    }

    // If no sort state is defined, fall back to sorting by search ranking.
    return this.sortBySearchRanking(products, rankById);
  }

  /**
   * Sort products based on the selected attribute and direction.
   * @param {array<HTMLLIElement>} products The list of product elements to sort.
   * @param {{key: string, direction: string}} sortState The current sort state containing the key and direction.
   * @returns {array<HTMLLIElement>} The sorted list of product elements.
   */
  sortBySelectedAttribute(products, sortState) {
    // Convert the sort key from kebab-case to camelCase to match the dataset property names.
    const dataKey = sortState.key.replace(/-([a-z])/g, (_, letter) =>
      letter.toUpperCase(),
    );

    return products.sort((left, right) => {
      // Extract the values for the left and right products based on the selected sort attribute.
      const leftValue = Number(left.dataset[dataKey]);
      const rightValue = Number(right.dataset[dataKey]);

      const safeLeft = Number.isFinite(leftValue)
        ? leftValue
        : Number.POSITIVE_INFINITY;
      const safeRight = Number.isFinite(rightValue)
        ? rightValue
        : Number.POSITIVE_INFINITY;

      // Compare the safe values based on the sort direction.
      return sortState.direction === "asc"
        ? safeLeft - safeRight
        : safeRight - safeLeft;
    });
  }

  /**
   * Sort the currently displayed products based on the selected attribute and direction.
   */
  sortCurrentProducts() {
    const products = Array.from(
      this.app.productList.querySelectorAll(":scope > li:not(.gap)"),
    );

    this.sortBySelectedAttribute(products, this.app.state.sort);

    products.forEach((product) => {
      this.app.productList.appendChild(product);
    });

    const sortedMatchingParents = products.filter((product) =>
      this.app.parentProducts.includes(product),
    );

    this.app.paginationService.paginateMatchingProducts(sortedMatchingParents);
    this.app.paginationService.renderCurrentPage();
    this.app.paginationService.addPaginationButtons();
    this.app.paginationService.updatePaginationCount();
  }

  /**
   * Sort products based on their search ranking.
   * @param {array<HTMLLIElement>} products The list of product elements to sort.
   * @param {Map<number, number>} rankById A map containing the search ranking for each product ID.
   * @returns {array<HTMLLIElement>} The sorted list of product elements.
   */
  sortBySearchRanking(products, rankById) {
    // Sort the products based on their search ranking, using the provided rankById map.
    return products.sort((left, right) => {
      const leftIds = (this.app.helpers.getProductIds(left) || []).map(Number);
      const rightIds = (this.app.helpers.getProductIds(right) || []).map(
        Number,
      );

      // Determine the search ranking for the left and right products.
      const leftRank =
        leftIds.length > 0
          ? Math.min(
              ...leftIds.map((id) =>
                rankById.has(id) ? rankById.get(id) : Number.MAX_SAFE_INTEGER,
              ),
            )
          : Number.MAX_SAFE_INTEGER;

      // Determine the search ranking for the right product.
      const rightRank =
        rightIds.length > 0
          ? Math.min(
              ...rightIds.map((id) =>
                rankById.has(id) ? rankById.get(id) : Number.MAX_SAFE_INTEGER,
              ),
            )
          : Number.MAX_SAFE_INTEGER;

      // If the search rankings are different, sort based on the ranking first.
      if (leftRank !== rightRank) {
        return leftRank - rightRank;
      }

      // If the search rankings are the same, fall back to the original product order.
      return (
        this.app.originalProductOrder.indexOf(left) -
        this.app.originalProductOrder.indexOf(right)
      );
    });
  }

  /**
   * Restore the products to the order they were in when initially loaded.
   * This is used when resetting the product list to its original state.
   * @returns {array<HTMLLIElement>} The restored, sorted product elements.
   */
  restoreProductsInSelectedOrder() {
    // Query the live DOM rather than the boot-time snapshot, matching sortCurrentProducts()/renderResults().
    const products = Array.from(
      this.app.productList.querySelectorAll(":scope > li:not(.gap)"),
    );

    if (this.app.state.sort) {
      this.sortBySelectedAttribute(products, this.app.state.sort);
    }

    products.forEach((product) => {
      this.app.productList.appendChild(product);
    });

    return products;
  }

  /**
   * Restore the original product order and visibility, hiding any "no results" message and showing the stats.
   * This is called when the search query and filters are cleared, allowing the user to see all products again.
   */
  showAllProducts() {
    // Rebuild the list in the active sort order rather than the raw load order.
    const products = this.restoreProductsInSelectedOrder();

    // Paginate using the sorted order so page 1 reflects the active sort, not the raw load order.
    this.app.state.page = 1;
    this.app.paginationService.paginateMatchingProducts(products);
    this.app.paginationService.renderCurrentPage();
    this.app.paginationService.updatePaginationCount();
    this.app.paginationService.addPaginationButtons();

    // Hide the "no results" message if it is currently displayed
    if (this.app.noResults) this.app.noResults.style.display = "none";

    // Show the stats element if it is currently hidden
    if (this.app.stats) this.app.stats.style.display = "";
  }
}
