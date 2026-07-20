<?php

// ABOUTME: Development fixtures that populate the database with realistic test data.
// ABOUTME: Uses Foundry factories to create categories, subscriptions, payments, and events.

declare(strict_types=1);

namespace App\DataFixtures;

use App\Enum\Currency;
use App\Factory\CategoryFactory;
use App\Factory\SubscriptionFactory;
use App\ValueObject\Money;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Create categories
        $entertainment = CategoryFactory::createOne(['name' => 'Entertainment']);
        $productivity = CategoryFactory::createOne(['name' => 'Productivity']);
        CategoryFactory::createOne(['name' => 'Utilities']);
        $news = CategoryFactory::createOne(['name' => 'News & Media']);
        $software = CategoryFactory::createOne(['name' => 'Software Development']);
        $fitness = CategoryFactory::createOne(['name' => 'Health & Fitness']);
        $education = CategoryFactory::createOne(['name' => 'Education']);
        $storage = CategoryFactory::createOne(['name' => 'Cloud Storage']);

        // Create active subscriptions with variety
        $netflix = SubscriptionFactory::createOne([
            'category' => $entertainment,
            'name' => 'Netflix',
            'cost' => new Money(1599, Currency::USD),
            'description' => 'Streaming service for movies and TV shows',
        ]);

        $spotify = SubscriptionFactory::createOne([
            'category' => $entertainment,
            'name' => 'Spotify Premium',
            'cost' => new Money(999, Currency::USD),
            'description' => 'Music streaming service',
        ]);

        $github = SubscriptionFactory::createOne([
            'category' => $software,
            'name' => 'GitHub Pro',
            'cost' => new Money(700, Currency::USD),
            'description' => 'Source code hosting and collaboration',
        ]);

        SubscriptionFactory::createOne([
            'category' => $news,
            'name' => 'New York Times Digital',
            'cost' => new Money(1700, Currency::USD),
            'description' => 'Digital news subscription',
        ]);

        $notion = SubscriptionFactory::createOne([
            'category' => $productivity,
            'name' => 'Notion',
            'cost' => new Money(1000, Currency::USD),
            'description' => 'All-in-one workspace',
        ]);

        // Create some archived subscriptions
        SubscriptionFactory::createOne([
            'category' => $entertainment,
            'name' => 'Hulu',
            'cost' => new Money(1299, Currency::USD),
            'description' => 'Streaming service',
        ])->archive();

        // Create subscriptions with different payment periods
        SubscriptionFactory::createOne([
            'category' => $storage,
            'name' => 'Dropbox Plus',
            'cost' => new Money(11990, Currency::USD),
            'paymentPeriod' => \App\Enum\PaymentPeriod::Year,
            'paymentPeriodCount' => 1,
            'description' => 'Cloud storage',
        ]);

        SubscriptionFactory::createOne([
            'category' => $software,
            'name' => 'Adobe Creative Cloud',
            'cost' => new Money(5499, Currency::USD),
            'description' => 'Creative software suite',
        ]);

        SubscriptionFactory::createOne([
            'category' => $fitness,
            'name' => 'Gym Membership',
            'cost' => new Money(4500, Currency::USD),
            'description' => 'Monthly gym access',
        ]);

        SubscriptionFactory::createOne([
            'category' => $education,
            'name' => 'Udemy Pro',
            'cost' => new Money(1999, Currency::USD),
            'description' => 'Online learning platform',
        ]);

        // Add payments to subscriptions (2-5 each for active ones)
        for ($i = 0; $i < 5; ++$i) {
            $netflix->recordPayment(
                paidDate: \App\ValueObject\CalendarDate::forDatetimeInTimezone(new \DateTimeImmutable('-' . $i . ' months'), new \DateTimeZone('UTC')),
                paymentType: \App\Enum\PaymentType::Verified,
            );
        }

        for ($i = 0; $i < 3; ++$i) {
            $spotify->recordPayment(
                paidDate: \App\ValueObject\CalendarDate::forDatetimeInTimezone(new \DateTimeImmutable('-' . $i . ' months'), new \DateTimeZone('UTC')),
                paymentType: \App\Enum\PaymentType::Verified,
            );
        }

        for ($i = 0; $i < 4; ++$i) {
            $github->recordPayment(
                paidDate: \App\ValueObject\CalendarDate::forDatetimeInTimezone(new \DateTimeImmutable('-' . $i . ' months'), new \DateTimeZone('UTC')),
                paymentType: \App\Enum\PaymentType::Verified,
            );
        }

        for ($i = 0; $i < 2; ++$i) {
            $notion->recordPayment(
                paidDate: \App\ValueObject\CalendarDate::forDatetimeInTimezone(new \DateTimeImmutable('-' . $i . ' months'), new \DateTimeZone('UTC')),
                paymentType: \App\Enum\PaymentType::Verified,
            );
        }

        // Update some subscriptions to create events
        $netflix->update(
            category: $entertainment,
            name: 'Netflix Premium',
            nextRenewal: $netflix->nextRenewal,
            description: 'Streaming service for movies and TV shows - Premium plan',
            link: 'https://netflix.com',
            logo: '',
            paymentPeriod: $netflix->paymentPeriod,
            paymentPeriodCount: $netflix->paymentPeriodCount,
            cost: new Money(1999, Currency::USD),
            color: $netflix->color,
            now: new \DateTimeImmutable('2000-01-01', new \DateTimeZone('UTC')),
        );

        $manager->flush();
    }
}
