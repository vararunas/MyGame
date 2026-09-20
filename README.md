# MyGame

Text-based business simulation game built with PHP.

## Current milestone
- Lithuania start map
- Cities with population >= 5,000
- Select a city to start a business

## Structure
- `public/` web entry point and assets
- `src/` domain/application code
- `config/` configuration
- `database/` schema and seed data
- `storage/` runtime data

## Administrator access

Set `MYGAME_ADMIN_PASSWORD_HASH` in the web server environment before opening `/admin/`.
Generate a hash with `php -r 'echo password_hash("your-secret", PASSWORD_DEFAULT), PHP_EOL;'`.
When the variable is absent, administrator pages return HTTP 503 and cannot change game data.

## Existing inconsistent data

Back up the database and inspect `/admin/system-diagnostics.php` after deployment.
It lists sales periods whose ledger, bank receipts, and monthly cycle disagree, including
sales with no cycle. The game stops processing an affected company/month instead of
selling the same inventory again. Reconcile each affected period from its ledger and
bank transactions before resuming the clock. The old test-history clearing command is
disabled because it deleted transaction history without restoring balances and stock.
