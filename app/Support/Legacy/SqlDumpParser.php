<?php

declare(strict_types=1);

namespace App\Support\Legacy;

use RuntimeException;

/**
 * Minimal parser for `INSERT INTO ... VALUES (...)` statements taken from a
 * mysqldump file. It exists so the legacy import can be developed and tested on
 * a fixture instead of a live database (task T2.7) — no MySQL server is required
 * to prove the mapping rules.
 */
final class SqlDumpParser
{
    /**
     * @return array<string, list<array<string, string|null>>> table => rows
     */
    public function parseFile(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('Nie znaleziono pliku ze zrzutem: '.$path);
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException('Nie można odczytać pliku: '.$path);
        }

        return $this->parse($contents);
    }

    /**
     * @return array<string, list<array<string, string|null>>>
     */
    public function parse(string $sql): array
    {
        $tables = [];
        $pattern = '/INSERT\s+(?:IGNORE\s+)?INTO\s+`?([A-Za-z0-9_]+)`?\s*\(([^)]*)\)\s*VALUES\s*/i';
        $offset = 0;

        while (preg_match($pattern, $sql, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $table = $match[1][0];
            $columnsText = $match[2][0];
            $bodyStart = $match[0][1] + strlen($match[0][0]);

            $body = $this->readStatementBody($sql, $bodyStart);
            $offset = $bodyStart + strlen($body);

            $columns = array_map(
                static fn (string $column): string => trim(trim($column), '`'),
                explode(',', $columnsText),
            );

            foreach ($this->parseValueTuples($body) as $tuple) {
                $row = [];

                foreach ($columns as $index => $column) {
                    $row[$column] = $tuple[$index] ?? null;
                }

                $tables[$table][] = $row;
            }
        }

        return $tables;
    }

    /** Reads from the start of the VALUES list up to the terminating semicolon. */
    private function readStatementBody(string $sql, int $start): string
    {
        $length = strlen($sql);
        $inQuotes = false;
        $quote = '';

        for ($i = $start; $i < $length; $i++) {
            $char = $sql[$i];

            if ($inQuotes) {
                if ($char === '\\') {
                    $i++;

                    continue;
                }

                if ($char === $quote) {
                    if (($sql[$i + 1] ?? '') === $quote) {
                        $i++;

                        continue;
                    }

                    $inQuotes = false;
                }

                continue;
            }

            if ($char === "'" || $char === '"') {
                $inQuotes = true;
                $quote = $char;

                continue;
            }

            if ($char === ';') {
                return substr($sql, $start, $i - $start);
            }
        }

        return substr($sql, $start);
    }

    /**
     * @return list<list<string|null>>
     */
    private function parseValueTuples(string $values): array
    {
        $tuples = [];
        $current = [];
        $buffer = '';
        $inQuotes = false;
        $quote = '';
        $depth = 0;
        $length = strlen($values);

        for ($i = 0; $i < $length; $i++) {
            $char = $values[$i];

            if ($inQuotes) {
                if ($char === '\\') {
                    $buffer .= $values[$i + 1] ?? '';
                    $i++;

                    continue;
                }

                if ($char === $quote) {
                    if (($values[$i + 1] ?? '') === $quote) {
                        $buffer .= $quote;
                        $i++;

                        continue;
                    }

                    $inQuotes = false;

                    continue;
                }

                $buffer .= $char;

                continue;
            }

            if ($char === "'" || $char === '"') {
                $inQuotes = true;
                $quote = $char;

                continue;
            }

            if ($char === '(') {
                $depth++;
                $current = [];
                $buffer = '';

                continue;
            }

            if ($char === ')') {
                $depth--;

                if ($depth === 0) {
                    $current[] = $this->normalizeToken($buffer);
                    $tuples[] = $current;
                    $current = [];
                    $buffer = '';

                    continue;
                }

                $buffer .= $char;

                continue;
            }

            if ($char === ',' && $depth === 1) {
                $current[] = $this->normalizeToken($buffer);
                $buffer = '';

                continue;
            }

            if ($depth === 1) {
                $buffer .= $char;
            }
        }

        return $tuples;
    }

    private function normalizeToken(string $token): ?string
    {
        $token = trim($token);

        if (strcasecmp($token, 'NULL') === 0) {
            return null;
        }

        return $token;
    }
}
