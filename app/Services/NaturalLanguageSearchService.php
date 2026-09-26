<?php

namespace App\Services;

/**
 * Turns free-text marketplace search ("3 bed flat in Borrowdale under $700,
 * furnished") into the normalized filter set. Heuristics only — anything it
 * cannot confidently recognise is returned as `keywords` so the token-based
 * text search still applies to it.
 */
class NaturalLanguageSearchService
{
    private const CITY_WORDS = ['Harare', 'Bulawayo', 'Mutare', 'Gweru', 'Kwekwe', 'Masvingo', 'Victoria Falls', 'Bindura', 'Marondera'];

    private const TYPE_WORDS = [
        'house' => 'house',
        'flat' => 'flat',
        'apartment' => 'apartment',
        'appartment' => 'apartment',
        'townhouse' => 'townhouse',
        'cottage' => 'cottage',
        'bachelor' => 'room',
        'bedsitter' => 'room',
        'bedsit' => 'room',
        'room' => 'room',
        'rooms' => 'room',
        'commercial' => 'commercial',
        'office' => 'commercial',
        'shop' => 'commercial',
        'warehouse' => 'commercial',
        'land' => 'land',
        'stand' => 'land',
    ];

    private const AMENITY_WORDS = [
        'backup power' => 'backup_power',
        'back up power' => 'backup_power',
        'air conditioning' => 'air_conditioning',
        'swimming pool' => 'swimming_pool',
        'airconditioned' => 'air_conditioning',
        'pet friendly' => 'pet_friendly',
        'water tank' => 'water_tank',
    ];

    private const AMENITY_WORDS_SIMPLE = [
        'borehole' => 'borehole',
        'solar' => 'solar',
        'generator' => 'generator',
        'internet' => 'internet',
        'wifi' => 'internet',
        'garden' => 'garden',
        'pool' => 'swimming_pool',
        'security' => 'security',
        'gated' => 'gated_community',
        'carport' => 'carport',
        'parking' => 'parking',
        'garage' => 'double_garage',
        'aircon' => 'air_conditioning',
        'ensuite' => 'ensuite',
        'balcony' => 'balcony',
        'pets' => 'pet_friendly',
    ];

    /**
     * Filler words dropped from the keyword leftover so they never have to
     * appear verbatim in a listing.
     */
    private const STOP_WORDS = [
        'a', 'an', 'the', 'in', 'at', 'on', 'of', 'near', 'around', 'close', 'to', 'for', 'with', 'and', 'or',
        'rent', 'rental', 'let', 'per', 'month', 'monthly', 'pm', 'usd', 'us', 'price', 'budget',
        'bed', 'beds', 'bedroom', 'bedrooms', 'property', 'properties', 'home', 'homes', 'place',
        'looking', 'want', 'need', 'available', 'me', 'my', 'is', 'am',
    ];

    private const NUMBER_WORDS = [
        'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5,
        'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10,
        'studio' => 0,
    ];

    /**
     * Parse free text into a normalized filter array. Recognised phrases
     * become structured filters and are removed from the text; what is left
     * (minus filler words) is returned as `keywords` for the token-based
     * text match, so the whole sentence never has to appear verbatim.
     *
     * @return array<string, mixed>
     */
    public function parse(?string $raw): array
    {
        $text = mb_strtolower(trim((string) $raw));

        if ($text === '') {
            return [];
        }

        $filters = [];

        if (preg_match('/(\d+|one|two|three|four|five|six|seven|eight|nine|ten)\s*-?\s*(?:bedrooms?|beds?)\b/', $text, $match)) {
            $filters['bedrooms'] = $this->toNumber($match[1]);
            $text = trim(str_replace($match[0], ' ', $text));
        }

        if (preg_match('/between\s+\$?\s*(\d[\d,]*)\s+(?:and|to|-)\s+\$?\s*(\d[\d,]*)/', $text, $match)) {
            $filters['min_price'] = $this->toAmount($match[1]);
            $filters['max_price'] = $this->toAmount($match[2]);
            $text = trim(str_replace($match[0], ' ', $text));
        } elseif (preg_match('/\$?\s*(\d[\d,]*)\s*[-–]\s*\$?\s*(\d[\d,]*)/', $text, $match)) {
            $filters['min_price'] = $this->toAmount($match[1]);
            $filters['max_price'] = $this->toAmount($match[2]);
            $text = trim(str_replace($match[0], ' ', $text));
        } elseif (preg_match('/(?:under|below|less than|max(?:imum)?|up to|at most)\s+\$?\s*(\d[\d,]*)/', $text, $match)) {
            $filters['max_price'] = $this->toAmount($match[1]);
            $text = trim(str_replace($match[0], ' ', $text));
        } elseif (preg_match('/(?:over|above|more than|at least|from|min(?:imum)?)\s+\$?\s*(\d[\d,]*)/', $text, $match)) {
            $filters['min_price'] = $this->toAmount($match[1]);
            $text = trim(str_replace($match[0], ' ', $text));
        }

        foreach (self::TYPE_WORDS as $word => $type) {
            $pattern = '/\b'.preg_quote($word, '/').'\b/';
            if (preg_match($pattern, $text)) {
                $filters['property_type'] = $type;
                $text = trim(preg_replace($pattern, ' ', $text));
                break;
            }
        }

        foreach (self::CITY_WORDS as $city) {
            if (str_contains($text, mb_strtolower($city))) {
                $filters['city'] = $city;
                $text = trim(str_replace(mb_strtolower($city), ' ', $text));
                break;
            }
        }

        $amenities = [];
        foreach (array_merge(self::AMENITY_WORDS, self::AMENITY_WORDS_SIMPLE) as $word => $key) {
            if (isset($amenities[$key])) {
                continue;
            }
            if (preg_match('/\b'.preg_quote($word, '/').'\b/', $text)) {
                $amenities[$key] = true;
                $text = trim(str_replace($word, ' ', $text));
            }
        }
        if ($amenities) {
            $filters['amenities'] = array_keys($amenities);
        }

        if (preg_match('/\bunfurnished\b/', $text)) {
            $filters['furnished'] = false;
            $text = trim(preg_replace('/\bunfurnished\b/', ' ', $text));
        } elseif (preg_match('/\bfurnished\b/', $text)) {
            $filters['furnished'] = true;
            $text = trim(preg_replace('/\bfurnished\b/', ' ', $text));
        }

        // Bare numbers left over are unparsed amounts/counts, not keywords.
        $filters['keywords'] = implode(' ', array_filter(
            PropertySearchService::keywordTokens($text),
            fn ($token) => ! in_array($token, self::STOP_WORDS, true) && ! ctype_digit($token),
        ));
        $filters['natural'] = true;

        return $filters;
    }

    private function toNumber(string $value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        return self::NUMBER_WORDS[$value] ?? 1;
    }

    private function toAmount(string $value): float
    {
        return (float) str_replace(',', '', trim($value));
    }
}