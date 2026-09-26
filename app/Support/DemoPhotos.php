<?php

namespace App\Support;

/**
 * Real listing photography for demo and load-test data.
 *
 * Photos come from database/seeders/data/property_photos.php (verified
 * Unsplash IDs). A listing gets a cover that matches its property type plus
 * interior shots, chosen deterministically from a seed so re-seeding is stable.
 */
final class DemoPhotos
{
    private const CDN = 'https://images.unsplash.com/';

    /** Which photo group a property type's cover comes from. */
    private const COVER_GROUP = [
        'house' => 'exterior',
        'townhouse' => 'exterior',
        'cottage' => 'cottage',
        'flat' => 'apartment',
        'apartment' => 'apartment',
        'room' => 'bedroom',
        'commercial' => 'office',
        'land' => 'land',
    ];

    /** Gallery groups after the cover, per property type. */
    private const GALLERY_GROUPS = [
        'commercial' => ['office', 'office', 'kitchen'],
        'land' => ['land', 'land'],
        'room' => ['bedroom', 'bathroom', 'kitchen'],
        'default' => ['living', 'kitchen', 'bedroom', 'bathroom'],
    ];

    private static ?array $catalogue = null;

    /**
     * @return array<string, array<int, string>>
     */
    public static function catalogue(): array
    {
        return self::$catalogue ??= require database_path('seeders/data/property_photos.php');
    }

    public static function url(string $photoId, int $width = 1200): string
    {
        return self::CDN.$photoId.'?auto=format&fit=crop&w='.$width.'&q=75';
    }

    /**
     * Cover first, then gallery shots, as full URLs.
     *
     * @return array<int, string>
     */
    public static function forListing(string $propertyType, int $seed): array
    {
        $catalogue = self::catalogue();
        $groups = [self::COVER_GROUP[$propertyType] ?? 'exterior', ...(self::GALLERY_GROUPS[$propertyType] ?? self::GALLERY_GROUPS['default'])];

        $picked = [];
        foreach ($groups as $i => $group) {
            $photos = $catalogue[$group];
            $index = ($seed * 7 + $i * 13) % count($photos);
            $url = self::url($photos[$index]);
            // Never repeat a photo inside one gallery.
            while (in_array($url, $picked, true)) {
                $index = ($index + 1) % count($photos);
                $url = self::url($photos[$index]);
            }
            $picked[] = $url;
        }

        return $picked;
    }
}
