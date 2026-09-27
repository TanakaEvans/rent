<?php

namespace App\Services;

use App\Models\Lease;
use App\Models\Property;
use App\Models\User;

/**
 * inDrive-style location privacy for property listings.
 *
 * The public marketplace only ever exposes an approximate area for a home;
 * the exact pin, street address and Google-Maps directions are revealed to a
 * viewer once they have a real relationship with the property (its owner, an
 * admin, or a tenant who booked an accepted viewing, holds an approved
 * application, or is on an open lease).
 */
class LocationVisibilityService
{
    /**
     * May the given viewer see the property's EXACT location?
     *
     * True for the owner, an Admin/Superuser, a tenant with an ACCEPTED
     * viewing request, an approved rental application, or a lease in any open
     * state. Guests and unrelated tenants get false.
     */
    public function canSeeExact(Property $property, ?User $viewer): bool
    {
        if (! $viewer) {
            return false;
        }

        if ((int) $property->owner_id === (int) $viewer->id) {
            return true;
        }

        if ($viewer->hasAnyRole(['Admin', 'Superuser'])) {
            return true;
        }

        if ($property->viewingRequests()
            ->where('tenant_id', $viewer->id)
            ->where('status', 'accepted')
            ->exists()) {
            return true;
        }

        if ($property->applications()
            ->where('applicant_id', $viewer->id)
            ->where('status', 'approved')
            ->exists()) {
            return true;
        }

        if ($property->leases()
            ->where('tenant_id', $viewer->id)
            ->whereIn('status', Lease::OPEN)
            ->exists()) {
            return true;
        }

        return false;
    }

    /**
     * A deterministic approximate point for the map circle centre.
     *
     * Seeded by the property id (falling back to the coordinates themselves)
     * so the offset is stable across every request for a listing, yet always
     * lands away from the real spot — between 45% and 95% of the radius out,
     * at a fixed bearing. The circle centre therefore never coincides with
     * the true location.
     *
     * @return array{lat: float, lng: float}
     */
    public function approximate(float $lat, float $lng, int $radiusMeters, int|string|null $seed = null): array
    {
        $hash = crc32((string) ($seed ?? ($lat.','.$lng)));

        $angle = ($hash % 360) * (M_PI / 180.0);
        // 0.45–0.95 of the radius: never the exact point, never off the circle.
        $fraction = 0.45 + ((intdiv($hash, 360) % 51) / 100.0);
        $distance = $radiusMeters * $fraction;

        // ~111,320 m per degree of latitude; longitude shrinks by cos(lat).
        $metresPerDegree = 111_320.0;
        $cos = cos(deg2rad($lat));

        $deltaLat = ($distance * cos($angle)) / $metresPerDegree;
        $deltaLng = ($distance * sin($angle)) / ($metresPerDegree * (abs($cos) > 0.000001 ? abs($cos) : 1));

        return [
            'lat' => round($lat + $deltaLat, 7),
            'lng' => round($lng + $deltaLng, 7),
        ];
    }

    /**
     * Rewrite a property's location for a viewer, in place, before it is sent
     * to the client. When the viewer is entitled the exact coordinates and
     * street address are kept; otherwise the coordinates are replaced with the
     * stable approximate point and the address is stripped. A `locationExact`
     * flag (and, when approximate, the circle radius) is always stamped on the
     * model so the client renders a pin or a shaded circle accordingly.
     */
    public function mask(Property $property, bool $exact, int $radiusMeters): Property
    {
        $hasCoords = $property->latitude !== null && $property->longitude !== null;

        if (! $exact && $hasCoords) {
            $approx = $this->approximate(
                (float) $property->latitude,
                (float) $property->longitude,
                $radiusMeters,
                $property->id,
            );

            $property->latitude = $approx['lat'];
            $property->longitude = $approx['lng'];
            $property->address = null;
        }

        $property->setAttribute('locationExact', $exact && $hasCoords);
        $property->setAttribute('approxRadiusM', $exact ? null : $radiusMeters);

        return $property;
    }
}
