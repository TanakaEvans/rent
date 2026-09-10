<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DocumentController extends Controller
{
    public function __construct(private readonly DocumentService $service)
    {
    }

    /**
     * The owner's agreement documents across their properties.
     */
    public function ownerIndex(Request $request)
    {
        return Inertia::render('Owner/Documents/Index', [
            'documents' => $this->service->listForOwner($request->user()),
        ]);
    }

    /**
     * The tenant's own agreement documents.
     */
    public function tenantIndex(Request $request)
    {
        return Inertia::render('Tenant/Documents', [
            'documents' => $this->service->listForTenant($request->user()),
        ]);
    }

    /**
     * Read a single document (parties on the lease or an admin).
     */
    public function show(Request $request, Document $document)
    {
        $document = $this->service->authorize($request->user(), $document->id);

        return Inertia::render('Documents/Show', [
            'document' => $document,
        ]);
    }

    /**
     * Download a document as a plain-text file (no public directory listing).
     */
    public function download(Request $request, Document $document)
    {
        $document = $this->service->authorize($request->user(), $document->id);

        $leaseNo = $document->lease ? $document->lease->lease_no : 'document';

        return response($document->content, 200, [
            'Content-Type' => $document->mime ?: 'text/plain; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.e(strtolower($leaseNo)).'-agreement-v'.$document->version.'.txt"',
        ]);
    }
}