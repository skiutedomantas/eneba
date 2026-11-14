<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;
use Normalizer;
use RuntimeException;

final class ProductSearchText
{
    public static function assertExtensionsAvailable(): void
    {
        if (!extension_loaded('intl') || !extension_loaded('mbstring')) {
            throw new RuntimeException('Search requires the PHP intl and mbstring extensions.');
        }
    }

    public static function normalize(string $text): string
    {
        self::assertExtensionsAvailable();
        if (!mb_check_encoding($text, 'UTF-8')) {
            throw new InvalidArgumentException('Search text must be valid UTF-8.');
        }

        $normalized = Normalizer::normalize($text, Normalizer::FORM_KC);
        if ($normalized === false) {
            throw new InvalidArgumentException('Search text could not be normalized.');
        }

        $normalized = mb_strtolower($normalized, 'UTF-8');
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized);
        if ($normalized === null) {
            throw new InvalidArgumentException('Search text could not be normalized.');
        }

        return trim($normalized);
    }

    /**
     * @return list<string>
     */
    public static function grams(string $normalized): array
    {
        $grams = [];
        foreach (explode(' ', $normalized) as $token) {
            if ($token === '') {
                continue;
            }

            $characters = mb_str_split($token, 1, 'UTF-8');
            $length = count($characters);
            for ($gramLength = 2; $gramLength <= min(3, $length); $gramLength++) {
                for ($offset = 0; $offset <= $length - $gramLength; $offset++) {
                    $grams[] = implode('', array_slice($characters, $offset, $gramLength));
                }
            }
        }

        return array_values(array_unique($grams));
    }

    public static function similarity(string $query, string $title): float
    {
        if ($query === '' || $title === '') {
            return 0.0;
        }
        if (str_contains($title, $query)) {
            return 1.0;
        }

        $queryTokens = explode(' ', $query);
        $titleTokens = explode(' ', $title);
        $total = 0.0;

        foreach ($queryTokens as $queryToken) {
            $best = 0.0;
            foreach ($titleTokens as $titleToken) {
                $queryLength = mb_strlen($queryToken, 'UTF-8');
                $titleLength = mb_strlen($titleToken, 'UTF-8');
                $maximumLength = max($queryLength, $titleLength);
                if ($maximumLength === 0) {
                    continue;
                }

                $distance = self::editDistance($queryToken, $titleToken);
                $score = 1 - ($distance / $maximumLength);
                if (str_starts_with($titleToken, $queryToken)) {
                    $score = max(
                        $score,
                        0.65 + (0.35 * $queryLength / $titleLength),
                    );
                }
                $best = max($best, $score);
            }
            $total += $best;
        }

        return $total / count($queryTokens);
    }

    private static function editDistance(string $left, string $right): int
    {
        $leftCharacters = mb_str_split($left, 1, 'UTF-8');
        $rightCharacters = mb_str_split($right, 1, 'UTF-8');
        $previous = range(0, count($rightCharacters));

        foreach ($leftCharacters as $leftIndex => $leftCharacter) {
            $current = [$leftIndex + 1];
            foreach ($rightCharacters as $rightIndex => $rightCharacter) {
                $current[] = min(
                    $current[$rightIndex] + 1,
                    $previous[$rightIndex + 1] + 1,
                    $previous[$rightIndex] + ($leftCharacter === $rightCharacter ? 0 : 1),
                );
            }
            $previous = $current;
        }

        return $previous[count($rightCharacters)];
    }
}
