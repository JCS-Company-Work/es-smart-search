# Overview

ES Smart Search improves product discovery on Emporio Surfaces listing pages. It searches in-stock batch records, then displays the matching customer-facing product cards.

## What It Does

- Searches product data, controlled fields, and product codes.
- Ranks batch matches in PHP.
- Shows matching parent product cards in the browser.
- Supports filters, pagination, shared URLs, and browser navigation.
- Records completed text searches for future reporting.

## Key Idea

PHP searches individual `batch` records. The page can group several batches into one parent product card. The browser converts batch matches into the cards the customer sees.

## Main Folders

- `src/`: WordPress and PHP search code.
- `assets/js/`: browser search code.
- `assets/css/`: search styles.
- `docs/`: architecture and development notes.
- `tests/php/`: PHPUnit tests.

Read [frontend.md](frontend.md), [backend.md](backend.md), and [search-flow.md](search-flow.md) for the working detail.
