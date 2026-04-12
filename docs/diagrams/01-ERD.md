# Entity Relationship Diagram (ERD)

> Covers all domain tables. Laravel system tables (cache, sessions, jobs, password_reset_tokens) are excluded.

```mermaid
erDiagram

    users {
        bigint id PK
        string name
        string email UK
        string role
        string password
        string otp_code
        timestamp otp_expires_at
        timestamp created_at
        timestamp updated_at
    }

    departments {
        bigint id PK
        string name
        string code UK
        timestamp created_at
        timestamp updated_at
    }

    budget_categories {
        bigint id PK
        string name
        text description
        timestamp created_at
        timestamp updated_at
    }

    budget_particulars {
        bigint id PK
        bigint category_id FK
        bigint department_id FK
        string account_code
        string account_name
        string particular
        text description
        timestamp created_at
        timestamp updated_at
    }

    annual_budgets {
        bigint id PK
        smallint year
        string semester
        timestamp created_at
        timestamp updated_at
    }

    budget_items {
        bigint id PK
        bigint budget_id FK
        bigint category_id FK
        bigint particular_id FK
        decimal appropriation
        decimal expenditure
        timestamp created_at
        timestamp updated_at
    }

    expenses {
        bigint id PK
        string ref_no UK
        string description
        bigint category_id FK
        bigint particular_id FK
        decimal amount
        decimal paid
        date date_encoded
        date date_approved
        enum status
        text notes
        timestamp created_at
        timestamp updated_at
    }

    disbursements {
        bigint id PK
        string disbursement_no UK
        string description
        string source
        string pay_to
        decimal amount
        enum method
        date date_encoded
        date date_approved
        enum status
        text notes
        timestamp created_at
        timestamp updated_at
    }

    budget_categories ||--o{ budget_particulars  : "has"
    departments       ||--o{ budget_particulars  : "owns"
    annual_budgets    ||--o{ budget_items         : "contains"
    budget_categories ||--o{ budget_items         : "classifies"
    budget_particulars||--o{ budget_items         : "itemized as"
    budget_categories ||--o{ expenses             : "classifies"
    budget_particulars||--o{ expenses             : "charged to"
```

## Notes

| Column | Detail |
|---|---|
| `users.role` | `super_admin` \| `budget_officer` \| `disbursement_officer` \| `auditor` |
| `annual_budgets.(year, semester)` | Composite unique key — one budget per year/semester |
| `expenses.ref_no` | Auto-generated as `EXP########` (e.g., `EXP00000001`) |
| `disbursements.disbursement_no` | Auto-generated as `DSB########` |
| `expenses.status` | `pending` \| `approved` \| `cancelled` |
| `disbursements.status` | `pending` \| `approved` \| `posted` \| `cancelled` |
| `disbursements.method` | `check` \| `cash` \| `bank_transfer` |
| `budget_items.expenditure` | Planned/tracked expenditure per line item (not linked to Expense rows) |
