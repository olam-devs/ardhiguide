# Deploying Ardhi Way to Evolution

This project is a PHP 8 + MySQL app. Web root is `public/`. Everything in
`app/`, `database/`, `scripts/`, and `storage/` lives one level above the
web root and must never be served directly.

Two things must already exist in cPanel before you start:

- **MySQL database** with a user attached and all privileges granted.
  In this project the names are:
  - Database: `olamtecc_ardhi_guide`
  - User: `olamtecc_ardhi_guide`
  - Host: `localhost`
- **Subdomain** `ardhiguide.olamtec.co.tz` with Document Root pointing at
  `/home/olamtecc/ardhi-guide/public` (you can edit the docroot after
  creating the subdomain).

## One-time setup (run on the Evolution server)

SSH in:

```bash
ssh olamtecc@vda6000
```

Then, from your home directory:

```bash
cd ~
git clone https://github.com/olam-devs/ardhiguide.git ardhi-guide
cd ardhi-guide

# Create the production env file from the template.
cp app/.env.production.example app/.env

# Open it and set the real DB password (everything else is pre-filled).
nano app/.env
#   -> set DB_PASS to the password from cPanel
#   -> save (Ctrl+O, Enter) and exit (Ctrl+X)

chmod 600 app/.env

# Make upload/storage folders writable for PHP.
chmod 755 public/uploads public/uploads/videos storage/private

# Import the schema (you will be prompted for the DB password).
mysql -u olamtecc_ardhi_guide -p olamtecc_ardhi_guide < database/install.sql
```

If you would rather paste the SQL into phpMyAdmin instead of running the
`mysql` CLI, open `database/install.sql`, copy its contents, and import
through phpMyAdmin's SQL tab.

## Verify

```bash
php -v                                  # should be 8.1 or newer
ls -la ~/ardhi-guide/public/index.php   # must exist
ls -la ~/ardhi-guide/app/.env           # must be -rw------- (600)
```

Now visit <https://ardhiguide.olamtec.co.tz>. Log in with the seed accounts:

- `admin@ardhiguide.local` / `Admin123!`
- `seller@ardhiguide.local` / `Seller123!`

Change the admin password from the My Account page (or by updating the
`users.password_hash` directly in phpMyAdmin) as soon as the site is up.

## Updating the site later

After making changes locally and pushing to GitHub:

```bash
ssh olamtecc@vda6000
cd ~/ardhi-guide
git pull
```

That is the entire update flow. PHP picks up the new files on the next
request, no restart needed. `app/.env`, `public/uploads/`, and
`storage/private/` are all gitignored so your live data is never touched
by a `git pull`.

## Enabling HTTPS

In cPanel, open **SSL/TLS Status** → find `ardhiguide.olamtec.co.tz` →
**Run AutoSSL**. Wait about a minute for the certificate to issue.

Once the certificate is active, edit `public/.htaccess` on the server and
uncomment the three `RewriteEngine` lines at the top to force every
request to HTTPS:

```bash
nano public/.htaccess
# uncomment the RewriteEngine block, save, exit
```

## Snippe online payments (optional)

After `git pull`, run the Snippe migration once:

```bash
cd ~/ardhi-guide
mysql -u olamtecc_ardhi_guide -p olamtecc_ardhi_guide < database/migration_008_snippe.sql
```

In `app/.env` set:

```ini
SNIPPE_ENABLED="1"
SNIPPE_API_KEY="snp_your_key_from_dashboard"
SNIPPE_WEBHOOK_SECRET="whsec_..."
```

Webhook URL to register in the Snippe dashboard (or it is sent per payment):

`https://ardhiguide.olamtec.co.tz/webhooks/snippe.php`

Admin controls fee amount and USSD push phone per listing under **Admin → listing detail → Payment & Snippe**.

## Common SSH commands

```bash
# Watch the live Apache error log for this domain (path varies per host):
tail -f ~/logs/ardhiguide.olamtec.co.tz.error.log

# Find which PHP CLI runs by default:
which php && php -v

# Re-set permissions if uploads ever stop working:
chmod 755 public/uploads public/uploads/videos storage/private

# Reset to whatever is on GitHub (destructive on local changes):
git fetch origin && git reset --hard origin/main
```
