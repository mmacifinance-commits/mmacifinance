# DFD Level 0 — Context Diagram

> Shows the system as a single process, all external entities, and the high-level data flows between them.

```mermaid
flowchart LR

    SA(["👤\nSuper Admin"])
    BO(["👤\nBudget Officer"])
    DO(["👤\nDisbursement\nOfficer"])
    AU(["👤\nAuditor"])
    MS(["📧\nMail Server\nSMTP"])

    SYSTEM["⬛ Budget Fund\nUtilization &\nTracking System"]

    SA -->|"Credentials, budget data,\nexpense & disbursement data"| SYSTEM
    BO -->|"Credentials, budget data"| SYSTEM
    DO -->|"Credentials, expense &\ndisbursement data"| SYSTEM
    AU -->|"Credentials"| SYSTEM

    SYSTEM -->|"Dashboard, reports,\nconfirmations"| SA
    SYSTEM -->|"Budget summaries,\nconfirmations"| BO
    SYSTEM -->|"Expense & disbursement\nrecords, confirmations"| DO
    SYSTEM -->|"Read-only dashboard\n& reports"| AU

    SYSTEM -->|"OTP email request"| MS
    MS -->|"Delivered OTP code\n(6-digit)"| SA
    MS -->|"Delivered OTP code"| BO
    MS -->|"Delivered OTP code"| DO
    MS -->|"Delivered OTP code"| AU
```

## External Entities

| Entity | Role in System |
|---|---|
| **Super Admin** | Full access — all CRUD + view |
| **Budget Officer** | Manages budget, categories, particulars, departments |
| **Disbursement Officer** | Manages expenses and disbursements |
| **Auditor** | Read-only access to all data |
| **Mail Server (SMTP)** | Delivers OTP codes for 2FA on every login |
