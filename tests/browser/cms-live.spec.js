import { readFile } from 'node:fs/promises';
import { test, expect } from '@playwright/test';

test('real Directus editor publishes within its clinic and cannot cross the tenant boundary', async ({ page, request }) => {
    test.skip(process.env.CMS_LIVE_TEST !== '1', 'Requires the separately running synthetic CMS and ignored generated credentials.');
    const credentials = JSON.parse(await readFile('.runtime/cms-credentials.json', 'utf8'));
    const base = process.env.CMS_URL || 'http://127.0.0.1:8055';
    const editor = credentials.editors['clinic-harbor'];
    const login = await request.post(`${base}/auth/login`, { data: editor });
    expect(login.status()).toBe(200);
    const headers = { Authorization: `Bearer ${(await login.json()).data.access_token}` };
    const pages = await request.get(`${base}/items/ss_pages`, { headers });
    expect(pages.status()).toBe(200);
    expect((await pages.json()).data.every(row => row.tenant_key === 'clinic-harbor')).toBe(true);
    const cedar = await request.get(`${base}/items/ss_pages`, { headers: { Authorization: `Bearer ${credentials.tokens['clinic-cedar']}` } });
    expect(cedar.status()).toBe(200);
    const other = (await cedar.json()).data[0];
    expect((await request.get(`${base}/items/ss_pages/${other.id}`, { headers })).status()).toBe(403);
    expect((await request.patch(`${base}/items/ss_pages/${other.id}`, { headers, data: { title: 'Denied synthetic attempt' } })).status()).toBe(403);
    expect((await request.get(`${base}/items/ss_pages`)).status()).toBe(403);
    const slug = `coursework-proof-${Date.now()}`;
    const created = await request.post(`${base}/items/ss_pages`, { headers, data: { slug, title: 'Synthetic publishing proof', body: 'Created through the separate CMS editor.', status: 'published' } });
    expect(created.status()).toBe(200);
    const item = (await created.json()).data;
    try {
        expect(item.tenant_key).toBe('clinic-harbor');
        await page.goto(`http://harbor.smilesuite.localhost:8000/content/${slug}`);
        await expect(page.getByRole('heading', { name: 'Synthetic publishing proof' })).toBeVisible();
        expect((await page.goto(`http://cedar.smilesuite.localhost:8000/content/${slug}`)).status()).toBe(404);
        expect((await request.patch(`${base}/items/ss_pages/${item.id}`, { headers, data: { title: 'Edited synthetic proof' } })).status()).toBe(200);
        const spoofed = await request.post(`${base}/items/ss_pages`, { headers, data: { tenant_key: 'clinic-cedar', slug: 'denied-spoof', title: 'Denied', status: 'draft' } });
        expect([400, 403]).toContain(spoofed.status());
        for (const collection of ['ss_directory', 'ss_reports']) {
            const response = await request.get(`${base}/items/${collection}`, { headers });
            expect(response.status()).toBe(200);
            const rows = (await response.json()).data;
            expect(rows.length).toBeGreaterThan(0);
            expect(rows.every(row => row.tenant_key === 'clinic-harbor')).toBe(true);
            expect(rows.every(row => Object.keys(row).every(key => !/email|password|token|patient|clinical|phone/i.test(key)))).toBe(true);
        }
        await page.goto(`${base}/admin/login`);
        await page.getByPlaceholder('Email', { exact: true }).fill(editor.email);
        await page.getByPlaceholder('Password', { exact: true }).fill(editor.password);
        await page.getByRole('button', { name: 'Sign In', exact: true }).click();
        await page.waitForURL(url => !url.pathname.includes('/login'));
        await page.goto(`${base}/admin/content/ss_pages`);
        await expect(page.getByText('Welcome to clinic-harbor', { exact: true })).toBeVisible();
        await expect(page.getByText('Welcome to clinic-cedar', { exact: true })).toHaveCount(0);
    } finally {
        expect((await request.delete(`${base}/items/ss_pages/${item.id}`, { headers })).status()).toBe(204);
    }
});
