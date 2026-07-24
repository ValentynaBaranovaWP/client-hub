# Order statuses and business events

## Custom WooCommerce statuses

| Slug (WC) | Label | Paid? | When applied |
|-----------|-------|-------|--------------|
| `wc-sch-assembling` | Assembling | yes | After successful payment / webhook `payment_succeeded` / `invoice_paid` |
| `wc-sch-await-docs` | Awaiting documents | no | Manual admin transition (business process) |
| `wc-sch-sub-active` | Active subscription | yes | After subscription created at checkout / successful renewal |
| `wc-sch-invoice` | Awaiting payment (invoice) | no | Gateway `sch_bank_invoice` after checkout |
| `wc-sch-hold` | Authorization (hold) | no | Gateway `sch_installment` after authorize |
| `wc-sch-renewal-fail` | Renewal failed | no | Cron renewal could not charge |

Standard WC statuses (`pending`, `processing`, `completed`, `failed`, `cancelled`, …) are also used.

## Events table `{prefix}sch_events`

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint | Primary key |
| `user_id` | bigint | User |
| `order_id` | bigint | Order |
| `subscription_id` | bigint | Subscription |
| `event_code` | varchar(64) | Event code |
| `event_label` | varchar(191) | Human-readable label |
| `payload` | longtext | JSON (masked when needed) |
| `created_at` | datetime | Timestamp |

### Event codes

| `event_code` | Description |
|--------------|-------------|
| `order_status_changed` | WC status change |
| `invoice_created` | Bank invoice created |
| `hold_authorized` | Hold authorized |
| `webhook_*` | Inbound / simulated webhook event |
| `coupon_issued` | Auto-issued coupon |
| `order_reordered` | Service reordered |
| `order_to_subscription` | Switched to subscription flow |
| `subscription_created` | New subscription |
| `subscription_paused` / `resumed` / `cancelled` | Subscription management |
| `subscription_renewed` | Successful auto-renewal |
| `subscription_renewal_failed` | Failed auto-charge |

Events are also available under **WooCommerce → Client Hub → Events**.
