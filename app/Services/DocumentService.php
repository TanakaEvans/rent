<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Lease;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DocumentService
{
    /**
     * Render the stored snapshot of a signed lease agreement as plain text
     * (M20-lite stores a text snapshot; binary/pdf uploads arrive with the
     * full document repository in a later wave).
     */
    public function renderAgreementText(Lease $lease): string
    {
        $lease->loadMissing(['property.owner:id,name,email', 'tenant:id,name,email', 'signatures.user:id,name']);

        $money = fn ($value) => '$'.number_format((float) $value, 2);
        $date = fn ($value) => $value ? $value->format('j F Y') : '—';

        $lines = [];
        $lines[] = 'DZIMBA LEASE AGREEMENT';
        $lines[] = str_repeat('=', 48);
        $lines[] = '';
        $lines[] = 'Lease number : '.$lease->lease_no;
        $lines[] = 'Status       : '.strtoupper($lease->status);
        $lines[] = 'Clause version: '.$lease->clause_version;
        $lines[] = '';
        $lines[] = 'PROPERTY';
        $lines[] = str_repeat('-', 48);
        $lines[] = $lease->property->title.' ('.ucfirst(str_replace('_', ' ', $lease->property->property_type)).')';
        $lines[] = 'Address     : '.$lease->property->address;
        $lines[] = 'Location    : '.implode(', ', array_filter([$lease->property->suburb, $lease->property->city]));
        $lines[] = '';
        $lines[] = 'PARTIES';
        $lines[] = str_repeat('-', 48);
        $lines[] = 'Owner       : '.$lease->property->owner->name.' <'.$lease->property->owner->email.'>';
        $lines[] = 'Tenant      : '.$lease->tenant->name.' <'.$lease->tenant->email.'>';
        $lines[] = '';
        $lines[] = 'TERM';
        $lines[] = str_repeat('-', 48);
        $lines[] = 'Start       : '.$date($lease->start_date);
        $lines[] = 'End         : '.$date($lease->end_date);
        $lines[] = '';
        $lines[] = 'FINANCIAL TERMS';
        $lines[] = str_repeat('-', 48);
        $lines[] = 'Rent        : '.$money($lease->rent_amount).' per '.($lease->payment_terms['frequency'] ?? 'month');
        $lines[] = 'Deposit     : '.$money($lease->deposit_amount);
        foreach ((array) ($lease->payment_terms ?? []) as $key => $value) {
            if ($key === 'frequency') {
                continue;
            }
            $lines[] = ' '.ucfirst($key).'      : '.(is_array($value) ? implode(', ', $value) : $value);
        }
        $lines[] = '';
        $lines[] = 'SIGNATURES';
        $lines[] = str_repeat('-', 48);
        if ($lease->signatures->isEmpty()) {
            $lines[] = 'Pending digital signatures.';
        }
        foreach ($lease->signatures as $signature) {
            $lines[] = '- '.$signature->user->name.' — "'.$signature->signature_payload.'" on '.$date($signature->signed_at);
        }
        $lines[] = '';
        $lines[] = 'This agreement is the authoritative record of the signed lease.';

        return implode(PHP_EOL, $lines);
    }

    /**
     * Store (or refresh) the agreement snapshot for a lease. Re-storing a
     * lease's agreement bumps its version while keeping a single row,
     * mirroring FR-03 versioning (full version history lands with M20).
     */
    public function storeLeaseAgreement(Lease $lease): Document
    {
        $content = $this->renderAgreementText($lease);

        $existing = Document::where('lease_id', $lease->id)->latest('version')->first();
        if ($existing) {
            $existing->update([
                'content' => $content,
                'size' => strlen($content),
                'version' => $existing->version + 1,
            ]);

            return $existing->fresh();
        }

        return Document::create([
            'lease_id' => $lease->id,
            'type' => 'lease_agreement',
            'name' => 'Lease Agreement '.$lease->lease_no,
            'content' => $content,
            'mime' => 'text/plain',
            'size' => strlen($content),
            'version' => 1,
            'visibility' => 'private',
        ]);
    }

    /**
     * Resolve a document for viewing/download. Parties on the linked lease and
     * admins may read it; everyone else receives a 404 (no cross-party leaks).
     */
    public function authorize(User $user, int $id): Document
    {
        $document = Document::with(['lease.property.owner:id,name,email', 'lease.tenant:id,name,email', 'lease.property'])
            ->find($id);

        if (! $document) {
            throw new NotFoundHttpException('Document not found.');
        }

        if (! $document->lease_id) {
            if ($user->hasAnyRole(['Admin', 'Superuser'])) {
                return $document;
            }

            throw new NotFoundHttpException('Document not found.');
        }

        if ($user->hasAnyRole(['Admin', 'Superuser'])) {
            return $document;
        }

        $lease = $document->lease;
        if ($lease->property->owner_id === $user->id || $lease->tenant_id === $user->id) {
            return $document;
        }

        throw new NotFoundHttpException('Document not found.');
    }

    /**
     * Documents attached to leases on the owner's properties, newest first.
     */
    public function listForOwner(User $owner)
    {
        return Document::with(['lease:id,lease_no', 'lease.property:id,title,property_type,suburb,city'])
            ->whereHas('lease.property', fn ($query) => $query->where('owner_id', $owner->id))
            ->latest()
            ->get();
    }

    /**
     * Documents attached to the tenant's own leases, newest first.
     */
    public function listForTenant(User $tenant)
    {
        return Document::with(['lease:id,lease_no', 'lease.property:id,title,property_type,suburb,city'])
            ->whereHas('lease', fn ($query) => $query->where('tenant_id', $tenant->id))
            ->latest()
            ->get();
    }
}