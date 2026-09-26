<?php

namespace App\Support\Manual;

use InvalidArgumentException;

/**
 * The in-app user manuals (Help Center).
 *
 * Each guide lives in app/Support/Manual/guides/{guide}.php as plain data:
 * sections → articles → steps. Articles link to real screens by route name
 * and cite the feature tests that prove the behaviour they describe, so
 * tests/Feature/UserManualTest can fail the build when the manual and the
 * system drift apart.
 */
final class Manual
{
    /** Guides anyone can read (tenants and owners need help before signing up). */
    public const PUBLIC_GUIDES = ['tenant', 'owner'];

    /** Every guide, including the admin guide that only lives inside the admin portal. */
    public const GUIDES = ['tenant', 'owner', 'admin'];

    /**
     * The guide exactly as written (route names unresolved).
     *
     * @return array{key: string, title: string, tagline: string, role: string, sections: array<int, array>}
     */
    public static function raw(string $guide): array
    {
        if (! in_array($guide, self::GUIDES, true)) {
            throw new InvalidArgumentException("Unknown guide [{$guide}].");
        }

        return require __DIR__."/guides/{$guide}.php";
    }

    /**
     * The guide ready for the UI: page links resolved to URLs and test
     * references stripped (they are for CI, not for readers).
     */
    public static function forDisplay(string $guide): array
    {
        $data = self::raw($guide);

        $data['sections'] = array_map(function (array $section) {
            $section['articles'] = array_map(function (array $article) {
                $article['links'] = array_map(fn (array $link) => [
                    'label' => $link['label'],
                    'href' => route($link['route']),
                ], $article['links'] ?? []);
                $article['steps'] ??= [];
                $article['notes'] ??= [];
                unset($article['verified_by']);

                return $article;
            }, $section['articles']);

            return $section;
        }, $data['sections']);

        return $data;
    }

    /**
     * A short card for the Help Center landing page.
     *
     * @return array{key: string, title: string, tagline: string, sections: int, articles: int, topics: array<int, string>}
     */
    public static function summary(string $guide): array
    {
        $data = self::raw($guide);

        return [
            'key' => $data['key'],
            'title' => $data['title'],
            'tagline' => $data['tagline'],
            'sections' => count($data['sections']),
            'articles' => array_sum(array_map(fn (array $section) => count($section['articles']), $data['sections'])),
            'topics' => array_map(fn (array $section) => $section['title'], $data['sections']),
        ];
    }
}
