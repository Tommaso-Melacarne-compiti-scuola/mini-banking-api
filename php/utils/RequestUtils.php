<?php

class RequestUtils
{
    public static function getIntArg(array $args, string $key, int $default = null): int
    {
        // If default is provided, use it else throw error if key is missing or not numeric
        if (!isset($args[$key])) {
            if ($default !== null) {
                return $default;
            }
            throw new InvalidArgumentException("Missing required argument: $key");
        }

        if (!is_numeric($args[$key])) {
            throw new InvalidArgumentException("Invalid argument type for $key");
        }

        return (int)$args[$key];
    }
}
