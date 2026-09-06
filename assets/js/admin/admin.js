document.addEventListener("DOMContentLoaded", function () {
  const wrapper = document.getElementById("esss-weight-matrix-wrapper");
  const container = document.getElementById("esss-weight-rows-container");
  const addButton = document.getElementById("esss-add-weight-row");

  if (!wrapper || !container || !addButton) return;

  // Parse the live schema array passed from the backend
  const allFields = JSON.parse(wrapper.getAttribute("data-all-fields"));

  /**
   * Rebuild and filter all dropdowns based on currently active selections
   */
  function updateAllDropdowns() {
    const selectors = document.querySelectorAll(".esss-field-selector");

    // Step 1: Collect what values are currently checked across the board
    const selectedValues = Array.from(selectors)
      .map((sel) => sel.value)
      .filter((val) => val !== "");

    selectors.forEach((select) => {
      const currentValue =
        select.value || select.getAttribute("data-selected") || "";

      // Clear existing dropdown options safely
      select.innerHTML =
        '<option value="" disabled selected>Select attribute field...</option>';

      // Step 2: Loop through total fields and append only if free or currently assigned to this row
      for (const [key, label] of Object.entries(allFields)) {
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

      // Sync structural attributes
      if (currentValue && !select.value) {
        select.value = currentValue;
      }
      select.removeAttribute("data-selected");
    });
  }

  // Handle Adding New Rows
  addButton.addEventListener("click", function (e) {
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

    container.appendChild(tr);
    updateAllDropdowns(); // Instantly update lists for the new row configuration
  });

  // Intercept selections and deletions via event delegation
  container.addEventListener("change", function (e) {
    if (e.target && e.target.classList.contains("esss-field-selector")) {
      updateAllDropdowns();
    }
  });

  container.addEventListener("click", function (e) {
    if (e.target && e.target.classList.contains("esss-remove-weight-row")) {
      e.preventDefault();
      e.target.closest(".esss-weight-row").remove();
      updateAllDropdowns(); // Put the deleted option back into rotation instantly
    }
  });

  // Run once on initial screen load to initialize saved rows
  updateAllDropdowns();
});
