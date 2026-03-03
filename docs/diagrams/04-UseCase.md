# Use Case Diagram

> Shows all system use cases and which actors can perform them.

```mermaid
flowchart LR

    %% ── Actors ────────────────────────────────────────────────────
    SA["👤\nSuper Admin"]
    BO["👤\nBudget Officer"]
    DO["👤\nDisbursement Officer"]
    AU["👤\nAuditor"]

    %% ── System Boundary ───────────────────────────────────────────
    subgraph SYSTEM ["Budget Fund Utilization & Tracking System"]

        subgraph AUTH ["Authentication"]
            UC1(["Login with\nEmail & Password"])
            UC2(["Verify 2FA\nOTP Code"])
            UC3(["Logout"])
        end

        subgraph DASH ["Dashboard & Reports"]
            UC4(["View Dashboard\n& Statistics"])
            UC5(["View Financial\nReports"])
        end

        subgraph BUDGET ["Budget Management"]
            UC6(["Manage Annual\nBudgets"])
            UC7(["Manage Budget\nItems"])
            UC8(["Manage Budget\nCategories"])
            UC9(["Manage Budget\nParticulars"])
            UC10(["Manage\nDepartments"])
        end

        subgraph EXPENSE ["Expenditure Management"]
            UC11(["Manage\nExpenses"])
        end

        subgraph DISB ["Disbursement Management"]
            UC12(["Manage\nDisbursements"])
        end
    end

    %% ── Super Admin ───────────────────────────────────────────────
    SA --- UC1
    SA --- UC2
    SA --- UC3
    SA --- UC4
    SA --- UC5
    SA --- UC6
    SA --- UC7
    SA --- UC8
    SA --- UC9
    SA --- UC10
    SA --- UC11
    SA --- UC12

    %% ── Budget Officer ────────────────────────────────────────────
    BO --- UC1
    BO --- UC2
    BO --- UC3
    BO --- UC4
    BO --- UC5
    BO --- UC6
    BO --- UC7
    BO --- UC8
    BO --- UC9
    BO --- UC10

    %% ── Disbursement Officer ──────────────────────────────────────
    DO --- UC1
    DO --- UC2
    DO --- UC3
    DO --- UC4
    DO --- UC5
    DO --- UC11
    DO --- UC12

    %% ── Auditor ───────────────────────────────────────────────────
    AU --- UC1
    AU --- UC2
    AU --- UC3
    AU --- UC4
    AU --- UC5
```

## Actor–Use Case Matrix

| Use Case | Super Admin | Budget Officer | Disbursement Officer | Auditor |
|---|:---:|:---:|:---:|:---:|
| Login with Email & Password | ✅ | ✅ | ✅ | ✅ |
| Verify 2FA OTP Code | ✅ | ✅ | ✅ | ✅ |
| Logout | ✅ | ✅ | ✅ | ✅ |
| View Dashboard & Statistics | ✅ | ✅ | ✅ | ✅ |
| View Financial Reports | ✅ | ✅ | ✅ | ✅ |
| Manage Annual Budgets | ✅ | ✅ | — | — |
| Manage Budget Items | ✅ | ✅ | — | — |
| Manage Budget Categories | ✅ | ✅ | — | — |
| Manage Budget Particulars | ✅ | ✅ | — | — |
| Manage Departments | ✅ | ✅ | — | — |
| Manage Expenses | ✅ | — | ✅ | — |
| Manage Disbursements | ✅ | — | ✅ | — |

> "Manage" = Create, Read, Update, Delete. Auditor has read-only access via the dashboard and reports.
