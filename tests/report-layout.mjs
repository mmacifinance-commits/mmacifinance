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
const server = spawn('php', ['-S', '127.0.0.1:8138', '-t', 'public', 'tests/browser-router.php'], { env, stdio: 'ignore', windowsHide: true })
let browser
try {
    for (let i = 0; i < 100; i++) {
        try { await fetch('http://127.0.0.1:8138/login'); break } catch { await new Promise(resolve => setTimeout(resolve, 100)) }
    }
    browser = await chromium.launch({ channel: 'msedge', headless: true })
    const page = await browser.newPage({ viewport: { width: 1600, height: 1000 } })
    await page.goto('http://127.0.0.1:8138/__test/login/super_admin')
    await page.goto('http://127.0.0.1:8138/reports/generate?report_type=budget_utilization')
    await page.locator('.report-table tbody tr').first().waitFor()
    const pdfLink = page.getByRole('link', { name: 'Print / Save PDF' })
    const pdfResponse = await page.request.get(await pdfLink.getAttribute('href'))
    assert.equal(pdfResponse.status(), 200)
    assert.match(pdfResponse.headers()['content-type'], /application\/pdf/)
    const pdfBytes = await pdfResponse.body()
    assert.equal(pdfBytes.subarray(0, 5).toString(), '%PDF-')
    writeFileSync(join(tmpdir(), 'finance-report-print-check.pdf'), pdfBytes)
    console.log('PASS print action opens a server-generated PDF without browser headers')
    await page.locator('.report-table tbody tr').first().evaluate(row => {
        row.children[5].textContent = 'LAPTOP FOR FINANCE DIRECTOR, BUDGET OFFICER, AND ACCOUNTING SUPERVISOR'
        row.children[6].textContent = '₱111,000.00'
    })
    for (const media of ['screen', 'print']) {
        await page.emulateMedia({ media })
        const result = await page.evaluate(() => {
            const table = document.querySelector('.report-table')
            const amount = table.querySelector('tbody tr').children[6]
            const particulars = table.querySelector('tbody tr').children[5]
            return {
                fits: table.getBoundingClientRect().width <= document.querySelector('.report-sheet').getBoundingClientRect().width,
                nowrap: getComputedStyle(amount).whiteSpace,
                numberFits: amount.scrollWidth <= amount.clientWidth + 1,
                rowHeight: particulars.getBoundingClientRect().height,
                font: getComputedStyle(particulars).fontFamily,
            }
        })
        assert.equal(result.fits, true)
        assert.equal(result.nowrap, 'nowrap')
        assert.equal(result.numberFits, true)
        assert.ok(result.rowHeight < 150, `${media}: excessively tall particulars row`)
        assert.match(result.font, /Arial/)
        console.log(`PASS ${media}: readable particulars and unbroken currency`)
    }
    for (const [type, heading] of [['account_title_ledger', 'Account Title Summary'], ['closing_report', 'Fiscal Year Closing Status']]) {
        await page.emulateMedia({ media: 'screen' })
        await page.goto(`http://127.0.0.1:8138/reports/generate?report_type=${type}`)
        await page.locator('.report-table').first().waitFor()
        assert.match(await page.locator('.report-table').first().innerText(), new RegExp(heading, 'i'))
        const overflowing = await page.locator('.report-number').evaluateAll(cells => cells.filter(cell => cell.scrollWidth > cell.clientWidth + 1).length)
        assert.equal(overflowing, 0, `${type}: currency must fit its columns`)
        await page.screenshot({ path: join(tmpdir(), `${type}-layout.png`), fullPage: true })
        console.log(`PASS ${type}: summary layout and currency fit`)
    }
} finally {
    await browser?.close()
    server.kill()
    await new Promise(resolve => server.once('exit', resolve))
    unlinkSync(database)
}
