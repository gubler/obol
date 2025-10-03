# Spec Requirements Document

> Spec: Category CRUD Operations
> Created: 2025-10-02

## Overview

Implement complete CRUD (Create, Read, Update, Delete) functionality for categories, allowing users to manage subscription categories through a web interface. This provides the foundation for organizing subscriptions and enables users to create custom categorization schemes.

## User Stories

### Create and Manage Categories

As a user, I want to create, edit, and delete categories, so that I can organize my subscriptions in a way that makes sense to me.

The user navigates to the categories page where they see a list of all existing categories. They can click "New Category" to open a form where they enter a category name. After submitting, the new category appears in the list and is immediately available for assigning to subscriptions. Users can also edit existing category names or delete categories that are no longer needed (with appropriate validation).

### Organize with Custom Categories

As a user, I want to see all my categories in one place with subscription counts, so that I can understand how my subscriptions are organized.

The categories list page shows each category with its name and the number of subscriptions assigned to it. Users can quickly see which categories are being used and click through to see category details including all subscriptions in that category.

## Spec Scope

1. **Create Category Form** - Form and controller action to create new categories with name validation
2. **Edit Category Functionality** - Update existing category names through a form
3. **List Categories Page** - Display all categories with subscription counts in a table
4. **Delete Categories** - Remove categories with validation preventing deletion if subscriptions exist
5. **Category Details Page** - Show category information with list of assigned subscriptions

## Out of Scope

- Bulk category operations (bulk delete, bulk edit)
- Category icons or colors
- Nested/hierarchical categories
- Category sorting or custom ordering
- Category import/export functionality
- Events/event subscribers (no async processing needed)

## Expected Deliverable

1. Users can create new categories through a web form that validates the name is not empty
2. Users can view a list of all categories showing name and subscription count
3. Users can edit category names and delete categories (with validation for subscriptions)
4. Users can view category details showing all subscriptions assigned to that category
