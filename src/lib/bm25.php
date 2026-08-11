<?php
/**
 * Okapi BM25 — dependency-free port of rank_bm25.
 * 
 * Provides lexical search capabilities.
 * 
 * @package PocketRAG
 */

declare(strict_types=1);

const BM25_K1 = 1.5;
const BM25_B  = 0.75;

const BM25_STOPWORDS = [
    'a' => true, 'about' => true, 'all' => true, 'am' => true, 'an' => true,
    'and' => true, 'any' => true, 'are' => true, 'as' => true, 'at' => true,
    'be' => true, 'been' => true, 'but' => true, 'by' => true, 'can' => true,
    'de' => true, 'del' => true, 'el' => true, 'en' => true, 'la' => true,
    'los' => true, 'las' => true, 'por' => true, 'para' => true, 'que' => true,
    'con' => true, 'un' => true, 'una' => true, 'es' => true, 'y' => true,
];

/**
 * Fold and normalize a string (lowercase and remove accents).
 *
 * @param string $text The text to normalize.
 * @return string The normalized text.
 */
function bm25_fold(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    static $map = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'ñ' => 'n',
    ];
    return strtr($text, $map);
}

/**
 * Tokenize a string into an array of words, removing stopwords.
 *
 * @param string $text The text to tokenize.
 * @return list<string> An array of valid tokens.
 */
function bm25_tokenize(string $text): array
{
    preg_match_all('/[a-z0-9]+/', bm25_fold($text), $matches);
    return array_values(array_filter(
        $matches[0],
        static fn(string $token): bool => !isset(BM25_STOPWORDS[$token])
    ));
}

/**
 * Build the BM25 index from a set of document entries.
 *
 * @param list<array{id:string,text:string,group?:string}> $entries The documents to index.
 * @return array{docs:list<array{id:string,len:int,freqs:array<string,int>}>,df:array<string,int>,avgdl:float,n:int} The computed index data.
 */
function bm25_index(array $entries): array
{
    $docs       = [];
    $total      = 0;
    $groupTerms = [];
    $entryDf    = [];

    foreach ($entries as $entry) {
        $tokens = bm25_tokenize($entry['text']);
        $freqs  = [];
        foreach ($tokens as $token) {
            $freqs[$token] = ($freqs[$token] ?? 0) + 1;
        }

        foreach (array_keys($freqs) as $term) {
            $entryDf[$term] = ($entryDf[$term] ?? 0) + 1;
        }

        if (isset($entry['group'])) {
            foreach (array_keys($freqs) as $term) {
                $groupTerms[$entry['group']][$term] = true;
            }
        }

        $length = count($tokens);
        $total += $length;
        $docs[] = ['id' => $entry['id'], 'len' => $length, 'freqs' => $freqs];
    }

    if ($groupTerms !== []) {
        $df = [];
        foreach ($groupTerms as $terms) {
            foreach (array_keys($terms) as $term) {
                $df[$term] = ($df[$term] ?? 0) + 1;
            }
        }
        $n = count($groupTerms);
    } else {
        $df = $entryDf;
        $n  = count($docs);
    }

    return [
        'docs'  => $docs,
        'df'    => $df,
        'avgdl' => count($docs) > 0 ? $total / count($docs) : 0.0,
        'n'     => $n,
    ];
}

/**
 * Search the BM25 index for a given query.
 *
 * @param array{docs:list<array{id:string,len:int,freqs:array<string,int>}>,df:array<string,int>,avgdl:float,n:int} $index The BM25 index.
 * @param string $query The search query.
 * @return list<array{id:string,score:float}> The search results sorted by score descending.
 */
function bm25_search(array $index, string $query): array
{
    if ($index['n'] === 0) {
        return [];
    }

    $queryTerms = bm25_tokenize($query);
    if ($queryTerms === []) {
        return [];
    }

    $queryTerms = array_unique($queryTerms);
    $avgdl      = $index['avgdl'] > 0 ? $index['avgdl'] : 1.0;
    $results    = [];

    foreach ($index['docs'] as $doc) {
        $score = 0.0;

        foreach ($queryTerms as $term) {
            $frequency = $doc['freqs'][$term] ?? 0;
            if ($frequency === 0) {
                continue;
            }

            $documentFrequency = $index['df'][$term] ?? 0;
            $idf = log(1.0 + (($index['n'] - $documentFrequency + 0.5) / ($documentFrequency + 0.5)));

            $numerator   = $frequency * (BM25_K1 + 1);
            $denominator = $frequency + BM25_K1 * (1 - BM25_B + BM25_B * ($doc['len'] / $avgdl));

            $score += $idf * ($numerator / $denominator);
        }

        $results[] = ['id' => $doc['id'], 'score' => $score];
    }

    usort($results, static function (array $a, array $b): int {
        return $b['score'] <=> $a['score'];
    });

    return $results;
}
