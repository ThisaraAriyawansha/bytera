<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboard) {}

    /**
     * The Business Overview for the chosen period (Today / 7 Days / 30 Days), with each section shown only to
     * users allowed to see it.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('dashboard', [
            'dashboard' => $this->dashboard->build($user, DashboardService::period($request->query('period'))),
            'canSell' => $user->can('sales.view'),
            'canViewBills' => $user->can('bills.view'),
            'canViewProducts' => $user->can('products.view'),
        ]);
    }
}
