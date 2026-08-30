export class LoadingService {
  constructor(app) {
    // Explicitly save a reference to the main SmartSearch instance
    this.app = app;
  }

  /**
   * Create a loading indicator element and append it to the product list.
   * The loading indicator is shown while an API request is in progress.
   */
  createLoadingIndicator() {
    this.app.loading = document.createElement("div");
    this.app.loading.className = "es-smart-search-loading";
    this.app.loading.setAttribute("role", "status");
    this.app.loading.setAttribute("aria-live", "polite");
    this.app.loading.innerHTML =
      '<span aria-hidden="true"></span><span class="screen-reader-text">Searching</span>';
    this.app.productList.appendChild(this.app.loading);
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
    this.app.loading?.classList.toggle("is-active", isLoading);

    // Update the input's aria-busy attribute to indicate whether the input is currently busy with a request
    if (this.app.input)
      this.app.input.setAttribute("aria-busy", isLoading ? "true" : "false");
  }

  /**
   * Position the loading indicator over the visible product area, ensuring it is centered and sized correctly.
   * @returns
   */
  positionLoading() {
    // If the loading indicator or product list is not available, exit the function early
    if (!this.app.loading || !this.app.productList) return;

    // Get the bounding rectangle of the product list to determine its position and size
    const bounds = this.app.productList.getBoundingClientRect();

    // Calculate the top, bottom, left, and right positions for the loading indicator, ensuring it stays within the viewport
    const top = Math.max(bounds.top, 0);

    // Calculate the bottom position, ensuring it does not exceed the window's inner height
    const bottom = Math.min(bounds.bottom, window.innerHeight);

    // Calculate the left position, ensuring it does not go below 0
    const left = Math.max(bounds.left, 0);

    // Calculate the right position, ensuring it does not exceed the window's inner width
    const right = Math.min(bounds.right, window.innerWidth);

    // Set the loading indicator's position and size based on the calculated values
    this.app.loading.style.top = `${top}px`;
    this.app.loading.style.left = `${left}px`;
    this.app.loading.style.width = `${Math.max(right - left, 0)}px`;
    this.app.loading.style.height = `${Math.max(bottom - top, 0)}px`;
  }
}
