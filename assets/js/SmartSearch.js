import { readUrlState, writeUrlState } from "./url.js";
import { bindFilters } from "./filters.js";
import {
  getCurrentPageItems,
  getMatchingParents,
  paginateMatchingProducts,
  addPaginationButtons,
  renderCurrentPage,
  updatePaginationCount,
} from "./pagination.js";

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

    // Create the loading indicator element and append it to the product list
    this.createLoadingIndicator();

    // Read the initial state from the URL hash or query parameters
    readUrlState.call(this);

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
      this.search();
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

  // /**
  //  * Restore query, filters and page from the URL.
  //  *
  //  * The hash is used by the in-page controls. The query parameter is used by
  //  * the site-wide search form, so both entry points share the same search flow.
  //  */
  // readUrlState() {
  //   // Get the current URL hash and remove the leading '#' character
  //   const hash = window.location.hash.replace(/^#/, "");
  //   const nextState = {
  //     query: "",
  //     filters: {},
  //     page: 1,
  //     pagination: {
  //       pages: {},
  //     },
  //   };

  //   // If there is no hash, check for a query parameter in the URL search parameters
  //   if (!hash) {
  //     // Use URLSearchParams to parse the query parameters from the current URL
  //     const params = new URLSearchParams(window.location.search);

  //     // Get the "textsearch" parameter from the query parameters, or default to an empty string
  //     nextState.query = params.get("textsearch") || "";

  //     // Get the "page" parameter from the query parameters, or default to 1
  //     this.state = nextState;

  //     // If there is a query parameter, update the URL hash to reflect the query and reset the page number to 1
  //     if (nextState.query) {
  //       const cleanUrl = `${window.location.pathname}#textsearch=${encodeURIComponent(
  //         nextState.query,
  //       )}&page=1`;

  //       window.history.replaceState(null, "", cleanUrl);
  //     }

  //     return;
  //   }

  //   // Split the hash into key-value pairs and process each part
  //   hash.split("&").forEach((part) => {
  //     // Find the index of the '=' separator in the part
  //     const separator = part.indexOf("=");

  //     // If there is no '=' separator, skip this part
  //     if (separator < 0) return;

  //     // Decode the key and value from the part
  //     const key = decodeURIComponent(part.slice(0, separator));

  //     // Decode the value from the part, handling URL encoding
  //     const value = decodeURIComponent(part.slice(separator + 1));

  //     // Update the nextState based on the key and value
  //     if (key === "textsearch") {
  //       nextState.query = value;
  //     } else if (key === "page") {
  //       nextState.page = parseInt(value, 10) || 1;
  //     } else if (value) {
  //       nextState.filters[key] = value.split(",");
  //     }
  //   });

  //   // Update the current state with the nextState derived from the URL
  //   this.state = nextState;
  // }

  // /**
  //  * Update the URL hash to reflect the current query, filters, and page.
  //  * @returns {void}
  //  */
  // writeUrlState() {
  //   // Build an array of key-value pairs for the URL hash
  //   const parts = [];

  //   // Check if there are any active filters in the current state
  //   const hasFilters = Object.keys(this.state.filters).length > 0;

  //   // Check if the current query meets the minimum length for searching
  //   const hasSearchableQuery =
  //     this.state.query.length >= this.minimumQueryLength;

  //   // Check if the URL already has a query parameter for textsearch
  //   const urlHasQuery = window.location.hash.includes("textsearch=");

  //   // If there is a searchable query, add it to the parts array for the URL hash
  //   if (hasSearchableQuery) {
  //     parts.push(`textsearch=${encodeURIComponent(this.state.query)}`);
  //   } else if (this.state.query && !hasFilters && !urlHasQuery) {
  //     return;
  //   }

  //   // Add each filter group and its values to the parts array for the URL hash
  //   Object.entries(this.state.filters).forEach(([group, values]) => {
  //     if (values.length) {
  //       parts.push(
  //         `${encodeURIComponent(group)}=${encodeURIComponent(values.join(","))}`,
  //       );
  //     }
  //   });

  //   // Add the current page number to the parts array for the URL hash
  //   parts.push(`page=${this.state.page}`);

  //   // Construct the new URL hash from the parts array
  //   const hash = `#${parts.join("&")}`;

  //   // Construct the full URL with the current pathname, search parameters, and new hash
  //   const url = `${window.location.pathname}${window.location.search}${hash}`;

  //   // If the new URL is different from the current URL, update the browser history
  //   if (
  //     url !==
  //     `${window.location.pathname}${window.location.search}${window.location.hash}`
  //   ) {
  //     window.history.pushState(null, "", url);
  //   }
  // }

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
      writeUrlState.call(this);
      this.renderResetState();
      this.scheduleSearch();
    });

    // Bind click events to filter controls within fieldsets that have a data-filter-group attribute
    bindFilters.call(this);

    // Bind click event to the reset button to clear the search state
    this.resetButton?.addEventListener("click", (event) => {
      event.preventDefault();
      this.reset();
    });

    // Handle browser navigation events to restore state from the URL
    window.addEventListener("popstate", () => {
      readUrlState.call(this);
      this.renderFilterState();
      this.search();
    });

    // Reposition the loading indicator when the user scrolls or resizes the window
    window.addEventListener("scroll", () => this.positionLoading(), {
      passive: true,
    });

    // Reposition the loading indicator when the window is resized
    window.addEventListener("resize", () => this.positionLoading());
  }

  // /**
  //  * Update filter state after a filter control is clicked.
  //  *
  //  * @param {HTMLButtonElement} button The clicked filter control.
  //  */
  // handleFilterClick(button) {
  //   // Get the filter group and value from the clicked button's dataset
  //   const group = button.closest("fieldset[data-filter-group]")?.dataset
  //     .filterGroup;

  //   // Get the value to toggle from the button's dataset
  //   const value = button.dataset.toggle;

  //   // If either the group or value is missing, return
  //   if (!group || !value) {
  //     return;
  //   }

  //   // Toggle the active state of the clicked button
  //   button.classList.toggle("mixitup-control-active");

  //   // Update the filter state for the group based on the active buttons
  //   this.state.filters[group] = Array.from(
  //     document.querySelectorAll(
  //       `fieldset[data-filter-group="${CSS.escape(group)}"] .control.mixitup-control-active`,
  //     ),
  //   ).map((activeButton) => activeButton.dataset.toggle);

  //   // If no active filters remain for the group, remove the group from the state
  //   if (!this.state.filters[group].length) {
  //     delete this.state.filters[group];
  //   }

  //   // Reset the page number to 1 when filters change
  //   this.state.page = 1;

  //   // Update the URL hash to reflect the new state
  //   writeUrlState.call(this);

  //   // Update the reset button state based on whether a query or filter is active
  //   this.renderResetState();

  //   // Perform a new search with the updated filter state
  //   this.search();
  // }

  /**
   * Schedule a search to be performed after a short delay, allowing for debouncing of rapid input changes.
   * This prevents excessive API requests while the user is typing or changing filters.
   */
  scheduleSearch() {
    clearTimeout(this.searchTimer);
    this.setLoading(true);
    this.searchTimer = setTimeout(() => this.search(), 300);
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
    writeUrlState.call(this);
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
    const matchingParents = getMatchingParents.call(this, products, matches);

    // Paginate the matching parent product cards and get the items for the current page
    paginateMatchingProducts.call(this, matchingParents);

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
    renderCurrentPage.call(this);

    // Get the count of visible products after applying the search results and update the visibleCount variable
    visibleCount = getCurrentPageItems.call(this).length;

    // Update the pagination buttons to reflect the current page and total pages
    addPaginationButtons.call(this);

    // Update the stats element to show the number of visible products and the current page range
    if (this.stats) {
      this.stats.style.display = this.parentProducts.length ? "" : "none";
      updatePaginationCount.call(this);
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

  // // =========================================================================
  // // PAGINATION
  // // =========================================================================

  // /**
  //  * Return the number of parent product cards to show on each page.
  //  *
  //  * The page size follows the existing responsive pagination rules used by
  //  * the previous TileFilter implementation.
  //  *
  //  * @returns {number} Number of parent cards per page.
  //  */
  // itemsPerPage() {
  //   // Get current window width
  //   const width = window.innerWidth;

  //   // Determine number of items to show per page
  //   if (width < 768) return 16;
  //   if (width < 1365) return 30;
  //   if (width < 1800) return 36;

  //   // For very large screens, show 40 items per page
  //   return 40;
  // }

  // /**
  //  * Paginate an array of items into pages based on the current itemsPerPage setting.
  //  * @param {Array} items Array of items to paginate.
  //  * @returns {Object} An object where keys are page numbers and values are arrays of items for that page.
  //  */
  // paginate(items) {
  //   // Get the number of items to show per page
  //   const perPage = this.itemsPerPage();

  //   // Reduce the items array into an object where keys are page numbers and values are arrays of items for that page
  //   return items.reduce((pages, item, index) => {
  //     // Calculate the page number for the current item based on its index and the number of items per page
  //     const pageNumber = Math.floor(index / perPage) + 1;

  //     // Create a key for the current page in the pages object
  //     const pageKey = `page${pageNumber}`;

  //     // Initialize the array for the current page if it doesn't exist yet
  //     pages[pageKey] ??= [];

  //     // Add the current item to the array for the current page
  //     pages[pageKey].push(item);

  //     // Return the updated pages object for the next iteration
  //     return pages;
  //   }, {});
  // }

  // /**
  //  * Paginate the matching parent product cards and return a Set of items for the current page.
  //  * @param {Array} matchingParents Array of matching parent product cards.
  //  * @returns {Set} Set of items for the current page.
  //  */
  // paginateMatchingProducts(matchingParents) {
  //   this.parentProducts = matchingParents;
  //   this.state.pagination.pages = this.paginate(matchingParents);

  //   this.state.page = Math.min(
  //     this.state.page,
  //     Object.keys(this.state.pagination.pages).length || 1,
  //   );

  //   return new Set(this.getCurrentPageItems());
  // }

  // /**
  //  * Get the items for the current page based on the current state and pagination.
  //  * @returns {Array} Array of items for the current page.
  //  */
  // getCurrentPageItems() {
  //   const pageKey = `page${this.state.page}`;

  //   return this.state.pagination.pages[pageKey] || [];
  // }

  // /**
  //  * Get the parent product elements that match the given set of matching product IDs.
  //  * @param {Array} products Array of product elements to filter.
  //  * @param {Set} matches Set of matching product IDs.
  //  * @returns {Array} Array of matching parent product elements.
  //  */
  // getMatchingParents(products, matches) {
  //   // Filter the products array to find the parent product elements that match the given set of matching product IDs
  //   return products.filter((product) => {
  //     // Collect the parent batch ID and all child batch IDs for the current product card
  //     const ids = [
  //       product.dataset.id,
  //       ...Array.from(product.querySelectorAll("[data-id]")).map(
  //         (child) => child.dataset.id,
  //       ),
  //     ];

  //     // Check if any of the product's batch IDs are present in the set of matching IDs
  //     return ids.some(
  //       (id) => Number.isFinite(Number(id)) && matches.has(Number(id)),
  //     );
  //   });
  // }

  // /**
  //  * Update the pagination count display based on the current page and total number of parent product cards.
  //  * @returns {void} Does not return a value.
  //  */
  // updatePaginationCount() {
  //   // If the stats element is not available, exit
  //   if (!this.stats) {
  //     return;
  //   }

  //   // Get the total number of parent product cards
  //   const total = this.parentProducts.length;

  //   // Get the number of items to show per page based on the current window width
  //   const perPage = this.itemsPerPage();

  //   // Calculate the starting index of the current page based on the total number of items, current page number, and items per page
  //   const start = total ? (this.state.page - 1) * perPage + 1 : 0;

  //   // Calculate the ending index of the current page based on the total number of items, current page number, and items per page
  //   const end = Math.min(this.state.page * perPage, total);

  //   // Update the stats element to show the range of items being displayed and the total number of items, or show "0 of 0" if there are no items
  //   this.stats.textContent = total
  //     ? `${start} to ${end} of ${total}`
  //     : "0 of 0";
  // }

  // /**
  //  * Render the current page of parent product cards based on the current state and pagination.
  //  * This method toggles the visibility of product cards based on whether they are in the current page items and are parent products.
  //  * It ensures that only the relevant products for the current page are displayed to the user.
  //  * @returns {void}
  //  */
  // renderCurrentPage() {
  //   // Get the set of items for the current page based on the current state and pagination
  //   const currentPageItems = new Set(this.getCurrentPageItems());

  //   // Loop over original product order and toggle visibility based on whether the product is in the current page items and is a parent product
  //   this.originalProductOrder.forEach((product) => {
  //     const visible =
  //       this.parentProducts.includes(product) && currentPageItems.has(product);

  //     // Toggle the "es-smart-search-hidden" class on the product card based on its visibility
  //     product.classList.toggle("es-smart-search-hidden", !visible);
  //   });
  // }

  // /**
  //  * Add pagination buttons to the pagination container based on the current state and total pages.
  //  * @returns {void} Does not return a value.
  //  */
  // addPaginationButtons() {
  //   const container = this.paginationContainer;
  //   const totalPages = Object.keys(this.state.pagination.pages).length;
  //   const currentPage = this.state.page;

  //   if (!container || totalPages <= 1) {
  //     return;
  //   }

  //   container.innerHTML = "";

  //   container.appendChild(
  //     this._createPaginationButton("«", "prev", "mixitup-control-prev"),
  //   );

  //   this._getCondensedPages(totalPages, currentPage).forEach((page) => {
  //     const isFirst = page === 1;
  //     const isLast = page === totalPages;

  //     container.appendChild(
  //       this._createPaginationButton(
  //         isFirst ? "First" : isLast ? "Last" : page,
  //         page,
  //         page === currentPage
  //           ? "mixitup-control mixitup-control-active"
  //           : "mixitup-control",
  //       ),
  //     );
  //   });

  //   container.appendChild(
  //     this._createPaginationButton("»", "next", "mixitup-control-next"),
  //   );
  // }

  // /**
  //  *
  //  * determine which pages to show
  //  * includes first, last, current, neighbors, and extra pages at edges
  //  * @param {*} total
  //  * @param {*} current
  //  * @returns
  //  */
  // _getCondensedPages(total, current) {
  //   const pages = new Set([1, total, current]);

  //   // previous page
  //   if (current > 1) pages.add(current - 1);

  //   // next page
  //   if (current < total) pages.add(current + 1);

  //   // If on first page, add extra neighbors
  //   if (current === 1 && total > 3) pages.add(2).add(3);

  //   // If on last page, add extra neighbors
  //   if (current === total && total > 3) pages.add(total - 1).add(total - 2);

  //   // return sorted array
  //   return [...pages].sort((a, b) => a - b);
  // }

  // /**
  //  *
  //  * create a button element
  //  * attaches click handler to update pagination state and scroll to top
  //  * @param {string} label
  //  * @param {*} page
  //  * @param {string} extraClasses
  //  * @returns
  //  */
  // _createPaginationButton(label, page, extraClasses = "") {
  //   const btn = document.createElement("button");
  //   btn.type = "button";
  //   btn.textContent = label;
  //   btn.className = `mixitup-control ${extraClasses}`.trim();
  //   btn.dataset.page = page;

  //   btn.addEventListener("click", (e) => {
  //     e.preventDefault();

  //     const newPage = this._resolvePage(page);

  //     // Scroll smoothly to top
  //     window.scrollTo({ top: 0, behavior: "smooth" });

  //     // Update state
  //     this.state.page = newPage;

  //     // Update URL
  //     writeUrlState.call(this);

  //     // Render current page
  //     this.renderCurrentPage();

  //     // Update pagination buttons
  //     this.addPaginationButtons();

  //     // Update pagination count
  //     this.updatePaginationCount();
  //   });

  //   return btn;
  // }

  // /**
  //  * resolve page number from button input
  //  * handles 'prev', 'next', or numeric pages
  //  * @param {string|number} page
  //  * @returns {number} The page number to navigate to
  //  */
  // _resolvePage(page) {
  //   const { page: current } = this.state.pagination;
  //   const total = Object.keys(this.state.pagination.pages).length;
  //   if (page === "prev") return Math.max(current - 1, 1);
  //   if (page === "next") return Math.min(current + 1, total);
  //   return parseInt(page, 10);
  // }

  // =========================================================================
  // SEARCH REQUEST
  // =========================================================================

  /**
   * Perform a search based on the current query and filter state.
   * @returns {Promise<void>} Resolves when the search is complete.
   */
  async search() {
    // Determine the query to use for the search, ensuring it meets the minimum length requirement
    const query =
      this.state.query.length >= this.minimumQueryLength
        ? this.state.query
        : "";

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
      const visibleProductCount = this.renderResults(
        data.matches,
        data.ranking,
      );

      // Log the search query, parameters, response, and visible product count for debugging purposes
      this.logSearch(data, params, response, visibleProductCount);
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
    console.log("Filters:", this.state.filters);
    console.log("Request:", `${ESSS.endpoint}?${params}`);
    console.log("Index:", response.headers.get("X-ESSS-Index-Source"));
    console.log("Indexed batches:", response.headers.get("X-ESSS-Index-Count"));
    console.log("Matching products on page:", visibleProductCount);
    console.log("Matching batches:", data.count);
    console.table(ranking);
    console.groupEnd();
  }

  // =========================================================================
  // LOADING INDICATOR
  // =========================================================================

  /**
   * Create a loading indicator element and append it to the product list.
   * The loading indicator is shown while an API request is in progress.
   */
  createLoadingIndicator() {
    this.loading = document.createElement("div");
    this.loading.className = "es-smart-search-loading";
    this.loading.setAttribute("role", "status");
    this.loading.setAttribute("aria-live", "polite");
    this.loading.innerHTML =
      '<span aria-hidden="true"></span><span class="screen-reader-text">Searching</span>';
    this.productList.appendChild(this.loading);
  }

  /**
   * Toggle the request loading overlay and input busy state.
   *
   * @param {boolean} isLoading Whether the request is in progress.
   */
  setLoading(isLoading) {
    // If the loading indicator is active, reposition it to stay centered over the visible product area
    if (isLoading) this.positionLoading();

    // Toggle the "is-active" class on the loading indicator based on whether a request is in progress
    this.loading?.classList.toggle("is-active", isLoading);

    // Update the input's aria-busy attribute to indicate whether the input is currently busy with a request
    if (this.input)
      this.input.setAttribute("aria-busy", isLoading ? "true" : "false");
  }

  /**
   * Position the loading indicator over the visible product area, ensuring it is centered and sized correctly.
   * @returns
   */
  positionLoading() {
    // If the loading indicator or product list is not available, exit the function early
    if (!this.loading || !this.productList) return;

    // Get the bounding rectangle of the product list to determine its position and size
    const bounds = this.productList.getBoundingClientRect();

    // Calculate the top, bottom, left, and right positions for the loading indicator, ensuring it stays within the viewport
    const top = Math.max(bounds.top, 0);

    // Calculate the bottom position, ensuring it does not exceed the window's inner height
    const bottom = Math.min(bounds.bottom, window.innerHeight);

    // Calculate the left position, ensuring it does not go below 0
    const left = Math.max(bounds.left, 0);

    // Calculate the right position, ensuring it does not exceed the window's inner width
    const right = Math.min(bounds.right, window.innerWidth);

    // Set the loading indicator's position and size based on the calculated values
    this.loading.style.top = `${top}px`;
    this.loading.style.left = `${left}px`;
    this.loading.style.width = `${Math.max(right - left, 0)}px`;
    this.loading.style.height = `${Math.max(bottom - top, 0)}px`;
  }

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
