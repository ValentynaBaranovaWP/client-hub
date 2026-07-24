# Single Client Hub

A modular WooCommerce client hub that combines cart, customer account, payments, subscriptions, coupons and notifications into a single interface.

> **Setup:** place the `[sch_hub_trigger]` shortcode where the hub icon should appear (usually the theme header, instead of the mini-cart).

**Repository:** https://github.com/ValentynaBaranovaWP/client-hub.git

## Requirements

- WordPress 6.0+
- WooCommerce 7.0+
- PHP 7.4+
- (optional) WooCommerce Stripe Gateway — for the saved-token bridge

Tested with:

- WordPress 6.8.x
- WooCommerce 10.x

## Installation

1. Copy the `single-client-hub` folder into `wp-content/plugins/`.
2. Activate the plugin in WordPress admin.
3. Place `[sch_hub_trigger]` in the header (or wherever the hub should open).
4. Go to **WooCommerce → Client Hub** for settings, logs, and webhook simulation.
5. Enable payment gateways under **WooCommerce → Settings → Payments**: `SCH: Bank invoice` and `SCH: Installment / Hold`.

## Activation

Upon activation the plugin automatically:

- creates database tables;
- registers custom order statuses;
- schedules recurring Cron events;
- flushes rewrite rules;
- creates default options.

## Database

The plugin creates the following custom tables:

- `wp_sch_payment_logs` — payment request / response / webhook traffic
- `wp_sch_events` — business events (statuses, coupons, cron, …)
- `wp_sch_subscriptions` — customer subscriptions
- `wp_sch_saved_methods` — saved payment methods (tokens)

*(Table names use your WordPress `$wpdb->prefix`; `wp_` is the default.)*

## Key Features

### Customer Hub

- Hub trigger via `[sch_hub_trigger]` (header / mini-cart slot)
- Mini cart
- Customer dashboard

### Orders

- Custom statuses
- Reorder
- Subscription conversion

### Payments

- Bank invoice
- Installment / Hold
- Saved payment methods
- Payment logs
- Webhooks

### Subscriptions

- WP Cron renewals
- Pause / Cancel

### Coupons

- Auto-generated coupons
- Coupon validation

### Notifications

- Status emails
- Renewal failure emails
- Webhook notifications

## Module structure

```
single-client-hub/
├── single-client-hub.php          # bootstrap + activation
├── includes/
│   ├── class-sch-plugin.php       # core wiring
│   ├── Database/                  # migrations
│   ├── Statuses/                  # custom WC statuses
│   ├── Payments/                  # logs, masking, webhooks, saved methods
│   │   └── Gateways/              # bank invoice + installment/hold
│   ├── Subscriptions/             # subscriptions + Cron renewals
│   ├── Coupons/                   # auto-issue + friendly errors
│   ├── Notifications/             # emails
│   ├── Frontend/                  # hub UI + REST + reorder
│   └── Admin/                     # admin screen
├── templates/                     # hub, account, emails
├── assets/
└── docs/
```

## Documentation

| File | Description |
|------|-------------|
| [docs/statuses-and-events.md](docs/statuses-and-events.md) | Business statuses and events |
| [docs/payments-and-webhooks.md](docs/payments-and-webhooks.md) | Payments, logs, and webhooks |
| [docs/test-scenarios.md](docs/test-scenarios.md) | Manual test scenarios |

## REST (overview)

- `GET /wp-json/sch/v1/hub/cart`
- `GET /wp-json/sch/v1/hub/account` (auth)
- `POST /wp-json/sch/v1/hub/coupon`
- `POST /wp-json/sch/v1/webhook/{gateway}`
- `POST /wp-json/sch/v1/webhook/{gateway}/simulate` (admin, test mode)

## License

Proprietary / project use — Lumavita / Client Hub.
