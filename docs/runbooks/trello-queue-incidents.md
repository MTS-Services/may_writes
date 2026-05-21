# Runbook: Trello onboarding and queue incidents

## Symptoms

- Customers report payment succeeded but no welcome email or Trello invite.
- `failed_jobs` contains `OnboardCustomerJob` entries.
- Logs show `Customer onboarding failed` or `OnboardCustomerJob permanently failed`.

## Checks

1. **Stripe webhooks** — Dashboard → Developers → Events: confirm `checkout.session.completed` (and `invoice.paid` if provisioning is deferred) reached your endpoint with HTTP 2xx.
2. **Queue worker** — Ensure a worker is running the `default` queue in the same environment as the app (`php artisan queue:work` or Horizon).
3. **Trello configuration** — `TRELLO_API_KEY`, `TRELLO_API_TOKEN`, `TRELLO_WORKSPACE_ID` (organization id, not board id). `TRELLO_TEMPLATE_BOARD_ID` is optional (leave empty in production).
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

## Plan changes and Trello webhooks

When a customer already has a Trello board (`trello_onboarded_at` set), **Stripe plan upgrades or downgrades do not re-run onboarding**. The app updates the **board title** via the Trello API so it stays aligned with the current plan name. **AI processing** (`ProcessTrelloTaskJob`) only runs for **new cards created in the REQUESTS (QUEUE) COLUMN**; instruction cards (suffix `(Instructions - Do not delete this card)`) and `EXAMPLE` cards are excluded. If someone archives or deletes a **template list** or **instruction card**, webhooks restore or recreate them via the Trello API.

## Template board layout (5 lists + instruction cards)

**Default (no template board id):** onboarding creates an empty workspace board, then provisions five lists, six instruction cards, and list order from `config/trello_template.php` via the Trello API. No dependency on a design-time template board.

**Optional:** set `TRELLO_TEMPLATE_BOARD_ID` to copy lists, labels, and cards from that board at create time (`keepFromSource=cards`); structure ensure still reconciles names and order afterward.

**Background:** set `TRELLO_BOARD_BACKGROUND_ID` for best-effort coffee-cup (or other) background on create and sync; if unset or invalid, Trello’s default background is used and onboarding continues (warning logged).

Expected list order (left to right): REQUESTS (QUEUE) → IN PROGRESS → DRAFT REVIEW → REVISIONS → DELIVERED.

Backfill or repair structure for onboarded customers:

```bash
php artisan trello:sync-template-structure
php artisan trello:sync-template-structure customer@example.com
```

Trello webhooks return HTTP 200 even when handler logic fails (check `webhook_logs` and Laravel logs). Repeated Trello 500s usually mean an uncaught exception before the try/catch wrapper was deployed.
