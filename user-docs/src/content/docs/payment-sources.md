---
title: Payment Sources
description: Track which card or account pays for each subscription.
---

A **payment source** is a method of payment - a credit card, a bank account, a PayPal balance - that you can attach to your subscriptions. Tracking them answers two everyday questions: how much you're paying through each method, and which subscriptions you'd need to update if a card changes.

## Creating a payment source

Go to **Payment Sources** and select **New Payment Source**, then give it:

- **Payment Source Name** - how you'll recognize it, e.g. "Amex 1234" or "Joint checking".
- **Comment** - optional free-text notes, e.g. an expiry date or which bank it's with.
- **Color** - a swatch used for its tile and its slice in [Reports](/obol-user/reports/).

## Attaching a source to a subscription

When you add or edit a subscription, pick a **Payment Source** alongside the category. It's optional - a subscription with none is treated as **Unassigned**. The choice is recorded in the subscription's **History**, so you can see when it moved.

The picker only appears once you've created at least one payment source.

## When a card gets reissued

This is what payment sources are built for. Say your Amex is replaced with a new number:

- **Same provider, new number?** Just open the source and **Edit** its name (and comment) in place. Every subscription stays attached - nothing else to do.
- **Switching to a genuinely different card or account?** Open the old source and use **Move all** to reassign every subscription on it to another source in one step. Each subscription records the move in its history.

Either way, the source's page lists exactly which subscriptions ride on it, so you always know what's affected.

## Editing and deleting

Open a payment source to see the subscriptions on it, then **Edit** to rename it or change its comment or color.

You can only **Delete** a source that has no subscriptions. If it still has some, Obol asks you to reassign or detach them first (see **Move all** above) - this prevents silently losing track of how something is paid for.

## Unassigned subscriptions

A payment source is optional. Subscriptions without one are grouped as **Unassigned** and get their own slice and drill-down in the [by-source report](/obol-user/reports/#monthly-obligation-by-payment-source).
