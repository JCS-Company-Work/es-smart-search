export class SuggestionService {
  constructor(app) {
    this.app = app;
  }

  showSuggestion(suggestions) {
    console.log(suggestions);
    // Loop through suggestions and build 'did you mean' links
    suggestions.forEach((suggestion) => {
      // Create a suggestion link for the "Did you mean" feature
      const suggestionLink = document.createElement("a");

      suggestionLink.classList.add("suggestion-link");

      // Set the href attribute to the search results page with the suggested query
      suggestionLink.href = "#";

      // Set the text content of the suggestion link to display the suggested query
      suggestionLink.innerHTML = `Did you mean: <span>${suggestion}</span>?`;

      // Add a click event listener to the suggestion link to execute the search with the suggested query
      suggestionLink.addEventListener("click", (event) => {
        // Prevent the default link behavior
        event.preventDefault();

        // Load the suggested query into the search input and execute the search
        this.loadSuggestion(suggestion);
      });

      // Update UI with suggestions
      if (this.app.noResults) {
        this.displaySuggestions(suggestionLink);
      }
    });
  }

  /**
   * Display the suggestion link in the "no results" message.
   * @param {HTMLAnchorElement} suggestionLink The suggestion link element to display in the "no results" message.
   */
  displaySuggestions(suggestionLink) {
    this.app.noResults.innerHTML = "";
    this.app.noResults.appendChild(suggestionLink);
    this.app.noResults.appendChild(suggestionLink);

    this.app.noResults.style.display = "block";
  }

  /**
   * Load the suggested query into the search input and execute the search.
   * @param {string} suggestion The suggested query to load and search for.
   */
  loadSuggestion(suggestion) {
    // Update search query term in state to suggestion
    this.app.state.query = suggestion;

    // Update the search input field with the suggested query
    const searchInput = document.querySelector(".live-filter");
    if (searchInput) {
      searchInput.value = suggestion;
    }

    this.app.state.page = 1;
    this.app.urlService.writeUrlState();
    this.app.displayService.renderResetState();

    // Execute the search with the updated query
    this.app.searchService.execute();

    // Update the query display to reflect the new suggestion
    this.app.displayService.updateQueryDisplay();

    // Hide the "no results" message after loading the suggestion
    this.app.noResults.innerHTML = "";
    this.app.noResults.style.display = "none";
  }
}
