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
      // Determine the rank of the left product card based on its batch IDs and the rankById map
      const leftRank = Math.min(
        ...this.app.helpers
          .getProductIds(left)
          .map((id) => rankById.get(id) ?? Number.MAX_SAFE_INTEGER),
      );

      // Sort the products by their rank in the API results, with lower ranks appearing first. If two products have the same rank, maintain their original order in the DOM.
      const rightRank = Math.min(
        ...this.app.helpers
          .getProductIds(right)
          .map((id) => rankById.get(id) ?? Number.MAX_SAFE_INTEGER),
      );

      // If the ranks are different, return the difference to sort by rank
      if (leftRank !== rightRank) {
        return leftRank - rightRank;
      }

      // If the ranks are the same, maintain the original order of the products in the DOM
      return (
        this.app.originalProductOrder.indexOf(left) -
        this.app.originalProductOrder.indexOf(right)
      );
    });

    // Append the sorted product cards back to the product list in the new order
    products.forEach((product) => this.app.productList.appendChild(product));

    // Render the current page of parent product cards based on the current state and pagination
    this.app.paginationService.renderCurrentPage();

    // Get the count of visible products after applying the search results and update the visibleCount variable
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

    // Update the no results element to show a message if there are no matching products, or hide it if there are matches
    if (this.noResults) {
      this.noResults.style.display = visibleCount ? "none" : "block";
    }

    // Return the count of visible products after applying the search results
    return visibleCount;
  }

  showSuggestion(suggestions) {
    // Loop through suggestions and build 'did you mean' links
    suggestions.forEach((suggestion) => {
      // Create a suggestion link for the "Did you mean" feature
      const suggestionLink = document.createElement("a");

      suggestionLink.classList.add("suggestion-link");

      // Set the href attribute to the search results page with the suggested query
      suggestionLink.href = "#";

      // Set the text content of the suggestion link to display the suggested query
      suggestionLink.innerHTML = `Did you mean: <span>${suggestion}</span>?`;

      // Add a click event listener to the suggestion link to execute the search with the suggested query
      suggestionLink.addEventListener("click", (event) => {
        // Prevent the default link behavior
        event.preventDefault();

        // Load the suggested query into the search input and execute the search
        this.loadSuggestion(suggestion);
      });

      // Update UI with suggestions
      if (this.app.noResults) {
        this.displaySuggestions(suggestionLink);
      }
    });
  }

  /**
   * Display the suggestion link in the "no results" message.
   * @param {HTMLAnchorElement} suggestionLink The suggestion link element to display in the "no results" message.
   */
  displaySuggestions(suggestionLink) {
    this.app.noResults.innerHTML = "";
    this.app.noResults.appendChild(suggestionLink);
    this.app.noResults.appendChild(suggestionLink);

    this.app.noResults.style.display = "block";
  }

  /**
   * Load the suggested query into the search input and execute the search.
   * @param {string} suggestion The suggested query to load and search for.
   */
  loadSuggestion(suggestion) {
    // Update search query term in state to suggestion
    this.app.state.query = suggestion;

    // Update the search input field with the suggested query
    const searchInput = document.querySelector(".live-filter");
    if (searchInput) {
      searchInput.value = suggestion;
    }

    this.app.state.page = 1;
    this.app.urlService.writeUrlState();
    this.renderResetState();

    // Execute the search with the updated query
    this.app.searchService.execute();

    // Update the query display to reflect the new suggestion
    this.updateQueryDisplay();

    // Hide the "no results" message after loading the suggestion
    this.app.noResults.innerHTML = "";
    this.app.noResults.style.display = "none";
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
