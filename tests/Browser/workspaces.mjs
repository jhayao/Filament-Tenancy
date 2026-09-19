import assert from 'node:assert/strict';
import { spawn, execFileSync } from 'node:child_process';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { setTimeout as delay } from 'node:timers/promises';
import { chromium } from 'playwright';

const directory = mkdtempSync(join(tmpdir(), 'tenancy-browser-'));
const port = process.env.TENANCY_BROWSER_PORT || '8765';
const baseURL = `http://127.0.0.1:${port}`;
const env = { ...process.env, TENANCY_BROWSER_DIRECTORY: directory, TENANCY_BROWSER_URL: baseURL };
const php = (args) => execFileSync('php', args, { env, encoding: 'utf8', timeout: 30000 });
let server;
let browser;
let serverLog = '';
let page;
try {
    for (const path of ['public', 'sessions', 'cache']) mkdirSync(join(directory, path));
    writeFileSync(join(directory, 'central.sqlite'), '');
    php(['tests/Browser/setup.php']);
    server = spawn('php', ['-S', `127.0.0.1:${port}`, 'tests/Browser/router.php'], { env });
    server.stderr.on('data', (data) => { serverLog += data; });
    server.stdout.on('data', (data) => { serverLog += data; });
    for (let attempt = 0; attempt < 100; attempt++) {
        if (server.exitCode !== null) throw new Error(serverLog);
        try {
            const response = await fetch(`${baseURL}/admin/login`);
            if (response.ok) break;
        } catch { /* Wait for the local host to listen. */ }
        if (attempt === 99) throw new Error(`Browser host did not start: ${serverLog}`);
        await delay(100);
    }
    browser = await chromium.launch({ headless: true });
    page = await browser.newPage();
    page.setDefaultTimeout(15000);
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    await page.goto(`${baseURL}/admin/login`);
    await page.getByLabel('Email address').fill('owner@example.test');
    await page.locator('input[autocomplete="current-password"]').fill('browser-password');
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await page.waitForURL('**/admin/new');

    async function createWorkspace(name, slug) {
        await page.locator('[id="form.name"]').fill(name);
        await page.locator('[id="form.slug"]').fill(slug);
        await page.getByRole('button', { name: 'Create workspace', exact: true }).click();
        await page.waitForURL(`**/admin/workspaces/${slug}/workspace-setup`);
        await page.getByText('Waiting for setup', { exact: true }).waitFor();
        php(['tests/Browser/artisan.php', 'queue:work', 'database', '--queue=tenant-provisioning', '--once', '--sleep=0']);
        await page.waitForURL(`**/admin/workspaces/${slug}`, { timeout: 20000 });
    }

    await createWorkspace('Browser Alpha', 'browser-alpha');
    await page.locator('.fi-tenant-menu-trigger').click();
    await page.getByRole('link', { name: 'Create workspace' }).click();
    await createWorkspace('Browser Beta', 'browser-beta');
    await page.locator('.fi-tenant-menu-trigger').click();
    await page.getByRole('link', { name: 'Browser Alpha', exact: true }).click();
    await page.waitForURL('**/admin/workspaces/browser-alpha');
    await page.locator('.fi-tenant-menu-trigger').click();
    await page.getByRole('link', { name: 'Browser Beta', exact: true }).click();
    await page.waitForURL('**/admin/workspaces/browser-beta');
    assert.deepEqual(errors, [], 'Browser JavaScript errors');
    console.log('Browser flow passed: login, two queued workspace registrations, polling, and switching.');
} catch (error) {
    if (page) {
        console.error((await page.locator('body').innerText()).slice(0, 5000));
        console.error(await page.locator('input').evaluateAll(inputs => inputs.map(input => ({ id: input.id, type: input.type, labels: [...(input.labels || [])].map(label => label.textContent) }))));
    }
    console.error(serverLog.slice(-5000));
    throw error;
} finally {
    await browser?.close();
    if (server && server.exitCode === null) {
        const stopped = new Promise((resolve) => server.once('exit', resolve));
        server.kill();
        await stopped;
    }
    rmSync(directory, { recursive: true, force: true });
}
