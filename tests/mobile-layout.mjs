import { chromium } from 'playwright'
import assert from 'node:assert/strict'
import { spawn, execFileSync } from 'node:child_process'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { randomUUID } from 'node:crypto'
import { unlinkSync } from 'node:fs'

const base = 'http://127.0.0.1:8143'
const database = join(tmpdir(), `finance-browser-${randomUUID().replaceAll('-', '')}.sqlite`)
const env = { ...process.env, FINANCE_BROWSER_DATABASE: database }
const viewportWidths = (process.env.FINANCE_LAYOUT_WIDTHS || '320,390,768,1440')
    .split(',')
    .map(Number)
    .filter(Number.isFinite)
const layoutRoutes = (process.env.FINANCE_LAYOUT_ROUTES || '/,/iaeo,/annual-budgets,/annual-budgets/1,/departments,/budget-categories,/budget-particulars,/income,/receipts,/expenses,/disbursements,/reports,/reports/generate')
    .split(',')
    .filter(Boolean)
execFileSync('php', ['tests/browser-fixture.php'], { env, stdio: 'pipe' })
const server = spawn('php', ['-S', '127.0.0.1:8143', '-t', 'public', 'tests/browser-router.php'], { env, stdio: 'ignore', windowsHide: true })
let browser
try {
    for (let i = 0; i < 100; i++) {
        if (server.exitCode !== null) throw new Error('Mobile test server failed to start')
        try { await fetch(base + '/login'); break } catch { await new Promise(resolve => setTimeout(resolve, 100)) }
    }
    browser = await chromium.launch({ channel: 'msedge', headless: true })
    const page = await browser.newPage({ baseURL: base })
    page.setDefaultTimeout(30000)
    const errors = []
    page.on('pageerror', error => errors.push(error.message))
    const fits = async label => {
        const sizes = await page.evaluate(() => ({ content: document.documentElement.scrollWidth, viewport: innerWidth }))
        assert.ok(sizes.content <= sizes.viewport + 1, `${label}: ${JSON.stringify(sizes)}`)
    }
    const keepsSummaryAmountsAligned = async (selector, label) => {
        const rows = await page.locator(selector).evaluateAll(elements => elements.map(element => {
            const [details, amount] = element.children
            if (!details || !amount) return null
            const detailsBox = details.getBoundingClientRect()
            const amountBox = amount.getBoundingClientRect()
            const rowBox = element.getBoundingClientRect()
            return {
                amountTop: amountBox.top,
                detailsTop: detailsBox.top,
                amountRight: amountBox.right,
                rowRight: rowBox.right,
            }
        }).filter(Boolean))

        assert.ok(rows.length > 0, `${label}: expected at least one summary row`)
        assert.ok(rows.every(row => row.amountTop <= row.detailsTop + 2 && row.amountRight <= row.rowRight + 1), `${label}: amount column dropped below or outside its summary row`)
    }
    await page.goto('/__test/login/super_admin')
    for (const width of viewportWidths) {
        await page.setViewportSize({ width, height: 900 })
        for (const route of layoutRoutes) {
            const response = await page.goto(route)
            assert.ok(response.ok(), `${route}: ${response.status()}`)
            await page.locator('main').waitFor()
            await fits(`${width} ${route}`)
            if (route === '/') await keepsSummaryAmountsAligned('.transaction-summary-row', `${width} Dashboard`)
            if (route === '/iaeo') await keepsSummaryAmountsAligned('.record-summary-row', `${width} IAEO`)
        }
        console.log(`PASS page widths at ${width}px`)
    }
    await page.setViewportSize({ width: 320, height: 740 })
    await page.goto('/expenses')
    await page.getByRole('button', { name: 'Toggle navigation' }).click()
    await page.getByRole('button', { name: 'Logout', exact: true }).waitFor()
    await fits('Expanded mobile navigation')
    await page.getByRole('button', { name: 'Toggle navigation' }).click()
    await page.getByRole('button', { name: 'Add Expense', exact: true }).click()
    await page.locator('.app-modal').waitFor()
    await fits('Expense modal')
    await page.screenshot({ path: join(tmpdir(), 'finance-mobile-expense.png'), fullPage: true })
    const guest = await browser.newContext({ baseURL: base, viewport: { width: 320, height: 740 } })
    const authPage = await guest.newPage()
    for (const route of ['/login', '/forgot-password']) {
        await authPage.goto(route)
        assert.ok(await authPage.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), route + ' overflows')
    }
    await guest.close()
    assert.deepEqual(errors, [])
    console.log('PASS mobile navigation and on-demand expense form')
} finally {
    await browser?.close()
    server.kill()
    if (server.exitCode === null) await new Promise(resolve => server.once('exit', resolve))
    unlinkSync(database)
}
