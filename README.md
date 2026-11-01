# Antrean Digital

A college Project Based Learning (PBL) application for managing the queue for current visits to a single restaurant in Kuta. Built with Laravel 13, PHP 8.3+, MySQL 8.4, Blade, CSS, and plain JavaScript. No Node.js build or running Node.js process is required to display the application.

## Running on this computer

From PowerShell:

```powershell
cd D:\Git\PBL
.\scripts\start.ps1
```

Open [the customer page](http://127.0.0.1:8085/) or [the staff dashboard](http://127.0.0.1:8085/staf). Local demo login details are stored in `storage/local/demo-credentials.txt`, which is excluded from Git. Use synthetic data when trying the application.

The project's dedicated MySQL instance uses port 3311 and stores its data in `storage/local/mysql`. Development, automated tests, and concurrency checks use separate databases: `pbl`, `pbl_testing`, and `pbl_concurrency`. The scripts leave other coursework databases and XAMPP/Laragon services untouched. Machine-specific executable paths are stored in `storage/local/runtime.json`.

Press Ctrl+C to stop the web server. Run `scripts/stop-database.ps1` to stop the project's MySQL instance.

## Setting up on another computer

1. Install PHP 8.3+ with the extensions required by Laravel, including PDO MySQL, along with Composer and MySQL 8.4.
2. Run `composer install`, copy `.env.example` to `.env`, and configure a new database dedicated to this project. Do not reuse another application's database.
3. Run `php artisan key:generate`, followed by `php artisan migrate`.
4. Create a staff account with `php artisan pbl:staff staff@example.test`. Enter the password through the hidden prompt. Public staff registration is not available.
5. Run `php artisan serve --host=127.0.0.1 --port=8085 --no-reload --quiet`.

For automated tests, create a separate database, copy `.env` to `.env.testing`, and update `APP_ENV=testing`, `DB_DATABASE`, and the credentials as needed. The test database name must end in `_testing`. Tests use `RefreshDatabase`; never point them at an operational database.

## Verification

```powershell
php artisan test --compact
php tests/Support/concurrency.php
node --check public/app.js
```

The concurrency script **drops and recreates tables only in `pbl_concurrency`**, which must be reserved for disposable test data. The main test suite uses `.env.testing` and the `pbl_testing` database on this computer. Node.js is needed only for the JavaScript syntax check shown above, not to run the application.

Recorded results and verification limits are documented in [docs/verification.md](docs/verification.md).

## Queue workflow and rules

Customer name and party size are required; telephone is optional. Customers keep a private link to their ticket. Staff call customers in first-in, first-out (FIFO) order when space is available, confirm their arrival, and complete the ticket after directing the party to a table. Several parties may be called and awaiting arrival at the same time.

Cancellation or skipping ends a ticket. Returning customers must take a new number.

The 15-minute arrival allowance is a **proposed rule that still requires validation**. The system flags overdue tickets, while staff decide whether to cancel them. Waiting-time estimates remain unavailable until an interval is configured through the dashboard. These timing values need validation with the restaurant.

## Documentation and security

See [the specification](docs/specification.md), [system design](docs/design.md), and [verification record](docs/verification.md). Original reference materials are stored in `reference/`. As clarified by the user, the presentation filename without `(1)` is the latest revision.

The November 3, 2026 presentation is PBL Stage 1, focused on design. A working application does not establish that all assessment deliverables or restaurant validation are complete.

Ticket links are access secrets. Do not share screenshots of their URLs or logs containing them. When configuring Nginx, use `public` as the document root, disable access logging for `/tiket/` routes, enable HTTPS, and prevent access to `.env`, `storage`, and the project root.

Production configuration and a data-retention policy are outside the current local demo. The application does not include SMS/WhatsApp notifications, customer accounts, payments, menu ordering, or automatic table-availability monitoring.
