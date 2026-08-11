<?php
/**
 * Native vector operations: binary packing/unpacking and Cosine Similarity.
 */

declare(strict_types=1);

/**
 * Pack float array to C-binary string (4 bytes per float).
 *
 * @param list<float> $vector
 */
function vector_pack(array $vector): string
{
    return pack('f*', ...$vector);
}

/**
 * Unpack C-binary BLOB to array of floats.
 *
 * @return list<float>
 */
function vector_unpack(string $blob): array
{
    $unpacked = unpack('f*', $blob);
    return $unpacked !== false ? array_values($unpacked) : [];
}

/**
 * Compute Euclidean magnitude ||V|| = sqrt(sum(v_i^2)).
 *
 * @param list<float> $vector
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
 * Cosine Similarity with precomputed magnitudes.
 *
 * Cosine(A, B) = (A . B) / (||A|| * ||B||)
 *
 * @param list<float> $a
 * @param list<float> $b
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

    $dotProduct = 0.0;
    $count = count($a);

    for ($i = 0; $i < $count; $i++) {
        $dotProduct += $a[$i] * $b[$i];
    }

    return $dotProduct / ($magA * $magB);
}
