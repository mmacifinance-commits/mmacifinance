# Network Topology

> Depicts the physical/logical network layout for a typical institutional LAN deployment of this system.

```mermaid
flowchart TB

    %% ── External ──────────────────────────────────────────────────
    INTERNET(["🌐 Internet /\nExternal SMTP"])

    %% ── Perimeter ─────────────────────────────────────────────────
    subgraph PERIMETER ["Network Perimeter"]
        FW["🔥 Firewall /\nRouter\n(Port 80 / 443 allowed)"]
    end

    %% ── LAN ───────────────────────────────────────────────────────
    subgraph LAN ["Institution LAN (192.168.x.x)"]

        SW["🔀 Managed Switch\n100 / 1000 Mbps"]

        subgraph SERVERS ["Server Subnet"]
            APP["🖥️ Application Server\nWindows + Laragon\n(Apache 2.4, PHP 8.2)\nIP: 192.168.1.10\nPort 80 / 443"]
            DB["🗄️ Database Server\nMySQL 8.0 / SQLite\nIP: 192.168.1.11\nPort 3306"]
            MAIL["📧 Internal Mail Relay\n(SMTP)\nIP: 192.168.1.12\nPort 25 / 587"]
        end

        subgraph CLIENTS ["Client Workstations"]
            C1["💻 Admin PC\n(Super Admin)"]
            C2["💻 Budget Officer PC"]
            C3["💻 Disbursement\nOfficer PC"]
            C4["💻 Auditor PC"]
        end
    end

    %% ── Connections ──────────────────────────────────────────────
    INTERNET <-->|"HTTPS / SMTP TLS"| FW
    FW <-->|"Filtered traffic"| SW

    SW <-->|"HTTP/HTTPS"| APP
    APP <-->|"MySQL TCP 3306"| DB
    APP <-->|"SMTP 587"| MAIL
    MAIL <-->|"SMTP relay"| INTERNET

    SW <-->|"HTTP/HTTPS"| C1
    SW <-->|"HTTP/HTTPS"| C2
    SW <-->|"HTTP/HTTPS"| C3
    SW <-->|"HTTP/HTTPS"| C4
```

## Node Reference

| Node | Role | Protocol / Port |
|---|---|---|
| Firewall / Router | Perimeter security, NAT, port filtering | HTTP 80, HTTPS 443 |
| Application Server | Runs Apache + PHP 8.2 + Laravel 12 + Queue Worker | HTTP/HTTPS |
| Database Server | Stores all application data (MySQL or SQLite file on same host) | TCP 3306 (MySQL) |
| Internal Mail Relay | Routes OTP emails to users; relays to external SMTP if needed | SMTP 25 / 587 |
| Client Workstations | Access the web application through a browser — no software install | HTTPS |

## Notes

- For a **Laragon local setup** (development), the Application Server and Database run on the same Windows machine on `localhost`.
- In **production**, separating the DB to its own host (or using MySQL on the same server) is recommended.
- All user-facing traffic should be served over **HTTPS** (TLS/SSL certificate required on the Application Server).
- The Queue Worker (`php artisan queue:listen`) runs as a background process on the Application Server and handles OTP mail dispatch asynchronously.
