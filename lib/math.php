<?php
/**
 * Native vector operations.
 * 
 * Provides binary packing/unpacking and Cosine Similarity computations.
 * 
 * @package PocketRAG
 */

declare(strict_types=1);

/**
 * Pack a float array to a C-binary string (4 bytes per float).
 *
 * @param list<float> $vector The float array to pack.
 * @return string The binary blob.
 */
function vector_pack(array $vector): string
{
    return pack('f*', ...$vector);
}

/**
 * Unpack a C-binary BLOB to an array of floats.
 *
 * @param string $blob The binary blob to unpack.
 * @return list<float> The array of floats.
 */
function vector_unpack(string $blob): array
{
    $unpacked = unpack('f*', $blob);
    return $unpacked !== false ? array_values($unpacked) : [];
}

/**
 * Compute Euclidean magnitude: ||V|| = sqrt(sum(v_i^2)).
 *
 * @param list<float> $vector The input vector.
 * @return float The magnitude of the vector.
 */
function vector_magnitude(array $vector): float
{
    $sum = 0.0;
    foreach ($vector as $val) {
        $sum += $val * $val;
    }
    return sqrt($sum);
}

/**
 * Compute Cosine Similarity with precomputed magnitudes.
 *
 * Formula: Cosine(A, B) = (A . B) / (||A|| * ||B||)
 *
 * Returns 0.0 and logs an error if vectors have mismatched dimensions, preventing
 * silent incorrect scores when the DB contains vectors from a different model/dimension
 * setting than the current query embedding.
 *
 * @param list<float> $a First vector.
 * @param float $magA Precomputed magnitude of the first vector.
 * @param list<float> $b Second vector.
 * @param float $magB Precomputed magnitude of the second vector.
 * @return float The cosine similarity.
 */
function cosine_similarity_precomputed(
    array $a,
    float $magA,
    array $b,
    float $magB
): float {
    if ($magA <= 0.0 || $magB <= 0.0) {
        return 0.0;
    }

    $countA = count($a);
    $countB = count($b);

    if ($countA !== $countB) {
        error_log(sprintf(
            'cosine_similarity_precomputed: dimension mismatch (%d vs %d). '
            . 'Delete data/knowledge.sqlite* and re-run php scripts/sync.php.',
            $countA,
            $countB
        ));
        return 0.0;
    }

    $dotProduct = 0.0;
    for ($i = 0; $i < $countA; $i++) {
        $dotProduct += $a[$i] * $b[$i];
    }

    return $dotProduct / ($magA * $magB);
}
