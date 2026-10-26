import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const base = process.env.BROWSER_BASE_URL || 'http://smilesuite.localhost:8000';
const clinic = (subdomain) => base.replace('smilesuite.localhost', `${subdomain}.smilesuite.localhost`);

async function demo(page, role = 'Clinic owner') {
    await page.goto(`${clinic('harbor')}/login`);
    await page.getByRole('button', { name: role, exact: true }).click();
    await page.waitForLoadState('networkidle');
}

test('owner navigation, isolated clinic sessions, responsive reflow and accessible dashboard', async ({ page, context }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await demo(page);
    await expect(page.getByRole('heading', { name: /A good day for great care/ })).toBeVisible();
    await page.screenshot({ path: 'test-results/dashboard-desktop.png', fullPage: true });
    const accessibility = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa']).analyze();
    await test.info().attach('accessibility', { body: JSON.stringify(accessibility.violations, null, 2), contentType: 'application/json' });
    expect(accessibility.violations.map(({ id, nodes })=>({id, targets:nodes.map(node=>node.target)}))).toEqual([]);
    for (const [url, heading] of [['schedule', 'Make room for better care.'], ['patients', 'Your patient community.'], ['services', 'Every smile starts somewhere.'], ['settings', 'A workspace that feels like your clinic.'], ['billing', 'Your clinic, at its own pace.'], ['store', 'A few good things for your smile.'], ['notifications', 'Notification delivery']]) {
        const response = await page.goto(`${clinic('harbor')}/${url}`);
        expect(response.status()).toBe(200);
        await expect(page.getByRole('heading', { name: heading })).toBeVisible();
    }
    await page.goto(`${clinic('cedar')}/dashboard`);
    await expect(page).toHaveURL(/\/login$/);
    await page.goto(`${clinic('harbor')}/dashboard`);
    await expect(page.getByRole('heading', { name: /A good day for great care/ })).toBeVisible();
    const cookies = await context.cookies();
    expect(cookies.filter(cookie => cookie.name.includes('session')).every(cookie => !cookie.domain.startsWith('.'))).toBe(true);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.reload();
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    await page.getByRole('button', { name: 'Toggle menu' }).click();
    await expect(page.locator('.sidebar')).toHaveClass(/is-open/);
    await page.screenshot({ path: 'test-results/dashboard-mobile.png', fullPage: true });
    expect(errors).toEqual([]);
});

test('patient books and cancels a real reservation and saves versioned intake', async ({ page }) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await demo(page, 'Patient');
    await page.getByRole('link', { name: 'Book a visit', exact: true }).first().click();
    await page.waitForLoadState('networkidle');
    const date = new Date(); date.setUTCDate(date.getUTCDate() + 3);
    // The seeded schedule includes weekdays and Saturday; move past a Sunday if needed.
    while (date.getUTCDay() === 0) date.setUTCDate(date.getUTCDate() + 1);
    await page.getByLabel('Preferred date').fill(date.toISOString().slice(0, 10));
    await page.getByLabel('Preferred date').dispatchEvent('change');
    await expect(page.locator('.slot-button').first()).toBeVisible({ timeout: 30000 });
    await page.locator('.slot-button').first().click();
    await page.getByRole('button', { name: 'Confirm appointment', exact: true }).click();
    await expect(page).toHaveURL(/\/portal$/);
    await expect(page.getByRole('status')).toContainText('Appointment booked');
    const latest = page.locator('.patient-appointment').first();
    await latest.getByRole('button', { name: 'Cancel visit', exact: true }).click();
    await expect(page.getByRole('status')).toContainText('Appointment updated');
    await page.getByRole('link', { name: 'My intake', exact: true }).click();
    await page.getByLabel('Allergies', { exact: true }).fill('Synthetic browser fixture — no real clinical data');
    await page.getByRole('checkbox').check();
    await page.getByRole('button', { name: 'Save new intake version' }).click();
    await expect(page.getByRole('status')).toContainText('new version');
    await page.locator('.intake-version').first().locator('summary').click();
    await expect(page.getByText('Synthetic browser fixture — no real clinical data', { exact: true }).first()).toBeVisible();
    expect(errors).toEqual([]);
});

test('public landing and clinic login have labeled controls and keyboard access', async ({ page }) => {
    await page.goto(base);
    await expect(page.getByRole('heading', { name: /Behind every/ })).toBeVisible();
    await page.keyboard.press('Tab');
    await expect(page.getByRole('link', { name: 'Skip to main content' })).toBeFocused();
    await page.goto(`${clinic('harbor')}/login`);
    await expect(page.getByLabel('Email address')).toBeVisible();
    await expect(page.getByLabel('Password', { exact: true })).toBeVisible();
    await page.screenshot({ path: 'test-results/login-desktop.png', fullPage: true });
});
