<?php
/**
 * PocketRAG Pure PHP Test Runner.
 * 
 * Zero-dependency test harness. Run via: php tests/run.php
 * Exit code 0 = all pass, 1 = one or more failures.
 * 
 * @package PocketRAG
 */

declare(strict_types=1);

// ── Harness ──────────────────────────────────────────────────────────────────

$passed  = 0;
$failed  = 0;
$current = '';

function describe(string $suite): void
{
    global $current;
    $current = $suite;
    echo "\n  \033[1;34m{$suite}\033[0m\n";
}

function it(string $label, callable $fn): void
{
    global $passed, $failed, $current;
    try {
        $fn();
        $passed++;
        echo "    \033[0;32m✓\033[0m {$label}\n";
    } catch (Throwable $e) {
        $failed++;
        echo "    \033[0;31m✗\033[0m {$label}\n";
        echo "      → {$e->getMessage()}\n";
    }
}

function expect(mixed $actual): object
{
    return new class ($actual) {
        public function __construct(private mixed $val) {}

        public function toBe(mixed $expected): void
        {
            if ($this->val !== $expected) {
                throw new AssertionError(sprintf(
                    "Expected %s, got %s",
                    var_export($expected, true),
                    var_export($this->val, true)
                ));
            }
        }

        public function toEqual(mixed $expected): void
        {
            if ($this->val != $expected) {
                throw new AssertionError(sprintf(
                    "Expected (loose) %s, got %s",
                    var_export($expected, true),
                    var_export($this->val, true)
                ));
            }
        }

        public function toContain(string $needle): void
        {
            if (!is_string($this->val) || !str_contains($this->val, $needle)) {
                throw new AssertionError(
                    "Expected string to contain \"{$needle}\", got: " . var_export($this->val, true)
                );
            }
        }

        public function toBeTrue(): void  { $this->toBe(true); }
        public function toBeFalse(): void { $this->toBe(false); }
        public function toBeNull(): void  { $this->toBe(null); }

        public function toBeGreaterThan(float|int $n): void
        {
            if (!($this->val > $n)) {
                throw new AssertionError("Expected {$this->val} > {$n}");
            }
        }

        public function toBeLessThan(float|int $n): void
        {
            if (!($this->val < $n)) {
                throw new AssertionError("Expected {$this->val} < {$n}");
            }
        }

        public function toHaveCount(int $n): void
        {
            $count = is_array($this->val) ? count($this->val) : -1;
            if ($count !== $n) {
                throw new AssertionError("Expected count {$n}, got {$count}");
            }
        }
    };
}

// ── Load test files ───────────────────────────────────────────────────────────

$testDir = __DIR__;
$files   = glob($testDir . '/*_test.php') ?: [];
sort($files);

foreach ($files as $file) {
    require $file;
}

// ── Summary ───────────────────────────────────────────────────────────────────

$total = $passed + $failed;
echo "\n";
if ($failed === 0) {
    echo "\033[0;32m  All {$total} tests passed.\033[0m\n\n";
    exit(0);
} else {
    echo "\033[0;31m  {$failed} of {$total} tests failed.\033[0m\n\n";
    exit(1);
}
