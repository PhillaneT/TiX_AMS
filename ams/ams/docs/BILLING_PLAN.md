# AMS Billing & Payments — Build Plan

**Status:** Phase A ✅ shipped (2026-05-02). Phase B (PayFast sandbox) is next.
**Date:** 2026-05-02
**Scope:** Add subscriptions, credits and top-ups to the AMS web app so each
assessor can pay to use AI marking.

---

## 0. ⏭ Next session — start here

**What's done:** All of Phase A (data model, signup, trial credits, sidebar
pill, AI-mark enforcement, admin grant button). Verified end-to-end. See
`replit.md` → "Session 6 additions" for the full inventory.

**Pick up at: Phase B — PayFast subscriptions (§10, items 9–15).**

Concrete first steps for tomorrow:

1. **Get PayFast sandbox credentials.** Sign in at
   <https://sandbox.payfast.co.za>, grab `merchant_id`, `merchant_key`,
   `passphrase`. Add via the Replit secrets workflow as
   `PAYFAST_MERCHANT_ID`, `PAYFAST_MERCHANT_KEY`, `PAYFAST_PASSPHRASE`,
   `PAYFAST_SANDBOX_MODE=true`.
2. **Create migrations** for `subscriptions` and `payments` (schemas in §3).
3. **Build `App\Services\Billing\PayFastService`** — three methods:
   `buildSubscribeUrl(BillingAccount, Plan)`, `buildTopupUrl(BillingAccount,
   Plan)`, `verifyItn(array $payload, string $sourceIp): bool` (signature
   check + IP allowlist + server postback verification).
4. **Wire `/billing/plans`** (the "pick a plan" page) and turn the disabled
   buttons in `billing/topup.blade.php` into real PayFast redirects.
5. **Implement `POST /payfast/itn`** (no auth, signature-verified) →
   marks the payment paid → calls `BillingService::grant()` with
   `REASON_SUBSCRIPTION` or `REASON_TOPUP`.
6. **Sandbox-test** the full subscribe flow, then the top-up flow, then a
   simulated renewal ITN.

**Test users to lean on:** `assessor@ajananova.co.za` / `ajananova2025`
(admin, account #1, balance refreshed via `/admin/accounts` Grant form
whenever needed).

**Don't touch:** Phase A code is stable. The `BillingService` API is
already shaped for Phase B — the ITN handler should call
`grant($account, $plan->monthly_credits, BillingService::REASON_SUBSCRIPTION,
$payment)` and that's it.

---

## 1. Decisions locked in (from planning chat)

| # | Decision | Choice |
|---|---|---|
| 1 | Paying entity | **Hybrid.** Solo assessor by default; can later upgrade to a team account that owns multiple assessor logins. |
| 2 | Payment gateway | **PayFast** (recurring + EFT, ZAR native). |
| 3 | Pricing | Per handover doc: **Portal R199/m (10 marks)**, **Top-up R99 (25 marks)**. All prices ex VAT. (Plugin Licence tier deferred — see §11.) |
| 4 | Free trial | **3 free AI marks** granted on signup. No card required to claim. |
| 5 | Credit expiry | **None.** Credits roll over forever. Single balance per account. |
| 6 | Out of credits | **Block AI marking only.** Show a "Top up or upgrade" screen. Manual marking continues to work. |

---

## 2. Mental model

```
┌──────────────────┐  1   *  ┌──────────────────┐
│ billing_account  │─────────│ user (assessor)  │
│ (the payer)      │         └──────────────────┘
│                  │
│  - plan          │  1   *  ┌──────────────────┐
│  - balance       │─────────│ credit_ledger    │  every + or − to balance
│                  │         └──────────────────┘
│                  │
│                  │  1   *  ┌──────────────────┐
│                  │─────────│ subscription     │  PayFast token, status, next_bill
│                  │         └──────────────────┘
│                  │
│                  │  1   *  ┌──────────────────┐
│                  │─────────│ payment          │  one row per PayFast charge
│                  │         └──────────────────┘
│                  │
│                  │  1   *  ┌──────────────────┐
│                  │─────────│ invoice          │  PDF + line items
└──────────────────┘         └──────────────────┘
```

- **`billing_account`** is the payer. Solo signup creates an account with one
  user. Team upgrade later attaches more users to the same account — no data
  migration needed because we put `billing_account_id` on `users` from day one.
- **Single credit balance** per account. Sub-grants, top-ups, trials and admin
  adjustments all flow into the same pool. The ledger remembers *where* each
  credit came from for reporting and audit.
- **`ai_usage`** (already exists) gets one new column: `billing_account_id`.
  Every successful AI mark deducts one credit and writes a `−1` ledger row.

---

