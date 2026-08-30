export class Events {
  constructor(app) {
    // Explicitly save a reference to the main SmartSearch instance
    this.app = app;
  }

  /**
   * Bind event listeners for input changes, filter clicks, reset button, navigation, and layout changes.
   * The input event triggers a search when the user types a query.
   * Filter clicks update the filter state and trigger a search.
   * The reset button clears the query and filters.
   * The popstate event handles browser navigation to restore state.
   * Scroll and resize events reposition the loading indicator.
   */
  bindEvents() {
    this.app.input.addEventListener("input", () => {
      this.app.state.query = this.app.input.value.trim();
      this.app.displayService.updateQueryDisplay();
      this.app.state.page = 1;
      this.app.urlService.writeUrlState();
      this.app.displayService.renderResetState();
      this.scheduleSearch();
    });

    // Bind click events to filter controls within fieldsets that have a data-filter-group attribute
    this.app.filterService.bindFilters();

    // Bind click event to the reset button to clear the search state
    this.app.resetButton?.addEventListener("click", (event) => {
      event.preventDefault();
      this.app.displayService.reset();
    });

    // Handle browser navigation events to restore state from the URL
    window.addEventListener("popstate", () => {
      this.app.urlService.readUrlState();
      this.app.displayService.renderFilterState();
      this.app.searchService.execute();
    });

    // Reposition the loading indicator when the user scrolls or resizes the window
    window.addEventListener(
      "scroll",
      () => this.app.loadingService.positionLoading(),
      {
        passive: true,
      },
    );

    // Reposition the loading indicator when the window is resized
    window.addEventListener("resize", () =>
      this.app.loadingService.positionLoading(),
    );
  }

  /**
   * Schedule a search to be performed after a short delay, allowing for debouncing of rapid input changes.
   * This prevents excessive API requests while the user is typing or changing filters.
   */
  scheduleSearch() {
    clearTimeout(this.app.searchTimer);
    this.app.loadingService.setLoading(true);
    this.app.searchTimer = setTimeout(
      () => this.app.searchService.execute(),
      300,
    );
  }
}
