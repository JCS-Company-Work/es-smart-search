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

test(
  "Smart Search shows and applies an API suggestion after zero results",
  { tag: ["@critical", "@smart-search"] },
  async ({ page, baseURL }) => {
    // Load the actual search page layout
    await openSearchResults(page, baseURL);

    // Type a natural typo of an active database word ("marble" -> "marmel")
    // Since your product matcher fuzzy cap is strict (max 1 typo), "marmel" (2 typos)
    // will force a true 0-results state from the PHP database loop naturally.
    await page.locator(".live-filter").fill("marmelxy");

    // Target your clean, real element IDs from your component markup
    const suggestionContainer = page.locator(".suggestion-link");
    const suggestionLink = page.locator(".suggestion-link span");

    // Verify the server successfully processed the dictionary lookup and returned the text
    await expect(suggestionContainer).toContainText("Did you mean");
    await expect(suggestionLink).toHaveText("marble");

    // Click your actionable button card to trigger your JavaScript execute() pipeline
    await suggestionLink.click();

    // Verify the frontend app syncs states and re-runs the query cleanly
    await expect(page.locator(".live-filter")).toHaveValue("marble");
    await expect(suggestionContainer).toBeHidden();

    // Matches the first product card that isn't masked by your hidden utility class
    await expect(
      page.locator(".product-list > li:not(.es-smart-search-hidden)").first(),
    ).toBeVisible();

    // Verify your urlService successfully updated the browser address bar
    // The .* allows any dynamic query parameters like pagination to follow cleanly
    await expect(page).toHaveURL(/#textsearch=marble.*$/);
  },
);