## 3. Database changes (Laravel migrations)

New tables (PostgreSQL):

| Table | Purpose | Key columns |
|---|---|---|
| `billing_accounts` | The payer | id, name, type (`solo` / `team`), owner_user_id, plan_code, status, balance, trial_credits_granted_at, vat_number, billing_email, billing_address |
| `plans` | Subscription tiers (seed data, editable) | code, name, price_cents, monthly_credits, is_active |
| `subscriptions` | Recurring agreement with PayFast | account_id, plan_code, status (`pending` / `active` / `cancelled` / `failed`), payfast_token, started_at, next_bill_at, cancelled_at |
| `payments` | One row per PayFast charge (subscription or top-up) | account_id, type (`subscription` / `topup`), amount_cents, vat_cents, status (`pending` / `paid` / `failed` / `refunded`), payfast_payment_id, payfast_token, paid_at, raw_itn (json) |
| `credit_ledger` | Append-only ledger of every balance change | account_id, delta (signed int), reason (`trial` / `subscription_grant` / `topup` / `ai_mark` / `admin_adjust` / `refund`), reference_type, reference_id, balance_after, created_at, created_by |
| `invoices` | Generated PDF invoices | account_id, number (`AJ-2026-000123`), period_start, period_end, subtotal_cents, vat_cents, total_cents, status, pdf_path, sent_at, paid_at |
| `invoice_line_items` | Line items per invoice | invoice_id, description, quantity, unit_price_cents, total_cents, line_type |

Modifications:

- `users` → add `billing_account_id` (nullable initially for migration safety,
  then back-filled and made `NOT NULL`).
- `ai_usage` → add `billing_account_id`, `credits_charged` (int, default 1).

---

## 4. Plans (seed data)

```
portal_only        R199 / month   10 marks/month
topup_25           R 99 once      25 marks  (not a plan, sold from store)
```

(`plugin_licence` will be added later — see §11.)

VAT (15%) added at invoice generation. All prices stored in cents to avoid
rounding bugs.

---

## 5. PayFast integration

Two flows, both via PayFast:

1. **Subscription** — recurring billing token. PayFast bills monthly on the
   anniversary of the first charge. We receive an ITN (Instant Transaction
   Notification) for each successful charge, verify the signature, mark the
   payment paid, grant that month's credits to the ledger.

2. **Top-up** — one-off ad-hoc payment. PayFast redirects the user, ITN comes
   back, we add credits to the ledger.

Endpoints we need:

| Method | Route | Purpose |
|---|---|---|
| GET | `/billing` | Account home: balance, current plan, recent activity |
| GET | `/billing/plans` | Pick / change plan |
| POST | `/billing/subscribe` | Build PayFast subscribe URL, redirect |
| POST | `/billing/topup` | Build PayFast one-off URL, redirect |
| POST | `/billing/cancel` | Cancel subscription at end of period |
| GET | `/billing/invoices` | Invoice history + PDF download |
| GET | `/billing/return` | PayFast success redirect (UI only) |
| GET | `/billing/cancel-return` | PayFast cancel redirect (UI only) |
| POST | `/payfast/itn` | Server-to-server notify endpoint (no auth, signature verified) |

PayFast credentials live in `.env` (`PAYFAST_MERCHANT_ID`,
`PAYFAST_MERCHANT_KEY`, `PAYFAST_PASSPHRASE`, `PAYFAST_SANDBOX_MODE=true|false`).
We will request these via the secrets workflow when we get to that step.

---

## 6. Enforcement at AI mark time

In `marking_engine` (or its AMS equivalent):

```
1. Resolve billing_account from authenticated user
2. If mock_mode → run mock, no deduction, no ledger row
3. Else if account.balance < 1
     → return status 'no_credits'
     → UI shows "Top up or upgrade" screen, link to /billing
4. Else
     → call Anthropic
     → on success: deduct 1 (atomic), write ledger row, write ai_usage row
     → on failure: no deduction
```

Manual marking is never gated.

---

## 7. Self-service UI (for assessors)

| Page | Contents |
|---|---|
| `/billing` | Balance, current plan, "Top up", "Change plan", "Cancel", recent ledger entries (last 20) |
| `/billing/plans` | Two plan cards + monthly price + included marks + "Subscribe" button |
| `/billing/topup` | Three top-up bundle cards (1×, 3×, 5× — pick whichever) |
| `/billing/invoices` | Table: invoice number, date, total, status, PDF download |
| Out-of-credits modal | Shown anywhere AI marking is attempted with empty balance. Two CTAs: "Top up R99" and "Upgrade plan". |
| Sidebar badge | Always-visible "Credits: 7" pill in the top nav, turns red when ≤ 3 |

