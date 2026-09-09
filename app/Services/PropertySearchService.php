<?php

namespace App\Services;

use App\Models\Property;
use Illuminate\Pagination\LengthAwarePaginator;

class PropertySearchService
{
    /**
     * Search available listings with optional combinable filters.
     *
     * Filters (null/empty = ignored):
     *  - property_type, city, zone, suburb (string equality)
     *  - min_price, max_price (true range, decimal)
     *  - bedrooms, bathrooms (at-least N semantics)
     *  - furnished (bool)
     *  - sort: newest | price_asc | price_desc
     *  - per_page
     *
     * Featured listings always sort above organic results.
     *
     * @param array<string, mixed> $filters
     */
    public function search(array $filters): LengthAwarePaginator
    {
        $query = Property::listed()->with('owner:id,name,email');

        $this->applyValue($query, 'property_type', $filters['property_type'] ?? null);
        $this->applyValue($query, 'city', $filters['city'] ?? null);
        $this->applyValue($query, 'zone', $filters['zone'] ?? null);
        $this->applyValue($query, 'suburb', $filters['suburb'] ?? null);

        $this->applyMinimum($query, 'price', $filters['min_price'] ?? null);
        $this->applyMaximum($query, 'price', $filters['max_price'] ?? null);
        $this->applyMinimum($query, 'bedrooms', $filters['bedrooms'] ?? null);
        $this->applyMinimum($query, 'bathrooms', $filters['bathrooms'] ?? null);

        if (($filters['furnished'] ?? null) !== null) {
            $query->where('furnished', (bool) $filters['furnished']);
        }

        [$column, $direction] = match ($filters['sort'] ?? 'newest') {
            'price_asc' => ['price', 'asc'],
            'price_desc' => ['price', 'desc'],
            default => ['created_at', 'desc'],
        };

        $perPage = isset($filters['per_page']) ? max(1, (int) $filters['per_page']) : 12;

        return $query
            ->orderBy('featured', 'desc')
            ->orderBy($column, $direction)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Load a single publicly-viewable (available) property for the detail page.
     */
    public function findPublicDetail(int $propertyId): ?Property
    {
        return Property::listed()
            ->with(['images', 'owner:id,name,email'])
            ->find($propertyId);
    }

    private function applyValue($query, string $column, $value): void
    {
        if ($value !== null && $value !== '') {
            $query->where($column, $value);
        }
    }

    private function applyMinimum($query, string $column, $value): void
    {
        if ($value !== null && $value !== '') {
            $query->where($column, '>=', (float) $value);
        }
    }

    private function applyMaximum($query, string $column, $value): void
    {
        if ($value !== null && $value !== '') {
            $query->where($column, '<=', (float) $value);
        }
    }
}