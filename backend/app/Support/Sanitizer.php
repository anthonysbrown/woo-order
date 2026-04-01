<?php

namespace App\Support;

class Sanitizer
{
    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public static function trimStrings(array $input): array
    {
        $output = $input;

        foreach ($output as $key => $value) {
            if (is_string($value)) {
                $output[$key] = trim($value);
            }
        }

        return $output;
    }

    public static function text(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/\s+/u', ' ', trim(strip_tags($value)));

        return $normalized === '' ? null : $normalized;
    }

    public static function multiline(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $sanitized = strip_tags($value);
        $sanitized = preg_replace("/\r\n|\r/u", "\n", $sanitized);
        $sanitized = preg_replace('/[ \t]+/u', ' ', $sanitized);
        $sanitized = trim($sanitized);

        return $sanitized === '' ? null : $sanitized;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, string> $map
     * @return array<string, mixed>
     */
    public static function apply(array $input, array $map): array
    {
        foreach ($map as $field => $type) {
            if (! array_key_exists($field, $input) || ! is_string($input[$field])) {
                continue;
            }

            if ($type === 'text') {
                $input[$field] = self::text($input[$field]);
                continue;
            }

            if ($type === 'multiline') {
                $input[$field] = self::multiline($input[$field]);
            }
        }

        return $input;
    }
}
