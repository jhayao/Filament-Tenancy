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

    async function setTheme(theme) {
        await page.evaluate((value) => {
            localStorage.setItem('theme', value);
            document.documentElement.classList.toggle('dark', value === 'dark');
        }, theme);
        await page.reload();
        await page.waitForURL('**/admin/new');
    }

    async function assertStandaloneTheme(theme) {
        const colors = await page.locator('body').evaluate((body) => ({
            background: getComputedStyle(body).backgroundColor,
            colorScheme: getComputedStyle(body).colorScheme,
            inputBackground: body.querySelector('.fi-input-wrp')
                ? getComputedStyle(body.querySelector('.fi-input-wrp')).backgroundColor
                : null,
        }));

        assert.equal(colors.colorScheme, theme);

        if (theme === 'dark') {
            assert.notEqual(colors.background, 'rgb(248, 250, 252)', 'Dark standalone page kept the light background');
            if (colors.inputBackground !== null) {
                assert.notEqual(colors.inputBackground, 'rgb(255, 255, 255)', 'Dark registration form kept the light input background');
            }
        } else {
            assert.equal(colors.background, 'rgb(248, 250, 252)');
            if (colors.inputBackground !== null) {
                assert.equal(colors.inputBackground, 'rgb(255, 255, 255)');
            }
        }
    }

    async function createWorkspace(name, slug, theme) {
        if (theme) await assertStandaloneTheme(theme);
        await page.locator('[id="form.name"]').fill(name);
        await page.locator('[id="form.slug"]').fill(slug);
        await page.getByRole('button', { name: 'Create workspace', exact: true }).click();
        await page.waitForURL(`**/admin/workspaces/${slug}/workspace-setup`);
        await page.getByText('Waiting for setup', { exact: true }).waitFor();
        if (theme) await assertStandaloneTheme(theme);
        php(['tests/Browser/artisan.php', 'queue:work', 'database', '--once', '--sleep=0']);
        await page.waitForURL(`**/admin/workspaces/${slug}`, { timeout: 20000 });
    }

    await setTheme('light');
    await createWorkspace('Browser Alpha', 'browser-alpha', 'light');
    await page.locator('.fi-tenant-menu-trigger').click();
    await page.getByRole('link', { name: 'Create workspace' }).click();
    await setTheme('dark');
    await createWorkspace('Browser Beta', 'browser-beta', 'dark');
    await page.locator('.fi-tenant-menu-trigger').click();
    await page.getByRole('link', { name: 'Browser Alpha', exact: true }).click();
    await page.waitForURL('**/admin/workspaces/browser-alpha');
    await page.locator('.fi-tenant-menu-trigger').click();
    await page.getByRole('link', { name: 'Browser Beta', exact: true }).click();
    await page.waitForURL('**/admin/workspaces/browser-beta');
    await page.goto(`${baseURL}/admin/workspaces/browser-beta/members`);
    async function assertMembersLayout(subject) {
        for (const dark of [false, true]) {
            await subject.evaluate(value => document.documentElement.classList.toggle('dark', value), dark);
            for (const width of [1440, 390]) {
                await subject.setViewportSize({ width, height: 900 });
                const layout = await subject.locator('.fi-tenancy-members').evaluate(root => {
                    const bounds = element => {
                        const { x, y, width, height } = element.getBoundingClientRect();
                        return { x, y, width, height };
                    };
                    const row = [...root.querySelectorAll('[wire\\:key^="member-"]')].at(-1);
                    return {
                        stats: [...root.querySelectorAll('.fi-wi-stats-overview-stat')].map(bounds),
                        identity: bounds(row.querySelector('.fi-tenancy-members-identity')),
                        actions: bounds(row.querySelector('.fi-tenancy-members-actions')),
                        direction: getComputedStyle(row).flexDirection,
                        identityDisplay: getComputedStyle(row.querySelector('.fi-tenancy-members-identity')).display,
                        overflow: document.documentElement.scrollWidth > innerWidth,
                    };
                });
                assert.equal(layout.stats.length, 3);
                assert.equal(layout.identityDisplay, 'flex');
                assert.equal(layout.overflow, false, `Members overflow at ${width}px`);
                if (width === 1440) {
                    assert.equal(layout.direction, 'row');
                    assert.ok(layout.stats.every(stat => Math.abs(stat.y - layout.stats[0].y) < 1), 'Desktop stats must share a row');
                    assert.ok(layout.actions.x >= layout.identity.x + layout.identity.width, 'Desktop actions must sit to the right of identity');
                } else {
                    assert.equal(layout.direction, 'column');
                    assert.ok(layout.stats[1].y >= layout.stats[0].y + layout.stats[0].height, 'Mobile stats must stack');
                    assert.ok(layout.actions.y >= layout.identity.y + layout.identity.height, 'Mobile actions must stack below identity');
                }
            }
        }
        await subject.setViewportSize({ width: 1440, height: 900 });
    }
    await assertMembersLayout(page);
    await page.getByRole('button', { name: 'Invite members', exact: true }).click();
    await page.getByLabel('Email addresses').fill('invited@example.test');
    await page.getByRole('button', { name: 'Submit', exact: true }).click();
    await page.getByText('invited@example.test', { exact: true }).waitFor();
    const invitationURL = php(['tests/Browser/invitation.php']).trim();
    const ownerPage = page;
    const guestContext = await browser.newContext();
    const guest = await guestContext.newPage();
    page = guest;
    guest.setDefaultTimeout(15000);
    guest.on('pageerror', (error) => errors.push(error.message));
    await guest.goto(invitationURL);
    await guest.waitForURL('**/admin/login');
    assert.equal(await guest.getByLabel('Email address').inputValue(), 'invited@example.test');
    assert.equal(await guest.getByLabel('Email address').getAttribute('readonly'), 'readonly');
    await guest.getByRole('link', { name: 'sign up' }).click();
    await guest.waitForURL('**/admin/register');
    await guest.waitForLoadState('networkidle');
    await guest.getByLabel('Name', { exact: false }).fill('Invited Member');
    assert.equal(await guest.getByLabel('Email address').inputValue(), 'invited@example.test');
    await guest.locator('[id="form.password"]').fill('invitation-password');
    await guest.getByLabel('Confirm password').fill('invitation-password');
    await guest.getByRole('button', { name: 'Sign up', exact: true }).click();
    await guest.waitForURL('**/admin/workspaces/browser-beta');
    await guest.waitForLoadState('networkidle');
    await guest.goto(`${baseURL}/admin/workspaces/browser-beta/members`);
    await guest.locator('.fi-tenancy-members').getByText('Invited Member', { exact: true }).waitFor();
    assert.equal(await guest.getByRole('button', { name: 'Invite members', exact: true }).count(), 0);
    await assertMembersLayout(guest);
    await ownerPage.goto(`${baseURL}/admin/workspaces/browser-beta/members`);
    await ownerPage.getByRole('button', { name: 'Impersonate', exact: true }).waitFor();
    await assertMembersLayout(ownerPage);
    page = ownerPage;
    await guestContext.close();
    assert.deepEqual(errors, [], 'Browser JavaScript errors');
    console.log('Browser flow passed: workspace provisioning/switching and invitation → locked-email registration → membership.');
} catch (error) {
    if (page && !page.isClosed()) {
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
