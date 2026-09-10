<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OwnerAnalyticsController extends Controller
{
    public function __construct(private readonly MarketplaceAnalyticsService $analytics)
    {
    }

    /**
     * Per-property performance + portfolio trend for an owner.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Owner/Analytics', [
            'analytics' => $this->analytics->ownerOverview($request->user()),
        ]);
    }
}