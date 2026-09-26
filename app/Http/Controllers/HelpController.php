<?php

namespace App\Http\Controllers;

use App\Support\Manual\Manual;
use Inertia\Inertia;
use Inertia\Response;

class HelpController extends Controller
{
    /**
     * Public Help Center landing: pick the tenant or owner guide.
     */
    public function index(): Response
    {
        return Inertia::render('Help/Index', [
            'guides' => array_map(fn (string $guide) => Manual::summary($guide), Manual::PUBLIC_GUIDES),
        ]);
    }

    /**
     * A public guide (tenant or owner). The route constrains {guide}.
     */
    public function show(string $guide): Response
    {
        return Inertia::render('Help/Guide', [
            'guide' => Manual::forDisplay($guide),
            'otherGuides' => array_map(
                fn (string $key) => Manual::summary($key),
                array_values(array_diff(Manual::PUBLIC_GUIDES, [$guide]))
            ),
        ]);
    }

    /**
     * The admin guide, rendered inside the admin portal.
     */
    public function admin(): Response
    {
        return Inertia::render('Admin/Help', [
            'guide' => Manual::forDisplay('admin'),
        ]);
    }
}
