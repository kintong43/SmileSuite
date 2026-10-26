import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const base = process.env.BROWSER_BASE_URL || 'http://smilesuite.localhost:8000';
const clinic = base.replace('smilesuite.localhost', 'harbor.smilesuite.localhost');

test('owner can reach profile reports and CMS status with labeled controls', async ({ page }) => {
    await page.goto(`${clinic}/login`);
    await page.getByRole('button', { name: 'Clinic owner', exact: true }).click();
    await page.getByRole('link', { name: 'My profile', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'My profile', exact: true })).toBeVisible();
    await expect(page.getByLabel('Email address', { exact: true })).toHaveValue('owner@harbor.example.test');
    await page.getByRole('link', { name: 'Reports', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Operational reports' })).toBeVisible();
    const download = page.waitForEvent('download');
    await page.getByRole('link', { name: 'Export CSV' }).click();
    expect((await download).suggestedFilename()).toMatch(/^operational-report-.*\.csv$/);
    const audit = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
    expect(audit.violations.map(violation => violation.id)).toEqual([]);
    await page.getByRole('link', { name: 'Content management', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Content management' })).toBeVisible();
    await page.setViewportSize({ width: 390, height: 844 });
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
});

test('public CMS pages offer navigation and published content or an honest fallback', async ({ page }) => {
    await page.goto(base);
    await page.getByRole('link', { name: 'About', exact: true }).click();
    await expect(page.locator('h1')).toHaveCount(1);
    await page.getByRole('link', { name: 'Announcements & services', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Announcements & services', exact: true })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Our services', exact: true })).toBeVisible();
});
