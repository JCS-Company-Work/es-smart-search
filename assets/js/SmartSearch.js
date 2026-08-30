import { UrlService } from "./url.js";
import { SearchService } from "./search.js";
import { FilterService } from "./filters.js";
import { LoadingService } from "./loading.js";
import { PaginationService } from "./pagination.js";

/** Coordinates search state, URL state, API requests, and product visibility. */
export default class SmartSearch {
  constructor() {
    // Current search state, including query, filters, and page number.
    this.state = {
      // Current search query string
      query: "",

      // Object to hold current filter state
      filters: {},

      // Current page number for pagination
      page: 1,

      // Object to hold pagination information, including total pages and current page
      pagination: {
        pages: {},
      },
    };

    // Initialise services and pass the main SmartSearch instance to them for context
    this.searchService = new SearchService(this);
    this.filterService = new FilterService(this);
    this.urlService = new UrlService(this);
    this.loadingService = new LoadingService(this);
    this.paginationService = new PaginationService(this);

    this.paginationContainer = null;

    // Controller properties for DOM elements, timers, and request state.

    this.input = null;
    this.productList = null;
    this.stats = null;
    this.noResults = null;
    this.resetButton = null;
    this.loading = null;
    this.requestController = null;
    this.searchTimer = null;

    // Minimum query length required to trigger a search request.
    this.minimumQueryLength = 3;

    // Store the original product order for restoring when no search is active
    this.originalProductOrder = [];

    // Array to hold parent product cards for pagination purposes
    this.parentProducts = [];
  }

  /** Find the search UI, restore URL state, and start active searches. */
  init() {
    // Get search input from DOM
    this.input = document.querySelector(".live-filter");

    // Get product list from DOM
    this.productList = document.querySelector(".product-list");
    this.paginationContainer = document.querySelector(".mixitup-page-list");

    // Get stats element from DOM
    this.stats = document.querySelector(".mixitup-page-stats");

    // Get query display element from DOM, this will show the current search query
    this.queryDisplay =
      window.location.pathname === "/collections/search-results/"
        ? document.querySelector(".banner-headline")
        : document.querySelector(".es-smart-search-query");

    // Get no results element from DOM this is the element that will be displayed when there are no results
    this.noResults = document.querySelector(".no-results");

    // Get reset button from DOM
    this.resetButton = document.getElementById("reset-filters");

    // If input, products or ESSS endpoint is missing, abort initialization
    if (!this.input || !this.productList || !window.ESSS) {
      return;
    }

    // Store the original product order for restoring when no search is active
    this.originalProductOrder = Array.from(
      this.productList.querySelectorAll(":scope > li"),
    );

    this.loadingService.createLoadingIndicator();

    // Read the initial state from the URL hash or query parameters
    this.urlService.readUrlState();

    // Bind event listeners for input, filters, reset, navigation, and layout changes
    this.bindEvents();

    // Render the initial filter state in the UI based on the current state
    this.renderFilterState();

    // Render the reset button state based on whether a query or filter is active
    this.renderResetState();

    // If debugging is enabled, log the initial state and endpoint
    if (window.ESSS.debug) {
      console.info("[ES Smart Search] initialised", {
        minimumQueryLength: this.minimumQueryLength,
        endpoint: ESSS.endpoint,
      });
    }

    // If a query or filter is active, perform an initial search to display results
    if (this.hasActiveState()) {
      this.searchService.execute();
    }
  }

  // =========================================================================
  // STATE MANAGEMENT
  // =========================================================================

  /**
   * Check if there is an active search query or any active filters.
   *
   * @returns {boolean} True if a query or filter is active, false otherwise.
   */
  hasActiveState() {
    return Boolean(this.state.query || Object.keys(this.state.filters).length);
  }

  // =========================================================================
  // EVENT HANDLING
  // =========================================================================

