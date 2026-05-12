# Production Deployment Checklist

## Before going live:
- [ ] Run: php artisan env:validate — all checks must pass
- [ ] Run: php artisan stripe:sync-prices — set real Stripe Price IDs
- [ ] Run: php artisan trello:test — verify Trello credentials
- [ ] Run: php artisan claude:test — verify Claude API key
- [ ] Change admin password: log into /admin, go to Settings, update password
- [ ] Set APP_ENV=production, APP_DEBUG=false in .env
- [ ] Run: php artisan config:cache && php artisan route:cache && php artisan view:cache
- [ ] Configure Supervisor for queue workers (see docs/supervisor.conf)
- [ ] Add Stripe webhook endpoint in Stripe Dashboard → /webhook/stripe
- [ ] Set STRIPE_WEBHOOK_SECRET from Stripe Dashboard
- [ ] Verify Trello template board has the correct columns: "To Do", "In Progress", "Done"
- [ ] Test full flow in Stripe test mode before going live
- [ ] Set up SSL on server (required for Stripe and Trello webhooks)
- [ ] Configure MAIL_* settings with business email credentials

## Stripe test flow:
1. Visit /
2. Click "Get started" on any plan
3. Use Stripe test card: 4242 4242 4242 4242, any future date, any CVC
4. Check: webhook received → customer created → email sent → Trello board created
5. Check: /admin/customers shows the new customer
