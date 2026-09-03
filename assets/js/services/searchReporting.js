export class SearchReportingService {
  constructor(app) {
    this.app = app;
  }

  record(data, visibleProductCount) {
    // Determine if the search returned any results based on the visible product count.
    const hasResults = visibleProductCount > 0 ? 1 : 0;

    // Prepare payload for reporting the search event.
    const payload = {
      visitor_id: this.getVisitorId(),
      session_id: this.getSessionId(),
      query_raw: this.app.state.query,
      query_normalised: data.query,
      matching_batches: data.count,
      displayed_parents: visibleProductCount,
      has_results: hasResults,
      top_matches_json: (data.ranking || []).slice(0, 20).map((match) => ({
        id: match.id,
        score: match.score,
        matched_fields: match.matched_fields,
      })),
      page_path: window.location.pathname,
    };

    // Send the search event to the reporting endpoint using a secure,
    // exit-resilient fetch request (keepalive: true) to ensure delivery even during fast page transitions.
    fetch(`${window.ESSS.reportingEndpoint}`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "X-WP-Nonce": window.ESSS.nonce,
      },
      body: JSON.stringify(payload),
      keepalive: true,
    }).catch((error) => {
      if (window.ESSS.debug) {
        console.error("Failed to report search data:", error);
      }
    });
  }

  getVisitorId() {
    const key = "es_smart_search_visitor_id";
    let visitorId = localStorage.getItem(key);

    if (!visitorId) {
      visitorId = crypto.randomUUID();
      localStorage.setItem(key, visitorId);
    }

    return visitorId;
  }

  /**
   * Get the current session ID, creating a new one if necessary due to inactivity.
   * @returns {string} The current session ID.
   */
  getSessionId() {
    // Session ID key
    const idKey = "es_smart_search_session_id";

    // Timeout key for tracking last activity
    const timeoutKey = "es_smart_search_session_last_seen";

    // Maximum inactivity duration before rotating the session ID (30 minutes)
    const maxInactivity = 30 * 60 * 1000;

    // Retrieve the current session ID from localStorage
    let sessionId = localStorage.getItem(idKey);

    // Retrieve the last activity timestamp from localStorage
    const lastSeen = Number(localStorage.getItem(timeoutKey));

    // Get the current timestamp
    const now = Date.now();

    // If no session exists, or the user has been inactive for > 30 mins, rotate the session ID
    if (!sessionId || now - lastSeen > maxInactivity) {
      sessionId = crypto.randomUUID();
      localStorage.setItem(idKey, sessionId);
    }

    // Always update the rolling activity timestamp
    localStorage.setItem(timeoutKey, String(now));

    // Return the current session ID
    return sessionId;
  }
}
