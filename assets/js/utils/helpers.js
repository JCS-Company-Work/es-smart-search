export class Helpers {
  constructor(app) {
    // Explicitly save a reference to the main SmartSearch instance
    this.app = app;
  }

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
