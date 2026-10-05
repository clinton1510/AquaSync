# AquaSync

A plain PHP 8 / PDO / MariaDB water refilling and recurring delivery system. Includes customer, station admin and rider portals. Bootstrap 5 and Bootstrap Icons are loaded from CDN; internet access is needed for those styles and icons. No PHP framework or Composer dependencies are required.

## Run on XAMPP

1. Put this folder in `C:\xampp\htdocs\AquaSync` and start Apache and MySQL.
2. Import `aquasync_db.sql` with phpMyAdmin, or run `C:\xampp\mysql\bin\mysql.exe -u root < aquasync_db.sql` in Command Prompt. Import once into an empty database; the script preserves existing databases and does not drop tables.
3. Edit `app/config.php` if your credentials differ. Environment overrides: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`, `APP_BASE` (default `/AquaSync`).
4. Open http://localhost/AquaSync/.

Development accounts all use **AquaSync123!**:

| Role | Email |
|---|---|
| Admin | admin@aquasync.local |
| Customer | customer@aquasync.local |
| Rider | rider@aquasync.local |

**Change every development password in Profile / Settings before production.** Use a dedicated database user, HTTPS, protected backups and disable public PHP error display. This application is for a single local station. Registration creates customer accounts only; admins create rider accounts.

## Workflows

- Customer: save an address, order a product, review live price and delivery fee, confirm, and open the order to track status or submit a GCash/Maya reference. Reorder prefills product and quantity with current prices. Customers may cancel Pending orders. Feedback is available once per Delivered order.
- Admin: confirm Pending orders, prepare, assign a rider, verify payments manually, and manage products, delivery fees and station settings. Products and delivery areas are disabled rather than deleted to preserve historical records. Customer entries link to their order history. Reports filter by order creation dates, barangay and status and display paid revenue separately from order totals.
- Rider: open an assigned delivery, mark Picked Up, Out for Delivery and Delivered. COD is marked Paid on delivery. Update availability and share a location manually while handling active deliveries. Browser location needs permission and HTTPS or localhost. There is no background GPS tracking or invented ETA.
- Subscriptions: choose weekdays, gallons, time and start date. Pause, resume or cancel future generation. Already generated orders remain and may be managed separately. Each subscription/date pair is unique.
- Tracking shows current status, rider contact and last coordinates during active delivery. Refresh to retrieve updates. No map API is required; `rider_locations` can support a later Leaflet integration.
- Refill estimates use the average interval between distinct completed delivery dates (up to ten most recent dates). At least two delivery dates are required. The estimate is not AI and does not block ordering.
- Delivery groups use barangay, requested date and morning/afternoon window. Where coordinates exist, the first two points show Haversine straight-line distance; this is not route optimization.

## Automatic recurring orders and reminders

Run `C:\xampp\php\php.exe C:\xampp\htdocs\AquaSync\cron\schedules.php` from Windows Task Scheduler every hour. Run only via CLI; HTTP access is denied. The job generates due deliveries through tomorrow, sends tomorrow-delivery and approaching-refill notifications, skips missed dates and avoids duplicates through a database lock, unique keys and per-subscription transactions. Failed subscriptions are reported and retried on the next run. Products or areas disabled by the station block generation until corrected. Scheduler-generated orders require normal admin confirmation.

## Structure

`app/` contains configuration, PDO helpers, reusable authentication, actions and shared portal/layout components. `customer/`, `admin/`, `rider/` enforce role access at entry. `assets/` contains responsive CSS and JavaScript. `cron/` contains the CLI scheduler. `aquasync_db.sql` includes all 16 required tables, foreign keys, indexes and sample orders. `tests/` contains isolated database integration checks.

## Verification

Run `C:\xampp\php\php.exe tests\integration.php`. It creates a uniquely named temporary database, exercises pricing, ownership restrictions, delivery transitions, payment references, subscriptions, address changes, feedback, notifications and repeated scheduler runs, then removes only that temporary database. Lint with `php -l` for each PHP file. The sample data includes two completed dates for refill prediction and a Pending order.

The pre-existing local database was preserved in `aquasync_archive_20261005_050016` before this application's schema was installed. It retains its original tables and settings. `tests/archive_legacy.php` is a one-time migration helper, not part of routine setup.

Passwords use PHP password hashing. All application queries use PDO prepared statements. Forms require CSRF tokens, user sessions rotate on login, cookies use HttpOnly and SameSite, output is escaped, and important order updates lock rows transactionally. Apache access rules protect application internals and SQL files. Bootstrap/font CDNs are external UI dependencies; self-host them if offline operation is required. GCash/Maya are manual workflows, with no direct payment API integration.
