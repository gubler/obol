<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Category;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Category>
 */
final class CategoryFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Category::class;
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaults(): array
    {
        return [
            // The founder owns factory-built data by default, matching authenticatedClient()'s login so
            // feature tests see their own categories. Isolation tests override with another owner.
            'owner' => UserFactory::founder(),
            'name' => self::faker()->words(nb: 2, asText: true),
        ];
    }
}
