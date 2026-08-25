/**
 * Coordinates search state, URL state, API requests and product visibility.
 */
class ESSmartSearch {
  /** Initialise the search state and DOM references. */
  constructor() {
    this.state = {
      query: "",
      filters: {},
      page: 1,
    };

    this.input = null;
    this.productList = null;
    this.stats = null;
    this.noResults = null;
    this.resetButton = null;
    this.loading = null;
    this.requestController = null;
    this.searchTimer = null;
    this.minimumQueryLength = 3;
    this.originalProductOrder = [];
  }

  /** Bind the search UI and restore any state encoded in the URL. */
  init() {
    this.input = document.querySelector(".live-filter");
    this.productList = document.querySelector(".product-list");
    this.stats = document.querySelector(".mixitup-page-stats");
    this.noResults = document.querySelector(".no-results");
    this.resetButton = document.getElementById("reset-filters");

    if (!this.input || !this.productList || !window.ESSS) {
      return;
    }

    this.originalProductOrder = Array.from(
      this.productList.querySelectorAll(":scope > li"),
    );

    this.createLoadingIndicator();
    this.readUrlState();
    this.bindEvents();
    this.renderFilterState();
    this.renderResetState();

    if (window.ESSS.debug) {
      console.info("[ES Smart Search] initialised", {
        minimumQueryLength: this.minimumQueryLength,
        endpoint: ESSS.endpoint,
      });
    }

    if (this.hasActiveState()) {
      this.search();
    }
  }

  /** @returns {boolean} Whether a query or filter is active. */
  hasActiveState() {
    return Boolean(this.state.query || Object.keys(this.state.filters).length);
  }

  /** Add the loading indicator used while a request is in flight. */
  createLoadingIndicator() {
    this.loading = document.createElement("div");
    this.loading.className = "es-smart-search-loading";
    this.loading.setAttribute("role", "status");
    this.loading.setAttribute("aria-live", "polite");
    this.loading.innerHTML =
      '<span aria-hidden="true"></span><span class="screen-reader-text">Searching</span>';
    this.productList.appendChild(this.loading);
  }

  /** Attach input, filter, reset, navigation and viewport listeners. */
  bindEvents() {
    this.input.addEventListener("input", () => {
      this.state.query = this.input.value.trim();
      this.state.page = 1;
      this.writeUrlState();
      this.renderResetState();
      this.scheduleSearch();
    });

    document
      .querySelectorAll("fieldset[data-filter-group] .control")
      .forEach((button) => {
        button.addEventListener("click", () => this.handleFilterClick(button));
      });

    this.resetButton?.addEventListener("click", (event) => {
      event.preventDefault();
      this.reset();
    });

    window.addEventListener("popstate", () => {
      this.readUrlState();
      this.renderFilterState();
      this.search();
    });

    window.addEventListener("scroll", () => this.positionLoading(), {
      passive: true,
    });
    window.addEventListener("resize", () => this.positionLoading());
  }

  /** @param {HTMLButtonElement} button The filter control that was clicked. */
  handleFilterClick(button) {
    const group = button.closest("fieldset[data-filter-group]")?.dataset
      .filterGroup;
    const value = button.dataset.toggle;

    if (!group || !value) {
      return;
    }

    button.classList.toggle("mixitup-control-active");
    this.state.filters[group] = Array.from(
      document.querySelectorAll(
        `fieldset[data-filter-group="${CSS.escape(group)}"] .control.mixitup-control-active`,
      ),
    ).map((activeButton) => activeButton.dataset.toggle);

    if (!this.state.filters[group].length) {
      delete this.state.filters[group];
    }

    this.state.page = 1;
    this.writeUrlState();
    this.renderResetState();
    this.search();
  }