  /**
   * Bind event listeners for input changes, filter clicks, reset button, navigation, and layout changes.
   * The input event triggers a search when the user types a query.
   * Filter clicks update the filter state and trigger a search.
   * The reset button clears the query and filters.
   * The popstate event handles browser navigation to restore state.
   * Scroll and resize events reposition the loading indicator.
   */
  bindEvents() {
    this.input.addEventListener("input", () => {
      this.state.query = this.input.value.trim();
      this.updateQueryDisplay();
      this.state.page = 1;
      this.urlService.writeUrlState();
      this.renderResetState();
      this.scheduleSearch();
    });

    // Bind click events to filter controls within fieldsets that have a data-filter-group attribute
    this.filterService.bindFilters();

    // Bind click event to the reset button to clear the search state
    this.resetButton?.addEventListener("click", (event) => {
      event.preventDefault();
      this.reset();
    });

    // Handle browser navigation events to restore state from the URL
    window.addEventListener("popstate", () => {
      this.urlService.readUrlState();
      this.renderFilterState();
      this.searchService.execute();
    });

    // Reposition the loading indicator when the user scrolls or resizes the window
    window.addEventListener(
      "scroll",
      () => this.loadingService.positionLoading(),
      {
        passive: true,
      },
    );

    // Reposition the loading indicator when the window is resized
    window.addEventListener("resize", () =>
      this.loadingService.positionLoading(),
    );
  }

  /**
   * Schedule a search to be performed after a short delay, allowing for debouncing of rapid input changes.
   * This prevents excessive API requests while the user is typing or changing filters.
   */
  scheduleSearch() {
    clearTimeout(this.searchTimer);
    this.loadingService.setLoading(true);
    this.searchTimer = setTimeout(() => this.searchService.execute(), 300);
  }

  // =========================================================================
  // DISPLAY UPDATES
  // =========================================================================

  /**
   * Update the input value and filter button states to reflect the current search state.
   * This ensures that the UI accurately represents the active query and filters.
   */
  renderFilterState() {
    // Update the input value and active class based on the current query state
    if (this.input) {
      // Set the input value to the current query state
      this.input.value = this.state.query;
      this.updateQueryDisplay();

      // Toggle the "mixitup-control-active" class on the input based on whether there is a query
      this.input.classList.toggle(
        "mixitup-control-active",
        Boolean(this.state.query),
      );
    }

    // Update the active state of filter buttons based on the current filter state
    document
      .querySelectorAll("fieldset[data-filter-group] .control")
      .forEach((button) => {
        const group = button.closest("fieldset[data-filter-group]")?.dataset
          .filterGroup;
        const selected =
          this.state.filters[group]?.includes(button.dataset.toggle) || false;
        button.classList.toggle("mixitup-control-active", selected);
      });
  }

  /**
   * Update the query display element with the current search query.
   * @returns {void}
   */
  updateQueryDisplay() {
    // If the query display element is not available, exit
    if (!this.queryDisplay) return;

    // Update the query display text based on the current search query
    this.queryDisplay.textContent = this.state.query
      ? `Search Results for "${this.state.query}"`
      : "";
  }

  /**
   * Update the reset button's active state based on whether there is an active query or any active filters.
   * This provides visual feedback to the user that they can reset the search state.
   */
  renderResetState() {
    this.resetButton?.classList.toggle("active", this.hasActiveState());
  }

