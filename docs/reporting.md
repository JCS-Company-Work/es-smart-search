# Search Reporting

Search reporting records completed text searches after their results have rendered. It does not record filter-only changes, pagination clicks, resets, aborted requests, failed requests, or queries below the minimum length.

## Event Flow

```text
SearchService receives a successful API response
  -> DisplayService renders results
  -> SearchReportingService sends a small event payload
  -> SearchReporting stores the event
```

## Table

The reporting table is created on plugin activation:

```text
{prefix}es_smart_search_events
```

It stores:

- `created_at`
- `visitor_id` and `session_id`
- `query_raw` and `query_normalised`
- `matching_batches`
- `displayed_parents`
- `has_results`
- `top_matches_json`
- `page_path`

`matching_batches` is the number of matching batch records from PHP. `displayed_parents` is the number of product cards shown to the customer. These values can differ because one card can contain several batches. `has_results` is `1` when at least one parent card is displayed and `0` otherwise.

## Diagnostic Snapshot

`top_matches_json` should contain at most 20 ranked batch matches. Each item records the batch ID, score, and matched fields. Do not store full product data in every event.

## Visitor and Session IDs

Use anonymous browser-generated IDs. Do not store IP addresses or full user-agent strings. A visitor ID may persist in local storage; a session ID should expire after inactivity.

The report route is publicly callable because it is intended for browser clients;
the current implementation relies on the client payload and does not authenticate
or rate-limit individual report submissions. Treat the table as analytics input,
not as a trusted audit log.

## Dashboard Use

The dashboard should prioritise common searches, zero-result searches, low-result searches, and sample ranking details. Index the date, normalised query, `has_results`, visitor ID, and session ID; do not index diagnostic JSON.
