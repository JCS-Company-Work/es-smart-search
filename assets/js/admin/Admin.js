/**
 * Manage the global WordPress Admin configurations and dynamic repeater inputs.
 */
class ESSS_Admin {
  /**
   * Set up global view handles and initialize all functional block components.
   */
  constructor() {
    // Cache frequently accessed DOM elements for later use
    this.cacheDOMElements();

    // Initialize tab navigation and event listeners for admin interface
    this.initTabs();

    // Initialize event listeners for generate and copy buttons
    this.initEvents();

    // Query all active weighting manager rows across the canvas workspace
    const managerBlocks = document.querySelectorAll(
      ".esss-weight-manager-block",
    );
    managerBlocks.forEach((block) => {
      this.initWeightBlock(block);
    });
  }

  cacheDOMElements() {
    this.apiField = document.getElementById("smart_search_api_field");
    this.genBtn = document.getElementById("smart_search_btn_generate");
    this.copyBtn = document.getElementById("smart_search_btn_copy");
    this.statusMsg = document.getElementById("smart_search_status_msg");
    this.defaultText =
      "Your API key is automatically encrypted before being saved to the database.";
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
          // Keep valid overlapping text attributes, but screen out strictly numeric ones
          if (
            key === "slip_rating" ||
            key === "discount" ||
            key === "quantity"
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

  /**
   * Initialize the API key management functionality.
   */
  /**
   * Bind action listeners ensuring lexical 'this' remains scoped to the class
   */
  initEvents() {
    this.genBtn.addEventListener("click", () => this.handleGenerate());
    this.copyBtn.addEventListener("click", () => this.handleCopy());
  }

  /**
   * Generates a new secure API key and updates the input field.
   */
  handleGenerate() {
    // Generate a secure random key
    const buffer = new Uint8Array(24);

    // Fill the buffer with secure random values
    window.crypto.getRandomValues(buffer);

    // Convert the buffer to a base64-encoded string and sanitize it to create the raw key
    const rawKey = btoa(String.fromCharCode.apply(null, buffer))
      .replace(/[^a-zA-Z0-9]/g, "")
      .substring(0, 32);

    // Prepend the 'sk_' prefix to indicate a secret key
    this.apiField.value = `sk_${rawKey}`;

    // Update the input field to display the newly generated key
    this.apiField.type = "text";

    this.updateStatus(
      'New key generated! Remember to click "Save Changes".',
      "#2271b1",
    );
  }

  /**
   * Extracts field text and passes it securely to the clipboard context
   */
  handleCopy() {
    // Retrieve the current value from the API key input field
    const keyValue = this.apiField.value;

    // If the key is empty, provide feedback and exit early
    if (!keyValue) {
      this.updateStatus(
        "Nothing to copy. Generate or enter a key first.",
        "#d63638",
      );
      return;
    }

    // Attempt to write the key to the clipboard
    navigator.clipboard
      .writeText(keyValue)
      .then(() => {
        this.updateStatus("Copied to clipboard!", "#68de7c");
        setTimeout(() => this.resetStatus(), 2500);
      })
      .catch(() => {
        this.updateStatus(
          "Failed to copy. Please copy it manually.",
          "#d63638",
        );
      });
  }

  /**
   * UI Feedback helpers
   */
  updateStatus(text, color) {
    this.statusMsg.textContent = text;
    this.statusMsg.style.color = color;
    this.statusMsg.style.fontWeight = "bold";
  }

  resetStatus() {
    this.statusMsg.textContent = this.defaultText;
    this.statusMsg.style.color = "";
    this.statusMsg.style.fontWeight = "normal";
  }
}

// Safely instantiate the class control context upon document lifecycle ready state
document.addEventListener("DOMContentLoaded", () => {
  new ESSS_Admin();
});
