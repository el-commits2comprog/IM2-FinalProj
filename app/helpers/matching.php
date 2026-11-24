<?php

declare(strict_types=1);

/**
 * ByteBack [M4] Matching helper: similarity score for a Lost report vs a Found report.
 *
 * This file only compares data. It never reads or writes the database and prints nothing,
 * so it is safe to call from pages, handlers, and models.
 * Load it with: require_once __DIR__ . '/../helpers/matching.php';
 * (constants.php is loaded by session.php; do not include it here.)
 *
 * Expected item rows (the same columns the items table uses):
 *   item_id, user_id, item_type, item_name, description, category_id, location_id,
 *   event_date, and optionally location_name for a joined location label.
 *
 * Score = 0 to 100, made of five parts:
 *   name         35  similar words in item_name (tolerates plural forms and small typos)
 *   category     20  same category_id
 *   description  20  similar words in description
 *   location     15  same location_id
 *   date         10  the Found date is on or shortly after the Lost date
 *
 * Quick use:
 *   $score = matching_score($lostRow, $foundRow);                    // int 0..100
 *   $info  = matching_score_breakdown($lostRow, $foundRow);          // score, label, parts, reasons
 *   $top   = matching_rank($lostRow, $foundRows, MATCHING_MIN_SCORE, 5);
 */

const MATCHING_WEIGHT_NAME = 35;
const MATCHING_WEIGHT_CATEGORY = 20;
const MATCHING_WEIGHT_DESCRIPTION = 20;
const MATCHING_WEIGHT_LOCATION = 15;
const MATCHING_WEIGHT_DATE = 10;

/** Pairs below this score are not suggested to the admin. */
const MATCHING_MIN_SCORE = 40;
/** Pairs at or above this score are labelled "Strong". */
const MATCHING_STRONG_SCORE = 70;

/**
 * Words that carry no meaning for matching.
 *
 * @return array<string, true>
 */
function matching_stopwords(): array
{
    static $stopwords = null;

    if ($stopwords === null) {
        $list = [
            'a', 'an', 'and', 'or', 'the', 'of', 'to', 'in', 'on', 'at', 'by', 'for', 'with',
            'from', 'near', 'is', 'it', 'its', 'my', 'me', 'i', 'was', 'were', 'has', 'have',
            'had', 'this', 'that', 'there', 'some', 'any', 'item', 'lost', 'found', 'left',
            'last', 'please', 'ang', 'ng', 'sa', 'na', 'ko',
        ];
        $stopwords = array_fill_keys($list, true);
    }

    return $stopwords;
}

/**
 * Clean text into a list of unique keywords: lowercase, no punctuation,
 * no stopwords, and plural "s" removed ("wallets" becomes "wallet").
 *
 * @return list<string>
 */
function matching_tokens(?string $text): array
{
    $text = mb_strtolower(trim((string) $text), 'UTF-8');

    if ($text === '') {
        return [];
    }

    $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? '';
    $stopwords = matching_stopwords();
    $tokens = [];

    foreach (explode(' ', $text) as $word) {
        if ($word === '' || mb_strlen($word, 'UTF-8') < 2 || isset($stopwords[$word])) {
            continue;
        }

        if (mb_strlen($word, 'UTF-8') > 3 && str_ends_with($word, 's') && !str_ends_with($word, 'ss')) {
            $word = mb_substr($word, 0, -1, 'UTF-8');
        }

        $tokens[$word] = true;
    }

    return array_map('strval', array_keys($tokens));
}

/**
 * Two keywords count as the same when they are identical, differ by one letter
 * (a typo, only for words of 5+ letters), or one starts with the other (4+ letters).
 */
function matching_words_alike(string $a, string $b): bool
{
    if ($a === $b) {
        return true;
    }

    $lenA = strlen($a);
    $lenB = strlen($b);

    if ($lenA >= 4 && $lenB >= 4 && (str_starts_with($a, $b) || str_starts_with($b, $a))) {
        return true;
    }

    // levenshtein() works on bytes, so it is only used for plain ASCII words.
    if ($lenA >= 5 && $lenB >= 5 && abs($lenA - $lenB) <= 1
        && !preg_match('/[^\x20-\x7E]/', $a . $b)) {
        return levenshtein($a, $b) <= 1;
    }

    return false;
}

