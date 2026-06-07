<?php

declare(strict_types=1);

namespace Tests;

abstract class TestCase
{
    private int $assertions = 0;

    final public function assertions(): int
    {
        return $this->assertions;
    }

    final protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertions++;
        if ($expected !== $actual) {
            throw new \RuntimeException($message !== '' ? $message : sprintf(
                'Expected %s, got %s',
                var_export($expected, true),
                var_export($actual, true)
            ));
        }
    }

    final protected function assertStringContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertions++;
        if (!str_contains($haystack, $needle)) {
            throw new \RuntimeException($message !== '' ? $message : sprintf(
                'Expected output to contain "%s"',
                $needle
            ));
        }
    }

    final protected function assertTrue(bool $condition, string $message = 'Expected condition to be true'): void
    {
        $this->assertions++;
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }
}

