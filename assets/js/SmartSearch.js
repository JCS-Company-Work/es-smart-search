import { DisplayService } from "./display.js";
import { UrlService } from "./url.js";
import { SearchService } from "./search.js";
import { FilterService } from "./filters.js";
import { LoadingService } from "./loading.js";
import { PaginationService } from "./pagination.js";
import { Helpers } from "./helpers.js";

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
    this.displayService = new DisplayService(this);
    this.helpers = new Helpers(this);

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
    this.displayService.renderFilterState();

    // Render the reset button state based on whether a query or filter is active
    this.displayService.renderResetState();

    // Render the initial query display based on the current state
    this.paginationService.resetToAllProducts();
    this.paginationService.updatePaginationCount();

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
      this.displayService.updateQueryDisplay();
      this.state.page = 1;
      this.urlService.writeUrlState();
      this.displayService.renderResetState();
      this.scheduleSearch();
    });

    // Bind click events to filter controls within fieldsets that have a data-filter-group attribute
    this.filterService.bindFilters();

    // Bind click event to the reset button to clear the search state
    this.resetButton?.addEventListener("click", (event) => {
      event.preventDefault();
      this.displayService.reset();
    });

    // Handle browser navigation events to restore state from the URL
    window.addEventListener("popstate", () => {
      this.urlService.readUrlState();
      this.displayService.renderFilterState();
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
  // PRODUCT HELPERS
  // =========================================================================

  // /**
  //  * Return all batch IDs represented by a rendered product card.
  //  *
  //  * @param {HTMLElement} product Rendered product card.
  //  * @returns {number[]} Parent and child batch IDs.
  //  */
  // getProductIds(product) {
  //   return [
  //     product.dataset.id,
  //     ...Array.from(product.querySelectorAll("[data-id]")).map(
  //       (child) => child.dataset.id,
  //     ),
  //   ]
  //     .map(Number)
  //     .filter(Number.isFinite);
  // }
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
