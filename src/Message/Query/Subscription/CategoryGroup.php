<?php

// ABOUTME: Read model pairing a category with its subscriptions for the homepage listing.
// ABOUTME: Exposes the category's combined monthly cost and combined savings target as of a fixed date.

declare(strict_types=1);

namespace App\Message\Query\Subscription;

use App\Entity\Category;
use App\Entity\Subscription;
use App\ValueObject\Money;
use Assert\Assertion;

final readonly class CategoryGroup
{
    /**
     * @param list<Subscription> $subscriptions
     */
    public function __construct(
        public Category $category,
        public array $subscriptions,
        private \DateTimeImmutable $asOf,
    ) {
    }

    /**
     * Combined monthly-equivalent cost of the group.
     */
    public function monthlyTotal(): Money
    {
        return $this->sum(static fn (Subscription $subscription): Money => $subscription->monthlyCost());
    }

    /**
     * Combined savings target of the group as of `$asOf`.
     */
    public function savingsTotal(): Money
    {
        return $this->sum(fn (Subscription $subscription): Money => $subscription->savingsTarget($this->asOf));
    }

    /**
     * Sum a per-subscription Money figure across the group. A group is always built from at least one
     * subscription, so there is always a currency to denominate the total in. Today every group is
     * single-currency; once non-USD costs are allowed (A3, #129) a mixed-currency group will make
     * `Money::add` throw - the converted cross-currency total is the reports work (#28, B1).
     *
     * @param callable(Subscription): Money $amount
     */
    private function sum(callable $amount): Money
    {
        $total = null;
        foreach ($this->subscriptions as $subscription) {
            $value = $amount($subscription);
            $total = null === $total ? $value : $total->add($value);
        }

        Assertion::notNull($total, 'A category group must contain at least one subscription');

        return $total;
    }
}
