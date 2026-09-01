export class SearchReportingService {
  constructor(app) {
    // Explicitly save a reference to the main SmartSearch instance
    this.app = app;
  }

  record(data, visibleProductCount) {
    console.log("Recording search data:", data, visibleProductCount);
    // Prepare the payload for reporting
    const payload = {
      visitor_id: this.getVisitorId(),
      session_id: this.getSessionId(),
      query_raw: this.app.state.query,
      query_normalised: data.query,
      matching_batches: data.count,
      displayed_parents: visibleProductCount,
      is_zero_result: visibleProductCount === 0 ? 1 : 0,
      top_matches_json: (data.ranking || []).slice(0, 20).map((match) => ({
        id: match.id,
        score: match.score,
        matched_fields: match.matched_fields,
      })),
      page_path: window.location.pathname,
    };

    // Send the reporting data to the server
    fetch(`${window.ESSS.reportingEndpoint}`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
      },
      body: JSON.stringify(payload),
    }).catch((error) => {
      console.error("Failed to report search data:", error);
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

  getSessionId() {
    const key = "es_smart_search_session_id";
    const sessionKey = "es_smart_search_session_last_seen";
    const timeout = 30 * 60 * 1000;

    let sessionId = sessionStorage.getItem(key);
    const lastSeen = Number(sessionStorage.getItem(sessionKey));

    if (!sessionId || Date.now() - lastSeen > timeout) {
      sessionId = crypto.randomUUID();
      sessionStorage.setItem(key, sessionId);
    }

    sessionStorage.setItem(sessionKey, String(Date.now()));

    return sessionId;
  }
}
