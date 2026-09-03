export class PaginationService {
  constructor(app) {
    // Explicitly save a reference to the main SmartSearch instance
    this.app = app;
  }

  /**
   * Paginate an array of items into pages based on the current itemsPerPage setting.
   * @param {Array} items Array of items to paginate.
   * @returns {Object} An object where keys are page numbers and values are arrays of items for that page.
   */
  paginate(items) {
    // Get the number of items to show per page
    const perPage = this.itemsPerPage();

    // Reduce the items array into an object where keys are page numbers and values are arrays of items for that page
    return items.reduce((pages, item, index) => {
      // Calculate the page number for the current item based on its index and the number of items per page
      const pageNumber = Math.floor(index / perPage) + 1;

      // Create a key for the current page in the pages object
      const pageKey = `page${pageNumber}`;

      // Initialize the array for the current page if it doesn't exist yet
      pages[pageKey] ??= [];

      // Add the current item to the array for the current page
      pages[pageKey].push(item);

      // Return the updated pages object for the next iteration
      return pages;
    }, {});
  }

  /**
   * Return the number of parent product cards to show on each page.
   *
   * The page size follows the existing responsive pagination rules used by
   * the previous TileFilter implementation.
   *
   * @returns {number} Number of parent cards per page.
   */
  itemsPerPage() {
    // Get current window width
    const width = window.innerWidth;

    // Determine number of items to show per page
    if (width < 768) return 16;
    if (width < 1365) return 30;
    if (width < 1800) return 36;

    // For very large screens, show 40 items per page
    return 40;
  }

  /**
   * Paginate the matching parent product cards and return a Set of items for the current page.
   * @param {Array} matchingParents Array of matching parent product cards.
   * @returns {Set} Set of items for the current page.
   */
  paginateMatchingProducts(matchingParents) {
    this.app.parentProducts = matchingParents;
    this.app.state.pagination.pages = this.paginate(matchingParents);

    this.app.state.page = Math.min(
      this.app.state.page,
      Object.keys(this.app.state.pagination.pages).length || 1,
    );

    return new Set(this.getCurrentPageItems());
  }

  /**
   * Get the items for the current page based on the current state and pagination.
   * @returns {Array} Array of items for the current page.
   */
  getCurrentPageItems() {
    const pageKey = `page${this.app.state.page}`;

    return this.app.state.pagination.pages[pageKey] || [];
  }

  /**
   * Get the parent product elements that match the given set of matching product IDs.
   * @param {Array} products Array of product elements to filter.
   * @param {Set} matches Set of matching product IDs.
   * @returns {Array} Array of matching parent product elements.
   */
  getMatchingParents(products, matches) {
    // Filter the products array to find the parent product elements that match the given set of matching product IDs
    return products.filter((product) => {
      // Collect the parent batch ID and all child batch IDs for the current product card
      const ids = [
        product.dataset.id,
        ...Array.from(product.querySelectorAll("[data-id]")).map(
          (child) => child.dataset.id,
        ),
      ];

      // Check if any of the product's batch IDs are present in the set of matching IDs
      return ids.some(
        (id) => Number.isFinite(Number(id)) && matches.has(Number(id)),
      );
    });
  }

  /**
   * Update the pagination count display based on the current page and total number of parent product cards.
   * @returns {void} Does not return a value.
   */
  updatePaginationCount() {
    // If the stats element is not available, exit
    if (!this.app.stats) {
      return;
    }

    // Get the total number of parent product cards
    const total = this.app.parentProducts.length;

    // Get the number of items to show per page based on the current window width
    const perPage = this.itemsPerPage();

    // Calculate the starting index of the current page based on the total number of items, current page number, and items per page
    const start = total ? (this.app.state.page - 1) * perPage + 1 : 0;

    // Calculate the ending index of the current page based on the total number of items, current page number, and items per page
    const end = Math.min(this.app.state.page * perPage, total);

    // Update the stats element to show the range of items being displayed and the total number of items, or show "0 of 0" if there are no items
    this.app.stats.textContent = total
      ? `${start} to ${end} of ${total}`
      : "0 of 0";
  }

  /**
   * Render the current page of parent product cards based on the current state and pagination.
   * This method toggles the visibility of product cards based on whether they are in the current page items and are parent products.
   * It ensures that only the relevant products for the current page are displayed to the user.
   * @returns {void}
   */
  renderCurrentPage() {
    // Get the set of items for the current page based on the current state and pagination
    const currentPageItems = new Set(this.getCurrentPageItems());

    // Loop over original product order and toggle visibility based on whether the product is in the current page items and is a parent product
    this.app.originalProductOrder.forEach((product) => {
      const visible =
        this.app.parentProducts.includes(product) &&
        currentPageItems.has(product);

      // Toggle the "es-smart-search-hidden" class on the product card based on its visibility
      product.classList.toggle("es-smart-search-hidden", !visible);
    });
  }

  /**
   * Add pagination buttons to the pagination container based on the current state and total pages.
   * @returns {void} Does not return a value.
   */
  addPaginationButtons() {
    const container = this.app.paginationContainer;
    const totalPages = Object.keys(this.app.state.pagination.pages).length;
    const currentPage = this.app.state.page;

    if (!container || totalPages <= 1) {
      return;
    }

    container.innerHTML = "";

    container.appendChild(
      this._createPaginationButton("«", "prev", "mixitup-control-prev"),
    );

    this._getCondensedPages(totalPages, currentPage).forEach((page) => {
      const isFirst = page === 1;
      const isLast = page === totalPages;

      container.appendChild(
        this._createPaginationButton(
          isFirst ? "First" : isLast ? "Last" : page,
          page,
          page === currentPage
            ? "mixitup-control mixitup-control-active"
            : "mixitup-control",
        ),
      );
    });

    container.appendChild(
      this._createPaginationButton("»", "next", "mixitup-control-next"),
    );
  }

  /**
   *
   * determine which pages to show
   * includes first, last, current, neighbors, and extra pages at edges
   * @param {*} total
   * @param {*} current
   * @returns
   */
  _getCondensedPages(total, current) {
    const pages = new Set([1, total, current]);

    // previous page
    if (current > 1) pages.add(current - 1);

    // next page
    if (current < total) pages.add(current + 1);

    // If on first page, add extra neighbors
    if (current === 1 && total > 3) pages.add(2).add(3);

    // If on last page, add extra neighbors
    if (current === total && total > 3) pages.add(total - 1).add(total - 2);

    // return sorted array
    return [...pages].sort((a, b) => a - b);
  }

  /**
   *
   * create a button element
   * attaches click handler to update pagination state and scroll to top
   * @param {string} label
   * @param {*} page
   * @param {string} extraClasses
   * @returns
   */
  _createPaginationButton(label, page, extraClasses = "") {
    const btn = document.createElement("button");
    btn.type = "button";
    btn.textContent = label;
    btn.className = `mixitup-control ${extraClasses}`.trim();
    btn.dataset.page = page;

    btn.addEventListener("click", (e) => {
      e.preventDefault();

      const newPage = this._resolvePage(page);

      // Scroll smoothly to top
      window.scrollTo({ top: 0, behavior: "smooth" });

      // Update state
      this.app.state.page = newPage;

      // Update URL
      this.app.urlService.writeUrlState();

      // Render current page
      this.renderCurrentPage();

      // Update pagination buttons
      this.addPaginationButtons();

      // Update pagination count
      this.updatePaginationCount();
    });

    return btn;
  }

  /**
   * resolve page number from button input
   * handles 'prev', 'next', or numeric pages
   * @param {string|number} page
   * @returns {number} The page number to navigate to
   */
  _resolvePage(page) {
    const current = this.app.state.page;
    const total = Object.keys(this.app.state.pagination.pages).length;
    if (page === "prev") return Math.max(current - 1, 1);
    if (page === "next") return Math.min(current + 1, total);
    return parseInt(page, 10);
  }

  /**
   * Reset the product list to show all products and update pagination.
   * This method resets the parent products to the original product order,
   * recalculates pagination, and sets the current page to 1.
   * @returns {void}
   */
  resetToAllProducts() {
    this.app.parentProducts = this.app.originalProductOrder;
    this.app.state.pagination.pages = this.paginate(this.app.parentProducts);

    this.app.state.page = 1;
    this.updatePaginationCount();
  }
}