/**
 * How alike two keyword lists are, from 0.0 to 1.0.
 * Each word can be paired with at most one word on the other side.
 *
 * @param list<string> $a
 * @param list<string> $b
 */
function matching_token_similarity(array $a, array $b): float
{
    if ($a === [] || $b === []) {
        return 0.0;
    }

    $countA = count($a);
    $countB = count($b);
    $remaining = array_values($b);
    $matched = 0;

    // Exact words first so a close-but-wrong word does not steal an exact partner.
    foreach ($a as $i => $word) {
        $pos = array_search($word, $remaining, true);

        if ($pos !== false) {
            $matched++;
            unset($remaining[$pos], $a[$i]);
        }
    }

    foreach ($a as $word) {
        foreach ($remaining as $pos => $candidate) {
            if (matching_words_alike($word, $candidate)) {
                $matched++;
                unset($remaining[$pos]);
                break;
            }
        }
    }

    $overlap = $matched / min($countA, $countB);
    $jaccard = $matched / ($countA + $countB - $matched);

    return min(1.0, ($overlap + $jaccard) / 2);
}

/**
 * Date closeness from 0.0 to 1.0. The Found report should be on or after the Lost date.
 * Missing or invalid dates give a neutral 0.5 so they neither help nor sink a match.
 */
function matching_date_similarity(?string $lostDate, ?string $foundDate): float
{
    $lostTs = $lostDate !== null && trim($lostDate) !== '' ? strtotime($lostDate) : false;
    $foundTs = $foundDate !== null && trim($foundDate) !== '' ? strtotime($foundDate) : false;

    if ($lostTs === false || $foundTs === false) {
        return 0.5;
    }

    $lostDay = (int) floor($lostTs / 86400);
    $foundDay = (int) floor($foundTs / 86400);
    $days = $foundDay - $lostDay;

    if ($days < -1) {
        return 0.0; // found before it was lost
    }

    if ($days <= 3) {
        return 1.0;
    }

    if ($days <= 7) {
        return 0.8;
    }

    if ($days <= 14) {
        return 0.6;
    }

    if ($days <= 30) {
        return 0.3;
    }

    return 0.1;
}

/**
 * Full comparison of one Lost row and one Found row.
 *
 * @param array<string, mixed> $lost
 * @param array<string, mixed> $found
 * @return array{
 *   score: int,
 *   label: string,
 *   eligible: bool,
 *   parts: array{name: int, category: int, description: int, location: int, date: int},
 *   reasons: list<string>
 * }
 */
