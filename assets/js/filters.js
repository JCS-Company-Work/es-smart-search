export class FilterService {
  constructor(app) {
    // Explicitly save a reference to the main SmartSearch instance
    this.app = app;
  }

  /**
   * Bind click events to filter controls and handle filter state updates.
   */
  bindFilters() {
    // Bind click events to filter controls within fieldsets that have a data-filter-group attribute
    document
      .querySelectorAll("fieldset[data-filter-group] .control")
      .forEach((button) => {
        button.addEventListener("click", () => this.handleFilterClick(button));
      });
  }

  /**
   * Update filter state after a filter control is clicked.
   *
   * @param {HTMLButtonElement} button The clicked filter control.
   */
  handleFilterClick(button) {
    // Ref to main SmartSearch class instance
    const app = this.app;
    // Get the filter group and value from the clicked button's dataset
    const group = button.closest("fieldset[data-filter-group]")?.dataset
      .filterGroup;

    // Get the value to toggle from the button's dataset
    const value = button.dataset.toggle;

    // If either the group or value is missing, return
    if (!group || !value) {
      return;
    }

    // Toggle the active state of the clicked button
    button.classList.toggle("mixitup-control-active");

    // Update the filter state for the group based on the active buttons
    app.state.filters[group] = Array.from(
      document.querySelectorAll(
        `fieldset[data-filter-group="${CSS.escape(group)}"] .control.mixitup-control-active`,
      ),
    ).map((activeButton) => activeButton.dataset.toggle);

    // If no active filters remain for the group, remove the group from the state
    if (!app.state.filters[group].length) {
      delete app.state.filters[group];
    }

    // Reset the page number to 1 when filters change
    app.state.page = 1;

    // Update the URL hash to reflect the new state
    this.app.urlService.writeUrlState();

    // Update the reset button state based on whether a query or filter is active
    this.app.renderResetState();

    // Perform a new search with the updated filter state
    this.app.searchService.execute();
  }
}
