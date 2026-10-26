import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/browser',
    timeout: 60000,
    workers: 1,
    use: { browserName: 'chromium', headless: true, viewport: { width: 1440, height: 1000 }, screenshot: 'only-on-failure' },
    reporter: [['list'], ['json', { outputFile: 'test-results/browser-results.json' }]],
});
