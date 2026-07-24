# Test scenarios

Prerequisites: WooCommerce active, Single Client Hub activated, test mode **ON**, both SCH gateways enabled.

## 1. Floating Hub — mini cart

1. Add a product to the cart.
2. On the storefront, click **Hub**.
3. **Cart** tab: line items, total, count badge.
4. Enter an invalid coupon → clear error message.
5. Complete an order with auto-coupon (see §8), return — reward chip visible and applicable.

## 2. Personal account in the hub

1. Log in as a customer with past orders.
2. Hub → **Account**: order list, status, **Reorder** / **Subscription** buttons.
3. Logged-out users see a login prompt.

## 3. Custom statuses

1. WooCommerce → Orders → set status to **Awaiting documents** / **Assembling**.
2. Check customer email (if enabled) and row in Client Hub → Events.

## 4. Bank invoice + webhook

1. Checkout → bank transfer / invoice.
2. Order status **Awaiting payment (invoice)**; thank-you page shows bank details; log `invoice_create`.
3. Client Hub → Payment modules → Order ID + event `invoice_paid` → **Simulate**.
4. Status **Assembling**, webhook log, possible auto-coupon + email.

## 5. Hold / installment

1. Checkout → Hold; enter last4 or a saved card.
2. Status **Authorization (hold)**; log `hold_authorize`.
3. Simulate `hold_captured` → payment complete; or `hold_released` → cancelled.

## 6. Saved payment methods

1. My Account → **Payment methods** → add a demo card.
2. Set default / delete.
3. On Hold checkout the card appears in the select.

## 7. Subscriptions + Cron

1. From orders click **Subscribe** or use the checkout checkbox.
2. After a paid order with the flag — row in `sch_subscriptions`, status **Active subscription**.
3. My Account → **Subscriptions**: next charge, Pause / Cancel.
4. Set `next_payment` in the past (SQL or phpMyAdmin) and run `wp cron event run sch_process_subscription_renewals` or wait ~15 minutes.
5. Success → new order + `subscription_renewed`; for failure: user meta `sch_force_renewal_fail=yes` → renewal-fail status + email.

## 8. Auto-coupon

1. Enable auto-issue in Client Hub.
2. Complete payment (or webhook paid).
3. Coupon in order meta `_sch_reward_coupon`, customer email, code in hub.

## 9. Reorder / subscribe

1. **Reorder service** → cart filled with same line items.
2. **Subscribe** → checkout with subscription flag enabled.

## 10. Email notifications

Verify enabled notification checkboxes and inbox / Mailhog / WP Mail Logging for:

- status change
- coupon issued
- failed renewal
- webhook event (`invoice_paid`, etc.)

## 11. Log masking

1. Place a hold with last4.
2. Request logs must not contain full secret token in plain text (token/card fields masked).
3. Disable masking → new logs store more detail (debug only).

## Migration on activation

Re-activation or bumping `SCH_DB_VERSION` runs `SCH_Schema::install()` (`dbDelta`) and updates `sch_db_version`. Tables:

- `wp_sch_payment_logs`
- `wp_sch_events`
- `wp_sch_subscriptions`
- `wp_sch_saved_methods`
