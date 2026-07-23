<?php

namespace App\Util;

final class TimeInputParser
{
    public static function required(mixed $value, string $field): \DateTime
    {
        return self::parse($value, $field, false);
    }

    public static function optional(mixed $value, string $field): ?\DateTime
    {
        return self::parse($value, $field, true);
    }

    private static function parse(mixed $value, string $field, bool $optional): ?\DateTime
    {
        if ($value === null) {
            if ($optional) {
                return null;
            }

            throw self::invalid($field);
        }

        if (!is_string($value)) {
            throw self::invalid($field);
        }

        $value = trim($value);

        if ($optional && ($value === '' || $value === '--:--')) {
            return null;
        }

        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) {
            throw self::invalid($field);
        }

        $time = \DateTime::createFromFormat('!H:i', $value);
        if ($time === false) {
            throw self::invalid($field);
        }

        return $time;
    }

    private static function invalid(string $field): \InvalidArgumentException
    {
        return new \InvalidArgumentException(sprintf(
            'Horário inválido para "%s". Use o formato HH:mm.',
            $field
        ));
    }
}
