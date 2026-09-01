import { DisplayService } from "./display.js";
import { UrlService } from "./url.js";
import { SearchService } from "./search.js";
import { FilterService } from "./filters.js";
import { LoadingService } from "./loading.js";
import { PaginationService } from "./pagination.js";
import { Helpers } from "./helpers.js";
import { Events } from "./events.js";
import { SearchReportingService } from "./Reporting/searchReporting.js";

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
    this.events = new Events(this);
    this.reportingService = new SearchReportingService(this);

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
    this.events.bindEvents();

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
    if (this.searchService.hasActiveState()) {
      this.searchService.execute();
    }
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
