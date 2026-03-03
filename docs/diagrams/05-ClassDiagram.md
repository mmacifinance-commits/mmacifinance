# Class Diagram

> Based on Eloquent models in `app/Models/`. Includes relationships, key attributes, and public methods.

```mermaid
classDiagram

    class User {
        +int id
        +string name
        +string email
        +string role
        +string password
        +string otp_code
        +datetime otp_expires_at
        +datetime created_at
        +datetime updated_at
        --
        +isSuperAdmin() bool
        +canManageBudget() bool
        +canManageExpenses() bool
        +canManageDisbursements() bool
        +getRoleLabelAttribute() string
    }

    class Department {
        +int id
        +string name
        +string code
        +datetime created_at
        +datetime updated_at
    }

    class BudgetCategory {
        +int id
        +string name
        +string description
        +datetime created_at
        +datetime updated_at
    }

    class BudgetParticular {
        +int id
        +int category_id
        +int department_id
        +string account_code
        +string account_name
        +string particular
        +string description
        +datetime created_at
        +datetime updated_at
        --
        +category() BelongsTo
        +department() BelongsTo
        +budgetItems() HasMany
        +expenses() HasMany
    }

    class AnnualBudget {
        +int id
        +int year
        +string semester
        +datetime created_at
        +datetime updated_at
        --
        +items() HasMany
    }

    class BudgetItem {
        +int id
        +int budget_id
        +int category_id
        +int particular_id
        +decimal appropriation
        +decimal expenditure
        +datetime created_at
        +datetime updated_at
        --
        +budget() BelongsTo
        +category() BelongsTo
        +particular() BelongsTo
    }

    class Expense {
        +int id
        +string ref_no
        +string description
        +int category_id
        +int particular_id
        +decimal amount
        +decimal paid
        +date date_encoded
        +date date_approved
        +enum status
        +string notes
        +datetime created_at
        +datetime updated_at
        --
        +category() BelongsTo
        +particular() BelongsTo
    }

    class Disbursement {
        +int id
        +string disbursement_no
        +string description
        +string source
        +string pay_to
        +decimal amount
        +enum method
        +date date_encoded
        +date date_approved
        +enum status
        +string notes
        +datetime created_at
        +datetime updated_at
    }

    %% ── Middleware / Controllers (supporting classes) ──────────────

    class CheckRole {
        +handle(Request, Closure, roles) Response
    }

    class HandleInertiaRequests {
        +share(Request) array
        +version(Request) string
    }

    class LoginController {
        +showLoginForm() Response
        +login(Request) Response
        +logout(Request) Response
    }

    class TwoFactorController {
        +index() Response
        +verify(Request) Response
        +resend(Request) Response
    }

    %% ── Relationships ─────────────────────────────────────────────

    AnnualBudget      "1" --> "0..*" BudgetItem       : items()
    BudgetCategory    "1" --> "0..*" BudgetParticular  : particulars
    BudgetCategory    "1" --> "0..*" BudgetItem        : classifies
    BudgetCategory    "1" --> "0..*" Expense           : classifies
    Department        "1" --> "0..*" BudgetParticular  : owns
    BudgetParticular  "1" --> "0..*" BudgetItem        : budgetItems()
    BudgetParticular  "1" --> "0..*" Expense           : expenses()

    LoginController     ..> User                       : authenticates
    TwoFactorController ..> User                       : verifies OTP
    CheckRole           ..> User                       : checks role
```

## Enumerations

| Class | Field | Values |
|---|---|---|
| `Expense` | `status` | `pending`, `approved`, `cancelled` |
| `Disbursement` | `status` | `pending`, `approved`, `posted`, `cancelled` |
| `Disbursement` | `method` | `check`, `cash`, `bank_transfer` |
| `User` | `role` | `super_admin`, `budget_officer`, `disbursement_officer`, `auditor` |
