<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CustomerStatus;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\TrelloTask;
use App\Models\TrelloTaskVersion;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function index(): Response
    {
        $customersByPlan = Customer::query()
            ->selectRaw('plan_id, count(*) as count')
            ->groupBy('plan_id')
            ->with('plan')
            ->get();

        $monthlyRevenue = Customer::query()
            ->where('status', CustomerStatus::Active)
            ->with('plan')
            ->get()
            ->sum(fn (Customer $customer): float => (float) ($customer->plan?->price ?? 0));

        return Inertia::render('admin/dashboard', [
            'totalCustomers' => Customer::count(),
            'activeCustomers' => Customer::where('status', CustomerStatus::Active)->count(),
            'cancelledCustomers' => Customer::where('status', CustomerStatus::Cancelled)->count(),
            'totalFiles' => TrelloTaskVersion::query()->whereNotNull('document_path')->count(),
            'totalTasks' => TrelloTask::count(),
            'recentCustomers' => Customer::with('plan')->latest()->take(5)->get(),
            'recentTasks' => TrelloTask::with('customer')->latest()->take(5)->get(),
            'customersByPlan' => $customersByPlan,
            'monthlyRevenue' => $monthlyRevenue,
            'plans' => Plan::all(),
        ]);
    }
}
