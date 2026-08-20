## Ardhi Way MVP (XAMPP / PHP / MySQL)

This is a minimal MVP for Ardhi Way:
- Buyer browsing + enquiry via WhatsApp
- Seller submits listings
- Admin reviews/approves listings
- Lead tracking (enquiry clicks/submissions)

### Requirements
- XAMPP (Apache + MySQL) on Windows
- PHP 8.x (bundled with XAMPP)

### Setup
1) Copy this project into your XAMPP web root (example):
   - `C:\xampp\htdocs\ardhi-guide-mvp`

2) Create a database (example: `ardhi_guide_mvp`) and import schema:
   - Open phpMyAdmin: `http://localhost/phpmyadmin`
   - Create DB `ardhi_guide_mvp`
   - Import `database/schema.sql` (new installs)
   - **Existing DBs** created before documents / linked enquiries: also run `database/migration_003_enquiries_and_documents.sql` once (ignore duplicate-column errors if re-run).
   - **Or** from project root run: `php scripts/migrate.php` (applies all `database/migration_*.sql` in order; duplicate-column messages usually mean that step was already applied).
   - After adding payment/notes features, `migration_004` extends `listings` (or use the same `php scripts/migrate.php`).
   - Ensure `storage/private/` exists and is writable (for private PDFs/images).
   - Optional: set `ADMIN_NOTIFY_EMAIL` in `app/.env` for `mail()` alerts on new listings (works only if your PHP sendmail is configured).
   - Optional: set `REQUIRE_PAYMENT_FOR_APPROVAL="1"` to block approving listings until payment is **Mark paid** or **Waive fee** in admin detail.

3) Configure environment:
   - Copy `app/.env.example` to `app/.env`
   - Update DB credentials in `app/.env`

4) Visit:
   - `http://localhost/ardhi-guide-mvp/public/`

### Accounts
- Admin is created by default seed in `database/schema.sql`:
  - email: `admin@ardhiguide.local`
  - password: `Admin123!`

### Notes
- “Verification” is represented as explicit statuses (Submitted → Under review → Approved/Rejected).
- WhatsApp links are generated; real WhatsApp Business API integration can be added later.

