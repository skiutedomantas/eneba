<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class Environment
{
    public static function loadFile(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        if (!is_readable($path)) {
            throw new RuntimeException('The environment file is not readable.');
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new RuntimeException('The environment file could not be read.');
        }

        $values = [];
        foreach ($lines as $index => $line) {
            if ($index === 0) {
                $line = preg_replace('/^\xEF\xBB\xBF/', '', $line) ?? $line;
            }

            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, strlen('export ')));
            }

            $separator = strpos($line, '=');
            if ($separator === false) {
                throw new RuntimeException(sprintf(
                    'Invalid environment assignment on line %d.',
                    $index + 1,
                ));
            }

            $name = trim(substr($line, 0, $separator));
            $value = trim(substr($line, $separator + 1));

            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $name)) {
                throw new RuntimeException(sprintf(
                    'Invalid environment variable name on line %d.',
                    $index + 1,
                ));
            }

            $startsWithQuote = str_starts_with($value, '"') || str_starts_with($value, "'");
            if ($startsWithQuote) {
                if (
                    strlen($value) < 2
                    || $value[0] !== $value[strlen($value) - 1]
                ) {
                    throw new RuntimeException(sprintf(
                        'Unmatched quote in environment value on line %d.',
                        $index + 1,
                    ));
                }

                $value = substr($value, 1, -1);
            }

            if (array_key_exists($name, $values)) {
                throw new RuntimeException(sprintf(
                    'Duplicate environment variable on line %d.',
                    $index + 1,
                ));
            }

            $values[$name] = $value;
        }

        foreach ($values as $name => $value) {
            if (getenv($name) !== false) {
                continue;
            }

            if (!putenv($name . '=' . $value)) {
                throw new RuntimeException('An environment variable could not be set.');
            }

            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}
