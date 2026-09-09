<?php

namespace App\Http\Controllers;

use App\Services\FavouriteService;
use Inertia\Inertia;

class FavouriteController extends Controller
{
    public function __construct(private readonly FavouriteService $service)
    {
    }

    /**
     * Show the tenant's saved favourites.
     */
    public function index()
    {
        $favourites = $this->service->listFor(auth()->user())->get();

        return Inertia::render('Tenant/Favourites', [
            'favourites' => $favourites,
            'stats' => [
                'total' => $favourites->count(),
                'available' => $favourites->where('status', 'available')->count(),
            ],
        ]);
    }

    /**
     * Toggle a property in/out of the current tenant's favourites.
     */
    public function toggle(int $property)
    {
        $this->service->toggle(auth()->user(), $property);

        return redirect()->back();
    }
}