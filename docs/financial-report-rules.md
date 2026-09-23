# Financial report calculation rules

All nine report types use `FinancialReportService` for the main page sections,
generated print sections, and Excel sections. Financial records are read only.

- Budget utilization: allocation appropriation less posted disbursements matching
  the selected allocation and payment-date filters. It is not cash balance.
- Projected income: existing rows without receipt numbers. No receipt is required.
- Cash receipts: existing rows with receipt numbers, by their recorded receipt
  date. These are actual collections and are excluded from projected income.
  Both remain in the existing storage table; no records, IDs or links are rewritten.
- Disbursements: posted payments only; draft, rejected, approved-but-unposted and
  other unposted releases are excluded from expenditure.
- Income vs receipts: projected income versus actual receipts in the date range,
  with separate detail tables. The difference is not proof of unpaid debt.
- Fund balance / available cash: receipts less posted disbursements for the same
  selected dates. No starting balance is added. This is not a full balance sheet
  or certified bank reconciliation.
- Responsibility center: totals grouped by center ID, with allocation detail.
- Account-title ledger: each allocation's opening available budget, posted
  payment entries in chronological order, and running remaining budget. This is
  a budget ledger, not a double-entry general ledger.
- Overall: allocation utilization, receipts and posted disbursement detail.
- Closing: full fiscal-year cash and budget sections only. Open periods are
  provisional; even closed periods are current stored data, not immutable snapshots.

Date filters use transaction dates. Allocation month applies to allocations and
their payments, not receipts. Receipts and cash for the whole school cannot be assigned
to a department/category/account because the records do not contain those links.
The reports explain this instead of fabricating an attribution. Pending commitment
figures reflect current workflow status, not status as of a historical date.

The full-year comparison table remains full-year and is labeled separately from
date-filtered report totals. Excel budget balances, totals and utilization use
formulas; user-provided text is written as text, not executable spreadsheet formulas.

Regression coverage: `FinancialReportAccuracyTest` verifies multi-center/date/status
filtering, income comparison, running balances and every export's cells and totals.