For the **team upgrade** path (deferred but data-modelled now):

- Owner can convert their account from `solo` to `team` (no payment change).
- Owner can invite extra assessors — their logins join the same
  `billing_account_id`. All marks they trigger draw from the same balance.

---

## 8. Admin UI (for you, the founder)

Single admin section gated by an `is_admin` flag on `users`:

- All accounts list (search, filter by plan/status)
- Account detail: balance, ledger, payments, invoices, "Grant credits" button
- Revenue dashboard: MRR, paying accounts, churn, top-ups this month
- Re-send any invoice, mark any invoice paid manually, refund a payment

---

## 9. Invoices & VAT

- Invoice number format: `AJ-YYYY-NNNNNN` (sequential, padded).
- One invoice per **paid** payment (subscription charge or top-up).
- PDF generated with TCPDF (already installed) using a clean template.
- Stored on disk under `storage/app/invoices/{account_id}/{invoice_number}.pdf`.
- Emailed to `billing_email` on the account when generated.
- VAT hard-coded 15% (SA standard rate). VAT number on invoice if account has one.

---

## 10. Build order

Phased so the user can see something working at every step.

### Phase A — Data + signup (no payments yet) ✅ COMPLETE (2026-05-02)
1. ✅ Migrations: `billing_accounts`, `plans`, `credit_ledger`, plus FKs on
   `users` and `ai_usage`. (Files: `database/migrations/2026_05_02_2*.php`.)
2. ✅ Models (`Plan`, `BillingAccount`, `CreditLedger`) + relationships +
   `PlansSeeder` (`portal_only` R199/m, `topup_25` R99 once-off).
3. ✅ `AssessorSeeder` flags the seeded assessor `is_admin = true` and
   back-fills a solo billing account with 3 trial credits. Safety net in
   `DatabaseSeeder` does the same for any other pre-existing user.
4. ✅ `Auth\RegisterController` + `/register` view. New users get a solo
   account, 3 trial credits, then land on `/billing`. POST throttled to
   5/min/IP so trial credits can't be farmed.
5. ✅ `BillingController@index` + `resources/views/billing/index.blade.php`
   — balance card, plan card, last 20 ledger entries.
6. ✅ Sidebar shows credit pill (red when ≤ 3) and a Billing link on every
   authenticated page; admin link appears for `is_admin` users.
7. ✅ `SubmissionController@mark` calls
   `BillingService::deduct($account, 1, REASON_AI_MARK, $submission)`
   *before* mock marking. On `InsufficientCreditsException` it redirects to
   `/billing/topup` with a "manual marking still works" flash.
8. ✅ `AdminController` + `EnsureAdmin` middleware + `/admin/accounts`
   page with per-row "Grant credits" form (audited as
   `billing.admin_grant`).

✅ At this point AMS works end-to-end with manual credit grants — perfect for
internal demo before touching real money.

### Phase B — PayFast subscriptions ⏭ START HERE NEXT SESSION
9. `payments`, `subscriptions` migrations.
10. PayFastService (sign request, verify ITN, build URLs).
11. `/billing/plans` + subscribe flow (sandbox first).
12. ITN handler → marks payment paid → grants month's credits.
13. Renewal ITN handling (subsequent monthly charges).
14. Cancel subscription flow.
15. End-to-end test in PayFast sandbox.

### Phase C — Top-ups + invoicing
16. `/billing/topup` flow + ITN handling for one-off payments.
17. `invoices` + `invoice_line_items` migrations.
18. Invoice generator service (TCPDF).
19. Auto-generate + email invoice on every successful payment.
20. `/billing/invoices` history page.

### Phase D — Admin + team support
21. Admin section (accounts, ledger, revenue, manual actions).
22. Convert solo → team flow.
23. Invite-and-add additional assessors under one account.

### Phase E — Cutover to production
24. Switch PayFast to live credentials.
25. Re-enable signup publicly.
26. Add `replit.md` section so future sessions know billing is live.

---

## 11. Plugin Licence tier — deferred

**Decision:** v1 ships with **Portal Only** as the single subscription tier.

The Plugin Licence tier (R299/m, 25 marks, for SDPs running their own Moodle
with our plugin) is deferred to a later round. When we revisit it we will:

1. Stand up the central licence-validation + usage-ingest API (handover §5.4)
   so the Moodle plugin can phone home for credit checks and event logging.
2. Add `plugin_licence` to the `plans` seeder.
3. Add a "licence keys" UI under each billing account (generate, revoke, copy).
4. Hook the existing Moodle plugin's `usage_logger.php` and
   `credit_manager.php` to the new central endpoints.

Until then, the data model already supports it (the `plans` table, the
single-balance design, the ledger) so adding it is additive — no rework needed.
