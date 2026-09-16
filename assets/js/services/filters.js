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
      .querySelectorAll("#accordion fieldset[data-filter-group] .control")
      .forEach((button) => {
        button.addEventListener("click", () => this.handleFilterClick(button));
      });
  }

  /**
   * Bind click events to the sort dropdown and handle its state updates.
   */
  bindSortDropdown() {
    const dropdown = document.querySelector(
      'fieldset[data-filter-group="sorting"] .dropdown',
    );
    console.log(dropdown);
    if (!dropdown) return;

    dropdown.addEventListener("click", (event) => {
      // Find the closest dropdown menu option that was clicked
      const option = event.target.closest(".dropdown-menu li");

      // If an option within the dropdown menu is clicked, update the sort label and close the dropdown
      if (option) {
        // Update the sort label to reflect the selected option
        dropdown.querySelector(".sort-label").textContent =
          option.textContent.trim();

        // Close the dropdown after selecting an option
        dropdown.classList.remove("active");

        return;
      }

      // If the click was not on a dropdown menu option, check if it was on the select element to toggle the dropdown
      if (event.target.closest(".select")) {
        dropdown.classList.toggle("active");
      }
    });

    dropdown.addEventListener("focusout", () => {
      dropdown.classList.remove("active");
    });
  }

  /**
   * Bind click events to sorting controls and handle sort state updates.
   */
  bindSorting() {
    document
      .querySelectorAll("fieldset[data-filter-group='sorting'] .control-sort")
      .forEach((button) => {
        button.addEventListener("click", () => {
          // Extract the sort key and direction from the clicked button's dataset
          const [key, direction] = button.dataset.sort.split(":");

          // Update the sort state in the main app instance
          this.app.state.sort = {
            key,
            direction,
          };

          // Sort the current products based on the selected attribute
          this.app.displayService.sortCurrentProducts();
        });
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
    this.app.displayService.renderResetState();

    // Perform a new search with the updated filter state
    this.app.searchService.execute();
  }
}
