<?php

// ABOUTME: Domain exception thrown when attempting to delete a category that has subscriptions.
// ABOUTME: Ensures business rule that categories with subscriptions cannot be deleted.

declare(strict_types=1);

namespace App\Exception;

class CategoryHasSubscriptionsException extends \DomainException
{
    public function __construct(string $categoryId)
    {
        parent::__construct(
            \sprintf('Cannot delete category "%s" because it has subscriptions assigned.', $categoryId)
        );
    }
}
