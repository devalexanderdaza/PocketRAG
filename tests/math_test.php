<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/math.php';

describe('Math — vector_pack / vector_unpack');

it('round-trips a float vector', function () {
    $original  = [1.0, 0.5, -0.25, 3.14];
    $blob      = vector_pack($original);
    $recovered = vector_unpack($blob);
    expect(count($recovered))->toBe(count($original));
    foreach ($original as $i => $val) {
        // float32 has ~7 decimal digits of precision
        if (abs($recovered[$i] - $val) >= 0.0001) {
            throw new AssertionError("Index {$i}: expected ~{$val}, got {$recovered[$i]}");
        }
    }
});

it('unpacks empty blob to empty array', function () {
    expect(vector_unpack(''))->toHaveCount(0);
});

describe('Math — vector_magnitude');

it('computes magnitude of unit vector', function () {
    $mag = vector_magnitude([1.0, 0.0, 0.0]);
    expect(abs($mag - 1.0) < 0.0001)->toBeTrue();
});

it('computes magnitude of known vector', function () {
    // sqrt(3^2 + 4^2) = 5
    $mag = vector_magnitude([3.0, 4.0]);
    expect(abs($mag - 5.0) < 0.0001)->toBeTrue();
});

it('returns 0 for zero vector', function () {
    expect(vector_magnitude([0.0, 0.0, 0.0]))->toBe(0.0);
});

describe('Math — cosine_similarity_precomputed');

it('returns 1.0 for identical vectors', function () {
    $v   = [1.0, 2.0, 3.0];
    $mag = vector_magnitude($v);
    $sim = cosine_similarity_precomputed($v, $mag, $v, $mag);
    expect(abs($sim - 1.0) < 0.0001)->toBeTrue();
});

it('returns 0.0 for orthogonal vectors', function () {
    $a    = [1.0, 0.0];
    $b    = [0.0, 1.0];
    $magA = vector_magnitude($a);
    $magB = vector_magnitude($b);
    $sim  = cosine_similarity_precomputed($a, $magA, $b, $magB);
    expect(abs($sim) < 0.0001)->toBeTrue();
});

it('returns 0.0 for zero-magnitude vector', function () {
    $a    = [0.0, 0.0];
    $b    = [1.0, 1.0];
    $magA = vector_magnitude($a);
    $magB = vector_magnitude($b);
    expect(cosine_similarity_precomputed($a, $magA, $b, $magB))->toBe(0.0);
});

it('logs error and returns 0.0 on dimension mismatch', function () {
    $a    = [1.0, 2.0];
    $b    = [1.0, 2.0, 3.0];
    $magA = vector_magnitude($a);
    $magB = vector_magnitude($b);
    // Should not throw; should return 0.0
    $result = cosine_similarity_precomputed($a, $magA, $b, $magB);
    expect($result)->toBe(0.0);
});

describe('Math — int8 quantization');

it('round-trips a vector with bounded error', function () {
    $original = [0.5, -0.25, 0.125, 0.0];
    $q = vector_quantize_int8($original);
    $restored = vector_dequantize_int8($q['values'], $q['scale']);
    foreach ($original as $i => $val) {
        if (abs($restored[$i] - $val) > 0.01) {
            throw new AssertionError("Index {$i}: expected ~{$val}, got {$restored[$i]}");
        }
    }
});

it('packs int8 blobs with magic and unpacks via vector_unpack_stored', function () {
    $original = [0.9, -0.1, 0.3];
    $blob = vector_pack_stored($original, 'int8');
    expect(strncmp($blob, VECTOR_INT8_MAGIC, 4))->toBe(0);
    $restored = vector_unpack_stored($blob);
    expect(count($restored))->toBe(3);
});

it('keeps the same top-1 cosine neighbor after int8 round-trip', function () {
    $docs = [
        'a' => [1.0, 0.0, 0.0],
        'b' => [0.0, 1.0, 0.0],
        'c' => [0.0, 0.0, 1.0],
    ];
    $query = [0.95, 0.05, 0.0];
    $qMag = vector_magnitude($query);
    $bestF32 = '';
    $bestF32Score = -2.0;
    $bestI8 = '';
    $bestI8Score = -2.0;
    foreach ($docs as $id => $vec) {
        $f32 = cosine_similarity_precomputed($query, $qMag, $vec, vector_magnitude($vec));
        if ($f32 > $bestF32Score) {
            $bestF32Score = $f32;
            $bestF32 = $id;
        }
        $restored = vector_unpack_stored(vector_pack_stored($vec, 'int8'));
        $i8 = cosine_similarity_precomputed($query, $qMag, $restored, vector_magnitude($vec));
        if ($i8 > $bestI8Score) {
            $bestI8Score = $i8;
            $bestI8 = $id;
        }
    }
    expect($bestF32)->toBe('a');
    expect($bestI8)->toBe('a');
});
