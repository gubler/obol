# Main Idea

## Product Name
SubSchedule

## Core Concept
A personal subscription management and payment tracking system that helps users proactively budget for recurring expenses through savings tracking, payment history, and comprehensive spending insights.

## Problem Statement
Managing multiple subscriptions is challenging:
- Hard to track when payments are due
- Difficult to see total monthly/yearly costs
- No way to track savings progress toward upcoming renewals
- Can't see how much should be saved by now for each subscription
- Need to have full amount saved BEFORE the renewal month for monthly budgeting
- Payment history gets lost over time
- Cost changes aren't tracked
- No aggregated view of savings needed per category

## Solution
SubSchedule provides a single place to:
- Track all subscriptions with payment schedules
- **Calculate savings targets**: See how much should be saved so far for each subscription based on time elapsed in the payment period
- **Proactive budgeting**: Ensure full subscription amount is saved before the renewal month (e.g., Aug 15 renewal = fully saved by July 31, August savings go toward next payment)
- **Category-level savings**: View total savings progress for entire categories
- Record payment history automatically
- Organize subscriptions by category
- View comprehensive dashboards and reports
- Maintain audit trail of all changes (cost changes, updates, archive/unarchive)

## Key Differentiators
- **Savings-first approach**: Track progress toward having money saved before renewals hit
- **Monthly budget alignment**: Full amount saved before renewal month, not on renewal date
- **Personal use focus**: No accounts, authentication, or multi-user complexity
- **Audit trail**: Complete history of all subscription changes
- **Strict type safety**: PHP 8.4+ with 100% type coverage for reliability
- **Modern PHP architecture**: Immutable properties, ULIDs, proper domain modeling

## Success Criteria
- Easy to add/edit subscriptions and categories
- Clear visibility into savings progress for each subscription
- Category-level savings aggregation
- Proactive budgeting that aligns with monthly budget cycles
- Historical payment tracking
- Insightful reporting on spending patterns