  /** Restore query, filters and page from the URL hash. */
  readUrlState() {
    const hash = window.location.hash.replace(/^#/, "");
    const nextState = { query: "", filters: {}, page: 1 };

    if (!hash) {
      this.state = nextState;
      return;
    }

    hash.split("&").forEach((part) => {
      const separator = part.indexOf("=");
      if (separator < 0) return;

      const key = decodeURIComponent(part.slice(0, separator));
      const value = decodeURIComponent(part.slice(separator + 1));

      if (key === "textsearch") {
        nextState.query = value;
      } else if (key === "page") {
        nextState.page = parseInt(value, 10) || 1;
      } else if (value) {
        nextState.filters[key] = value.split(",");
      }
    });

    this.state = nextState;
  }

  /** Serialise the current state into a shareable URL hash. */
  writeUrlState() {
    const parts = [];
    const hasFilters = Object.keys(this.state.filters).length > 0;
    const hasSearchableQuery =
      this.state.query.length >= this.minimumQueryLength;
    const urlHasQuery = window.location.hash.includes("textsearch=");

    if (hasSearchableQuery) {
      parts.push(`textsearch=${encodeURIComponent(this.state.query)}`);
    } else if (this.state.query && !hasFilters && !urlHasQuery) {
      return;
    }

    Object.entries(this.state.filters).forEach(([group, values]) => {
      if (values.length) {
        parts.push(
          `${encodeURIComponent(group)}=${encodeURIComponent(values.join(","))}`,
        );
      }
    });

    parts.push(`page=${this.state.page}`);
    const hash = `#${parts.join("&")}`;
    const url = `${window.location.pathname}${window.location.search}${hash}`;

    if (
      url !==
      `${window.location.pathname}${window.location.search}${window.location.hash}`
    ) {
      window.history.pushState(null, "", url);
    }
  }

  /** Reflect the current state in the input and filter controls. */
  renderFilterState() {
    if (this.input) {
      this.input.value = this.state.query;
      this.input.classList.toggle(
        "mixitup-control-active",
        Boolean(this.state.query),
      );
    }

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

  /** Toggle the reset button according to the current state. */
  renderResetState() {
    this.resetButton?.classList.toggle("active", this.hasActiveState());
  }

  /** Debounce text input before starting an API search. */
  scheduleSearch() {
    clearTimeout(this.searchTimer);
    this.setLoading(true);
    this.searchTimer = setTimeout(() => this.search(), 300);
  }

  /** Fetch matches for the current state and render them. */
  async search() {
    const query =
      this.state.query.length >= this.minimumQueryLength
        ? this.state.query
        : "";
    const hasFilters = Object.keys(this.state.filters).length > 0;

    if (!query && !hasFilters) {
      if (window.ESSS.debug && this.state.query) {
        console.info("[ES Smart Search] query skipped", {
          query: this.state.query,
          minimumQueryLength: this.minimumQueryLength,
        });
      }
      this.showAllProducts();
      this.setLoading(false);
      return;
    }

    this.requestController?.abort();
    this.requestController = new AbortController();
    const currentController = this.requestController;
    this.setLoading(true);

    const params = new URLSearchParams({
      q: query,
      filters: JSON.stringify(this.state.filters),
    });

    try {
      const response = await fetch(`${ESSS.endpoint}?${params}`, {
        signal: currentController.signal,
      });

      if (!response.ok) {
        throw new Error(`Smart search request failed: ${response.status}`);
      }

      const data = await response.json();
      const visibleProductCount = this.renderResults(
        data.matches,
        data.ranking,
      );
      this.logSearch(data, params, response, visibleProductCount);
    } catch (error) {
      if (error.name !== "AbortError") {
        console.error("Smart search failed:", error);
      }
    } finally {
      if (
        this.requestController === currentController &&
        !currentController.signal.aborted
      ) {
        this.setLoading(false);
      }
    }
  }

  /** Log the query, index details and ranking rationale for prototype demos. */
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

  /**
   * Apply ranked visibility and order to the rendered product cards.
   *
   * @param {Array<number|string>} matchIds Batch IDs returned by the API.
   * @param {Array<object>} ranking Ranked API results with scores and IDs.
   */
  renderResults(matchIds, ranking = []) {
    const matches = new Set(
      (Array.isArray(matchIds) ? matchIds : []).map(Number),
    );
    const rankedIds = (Array.isArray(ranking) ? ranking : []).map((result) =>
      Number(result.id),
    );
    const rankById = new Map(rankedIds.map((id, index) => [id, index]));
    let visibleCount = 0;

    const products = Array.from(
      this.productList.querySelectorAll(":scope > li"),
    );

    products.forEach((product) => {
      const ids = [
        product.dataset.id,
        ...Array.from(product.querySelectorAll("[data-id]")).map(
          (child) => child.dataset.id,
        ),
      ];
      const visible = ids.some(
        (id) => Number.isFinite(Number(id)) && matches.has(Number(id)),
      );

      product.classList.toggle("es-smart-search-hidden", !visible);
      if (visible) visibleCount += 1;
    });

    products.sort((left, right) => {
      const leftRank = Math.min(
        ...this.getProductIds(left).map(
          (id) => rankById.get(id) ?? Number.MAX_SAFE_INTEGER,
        ),
      );
      const rightRank = Math.min(
        ...this.getProductIds(right).map(
          (id) => rankById.get(id) ?? Number.MAX_SAFE_INTEGER,
        ),
      );

      if (leftRank !== rightRank) {
        return leftRank - rightRank;
      }

      return (
        this.originalProductOrder.indexOf(left) -
        this.originalProductOrder.indexOf(right)
      );
    });

    products.forEach((product) => this.productList.appendChild(product));

    if (this.stats) {
      this.stats.textContent = `${visibleCount} matching products`;
      this.stats.style.display = visibleCount ? "" : "none";
    }

    if (this.noResults) {
      this.noResults.style.display = visibleCount ? "none" : "block";
    }

    return visibleCount;
  }

  /** @param {HTMLElement} product @returns {number[]} Parent and child batch IDs. */
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

  /** Restore all product cards when no search state is active. */
  showAllProducts() {
    this.originalProductOrder.forEach((product) =>
      this.productList.appendChild(product),
    );

    this.originalProductOrder.forEach((product) => {
      product.classList.remove("es-smart-search-hidden");
    });

    if (this.noResults) this.noResults.style.display = "none";
    if (this.stats) this.stats.style.display = "";
  }

  /** Clear query, filters, URL state and product visibility. */
  reset() {
    this.state = { query: "", filters: {}, page: 1 };
    this.renderFilterState();
    this.renderResetState();
    this.writeUrlState();
    this.showAllProducts();
  }

  /** @param {boolean} isLoading Whether the request overlay is visible. */
  setLoading(isLoading) {
    if (isLoading) this.positionLoading();
    this.loading?.classList.toggle("is-active", isLoading);
    if (this.input)
      this.input.setAttribute("aria-busy", isLoading ? "true" : "false");
  }

  /** Keep the loading overlay centred over the visible product area. */
  positionLoading() {
    if (!this.loading || !this.productList) return;

    const bounds = this.productList.getBoundingClientRect();
    const top = Math.max(bounds.top, 0);
    const bottom = Math.min(bounds.bottom, window.innerHeight);
    const left = Math.max(bounds.left, 0);
    const right = Math.min(bounds.right, window.innerWidth);

    this.loading.style.top = `${top}px`;
    this.loading.style.left = `${left}px`;
    this.loading.style.width = `${Math.max(right - left, 0)}px`;
    this.loading.style.height = `${Math.max(bottom - top, 0)}px`;
  }
}

document.addEventListener(
  "DOMContentLoaded",
  () => {
    window.esSmartSearch = new ESSmartSearch();
    window.esSmartSearch.init();
  },
  { once: true },
);
