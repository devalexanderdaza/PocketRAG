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

const VECTOR_INT8_MAGIC = 'I8Q1';

/**
 * Quantize a float vector to signed int8 using a max-abs scale.
 *
 * @param list<float> $vector Source floats.
 * @return array{scale:float,values:list<int>} Scale and int8 values in -127..127.
 */
function vector_quantize_int8(array $vector): array
{
    $maxAbs = 0.0;
    foreach ($vector as $val) {
        $abs = abs((float) $val);
        if ($abs > $maxAbs) {
            $maxAbs = $abs;
        }
    }
    $scale = $maxAbs > 0.0 ? $maxAbs : 1.0;
    $values = [];
    foreach ($vector as $val) {
        $q = (int) round(((float) $val / $scale) * 127.0);
        if ($q > 127) {
            $q = 127;
        } elseif ($q < -127) {
            $q = -127;
        }
        $values[] = $q;
    }
    return ['scale' => $scale, 'values' => $values];
}

/**
 * Restore floats from an int8 quantization.
 *
 * @param list<int> $values Int8 codes.
 * @param float $scale Max-abs scale used at encode time.
 * @return list<float>
 */
function vector_dequantize_int8(array $values, float $scale): array
{
    $out = [];
    foreach ($values as $q) {
        $out[] = ((int) $q / 127.0) * $scale;
    }
    return $out;
}

/**
 * Pack a vector for SQLite storage (f32 default or int8 with magic prefix).
 *
 * @param list<float> $vector Source floats.
 * @param string $precision `f32` or `int8`.
 * @return string Binary blob.
 */
function vector_pack_stored(array $vector, string $precision = 'f32'): string
{
    if ($precision === 'int8') {
        $q = vector_quantize_int8($vector);
        return VECTOR_INT8_MAGIC . pack('f', $q['scale']) . pack('c*', ...$q['values']);
    }
    return vector_pack($vector);
}

/**
 * Unpack a stored embedding blob (int8 magic or raw f32).
 *
 * @param string $blob Stored BLOB.
 * @return list<float>
 */
function vector_unpack_stored(string $blob): array
{
    if (strncmp($blob, VECTOR_INT8_MAGIC, 4) === 0 && strlen($blob) > 8) {
        $scaleParts = unpack('f', substr($blob, 4, 4));
        $scale = is_array($scaleParts) ? (float) ($scaleParts[1] ?? 1.0) : 1.0;
        $codes = unpack('c*', substr($blob, 8));
        $values = $codes !== false ? array_values($codes) : [];
        return vector_dequantize_int8($values, $scale);
    }
    return vector_unpack($blob);
}

