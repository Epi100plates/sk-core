<?php

declare(strict_types=1);

namespace App\Support\Legacy;

/**
 * Result of a legacy import run: counters plus every ambiguity that a human has
 * to look at (plan section 9.2). `--dry-run` produces exactly this object
 * without writing anything.
 */
final class LegacyReport
{
    /** @var array<string, int> */
    private array $counters = [];

    /** @var list<string> */
    private array $warnings = [];

    public function count(string $key, int $by = 1): void
    {
        $this->counters[$key] = ($this->counters[$key] ?? 0) + $by;
    }

    public function warn(string $message): void
    {
        $this->warnings[] = $message;
    }

    /**
     * @return array<string, int>
     */
    public function counters(): array
    {
        return $this->counters;
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    public function toCsv(): string
    {
        $lines = ['typ;liczba'];

        foreach ($this->counters as $key => $value) {
            $lines[] = $key.';'.$value;
        }

        $lines[] = '';
        $lines[] = 'ostrzeżenie';

        foreach ($this->warnings as $warning) {
            $lines[] = str_replace(';', ',', $warning);
        }

        return implode("\n", $lines)."\n";
    }
}
