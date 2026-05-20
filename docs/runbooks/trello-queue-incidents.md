# Runbook: Trello onboarding and queue incidents

## Symptoms

- Customers report payment succeeded but no welcome email or Trello invite.
- `failed_jobs` contains `OnboardCustomerJob` entries.
- Logs show `Customer onboarding failed` or `OnboardCustomerJob permanently failed`.

## Checks

1. **Stripe webhooks** — Dashboard → Developers → Events: confirm `checkout.session.completed` (and `invoice.paid` if provisioning is deferred) reached your endpoint with HTTP 2xx.
2. **Queue worker** — Ensure a worker is running the `default` queue in the same environment as the app (`php artisan queue:work` or Horizon).
3. **Trello configuration** — `TRELLO_API_KEY`, `TRELLO_API_TOKEN`, `TRELLO_TEMPLATE_BOARD_ID`, `TRELLO_WORKSPACE_ID` (organization id, not board id).
4. **Customer row** — `customers.trello_onboarding_status`: `pending` (in progress), `completed` (success), `failed` (all retries exhausted). Read `trello_onboarding_last_error` for the last error text.

## Recovery

1. Fix the underlying issue (Trello API, credentials, workspace id).
2. Retry onboarding for the affected customer (must not already have `trello_onboarded_at`):

   ```bash
   php artisan customers:retry-trello-onboarding customer@example.com
   ```

   Or by database id:

   ```bash
   php artisan customers:retry-trello-onboarding 42
   ```

3. From Horizon / `failed_jobs`, **retry** the job after the fix if the failure was transient.

## Alerting

Set `BILLING_ONBOARDING_FAILURE_EMAIL` in production so a mail is sent when `OnboardCustomerJob` exhausts retries (`config/billing.php` → `alerts.onboarding_failure_email`).

## Pausing load during a Trello outage

- Stop or scale down queue workers to avoid hammering Trello, **or** temporarily disable provisioning (only if you understand the product impact — see `TRELLO_PROVISION_ON_CHECKOUT` and deferred invoice flow in `config/billing.php`).

## Optional auth (deferred)

Post-checkout magic links or full Fortify accounts are not required for this runbook; add only if you need self-serve beyond Stripe Customer Portal.
