import { writeUrlState } from "./url.js";

export function bindFilters() {
  // Bind click events to filter controls within fieldsets that have a data-filter-group attribute
  document
    .querySelectorAll("fieldset[data-filter-group] .control")
    .forEach((button) => {
      button.addEventListener("click", () =>
        handleFilterClick.call(this, button),
      );
    });
}

/**
 * Update filter state after a filter control is clicked.
 *
 * @param {HTMLButtonElement} button The clicked filter control.
 */
function handleFilterClick(button) {
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
  this.state.filters[group] = Array.from(
    document.querySelectorAll(
      `fieldset[data-filter-group="${CSS.escape(group)}"] .control.mixitup-control-active`,
    ),
  ).map((activeButton) => activeButton.dataset.toggle);

  // If no active filters remain for the group, remove the group from the state
  if (!this.state.filters[group].length) {
    delete this.state.filters[group];
  }

  // Reset the page number to 1 when filters change
  this.state.page = 1;

  // Update the URL hash to reflect the new state
  writeUrlState.call(this);

  // Update the reset button state based on whether a query or filter is active
  this.renderResetState();

  // Perform a new search with the updated filter state
  this.search();
}
