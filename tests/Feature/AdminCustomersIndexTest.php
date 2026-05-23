<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\User;

test('admin customers index paginates results', function () {
    $user = User::factory()->create();

    foreach (range(1, 18) as $index) {
        Customer::query()->create([
            'name' => "Customer {$index}",
            'email' => "customer{$index}@example.com",
            'status' => CustomerStatus::Active,
        ]);
    }

    $this->actingAs($user)
        ->get(route('admin.customers'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/customers/index')
            ->where('customers.total', 18)
            ->where('customers.per_page', 15)
            ->where('customers.last_page', 2)
            ->has('customers.data', 15));

    $this->actingAs($user)
        ->get(route('admin.customers', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('customers.current_page', 2)
            ->has('customers.data', 3));
});

test('admin customers index filters by name or email via search query param', function () {
    $user = User::factory()->create();

    Customer::query()->create([
        'name' => 'Unique Alpha',
        'email' => 'alpha@example.com',
        'status' => CustomerStatus::Active,
    ]);

    Customer::query()->create([
        'name' => 'Other User',
        'email' => 'beta.unique@example.com',
        'status' => CustomerStatus::Active,
    ]);

    Customer::query()->create([
        'name' => 'Someone Else',
        'email' => 'other@example.com',
        'status' => CustomerStatus::Active,
    ]);

    $this->actingAs($user)
        ->get(route('admin.customers', ['search' => 'Unique Alpha']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.search', 'Unique Alpha')
            ->where('customers.total', 1)
            ->where('customers.data.0.name', 'Unique Alpha'));

    $this->actingAs($user)
        ->get(route('admin.customers', ['search' => 'beta.unique@']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('customers.total', 1)
            ->where('customers.data.0.email', 'beta.unique@example.com'));
});
