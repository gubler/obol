<?php

namespace App\Tests\Unit\Entity;

use App\Enum\PaymentPeriod;

test(
    description: '',
    closure: function () {
        $category = new \App\Entity\Category(
            name: 'test',
        );
        $subscription = new \App\Entity\Subscription(
            category: $category,
            name: 'test',
            lastPaidDate: new \DateTimeImmutable(),
            paymentPeriod: PaymentPeriod::Year,
            paymentPeriodCount: 1,
            cost: 1.99,
            currency: \App\Enum\Currency::USD,
            description: 'test',
            link: 'test',
            logo: 'test',
        );

        expect($subscription->subscriptionEvents)->count()->toBe(0);
    }
);
