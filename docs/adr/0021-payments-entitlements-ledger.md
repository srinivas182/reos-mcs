# 0021: Payments, entitlements and the ledger

**Status:** accepted (B5.1, 22 November 2026); B5.2 (more gateways, routing, payouts) and B5.3 (credits, points, the ledger) extend it.

## Context

REOS sells plans, courses, packages, credit packs and seat packs to candidates, agents, brokerages, schools, providers and associations, with a price per state (decision 3), sponsors paying part, and Texas sales tax. Buying happens on the web only (screen 3); the phone apps never sell (decision 4). Course-commerce tools tie access to one gateway's subscriptions; Stripe's own Entitlements cover subscriptions but not one-time purchases. Webhooks arrive late or twice.

## Decision

1. **Catalogue (screen 67).** `products` (kind, buyer, what's included, the features they unlock as entitlement keys such as `learn.qualifying`, AI help) and `prices` per state and interval (once, month, year) in USD with an optional floor. Prices are never edited: a change ends the current price and starts a new one, so the history stays and subscribers keep their price until renewal.
2. **Sponsor discounts** (`sponsor_discounts`): a brokerage, association or school pays part of a product for its people; checkout applies the largest one automatically, never below the price's floor.
3. **Checkout (screen 3).** An order is created with the quote, then the gateway's own payment fields take the card: Stripe embedded Checkout (PCI SAQ A; REOS never sees card data) with Stripe Tax (`automatic_tax`, address collected, prices tax-exclusive), one customer per person and environment, and idempotency keys per order. The Simulator (outside production) shows a clearly marked test panel. Off pauses purchases.
4. **Unlock at once (addition 5).** On return REOS asks the gateway for the session (`status: complete`, `payment_status: paid`) and fulfils the order in a locked transaction: paid, totals and tax, receipt link, entitlements, a subscription row for plans, one receipt email. The webhook and `reos:billing:reconcile` (every 10 minutes) call the same code, so a missed or repeated event can't skip or double anything.
5. **REOS keeps its own entitlements** (`entitlements`: person, feature, source, state, starts, ends, revoked), fed by orders (no end), subscriptions (period end plus two days, so a slightly late renewal doesn't close access), sponsors and admins. The web and `GET /api/v1/me/entitlements` read them; the app asks on start and on returning to the front, so a purchase unlocks the phone within seconds. Course access is `learn.<course type>` in the person's state; each course's first lesson is free.
6. **Webhooks** (`/webhooks/stripe`): `Stripe-Signature` checked (five-minute tolerance), each event stored once by id (`webhook_receipts`), handled on the `billing` queue: checkout completed, invoice paid (renewal), payment failed (past due, a 7-day grace period, an email), subscription deleted (access ends), charge refunded in full (access revoked).
7. **Plan and billing** (screen 29 and each role's equivalent, in Settings): plans for the person's role and state, what they have now, subscriptions (cards and cancelling in Stripe's customer portal) and receipts.
8. **Records.** Orders stay for tax records when an account is deleted, linked to no one. Price changes, plan changes and orders are audited.

## Consequences

- Workers listen on the `billing` queue; the Stripe webhook endpoint subscribes to `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `invoice.paid`, `invoice.payment_failed`, `customer.subscription.deleted` and `charge.refunded`.
- Stripe Tax needs the Texas registration in the client's Stripe account before live mode.
- B5.2 puts PayPal and Authorize.net behind the same `PaymentGateway` interface with routing per state and product and the kill switch (screen 66); B5.3 adds the ledger, credit pools, sponsor pools and codes, refunds from REOS and reconciliation with the gateway's balance transactions.
