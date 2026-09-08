/**
 * Manage the global WordPress Admin configurations and dynamic repeater inputs.
 */
class ESSS_Admin {
  /**
   * Set up global view handles and initialize all functional block components.
   */
  constructor() {
    this.initTabs();

    // Query all active weighting manager rows across the canvas workspace
    const managerBlocks = document.querySelectorAll(
      ".esss-weight-manager-block",
    );
    managerBlocks.forEach((block) => {
      this.initWeightBlock(block);
    });
  }

  /**
   * Initialize a specific repeatable block layout instance cleanly
   */
  initWeightBlock(block) {
    const container = block.querySelector(".esss-weight-rows-container");
    const addButton = block.querySelector(".esss-add-weight-row");

    if (!container || !addButton) return;

    const isFilterType = block.getAttribute("data-type") === "filter";
    const allFields = JSON.parse(block.getAttribute("data-all-fields")) || {};
    const inputName = isFilterType ? "esss_weight_filters" : "esss_weight_text";

    // Rebuild initial state options on screen mount layout
    this.updateDropdownOptions(block, allFields);

    // Bind Event: Row creation trigger (delegates to our dedicated handler method)
    addButton.addEventListener("click", (e) => {
      this.handleRowAddition(e, container, inputName, block, allFields);
    });

    // Bind Event: Dropdown select choice tracking modifications via event delegation
    container.addEventListener("change", (e) => {
      if (e.target && e.target.classList.contains("esss-field-selector")) {
        this.updateDropdownOptions(block, allFields);
      }
    });

    // Bind Event: Row target destructions via event delegation
    container.addEventListener("click", (e) => {
      if (e.target && e.target.classList.contains("esss-remove-weight-row")) {
        e.preventDefault();
        e.target.closest(".esss-weight-row").remove();
        this.updateDropdownOptions(block, allFields);
      }
    });
  }

  /**
   * Dedicated method to handle appending a brand new row structure
   */
  handleRowAddition(e, container, inputName, block, allFields) {
    e.preventDefault();

    const tr = document.createElement("tr");
    tr.className = "esss-weight-row";
    tr.innerHTML = `
        <td><select name="${inputName}[keys][]" class="esss-field-selector" style="width: 100%;"></select></td>
        <td><input type="number" name="${inputName}[values][]" value="50" class="small-text" min="0" max="100" style="width: 100%;"></td>
        <td style="text-align: center;"><button type="button" class="button esss-remove-weight-row" style="color: #b32d2e; border-color: #b32d2e;">Delete</button></td>
    `;

    container.appendChild(tr);
    this.updateDropdownOptions(block, allFields);
  }

  /**
   * Rebuild available dropdown option arrays inside the scope of a single block wrapper matrix
   */
  updateDropdownOptions(block, allFields) {
    const selectors = block.querySelectorAll(".esss-field-selector");
    const isFilterBlock = block.getAttribute("data-type") === "filter";

    // Identify options currently claimed *strictly within this block container context*
    const selectedValues = Array.from(selectors)
      .map((sel) => sel.value)
      .filter((val) => val !== "");

    selectors.forEach((select) => {
      const currentValue =
        select.value || select.getAttribute("data-selected") || "";

      select.innerHTML =
        '<option value="" disabled selected>Select attribute field...</option>';

      // Core filter key arrays: explicitly isolate key sets to ensure separation
      const filterKeys = [
        "colour",
        "effect",
        "category",
        "finish",
        "size",
        "dimensions",
        "usage",
        "thickness",
        "slip_rating",
        "discount",
        "quantity",
      ];

      for (const [key, label] of Object.entries(allFields)) {
        // Match Context: If we are on filters block, only allow explicit filter array slugs
        if (isFilterBlock && !filterKeys.includes(key)) {
          continue;
        }
        // Match Context: If we are on text block, explicitly block filter-only taxonomy keys
        if (
          !isFilterBlock &&
          filterKeys.includes(key) &&
          key !== "size" &&
          key !== "usage" &&
          key !== "colour" &&
          key !== "category" &&
          key !== "finish" &&
          key !== "effect"
        ) {
          // Keep your valid overlapping text attributes, but screen out strictly numeric ones
          if (
            key === "thickness" ||
            key === "slip_rating" ||
            key === "discount" ||
            key === "quantity" ||
            key === "dimensions"
          ) {
            continue;
          }
        }

        // Append options that are free or assigned to the current row
        if (!selectedValues.includes(key) || key === currentValue) {
          const option = document.createElement("option");
          option.value = key;
          option.textContent = label;

          if (key === currentValue) {
            option.selected = true;
          }
          select.appendChild(option);
        }
      }

      if (currentValue && !select.value) {
        select.value = currentValue;
      }
      select.removeAttribute("data-selected");
    });
  }

  /**
   * Initialize tabbed navigation for the admin interface.
   */
  initTabs() {
    const tabs = document.querySelectorAll(".nav-tab");
    const panes = document.querySelectorAll(".esss-tab-pane");

    tabs.forEach((tab) => {
      tab.addEventListener("click", function (e) {
        e.preventDefault();
        tabs.forEach((t) => t.classList.remove("nav-tab-active"));
        this.classList.add("nav-tab-active");

        panes.forEach((p) => (p.style.display = "none"));
        const targetId = this.getAttribute("href");

        const targetPane = document.querySelector(targetId);
        if (targetPane) {
          targetPane.style.display =
            targetId === "#esss-tab-dictionary" ? "flex" : "block";
        }
      });
    });
  }
}

// Safely instantiate the class control context upon document lifecycle ready state
document.addEventListener("DOMContentLoaded", () => {
  new ESSS_Admin();
});
