<?php

// ABOUTME: Development fixtures that populate the database with realistic test data.
// ABOUTME: Uses Foundry factories to create categories, subscriptions, payments, and events.

declare(strict_types=1);

namespace App\DataFixtures;

use App\Factory\CategoryFactory;
use App\Factory\PaymentFactory;
use App\Factory\SubscriptionFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Create categories
        $entertainment = CategoryFactory::createOne(['name' => 'Entertainment']);
        $productivity = CategoryFactory::createOne(['name' => 'Productivity']);
        $utilities = CategoryFactory::createOne(['name' => 'Utilities']);
        $news = CategoryFactory::createOne(['name' => 'News & Media']);
        $software = CategoryFactory::createOne(['name' => 'Software Development']);
        $fitness = CategoryFactory::createOne(['name' => 'Health & Fitness']);
        $education = CategoryFactory::createOne(['name' => 'Education']);
        $storage = CategoryFactory::createOne(['name' => 'Cloud Storage']);

        // Create active subscriptions with variety
        $netflix = SubscriptionFactory::createOne([
            'category' => $entertainment,
            'name' => 'Netflix',
            'cost' => 1599,
            'description' => 'Streaming service for movies and TV shows',
        ]);

        $spotify = SubscriptionFactory::createOne([
            'category' => $entertainment,
            'name' => 'Spotify Premium',
            'cost' => 999,
            'description' => 'Music streaming service',
        ]);

        $github = SubscriptionFactory::createOne([
            'category' => $software,
            'name' => 'GitHub Pro',
            'cost' => 700,
            'description' => 'Source code hosting and collaboration',
        ]);

        $nytimes = SubscriptionFactory::createOne([
            'category' => $news,
            'name' => 'New York Times Digital',
            'cost' => 1700,
            'description' => 'Digital news subscription',
        ]);

        $notion = SubscriptionFactory::createOne([
            'category' => $productivity,
            'name' => 'Notion',
            'cost' => 1000,
            'description' => 'All-in-one workspace',
        ]);

        // Create some archived subscriptions
        $archivedHulu = SubscriptionFactory::createOne([
            'category' => $entertainment,
            'name' => 'Hulu',
            'cost' => 1299,
            'description' => 'Streaming service',
        ])->archive();

        // Create subscriptions with different payment periods
        $dropbox = SubscriptionFactory::createOne([
            'category' => $storage,
            'name' => 'Dropbox Plus',
            'cost' => 11990,
            'paymentPeriod' => \App\Enum\PaymentPeriod::Year,
            'paymentPeriodCount' => 1,
            'description' => 'Cloud storage',
        ]);

        $adobe = SubscriptionFactory::createOne([
            'category' => $software,
            'name' => 'Adobe Creative Cloud',
            'cost' => 5499,
            'description' => 'Creative software suite',
        ]);

        $gymMembership = SubscriptionFactory::createOne([
            'category' => $fitness,
            'name' => 'Gym Membership',
            'cost' => 4500,
            'description' => 'Monthly gym access',
        ]);

        $udemy = SubscriptionFactory::createOne([
            'category' => $education,
            'name' => 'Udemy Pro',
            'cost' => 1999,
            'description' => 'Online learning platform',
        ]);

        // Add payments to subscriptions (2-5 each for active ones)
        for ($i = 0; $i < 5; ++$i) {
            $netflix->_real()->recordPayment(
                paidDate: new \DateTimeImmutable('-' . $i . ' months'),
                paymentType: \App\Enum\PaymentType::Verified,
            );
        }

        for ($i = 0; $i < 3; ++$i) {
            $spotify->_real()->recordPayment(
                paidDate: new \DateTimeImmutable('-' . $i . ' months'),
                paymentType: \App\Enum\PaymentType::Verified,
            );
        }

        for ($i = 0; $i < 4; ++$i) {
            $github->_real()->recordPayment(
                paidDate: new \DateTimeImmutable('-' . $i . ' months'),
                paymentType: \App\Enum\PaymentType::Verified,
            );
        }

        for ($i = 0; $i < 2; ++$i) {
            $notion->_real()->recordPayment(
                paidDate: new \DateTimeImmutable('-' . $i . ' months'),
                paymentType: \App\Enum\PaymentType::Verified,
            );
        }

        // Update some subscriptions to create events
        $netflix->_real()->update(
            category: $entertainment->_real(),
            name: 'Netflix Premium',
            lastPaidDate: $netflix->_real()->lastPaidDate,
            description: 'Streaming service for movies and TV shows - Premium plan',
            link: 'https://netflix.com',
            logo: '',
            paymentPeriod: $netflix->_real()->paymentPeriod,
            paymentPeriodCount: $netflix->_real()->paymentPeriodCount,
            cost: 1999,
        );

        $manager->flush();
    }
}
