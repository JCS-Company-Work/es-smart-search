/**
 * Manage the dynamic text search weighting matrix repeater inside WordPress admin.
 */
class ESSS_Admin {
  /**
   * Set up the repeater elements and kick off event listeners.
   */
  constructor() {
    this.wrapper = document.getElementById("esss-weight-matrix-wrapper");
    this.container = document.getElementById("esss-weight-rows-container");
    this.addButton = document.getElementById("esss-add-weight-row");

    // Fail-safe exit if the structural elements are missing from the current view
    if (!this.wrapper || !this.container || !this.addButton) {
      return;
    }

    // Initialize the master catalog options cache from the DOM metadata block
    this.allFields =
      JSON.parse(this.wrapper.getAttribute("data-all-fields")) || {};

    this.init();
  }

  /**
   * Start the engine processes and initial rendering runs
   */
  init() {
    this.bindEvents();
    this.updateAllDropdowns();
    this.initTabs();
  }

  /**
   * Bind core interactivity routines using structural context pointers
   */
  bindEvents() {
    // Handle explicit row additions
    this.addButton.addEventListener("click", (e) => this.handleRowAddition(e));

    // Intercept dropdown changes using dynamic event delegation
    this.container.addEventListener("change", (e) => {
      if (e.target && e.target.classList.contains("esss-field-selector")) {
        this.updateAllDropdowns();
      }
    });

    // Intercept row deletions using dynamic event delegation
    this.container.addEventListener("click", (e) => {
      if (e.target && e.target.classList.contains("esss-remove-weight-row")) {
        e.preventDefault();
        e.target.closest(".esss-weight-row").remove();
        this.updateAllDropdowns();
      }
    });
  }

  /**
   * Append a clean, unassigned row template grid to the DOM canvas
   */
  handleRowAddition(e) {
    e.preventDefault();

    const tr = document.createElement("tr");
    tr.className = "esss-weight-row";
    tr.innerHTML = `
            <td>
                <select name="esss_weight_text[keys][]" class="esss-field-selector" style="width: 100%;"></select>
            </td>
            <td>
                <input type="number" name="esss_weight_text[values][]" value="50" class="small-text" min="0" max="100" style="width: 100%;">
            </td>
            <td style="text-align: center;">
                <button type="button" class="button esss-remove-weight-row" style="color: #b32d2e; border-color: #b32d2e;">Delete</button>
            </td>
        `;

    this.container.appendChild(tr);
    this.updateAllDropdowns();
  }

  /**
   * Rebuild and strip chosen items from available select field option pools
   */
  updateAllDropdowns() {
    const selectors = document.querySelectorAll(".esss-field-selector");

    // Identify every option currently claimed by active inputs on the viewport screen canvas
    const selectedValues = Array.from(selectors)
      .map((sel) => sel.value)
      .filter((val) => val !== "");

    selectors.forEach((select) => {
      const currentValue =
        select.value || select.getAttribute("data-selected") || "";

      // Flush out existing option strings before rebuilding
      select.innerHTML =
        '<option value="" disabled selected>Select attribute field...</option>';

      // Evaluate fields dynamically against ownership vectors
      for (const [key, label] of Object.entries(this.allFields)) {
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

      // Lock selected attributes back to their target element structures
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
        document.querySelector(targetId).style.display =
          targetId === "#esss-tab-dictionary" ? "flex" : "block";
      });
    });
  }
}

// Safely instantiate the class control context upon document lifecycle ready state
document.addEventListener("DOMContentLoaded", () => {
  new ESSS_Admin();
});
