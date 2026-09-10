<?php

namespace App\Http\Controllers;

use App\Services\MarketplaceAnalyticsService;
use Inertia\Inertia;
use Inertia\Response;

class AdminMarketplaceAnalyticsController extends Controller
{
    public function __construct(private readonly MarketplaceAnalyticsService $analytics)
    {
    }

    /**
     * Platform-wide marketplace health for admins.
     */
    public function index(): Response
    {
        return Inertia::render('Admin/Marketplace/Analytics', [
            'analytics' => $this->analytics->adminOverview(),
        ]);
    }
}