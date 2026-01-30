<?php

use FuzzyFox\FluentValue;

if (! function_exists('fluent')) {
    /**
     * Create a new FluentValue instance
     */
    /** @return FluentValue<array-key, mixed> */
    function fluent(mixed $data): FluentValue
    {
        return FluentValue::make($data);
    }
}

if (! function_exists('value')) {
    /**
     * Return the default value of the given value.
     * Resolves closures by passing them the optional context.
     */
    function value(mixed $value, mixed $context = null): mixed
    {
        return $value instanceof Closure ? $value($context) : $value;
    }
}