  /** Clear query, filters, URL state, and product visibility. */
  reset() {
    this.state = {
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
    this.urlService.writeUrlState();
    this.searchService.execute();
    this.loadingService.setLoading(false);
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
      this.productList.querySelectorAll(":scope > li"),
    );

    // Get all product list items (li elements) that are direct children of the product list
    const matchingParents = this.paginationService.getMatchingParents(
      products,
      matches,
    );

    // Paginate the matching parent product cards and get the items for the current page
    this.paginationService.paginateMatchingProducts(matchingParents);

    // Sort the product cards based on their rank in the API results and their original order
    products.sort((left, right) => {
      // Determine the rank of the left product card based on its batch IDs and the rankById map
      const leftRank = Math.min(
        ...this.getProductIds(left).map(
          (id) => rankById.get(id) ?? Number.MAX_SAFE_INTEGER,
        ),
      );

      // Sort the products by their rank in the API results, with lower ranks appearing first. If two products have the same rank, maintain their original order in the DOM.
      const rightRank = Math.min(
        ...this.getProductIds(right).map(
          (id) => rankById.get(id) ?? Number.MAX_SAFE_INTEGER,
        ),
      );

      // If the ranks are different, return the difference to sort by rank
      if (leftRank !== rightRank) {
        return leftRank - rightRank;
      }

      // If the ranks are the same, maintain the original order of the products in the DOM
      return (
        this.originalProductOrder.indexOf(left) -
        this.originalProductOrder.indexOf(right)
      );
    });

    // Append the sorted product cards back to the product list in the new order
    products.forEach((product) => this.productList.appendChild(product));

    // Render the current page of parent product cards based on the current state and pagination
    this.paginationService.renderCurrentPage();

    // Get the count of visible products after applying the search results and update the visibleCount variable
    visibleCount = this.paginationService.getCurrentPageItems().length;

    // Update the pagination buttons to reflect the current page and total pages
    this.paginationService.addPaginationButtons();

    // Update the stats element to show the number of visible products and the current page range
    if (this.stats) {
      this.stats.style.display = this.parentProducts.length ? "" : "none";
      this.paginationService.updatePaginationCount();
    }

    // Update the no results element to show a message if there are no matching products, or hide it if there are matches
    if (this.noResults) {
      this.noResults.style.display = visibleCount ? "none" : "block";
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
    this.originalProductOrder.forEach((product) =>
      this.productList.appendChild(product),
    );

    // Remove the "es-smart-search-hidden" class from all products to make them visible again
    this.originalProductOrder.forEach((product) => {
      product.classList.remove("es-smart-search-hidden");
    });

    // Hide the "no results" message if it is currently displayed
    if (this.noResults) this.noResults.style.display = "none";

    // Show the stats element if it is currently hidden
    if (this.stats) this.stats.style.display = "";
  }

  // =========================================================================
  // LOADING INDICATOR
  // =========================================================================

  // /**
  //  * Create a loading indicator element and append it to the product list.
  //  * The loading indicator is shown while an API request is in progress.
  //  */
  // createLoadingIndicator() {
  //   this.loading = document.createElement("div");
  //   this.loading.className = "es-smart-search-loading";
  //   this.loading.setAttribute("role", "status");
  //   this.loading.setAttribute("aria-live", "polite");
  //   this.loading.innerHTML =
  //     '<span aria-hidden="true"></span><span class="screen-reader-text">Searching</span>';
  //   this.productList.appendChild(this.loading);
  // }

  // /**
  //  * Toggle the request loading overlay and input busy state.
  //  *
  //  * @param {boolean} isLoading Whether the request is in progress.
  //  */
  // setLoading(isLoading) {
  //   // If the loading indicator is active, reposition it to stay centered over the visible product area
  //   if (isLoading) this.positionLoading();

  //   // Toggle the "is-active" class on the loading indicator based on whether a request is in progress
  //   this.loading?.classList.toggle("is-active", isLoading);

  //   // Update the input's aria-busy attribute to indicate whether the input is currently busy with a request
  //   if (this.input)
  //     this.input.setAttribute("aria-busy", isLoading ? "true" : "false");
  // }

  // /**
  //  * Position the loading indicator over the visible product area, ensuring it is centered and sized correctly.
  //  * @returns
  //  */
  // positionLoading() {
  //   // If the loading indicator or product list is not available, exit the function early
  //   if (!this.loading || !this.productList) return;

  //   // Get the bounding rectangle of the product list to determine its position and size
  //   const bounds = this.productList.getBoundingClientRect();

  //   // Calculate the top, bottom, left, and right positions for the loading indicator, ensuring it stays within the viewport
  //   const top = Math.max(bounds.top, 0);

  //   // Calculate the bottom position, ensuring it does not exceed the window's inner height
  //   const bottom = Math.min(bounds.bottom, window.innerHeight);

  //   // Calculate the left position, ensuring it does not go below 0
  //   const left = Math.max(bounds.left, 0);

  //   // Calculate the right position, ensuring it does not exceed the window's inner width
  //   const right = Math.min(bounds.right, window.innerWidth);

  //   // Set the loading indicator's position and size based on the calculated values
  //   this.loading.style.top = `${top}px`;
  //   this.loading.style.left = `${left}px`;
  //   this.loading.style.width = `${Math.max(right - left, 0)}px`;
  //   this.loading.style.height = `${Math.max(bottom - top, 0)}px`;
  // }

  // =========================================================================
  // PRODUCT HELPERS
  // =========================================================================

  /**
   * Return all batch IDs represented by a rendered product card.
   *
   * @param {HTMLElement} product Rendered product card.
   * @returns {number[]} Parent and child batch IDs.
   */
  getProductIds(product) {
    return [
      product.dataset.id,
      ...Array.from(product.querySelectorAll("[data-id]")).map(
        (child) => child.dataset.id,
      ),
    ]
      .map(Number)
      .filter(Number.isFinite);
  }
}

// Initialize the ESSmartSearch instance when the DOM content is fully loaded, ensuring that the search functionality is ready to use.
document.addEventListener(
  "DOMContentLoaded",
  () => {
    window.esSmartSearch = new SmartSearch();
    window.esSmartSearch.init();
  },
  { once: true },
);
