<?php

namespace App\Support;

/**
 * The arithmetic of PMC's Contractor Performance Evaluation Framework, over
 * the definition in config/contractor_evaluation.php.
 *
 *   Weighted score = (rating ÷ 5) × criterion weight
 *   Category score = sum of its weighted scores
 *   Project score  = sum of the eight category scores (out of 100)
 *
 * A category with any criterion unrated has no score yet: a partly rated form
 * is "not yet evaluated", never scored as if the gaps were zero.
 */
class ContractorEvaluationScoring
{
    /** @return array<string, array> category key => definition */
    public static function categories(): array
    {
        return config('contractor_evaluation.categories');
    }

    public static function has(string $category): bool
    {
        return isset(self::categories()[$category]);
    }

    /** @return array<int, string> the criterion keys of one category, in form order */
    public static function criterionKeys(string $category): array
    {
        return array_column(self::categories()[$category]['criteria'] ?? [], 'key');
    }

    /**
     * Keep only known criteria with a 1–5 rating.
     *
     * @param  array<string, mixed>  $ratings
     * @return array<string, int>
     */
    public static function cleanRatings(string $category, array $ratings): array
    {
        $clean = [];

        foreach (self::criterionKeys($category) as $key) {
            $value = $ratings[$key] ?? null;

            if (is_numeric($value) && (int) $value >= 1 && (int) $value <= 5) {
                $clean[$key] = (int) $value;
            }
        }

        return $clean;
    }

    public static function isComplete(string $category, ?array $ratings): bool
    {
        return count(self::cleanRatings($category, $ratings ?? [])) === count(self::criterionKeys($category));
    }

    /** The category's score, or null while any criterion is unrated. */
    public static function categoryScore(string $category, ?array $ratings): ?float
    {
        if (! self::isComplete($category, $ratings)) {
            return null;
        }

        $score = 0.0;

        foreach (self::categories()[$category]['criteria'] as $criterion) {
            $score += ($ratings[$criterion['key']] / 5) * $criterion['weight'];
        }

        return round($score, 2);
    }

    /**
     * The project score from each category's score, or null while any of the
     * eight is missing.
     *
     * @param  array<string, float|null>  $categoryScores
     */
    public static function totalScore(array $categoryScores): ?float
    {
        $total = 0.0;

        foreach (array_keys(self::categories()) as $category) {
            $score = $categoryScores[$category] ?? null;

            if ($score === null) {
                return null;
            }

            $total += (float) $score;
        }

        return round($total, 2);
    }

    /** @return array{label: string, action: string}|null */
    public static function classify(?float $score): ?array
    {
        if ($score === null) {
            return null;
        }

        foreach (config('contractor_evaluation.classifications') as $band) {
            if ($score >= $band['min']) {
                return ['label' => $band['label'], 'action' => $band['action']];
            }
        }

        return null;
    }
}
