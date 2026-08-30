/**
 * Restore query, filters and page from the URL.
 *
 * The hash is used by the in-page controls. The query parameter is used by
 * the site-wide search form, so both entry points share the same search flow.
 */
export function readUrlState() {
  // Get the current URL hash and remove the leading '#' character
  const hash = window.location.hash.replace(/^#/, "");
  const nextState = {
    query: "",
    filters: {},
    page: 1,
    pagination: {
      pages: {},
    },
  };

  // If there is no hash, check for a query parameter in the URL search parameters
  if (!hash) {
    // Use URLSearchParams to parse the query parameters from the current URL
    const params = new URLSearchParams(window.location.search);

    // Get the "textsearch" parameter from the query parameters, or default to an empty string
    nextState.query = params.get("textsearch") || "";

    // Get the "page" parameter from the query parameters, or default to 1
    this.state = nextState;

    // If there is a query parameter, update the URL hash to reflect the query and reset the page number to 1
    if (nextState.query) {
      const cleanUrl = `${window.location.pathname}#textsearch=${encodeURIComponent(
        nextState.query,
      )}&page=1`;

      window.history.replaceState(null, "", cleanUrl);
    }

    return;
  }

  // Split the hash into key-value pairs and process each part
  hash.split("&").forEach((part) => {
    // Find the index of the '=' separator in the part
    const separator = part.indexOf("=");

    // If there is no '=' separator, skip this part
    if (separator < 0) return;

    // Decode the key and value from the part
    const key = decodeURIComponent(part.slice(0, separator));

    // Decode the value from the part, handling URL encoding
    const value = decodeURIComponent(part.slice(separator + 1));

    // Update the nextState based on the key and value
    if (key === "textsearch") {
      nextState.query = value;
    } else if (key === "page") {
      nextState.page = parseInt(value, 10) || 1;
    } else if (value) {
      nextState.filters[key] = value.split(",");
    }
  });

  // Update the current state with the nextState derived from the URL
  this.state = nextState;
}

/**
 * Update the URL hash to reflect the current query, filters, and page.
 * @returns {void}
 */
export function writeUrlState() {
  // Build an array of key-value pairs for the URL hash
  const parts = [];

  // Check if there are any active filters in the current state
  const hasFilters = Object.keys(this.state.filters).length > 0;

  // Check if the current query meets the minimum length for searching
  const hasSearchableQuery = this.state.query.length >= this.minimumQueryLength;

  // Check if the URL already has a query parameter for textsearch
  const urlHasQuery = window.location.hash.includes("textsearch=");

  // If there is a searchable query, add it to the parts array for the URL hash
  if (hasSearchableQuery) {
    parts.push(`textsearch=${encodeURIComponent(this.state.query)}`);
  } else if (this.state.query && !hasFilters && !urlHasQuery) {
    return;
  }

  // Add each filter group and its values to the parts array for the URL hash
  Object.entries(this.state.filters).forEach(([group, values]) => {
    if (values.length) {
      parts.push(
        `${encodeURIComponent(group)}=${encodeURIComponent(values.join(","))}`,
      );
    }
  });

  // Add the current page number to the parts array for the URL hash
  parts.push(`page=${this.state.page}`);

  // Construct the new URL hash from the parts array
  const hash = `#${parts.join("&")}`;

  // Construct the full URL with the current pathname, search parameters, and new hash
  const url = `${window.location.pathname}${window.location.search}${hash}`;

  // If the new URL is different from the current URL, update the browser history
  if (
    url !==
    `${window.location.pathname}${window.location.search}${window.location.hash}`
  ) {
    window.history.pushState(null, "", url);
  }
}
