import { defineConfig, devices } from "@playwright/test";

export default defineConfig({
  testDir: "./tests/e2e",
  outputDir: "./test-results/playwright",
  timeout: 60000,
  retries: 0,
  use: {
    headless: true,
    trace: "on",
    viewport: { width: 1280, height: 720 },
    ignoreHTTPSErrors: true,
    video: "retain-on-failure",
    screenshot: "only-on-failure",
    baseURL: process.env.PLAYWRIGHT_BASE_URL || "https://emporiosurfaces.local",
  },
  projects: [
    {
      name: "chromium",
      use: { ...devices["Desktop Chrome"] },
      grep: /@critical/,
    },
  ],
});
