# Deploying to Laravel Cloud

How the hosted demo runs. Laravel Cloud deploys from this GitHub repo on every push to `main`. No secret goes in the repo: they live only in the Cloud environment's variables.

## One-time setup

1. In Laravel Cloud, create an application from this GitHub repo, with an environment on the `main` branch.
2. In the environment's settings:
   - **PHP version:** 8.4.
   - **Database:** attach a Laravel Serverless Postgres database. Cloud adds the connection variables itself.
   - **Build commands:** `composer install --no-dev && npm ci && npm run build && php artisan optimize`
   - **Deploy commands:** `php artisan migrate --force`
3. Environment variables, besides the ones Cloud adds:

   | Variable | Value |
   |---|---|
   | `APP_ENV` | `production` |
   | `APP_DEBUG` | `false` (no stack traces in error pages) |
   | `APP_URL` | the environment's `https://….laravel.cloud` address |
   | `APP_KEY` | set by Cloud; if it is empty, generate one with `php artisan key:generate --show` |
   | `SESSION_SECURE_COOKIE` | `true` |
   | `DEMO_PASSWORD` | a three-word passphrase, shared only in the private submission |
   | `ANTHROPIC_API_KEY` | a key from a workspace with a spend limit |

4. Deploy. Then, in the environment's **Commands** tab, load the demo data once:

   ```bash
   php artisan db:seed --force
   ```

   The seeder hashes `DEMO_PASSWORD` into every account, so set that variable first.

## Resetting the demo data

In the **Commands** tab: `php artisan migrate:fresh --seed --force`. This also signs everyone out.

## Checks after a deploy

- `/up` answers 200 (Laravel's health check).
- The sign-in page loads, and an advisor can sign in and open a student.
- Response headers include `Strict-Transport-Security`, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, and `Referrer-Policy: same-origin`.
- Opening a student writes an AI prep brief. If it says the brief is not available, check `ANTHROPIC_API_KEY`.
