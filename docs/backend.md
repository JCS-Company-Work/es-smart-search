# Back End

The plugin uses Composer and the `EsSmartSearch` namespace.

## Startup

`es-smart-search.php` loads Composer and starts `Plugin`.

`Plugin::boot()` registers these classes:

| Class             | Responsibility                                                 |
| ----------------- | -------------------------------------------------------------- |
| `Assets`          | Loads the front-end JavaScript and CSS on supported pages.     |
| `Search`          | Registers the search REST endpoint and ranks batch matches.    |
| `SearchIndex`     | Clears the cached index when batch data changes.               |
| `SearchReporting` | Creates the reporting table and receives logged search events. |

## Search Endpoint

```text
GET /wp-json/emporio-search/v1/search
```

It accepts `q` and optional JSON filters. It returns matching batch IDs, the total batch count, and ranked match details.

## Index

The search index includes in-stock published `batch` posts. It is cached in a WordPress transient and rebuilt when a relevant batch, field, taxonomy, or stock value changes.

## Ranking

PHP normalises query text, applies structured filters, scores matching batches, and sorts the response by score. The browser uses that ranking to order the already-rendered parent cards.
