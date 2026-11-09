<?php

use App\Models\User;

if (!function_exists('is_digit')) {
    /**
     * Deal with normal (and irritating) PHP behavior to determine if
     * a value is a non-float positive integer.
     */
    function is_digit(mixed $value): bool
    {
        return !is_bool($value) && ctype_digit(strval($value));
    }
}

if (!function_exists('is_ip')) {
    function is_ip(?string $address): bool
    {
        return $address !== null && filter_var($address, FILTER_VALIDATE_IP) !== false;
    }
}

if (!function_exists('is_ipv4')) {
    function is_ipv4(?string $address): bool
    {
        return $address !== null && filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    }
}

if (!function_exists('is_ipv6')) {
    function is_ipv6(?string $address): bool
    {
        return $address !== null && filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
    }
}

if (!function_exists('convert_bytes_to_readable')) {
    function convert_bytes_to_readable(int $bytes, int $decimals = 2, ?int $base = null): string
    {
        $binarySuffixes = ['Bytes', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'];
        $decimalSuffixes = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        $suffix = config('panel.use_binary_prefix') ? $binarySuffixes : $decimalSuffixes;

        if ($bytes <= 0) {
            return '0 ' . $suffix[0];
        }

        $conversionUnit = config('panel.use_binary_prefix') ? 1024 : 1000;

        $fromBase = log($bytes) / log($conversionUnit);
        $base ??= floor($fromBase);

        if ($base > count($suffix) - 1) {
            $base = count($suffix) - 1;
        }

        return format_number(pow($conversionUnit, $fromBase - $base), precision: $decimals) . ' ' . $suffix[$base];
    }
}

if (!function_exists('join_paths')) {
    function join_paths(string $base, string ...$paths): string
    {
        $base = rtrim($base, '/');

        $paths = array_map(fn (string $path) => trim($path, '/'), $paths);
        $paths = array_filter($paths, fn (string $path) => strlen($path) > 0);

        if (empty($base)) {
            return implode('/', $paths);
        }

        return $base . '/' . implode('/', $paths);
    }
}

if (!function_exists('resolve_path')) {
    function resolve_path(string $path): string
    {
        $parts = array_filter(explode('/', $path), fn (string $p) => strlen($p) > 0);

        $absolutes = [];
        foreach ($parts as $part) {
            if ($part == '.') {
                continue;
            }

            if ($part == '..') {
                array_pop($absolutes);
            } else {
                $absolutes[] = $part;
            }
        }

        return implode('/', $absolutes);
    }
}

if (!function_exists('plugin_path')) {
    function plugin_path(string $plugin, string ...$paths): string
    {
        return join_paths(base_path('plugins'), $plugin, implode('/', $paths));
    }
}

if (!function_exists('get_ip_from_hostname')) {
    function get_ip_from_hostname(string $hostname): string|bool
    {
        $validARecords = @dns_get_record($hostname, DNS_A);
        if ($validARecords) {
            return collect($validARecords)->first()['ip'];
        }

        $validAAAARecords = @dns_get_record($hostname, DNS_AAAA);
        if ($validAAAARecords) {
            return collect($validAAAARecords)->first()['ipv6'];
        }

        // Fall back to the system resolver, which honors /etc/hosts (e.g. Docker extra_hosts)
        $systemRecords = @gethostbynamel($hostname);
        if ($systemRecords) {
            return $systemRecords[0];
        }

        return false;
    }
}

if (!function_exists('format_number')) {
    function format_number(int|float $number, ?int $precision = null, ?int $maxPrecision = null): false|string
    {
        try {
            return Number::format($number, $precision, $maxPrecision, user()->language ?? 'en');
        } catch (Throwable) {
            // User language is invalid, so default to english
            return Number::format($number, $precision, $maxPrecision, 'en');
        }
    }
}

if (!function_exists('encode_path')) {
    function encode_path(string $path): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $path)));
    }
}

if (!function_exists('convert_to_utf8')) {
    /**
     * Convert a string to UTF-8 from an unknown encoding
     */
    function convert_to_utf8(string $contents): string
    {
        // Valid UTF-8 passes through unchanged
        if (mb_check_encoding($contents, 'UTF-8')) {
            return $contents;
        }

        // Only detect UTF-16 by BOM instead of mb_check_encoding('UTF-16') which can cause false positives
        if (str_starts_with($contents, "\xFF\xFE") || str_starts_with($contents, "\xFE\xFF")) {
            return mb_convert_encoding($contents, 'UTF-8', 'UTF-16');
        }

        // ISO-8859-1 serves as a universal fallback since any byte sequence is valid in it
        return mb_convert_encoding($contents, 'UTF-8', 'ISO-8859-1');
    }
}

if (!function_exists('user')) {
    function user(): ?User
    {
        return auth(config('auth.defaults.guard', 'web'))->user();
    }
}
