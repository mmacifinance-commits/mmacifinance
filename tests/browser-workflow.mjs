import { chromium } from 'playwright'
import assert from 'node:assert/strict'
import { spawn, execFileSync } from 'node:child_process'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { randomUUID } from 'node:crypto'
import { unlinkSync } from 'node:fs'

const base = 'http://127.0.0.1:8137'
const database = join(tmpdir(), 'finance-browser-'+randomUUID().replaceAll('-', '')+'.sqlite')
const env = { ...process.env, FINANCE_BROWSER_DATABASE: database }
execFileSync('php', ['tests/browser-fixture.php'], { env, stdio: 'pipe' })
const server = spawn('php', ['-S', '127.0.0.1:8137', '-t', 'public', 'tests/browser-router.php'], { env, stdio: 'ignore', windowsHide: true })
for (let i=0; i<100; i++) {
    if (server.exitCode !== null) throw new Error('Isolated browser server could not start')
    try { await fetch(base+'/login'); break } catch { await new Promise(resolve => setTimeout(resolve, 100)) }
}
const browser = await chromium.launch({ channel: 'msedge', headless: true })
const context = await browser.newContext({ baseURL: base, acceptDownloads: true, viewport: { width: 1920, height: 1080 } })
const page = await context.newPage()
page.setDefaultTimeout(15000)
const errors = []
page.on('pageerror', (error) => errors.push(error.message))
page.on('dialog', (dialog) => dialog.accept())
const field = (text) => page.locator('.app-modal label').filter({ hasText: text }).locator('..').locator('input,select,textarea').first()
const ready = async () => {
    await page.locator('[role="status"]').filter({ hasText: 'Loading...' }).waitFor({ state: 'hidden' }).catch(() => {})
}
try {
    await page.goto('/__test/login/super_admin')
    if (!await page.getByText('BROWSER-OR-1', { exact: false }).count()) {
    await page.getByRole('button', { name: 'Add Receipt', exact: true }).click()
    await field('Receipt No.').fill('BROWSER-OR-1')
    await field('Receipt Type').fill('Browser Tuition')
    await field('Source').fill('Browser Collections')
    await field('Description').fill('Browser receipt workflow')
    await field('Amount').fill('5000')
    await field('Date').fill('2026-09-16')
    await page.getByRole('button', { name: 'Create Receipt', exact: true }).click()
    }
    await page.getByText('BROWSER-OR-1', { exact: false }).first().waitFor()
    console.log('PASS browser receipt creation')

    await page.goto('/expenses')
    await page.getByRole('button', { name: 'Submit for Approval', exact: true }).first().click()
    await page.getByRole('button', { name: 'Approve', exact: true }).first().click()
    await ready()
    console.log('PASS browser expense submission and approval')

    await page.goto('/disbursements')
    await page.getByRole('button', { name: 'Create Payment Release' }).click()
    await page.locator('.app-modal button').filter({ hasText: 'Browser supplies' }).first().click()
    await field('Pay To').fill('Browser Supplier')
    await field('Disbursement Amount').fill('1000')
    await field('Date Encoded').fill('2026-09-16')
    await page.locator('.app-modal button[type="submit"]').click()
    await page.getByRole('button', { name: 'Submit Release', exact: true }).first().click()
    await page.getByRole('button', { name: 'Approve', exact: true }).first().click()
    await page.locator('.app-modal button[type="submit"]').click()
    await page.getByRole('button', { name: 'Post Release', exact: true }).first().click()
    await page.locator('.app-modal button[type="submit"]').click()
    await page.locator('tbody').getByText('Posted (GL)', { exact: true }).waitFor()
    console.log('PASS browser payment creation, approval and posting')

    await page.goto('/reports')
    const [download] = await Promise.all([
        page.waitForEvent('download'),
        page.getByRole('link', { name: 'Export Current Report' }).click(),
    ])
    assert.match(download.suggestedFilename(), /\.xlsx$/)
    assert.equal(await download.failure(), null)
    console.log('PASS browser XLSX download')

    await page.goto('/departments')
    await page.getByRole('button', { name: /Next/ }).click()
    await page.waitForURL(/page=2/)
    assert.equal(await page.locator('tbody tr').count(), 2)
    console.log('PASS browser pagination')

    await page.goto('/receipts')
    await page.getByPlaceholder('Search receipt no, income no, source, or description...').fill('BROWSER-OR-1')
    await page.waitForURL(/search=BROWSER-OR-1/)
    await page.getByText('BROWSER-OR-1', { exact: false }).first().waitFor()
    console.log('PASS browser filters')

    await page.getByRole('button', { name: 'Import XLSX', exact: true }).click()
    await page.locator('.app-modal input[type="file"]').setInputFiles({
        name: 'browser-receipts.csv', mimeType: 'text/csv',
        buffer: Buffer.from('receipt_no,receipt_type,source,description,amount,date_encoded\nBROWSER-IMPORT,Tuition,Collections,Browser import,75,2026-09-16'),
    })
    await page.getByRole('button', { name: 'Preview Import', exact: true }).click()
    await page.getByRole('button', { name: 'Confirm Import', exact: true }).waitFor()
    const version = await page.evaluate(() => JSON.parse(document.getElementById('app').dataset.page).version)
    const previewCheck = await page.request.get('/receipts?search=BROWSER-IMPORT', { headers: { 'X-Inertia': 'true', 'X-Inertia-Version': version } })
    assert.equal((await previewCheck.json()).props.receipts.data.length, 0)
    await page.getByRole('button', { name: 'Confirm Import', exact: true }).click()
    await page.getByText('BROWSER-IMPORT', { exact: true }).waitFor()
    console.log('PASS browser import preview saves only after confirmation')

    await page.getByRole('button', { name: 'Delete Records', exact: true }).click()
    await page.locator('.app-modal label').filter({ hasText: 'BROWSER-IMPORT' }).locator('input[type="checkbox"]').check()
    await page.getByRole('button', { name: 'Review Deletion', exact: true }).click()
    await page.getByText('Permanently delete 1 record(s)?', { exact: false }).waitFor()
    await page.getByLabel('Type DELETE to confirm').fill('DELETE')
    await page.getByRole('button', { name: 'Permanently Delete', exact: true }).click()
    await page.getByText('1 record(s) deleted successfully.', { exact: true }).waitFor()
    const deletedCheck = await page.request.get('/receipts?search=BROWSER-IMPORT', { headers: { 'X-Inertia': 'true', 'X-Inertia-Version': version } })
    assert.equal((await deletedCheck.json()).props.receipts.data.length, 0)
    console.log('PASS browser selected deletion, confirmation, and successful refresh')

    await page.goto('/expenses')
    await page.getByRole('button', { name: 'Delete Records', exact: true }).click()
    await page.getByLabel('Delete all records in this module', { exact: false }).check()
    await page.getByRole('button', { name: 'Review Deletion', exact: true }).click()
    await page.getByLabel('Type DELETE to confirm').fill('DELETE')
    await page.getByRole('button', { name: 'Permanently Delete', exact: true }).click()
    await page.locator('.app-modal [role="alert"]').filter({ hasText: 'linked disbursements' }).waitFor()
    await page.getByRole('button', { name: 'Cancel', exact: true }).click()
    console.log('PASS browser protected deletion warning')

    await page.goto('/annual-budgets')
    await page.getByRole('link', { name: 'Manage Items', exact: true }).first().click()
    await page.getByRole('heading', { name: 'Annual Budget Allocations', exact: true }).waitFor()
    console.log('PASS browser Manage Items navigation')
    await page.goto('/receipts')

    await page.evaluate(() => navigator.serviceWorker.ready)
    await page.getByRole('link', { name: 'INCOME', exact: true }).click()
    await page.waitForURL(/\/income/)
    await ready()
    await page.getByRole('link', { name: 'RECEIPTS', exact: true }).click()
    await page.waitForURL(/\/receipts/)
    await ready()
    await context.setOffline(true)
    await page.getByRole('link', { name: 'INCOME', exact: true }).click()
    await page.waitForURL(/\/income/)
    await ready()
    await page.getByRole('link', { name: 'RECEIPTS', exact: true }).click()
    await page.waitForURL(/\/receipts/)
    await ready()
    await page.getByPlaceholder('Search receipt no, income no, source, or description...').fill('NEVER-CACHED')
    await page.getByText('This page is not available offline yet.', { exact: false }).waitFor()
    assert.equal(await page.locator('iframe').count(), 0)
    await context.setOffline(false)
    console.log('PASS browser cached navigation and friendly uncached offline message')

    for (const role of ['cashier', 'budget_officer', 'disbursement_officer', 'auditor']) {
        await page.goto('/__test/login/'+role)
        await page.goto('/reports')
        await page.getByRole('link', { name: 'Generate Report', exact: true }).waitFor()
    }
    assert.deepEqual(errors, [])
    console.log('PASS report access for all five roles; no uncaught browser errors')
} catch (error) {
    console.error((await page.locator('body').innerText()).slice(-5000))
    throw error
} finally {
    await browser.close()
    server.kill()
    await new Promise(resolve => server.once('exit', resolve))
    unlinkSync(database)
}
