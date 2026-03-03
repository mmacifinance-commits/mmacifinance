# DFD Level 1 — Process Decomposition

> Expands the single system bubble from Level 0 into five internal processes, showing data stores and flows between them.

```mermaid
flowchart TB

    %% ── External Entities ──────────────────────────────────────────
    SA(["👤 Super Admin"])
    BO(["👤 Budget Officer"])
    DO(["👤 Disbursement Officer"])
    AU(["👤 Auditor"])
    MS(["📧 Mail Server"])

    %% ── Processes ───────────────────────────────────────────────────
    P1["1.0\nAuthentication\n& 2FA"]
    P2["2.0\nBudget\nManagement"]
    P3["3.0\nExpense\nManagement"]
    P4["4.0\nDisbursement\nManagement"]
    P5["5.0\nDashboard &\nReporting"]

    %% ── Data Stores ─────────────────────────────────────────────────
    D1[("D1\nusers")]
    D2[("D2\nannual_budgets")]
    D3[("D3\nbudget_items")]
    D4[("D4\nbudget_categories")]
    D5[("D5\nbudget_particulars")]
    D6[("D6\nexpenses")]
    D7[("D7\ndisbursements")]
    D8[("D8\ndepartments")]

    %% ── 1.0 Authentication & 2FA ────────────────────────────────────
    SA & BO & DO & AU -->|"email + password"| P1
    P1 <-->|"lookup / write OTP"| D1
    P1 -->|"OTP email"| MS
    MS -->|"OTP code"| SA & BO & DO & AU
    P1 -->|"session token / auth"| SA & BO & DO & AU

    %% ── 2.0 Budget Management ───────────────────────────────────────
    SA & BO -->|"budget data,\ncategories, particulars,\ndepartment info"| P2
    AU -->|"view request"| P2
    P2 <-->|"read / write"| D2
    P2 <-->|"read / write"| D3
    P2 <-->|"read / write"| D4
    P2 <-->|"read / write"| D5
    P2 <-->|"read / write"| D8
    P2 -->|"budget records,\nconfirmation"| SA & BO & AU

    %% ── 3.0 Expense Management ──────────────────────────────────────
    SA & DO -->|"expense data"| P3
    AU -->|"view request"| P3
    P3 <-->|"read / write"| D6
    P3 -->|"read"| D4 & D5
    P3 -->|"expense records,\nconfirmation"| SA & DO & AU

    %% ── 4.0 Disbursement Management ─────────────────────────────────
    SA & DO -->|"disbursement data"| P4
    AU -->|"view request"| P4
    P4 <-->|"read / write"| D7
    P4 -->|"disbursement records,\nconfirmation"| SA & DO & AU

    %% ── 5.0 Dashboard & Reporting ───────────────────────────────────
    D2 & D3 & D6 & D7 -->|"aggregated data"| P5
    P5 -->|"dashboard stats,\nfinancial reports"| SA & BO & DO & AU
```

## Process Descriptions

| Process | Description |
|---|---|
| **1.0 Authentication & 2FA** | Validates credentials against `users`, generates a 6-digit OTP, emails it via SMTP, then verifies OTP before granting a session |
| **2.0 Budget Management** | CRUD for `annual_budgets`, `budget_items`, `budget_categories`, `budget_particulars`, and `departments` |
| **3.0 Expense Management** | CRUD for `expenses`; auto-generates `ref_no` (EXP########); reads categories and particulars for classification |
| **4.0 Disbursement Management** | CRUD for `disbursements`; tracks payment method, payee, and approval status |
| **5.0 Dashboard & Reporting** | Aggregates totals (appropriation, expenditure, balance), utilization rates per category, and recent activity feeds |
