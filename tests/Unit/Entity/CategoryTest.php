<?php

// ABOUTME: Unit tests for Category entity ensuring proper instantiation and state validation.
// ABOUTME: Tests verify valid category creation, property initialization, and business invariants.

declare(strict_types=1);

use App\Entity\Category;

test('creates category with valid name', function (): void {
    $category = new Category(name: 'Entertainment');

    expect($category->name)->toBe('Entertainment');
});

test('initializes empty subscriptions collection', function (): void {
    $category = new Category(name: 'Software');

    expect($category->subscriptions)->toHaveCount(0);
});

test('allows setting name', function (): void {
    $category = new Category(name: 'Original Name');

    $category->setName('Updated Name');

    expect($category->name)->toBe('Updated Name');
});

test('rejects empty name', function (): void {
    new Category(name: '');
})->throws(Assert\InvalidArgumentException::class);

test('rejects whitespace name', function (): void {
    new Category(name: '   ');
})->throws(Assert\InvalidArgumentException::class);
