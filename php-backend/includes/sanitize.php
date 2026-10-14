<?php

function sanitizeInt($value, int $max = PHP_INT_MAX): int
{
    if (is_array($value) || is_object($value)) {
        return 0;
    }
    $int = filter_var($value, FILTER_VALIDATE_INT);
    if ($int === false || $int < 0 || $int > $max) {
        return 0;
    }
    return $int;
}

function sanitizeString($value, int $maxLength = 255): string
{
    if (is_array($value) || is_object($value)) {
        return '';
    }
    $str = trim((string)$value);
    $str = strip_tags($str);
    if (function_exists('mb_strimwidth')) {
        $str = mb_strimwidth($str, 0, $maxLength, '');
    } else {
        $str = substr($str, 0, $maxLength);
    }
    return $str;
}

function sanitizeOutput($value): string
{
    if (is_array($value) || is_object($value)) {
        return '';
    }
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sanitizeEnum($value, array $allowed, $fallback = null)
{
    return in_array($value, $allowed, true) ? $value : $fallback;
}
