import { test, expect } from "@playwright/test";

const searchResultsPath = "/collections/search-results/";

async function openSearchResults(page, baseURL) {
  await page.goto(`${baseURL}${searchResultsPath}`);
  const consentButton = page.getByRole("button", { name: "AGREE TO ALL" });

  if (await consentButton.isVisible()) {
    await consentButton.click();
  }

  await page.locator(".live-filter").waitFor();
  await page.locator(".product-list > li").first().waitFor();
}

test("Smart Search renders API matches and reports the completed search @critical @smart-search", async ({
  page,
  baseURL,
}) => {
  await openSearchResults(page, baseURL);

  const matchingCard = page.locator(".product-list > li").first();
  const matchingBatchId = await matchingCard.getAttribute("data-id");

  await page.route(/\/es-smart-search\/v1\/search(?:\?|$)/, async (route) => {
    const requestUrl = new URL(route.request().url());

    await route.fulfill({
      contentType: "application/json",
      body: JSON.stringify({
        query: requestUrl.searchParams.get("q"),
        matches: [Number(matchingBatchId)],
        count: 1,
        ranking: [
          {
            id: Number(matchingBatchId),
            score: 100,
            matched_fields: { title: 50 },
          },
        ],
        suggestion: null,
      }),
    });
  });

  await page.route(/\/es-smart-search\/v1\/report(?:\?|$)/, (route) =>
    route.fulfill({ status: 204 }),
  );
  const reportPayload = page.waitForRequest(
    (request) =>
      request.url().includes("/es-smart-search/v1/report") &&
      request.method() === "POST",
  );

  await page.locator(".live-filter").fill("marble");
  await expect(matchingCard).toBeVisible();
  await expect(page).toHaveURL(/#textsearch=marble&page=1$/);

  const payload = JSON.parse((await reportPayload).postData());
  expect(payload).toMatchObject({
    query_raw: "marble",
    query_normalised: "marble",
    matching_batches: 1,
    displayed_parents: 1,
    has_results: 1,
    page_path: searchResultsPath,
  });
});

test("Smart Search shows and applies an API suggestion after zero results @critical @smart-search", async ({
  page,
  baseURL,
}) => {
  await openSearchResults(page, baseURL);
  const matchingBatchId = await page
    .locator(".product-list > li")
    .first()
    .getAttribute("data-id");

  await page.route(/\/es-smart-search\/v1\/search(?:\?|$)/, async (route) => {
    const query = new URL(route.request().url()).searchParams.get("q");
    const isSuggestionQuery = query === "marble";

    await route.fulfill({
      contentType: "application/json",
      body: JSON.stringify({
        query,
        matches: isSuggestionQuery ? [Number(matchingBatchId)] : [],
        count: isSuggestionQuery ? 1 : 0,
        ranking: isSuggestionQuery
          ? [{ id: Number(matchingBatchId), score: 100 }]
          : [],
        suggestion: isSuggestionQuery ? null : ["marble"],
      }),
    });
  });

  await page.route(/\/es-smart-search\/v1\/report(?:\?|$)/, (route) =>
    route.fulfill({ status: 204 }),
  );
  await page.locator(".live-filter").fill("marmel");

  const suggestion = page.locator(".no-results .suggestion-link");
  await expect(suggestion).toHaveText("Did you mean: marble?");
  await suggestion.click();

  await expect(page.locator(".live-filter")).toHaveValue("marble");
  await expect(page.locator(".no-results")).toBeHidden();
  await expect(page.locator(".product-list > li").first()).toBeVisible();
  await expect(page).toHaveURL(/#textsearch=marble&page=1$/);
});
