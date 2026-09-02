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
| `SearchMatcher`   | Applies filters and scores matching batch records.             |

## Search Endpoint

```text
GET /wp-json/emporio-search/v1/search
```

It accepts `q` and optional JSON filters. It returns matching batch IDs, the total batch count, and ranked match details.

## Index

The search index includes in-stock published `batch` posts. It is cached in a WordPress transient and rebuilt when a relevant batch, field, taxonomy, or stock value changes.

## Ranking

`SearchMatcher` normalises query text and splits it into words. Common terms such as `tile`, `tiles`, `porcelain`, `product`, and `products` are ignored.

Each remaining word is scored against the batch fields using the highest matching field weight:

| Field          | Weight |
| -------------- | ------ |
| `product_code` | 100    |
| `size`         | 90     |
| `usage`        | 80     |
| `colour`       | 70     |
| `effect`       | 65     |
| `category`     | 60     |
| `finish`       | 55     |
| `title`        | 50     |
| `factory`      | 35     |

Exact substring matches are checked first. When no exact match is found, fuzzy matching is allowed only for `title`, `colour`, `effect`, `category`, and `factory`. Fuzzy candidates must contain at least four characters; four-letter words allow one edit, longer words allow two, and a fuzzy match receives a 30% score penalty.

The terms `floor`, `wall`, `outdoor`, and dimension patterns such as `60x60` are strict exclusions when they do not match a batch exactly. Structured filters must all match. `categories` is treated as `category`, `dimensions` as `size`, and `quantity` supports `sqm-min-max` and `sqm-min+` bands.

PHP sorts matching batches by descending score. The browser uses that ranking to order the already-rendered parent cards.