function matching_score_breakdown(array $lost, array $found): array
{
    $parts = ['name' => 0, 'category' => 0, 'description' => 0, 'location' => 0, 'date' => 0];
    $reasons = [];

    $ineligible = static function (string $reason) use ($parts): array {
        return [
            'score' => 0,
            'label' => 'No match',
            'eligible' => false,
            'parts' => $parts,
            'reasons' => [$reason],
        ];
    };

    if (($lost['item_type'] ?? null) !== ITEM_TYPE_LOST || ($found['item_type'] ?? null) !== ITEM_TYPE_FOUND) {
        return $ineligible('A match needs one Lost report and one Found report.');
    }

    $lostId = (int) ($lost['item_id'] ?? 0);
    $foundId = (int) ($found['item_id'] ?? 0);

    if ($lostId > 0 && $lostId === $foundId) {
        return $ineligible('An item cannot match itself.');
    }

    $lostOwner = (int) ($lost['user_id'] ?? 0);

    if ($lostOwner > 0 && $lostOwner === (int) ($found['user_id'] ?? -1)) {
        return $ineligible('Both reports were made by the same person.');
    }

    // Name
    $nameFraction = matching_token_similarity(
        matching_tokens($lost['item_name'] ?? null),
        matching_tokens($found['item_name'] ?? null)
    );
    $parts['name'] = (int) round($nameFraction * MATCHING_WEIGHT_NAME);

    if ($nameFraction >= 0.5) {
        $reasons[] = 'Similar item names.';
    }

    // Category
    $lostCategory = (int) ($lost['category_id'] ?? 0);

    if ($lostCategory > 0 && $lostCategory === (int) ($found['category_id'] ?? 0)) {
        $parts['category'] = MATCHING_WEIGHT_CATEGORY;
        $reasons[] = 'Same category.';
    }

    // Description
    $descFraction = matching_token_similarity(
        matching_tokens($lost['description'] ?? null),
        matching_tokens($found['description'] ?? null)
    );
    $parts['description'] = (int) round($descFraction * MATCHING_WEIGHT_DESCRIPTION);

    if ($descFraction >= 0.3) {
        $reasons[] = 'Descriptions share details.';
    }

    // Location: same id, otherwise compare joined location names when both exist.
    $lostLocation = (int) ($lost['location_id'] ?? 0);
    $locationFraction = 0.0;

    if ($lostLocation > 0 && $lostLocation === (int) ($found['location_id'] ?? 0)) {
        $locationFraction = 1.0;
    } elseif (trim((string) ($lost['location_name'] ?? '')) !== '' && trim((string) ($found['location_name'] ?? '')) !== '') {
        $locationFraction = matching_token_similarity(
            matching_tokens($lost['location_name']),
            matching_tokens($found['location_name'])
        );
    }

    $parts['location'] = (int) round($locationFraction * MATCHING_WEIGHT_LOCATION);

    if ($locationFraction >= 0.5) {
        $reasons[] = 'Same or similar location.';
    }

    // Date
    $dateFraction = matching_date_similarity(
        isset($lost['event_date']) ? (string) $lost['event_date'] : null,
        isset($found['event_date']) ? (string) $found['event_date'] : null
    );
    $parts['date'] = (int) round($dateFraction * MATCHING_WEIGHT_DATE);

    if ($dateFraction >= 0.8) {
        $reasons[] = 'Dates are close.';
    }

    $score = max(0, min(100, array_sum($parts)));

    return [
        'score' => $score,
        'label' => matching_label($score),
        'eligible' => true,
        'parts' => $parts,
        'reasons' => $reasons,
    ];
}

/**
 * Just the number, 0 to 100.
 *
 * @param array<string, mixed> $lost
 * @param array<string, mixed> $found
 */
function matching_score(array $lost, array $found): int
{
    return matching_score_breakdown($lost, $found)['score'];
}

/**
 * Text label for a score, for chips and tables.
 */
function matching_label(int $score): string
{
    if ($score >= MATCHING_STRONG_SCORE) {
        return 'Strong';
    }

    if ($score >= MATCHING_MIN_SCORE) {
        return 'Possible';
    }

    return 'Weak';
}

/**
 * Rank candidates against one item. $source may be a Lost or a Found row;
 * candidates of the same type are skipped. Highest score first, ties by lowest item_id.
 *
 * @param array<string, mixed> $source
 * @param list<array<string, mixed>> $candidates
 * @return list<array{item: array<string, mixed>, score: int, label: string, parts: array<string, int>, reasons: list<string>}>
 */
function matching_rank(array $source, array $candidates, int $minScore = MATCHING_MIN_SCORE, int $limit = 10): array
{
    $sourceIsLost = ($source['item_type'] ?? null) === ITEM_TYPE_LOST;
    $ranked = [];

    foreach ($candidates as $candidate) {
        $result = $sourceIsLost
            ? matching_score_breakdown($source, $candidate)
            : matching_score_breakdown($candidate, $source);

        if (!$result['eligible'] || $result['score'] < $minScore) {
            continue;
        }

        $ranked[] = [
            'item' => $candidate,
            'score' => $result['score'],
            'label' => $result['label'],
            'parts' => $result['parts'],
            'reasons' => $result['reasons'],
        ];
    }

    usort($ranked, static function (array $x, array $y): int {
        return [$y['score'], (int) ($x['item']['item_id'] ?? 0)]
            <=> [$x['score'], (int) ($y['item']['item_id'] ?? 0)];
    });

    return $limit > 0 ? array_slice($ranked, 0, $limit) : $ranked;
}