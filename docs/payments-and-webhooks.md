# Payments and webhooks

## Gateway modules

| ID | Name | Behavior |
|----|------|----------|
| `sch_bank_invoice` | Bank invoice | Creates a mock invoice, status `sch-invoice`, shows bank details (`sch_bank_invoice_details`). Confirmation via webhook. |
| `sch_installment` | Installment / Hold | Mock authorize, status `sch-hold`, saved card selection. Capture/release via webhook. |

Optional: when **WooCommerce Stripe** is active, Stripe tokens appear under **Payment methods** (read-only bridge).

## Log table `{prefix}sch_payment_logs`

| Column | Description |
|--------|-------------|
| `id` | Log row ID |
| `order_id` | Order |
| `gateway` | Gateway ID |
| `event_type` | Event type |
| `direction` | `outbound` (API request) / `inbound` (webhook) |
| `status` | Status string |
| `http_code` | HTTP status code |
| `request_payload` | Request JSON (masked) |
| `response_payload` | Response JSON (masked) |
| `masked` | 1 if masking enabled |
| `test_mode` | 1 if test mode |
| `ip_address` | Client / webhook IP |
| `created_at` | Timestamp |

View logs: **WooCommerce → Client Hub → Payment logs**.

## Masking

Class `SCH_Payment_Masker` hides PAN/cards, CVV, IBAN, token, api_key, secret, password, etc. Controlled by option `sch_mask_sensitive`.

## Webhook endpoint

| Method | URL | Auth |
|--------|-----|------|
| `POST` | `/wp-json/sch/v1/webhook/{gateway}` | Header `X-SCH-Secret: {sch_bank_webhook_secret}` (optional in test mode) |

### Example body

```json
{
  "order_id": 123,
  "event": "invoice_paid",
  "amount": 99.00
}
```

### Supported `event` values

| Event | Action |
|-------|--------|
| `payment_succeeded` / `invoice_paid` | `payment_complete` → status Assembling + coupon |
| `payment_failed` | order failed |
| `hold_authorized` | hold status |
| `hold_captured` | complete + processing + coupon |
| `hold_released` | cancelled |

Simulate from admin: **Payment modules** tab.
