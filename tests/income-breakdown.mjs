import { chromium } from 'playwright'
import assert from 'node:assert/strict'
import { spawn, execFileSync } from 'node:child_process'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { randomUUID } from 'node:crypto'
import { unlinkSync, writeFileSync } from 'node:fs'

const database = join(tmpdir(), `finance-browser-${randomUUID().replaceAll('-', '')}.sqlite`)
const env = { ...process.env, FINANCE_BROWSER_DATABASE: database }
execFileSync('php', ['tests/browser-fixture.php'], { env, stdio: 'pipe' })
const server = spawn('php', ['-S', '127.0.0.1:8140', '-t', 'public', 'tests/browser-router.php'], { env, stdio: 'ignore', windowsHide: true })
let browser
try {
    for (let i = 0; i < 100; i++) {
        try { await fetch('http://127.0.0.1:8140/login'); break } catch { await new Promise(resolve => setTimeout(resolve, 100)) }
    }
    browser = await chromium.launch({ channel: 'msedge', headless: true })
    const page = await browser.newPage({ viewport: { width: 1600, height: 1000 } })
    await page.goto('http://127.0.0.1:8140/__test/login/super_admin')
    await page.goto('http://127.0.0.1:8140/income')
    const errors = []
    page.on('pageerror', e => errors.push(e.message))
    await page.getByRole('button', { name: /Total Income/ }).click()
    await page.getByText('Calculating breakdown...', { exact: true }).waitFor({ state: 'hidden' })
    await page.getByLabel('Income source', { exact: true }).fill('Tuition Fee')
    await page.getByLabel('Fiscal-year quota (₱)', { exact: true }).fill('10000')
    await page.getByRole('button', { name: 'Save Quota', exact: true }).click()
    await page.getByText('Quota saved.', { exact: true }).waitFor()
    await page.getByText('Calculating breakdown...', { exact: true }).waitFor({ state: 'hidden' })
    assert.match(await page.locator('tbody').last().innerText(), /10,000.00/)
    await page.screenshot({ path: join(tmpdir(), 'income-breakdown.png'), fullPage: true })
    await page.goto('http://127.0.0.1:8140/income')
    await page.getByRole('button', { name: 'Add Income', exact: true }).click()
    await page.getByLabel('Student count', { exact: true }).fill('20')
    await page.getByLabel('Fee per student (₱)', { exact: true }).fill('500.50')
    assert.equal(await page.locator('input[readonly]').inputValue(), '10010')
    await page.goto('http://127.0.0.1:8140/reports')
    await page.getByRole('button', { name: /Projected Income vs Actual Receipts/ }).click()
    await page.getByText('Calculating breakdown...', { exact: true }).waitFor({ state: 'hidden' })
    await page.goto('http://127.0.0.1:8140/__test/login/cashier')
    await page.goto('http://127.0.0.1:8140/receipts')
    assert.equal(await page.getByRole('button', { name: 'Add Receipt', exact: true }).count(), 0)
    await page.getByRole('button', { name: /View receipts and projected income/ }).click()
    assert.equal(await page.getByRole('button', { name: 'Save Quota', exact: true }).count(), 0)
    assert.deepEqual(errors, [])
    console.log('PASS income popup, quota editing, student calculation, report popup and cashier restrictions')
} finally {
    await browser?.close()
    server.kill()
    await new Promise(resolve => server.once('exit', resolve))
    unlinkSync(database)
}
