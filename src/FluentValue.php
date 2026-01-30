<?php

namespace FuzzyFox;

use ArrayAccess;
use ArrayIterator;
use Closure;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use ReflectionProperty;
use stdClass;
use Traversable;

use function value;

/**
 * @template TKey of array-key
 * @template TValue
 *
 * @phpstan-consistent-constructor
 *
 * @implements ArrayAccess<TKey, TValue>
 * @implements IteratorAggregate<TKey, TValue>
 */
final class FluentValue implements ArrayAccess, Countable, IteratorAggregate, JsonSerializable
{
    /** @var array<array-key, mixed>|object */
    private array|object $items;

    /** @var array<string, mixed> */
    private array $pendingOverrides = [];

    /** @var self<TKey, TValue>|null */
    private ?self $overrideRoot = null;

    private ?string $overrideKey = null;

    /** @var array<int, string> */
    private array $overridePath = [];

    /** @param  self<TKey, TValue>|null  $parent */
    public function __construct(mixed $data, private readonly ?self $parent = null)
    {
        $this->items = $this->normalize($data);
    }

    /**
     * Normalize the input data into an array structure
     */
    /** @return array<array-key, mixed>|object */
    private function normalize(mixed $data): array|object
    {
        if ($data instanceof self) {
            return $data->raw();
        }

        if (is_array($data) || is_object($data)) {
            return $data;
        }

        return ['value' => $data];
    }

    /**
     * Resolve a value, handling closures with parent context
     */
    private function resolve(mixed $value): mixed
    {
        return value($value, $this);
    }

    /**
     * @return array{bool, mixed}
     */
    private function getContainerValue(mixed $container, string $key, bool $allowMagic = true): array
    {
        if (is_array($container)) {
            if (array_key_exists($key, $container)) {
                return [true, $container[$key]];
            }

            return [false, null];
        }

        if (! is_object($container)) {
            return [false, null];
        }

        if (property_exists($container, $key)) {
            if ($container instanceof stdClass) {
                return [true, $container->$key];
            }

            if ((new ReflectionProperty($container, $key))->isPublic()) {
                return [true, $container->$key];
            }
        }

        if ($allowMagic && method_exists($container, '__get')) {
            return [true, $container->$key];
        }

        if ($container instanceof ArrayAccess && $container->offsetExists($key)) {
            return [true, $container[$key]];
        }

        return [false, null];
    }

    private function setContainerValue(mixed &$container, string $key, mixed $value): bool
    {
        if (is_array($container)) {
            $container[$key] = $value;

            return true;
        }

        if (! is_object($container)) {
            return false;
        }

        if ($container instanceof stdClass) {
            $container->$key = $value;

            return true;
        }

        if (
            property_exists($container, $key) &&
            (new ReflectionProperty($container, $key))->isPublic()
        ) {
            $container->$key = $value;

            return true;
        }

        if (method_exists($container, '__set')) {
            $container->$key = $value;

            return true;
        }

        if ($container instanceof ArrayAccess) {
            $container[$key] = $value;

            return true;
        }

        return false;
    }

    private function unsetContainerValue(mixed &$container, string $key): bool
    {
        if (is_array($container)) {
            unset($container[$key]);

            return true;
        }

        if (! is_object($container)) {
            return false;
        }

        if ($container instanceof ArrayAccess) {
            $container->offsetUnset($key);

            return true;
        }

        if ($container instanceof stdClass || property_exists($container, $key) || method_exists($container,
            '__unset')) {
            unset($container->$key);

            return true;
        }

        return false;
    }

    /** @param  array<int, string>  $keys */
    private function setNestedValue(mixed &$container, array $keys, mixed $value): void
    {
        $segment = array_shift($keys);

        if ($keys === []) {
            $this->setContainerValue($container, $segment, $value);

            return;
        }

        [$found, $next] = $this->getContainerValue($container, $segment);

        if (! $found || (! is_array($next) && ! is_object($next))) {
            $next = [];

            if (! $this->setContainerValue($container, $segment, $next)) {
                return;
            }
        }

        if (is_array($next)) {
            $this->setNestedValue($next, $keys, $value);
            $this->setContainerValue($container, $segment, $next);

            return;
        }

        $this->setNestedValue($next, $keys, $value);
    }

    /**
     * Wrap a value in FluentValue if it's an array or object
     */
    private function wrap(mixed $value, ?string $key = null): mixed
    {
        $resolved = $this->resolve($value);

        if (! is_array($resolved) && ! is_object($resolved)) {
            return $resolved;
        }

        $instance = new self($resolved, $this);

        if ($key === null) {
            return $instance;
        }

        if ($this->overrideRoot instanceof self) {
            $instance->overrideRoot = $this->overrideRoot;
            $instance->overrideKey = $this->overrideKey;
            $instance->overridePath = array_merge($this->overridePath, [$key]);
        } else {
            [$exists, $rawValue] = $this->getContainerValue($this->items, $key, false);

            if ($exists && $rawValue instanceof Closure) {
                $instance->overrideRoot = $this;
                $instance->overrideKey = $key;
                $instance->overridePath = [];
            }
        }

        // Apply any pending overrides for this key
        if (isset($this->pendingOverrides[$key])) {
            $instance->mergePendingOverrides($this->pendingOverrides[$key]);
        }

        return $instance;
    }

    /**
     * Merge pending overrides into this instance's data
     */
    /** @param  array<string, mixed>  $overrides */
    private function mergePendingOverrides(array $overrides): void
    {
        foreach ($overrides as $key => $value) {
            [$exists, $existingValue] = $this->getContainerValue($this->items, $key, false);

            if (! (is_array($value) && $exists)) {
                // Direct assignment
                $this->setContainerValue($this->items, $key, $value);

                continue;
            }

            if (! is_array($existingValue) && ! $existingValue instanceof Closure) {
                $this->setContainerValue($this->items, $key, $value);

                continue;
            }

            if (! isset($this->pendingOverrides[$key])) {
                $this->pendingOverrides[$key] = [];
            }

            $this->pendingOverrides[$key] = array_merge(
                $this->pendingOverrides[$key] ?? [],
                $value
            );
        }
    }

    /**
     * Get a value using dot notation
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $current = $this;

        $lastIndex = array_key_last($keys);
        foreach ($keys as $index => $segment) {
            [$exists, $value] = $current->getContainerValue($current->items, $segment);
            if (! $exists) {
                return $this->wrap($this->resolve($default));
            }

            $current = $current->wrap($value, $segment);

            if (! ($current instanceof self) && $index !== $lastIndex) {
                return $this->wrap($this->resolve($default));
            }
        }

        return $current;
    }

    /**
     * Set a value using dot notation
     */
    /** @return self<TKey, TValue> */
    public function set(string $key, mixed $value): self
    {
        $keys = explode('.', $key);

        if ($this->overrideRoot instanceof self && $this->overrideKey !== null) {
            $path = array_merge($this->overridePath, $keys);
            $this->overrideRoot->setPendingOverride($this->overrideKey, $path, $value);
            $this->setLocal($keys, $value);

            return $this;
        }

        // Handle direct key (no nesting)
        if (count($keys) === 1) {
            $this->setContainerValue($this->items, $key, $value);

            return $this;
        }

        // For nested paths, check if the first segment is unresolved
        $firstKey = $keys[0];
        $remainingPath = array_slice($keys, 1);

        // If the first key exists and is a FluentValue or resolvable
        [$hasFirst, $firstValue] = $this->getContainerValue($this->items, $firstKey, false);

        // If it's a closure (unresolved), store as pending override
        if ($hasFirst && $firstValue instanceof Closure) {
            $this->setPendingOverride($firstKey, $remainingPath, $value);

            return $this;
        }

        $this->setNestedValue($this->items, $keys, $value);

        return $this;
    }

    /**
     * Set a value on this instance's data only
     */
    /** @param  array<int, string>  $keys */
    private function setLocal(array $keys, mixed $value): void
    {
        if (count($keys) === 1) {
            $this->setContainerValue($this->items, $keys[0], $value);

            return;
        }

        $this->setNestedValue($this->items, $keys, $value);
    }

    /**
     * Set a pending override for an unresolved path
     */
    /** @param  array<int, string>  $path */
    private function setPendingOverride(string $key, array $path, mixed $value): void
    {
        if (! isset($this->pendingOverrides[$key])) {
            $this->pendingOverrides[$key] = [];
        }

        $current = &$this->pendingOverrides[$key];
        while (count($path) > 1) {
            $segment = array_shift($path);
            if (! isset($current[$segment]) || ! is_array($current[$segment])) {
                $current[$segment] = [];
            }

            $current = &$current[$segment];
        }

        $current[array_shift($path)] = $value;
    }

    /**
     * Check if a key exists using dot notation
     */
    public function has(string $key): bool
    {
        $keys = explode('.', $key);
        $current = $this;

        $lastIndex = array_key_last($keys);
        foreach ($keys as $index => $segment) {
            $container = $current->items;

            $hasKey = false;
            if (is_array($container)) {
                $hasKey = array_key_exists($segment, $container);
            } elseif (is_object($container)) {
                if (property_exists($container, $segment)) {
                    $hasKey = $container instanceof stdClass ? true : (new ReflectionProperty($container, $segment))->isPublic();
                } elseif (method_exists($container, '__isset')) {
                    $hasKey = isset($container->$segment);
                } elseif ($container instanceof ArrayAccess) {
                    $hasKey = $container->offsetExists($segment);
                }
            }

            if (! $hasKey) {
                return false;
            }

            [$exists, $value] = $current->getContainerValue($current->items, $segment);
            if (! $exists) {
                return false;
            }

            $current = $current->wrap($value, $segment);

            if (! ($current instanceof self) && $index !== $lastIndex) {
                return false;
            }
        }

        return true;
    }

    /**
     * Magic getter for property access
     */
    public function __get(string $key): mixed
    {
        [$exists, $value] = $this->getContainerValue($this->items, $key);
        if ($exists) {
            return $this->wrap($value, $key);
        }

        return $this->wrap(null);
    }

    /**
     * Magic setter for property access
     */
    public function __set(string $key, mixed $value): void
    {
        $this->set($key, $value);
    }

    /**
     * Magic isset for property access
     */
    public function __isset(string $key): bool
    {
        return $this->has($key);
    }

    /**
     * ArrayAccess: offsetExists
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->has($offset);
    }

    /**
     * ArrayAccess: offsetGet
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->get($offset);
    }

    /**
     * ArrayAccess: offsetSet
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (is_null($offset)) {
            if (is_array($this->items)) {
                $this->items[] = $value;
            }
        } else {
            $this->set($offset, $value);
        }
    }

    /**
     * ArrayAccess: offsetUnset
     */
    public function offsetUnset(mixed $offset): void
    {
        $keys = explode('.', (string) $offset);

        // If it's a single key, also clear pending overrides
        if (count($keys) === 1) {
            $this->unsetContainerValue($this->items, (string) $offset);
            unset($this->pendingOverrides[(string) $offset]);

            return;
        }

        if (! is_array($this->items)) {
            return;
        }

        $data = &$this->items;

        while (count($keys) > 1) {
            $segment = array_shift($keys);

            if (! isset($data[$segment]) || ! is_array($data[$segment])) {
                return;
            }

            $data = &$data[$segment];
        }

        unset($data[array_shift($keys)]);
    }

    /**
     * Get all data as array, resolving closures
     */
    /** @return array<array-key, mixed> */
    public function toArray(): array
    {
        if (is_object($this->items)) {
            return $this->convertObjectToArray($this->items);
        }

        $result = [];

        foreach ($this->items as $key => $value) {
            $resolved = $this->resolve($value);

            if ($resolved instanceof self) {
                $result[$key] = $resolved->toArray();

                continue;
            }

            if (is_object($resolved)) {
                if (method_exists($resolved, 'toArray')) {
                    $converted = $resolved->toArray();
                    if (is_array($converted)) {
                        $instance = new self($converted, $this);
                        if (isset($this->pendingOverrides[$key])) {
                            $instance->mergePendingOverrides($this->pendingOverrides[$key]);
                        }

                        $result[$key] = $instance->toArray();

                        continue;
                    }

                    $result[$key] = $converted;

                    continue;
                }

                $result[$key] = $resolved;

                continue;
            }

            if (is_array($resolved)) {
                $instance = new self($resolved, $this);

                // Apply pending overrides if they exist
                if (isset($this->pendingOverrides[$key])) {
                    $instance->mergePendingOverrides($this->pendingOverrides[$key]);
                }

                $result[$key] = $instance->toArray();

                continue;
            }

            $result[$key] = $resolved;
        }

        return $result;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function convertObjectToArray(object $object): array
    {
        if (! method_exists($object, 'toArray')) {
            return ['value' => $object];
        }

        $converted = $object->toArray();

        if (! is_array($converted)) {
            return ['value' => $converted];
        }

        $instance = new self($converted, $this);

        if ($this->pendingOverrides !== []) {
            $instance->mergePendingOverrides($this->pendingOverrides);
        }

        return $instance->toArray();
    }

    /**
     * Get all data as an array without resolving closures (raw)
     */
    /** @return array<array-key, mixed> */
    /** @return array<array-key, mixed>|object */
    public function raw(): array|object
    {
        return $this->items;
    }

    /**
     * JsonSerializable: jsonSerialize
     */
    /** @return array<array-key, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Countable: count
     */
    public function count(): int
    {
        if (is_array($this->items)) {
            return count($this->items);
        }

        if ($this->items instanceof Countable) {
            return count($this->items);
        }

        return count(get_object_vars($this->items));
    }

    /**
     * IteratorAggregate: getIterator
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->toArray());
    }

    /**
     * Get the parent FluentValue instance
     */
    /** @return self<TKey, TValue>|null */
    public function parent(): ?self
    {
        return $this->parent;
    }

    /**
     * Map over the data
     */
    /** @return self<TKey, TValue> */
    public function map(callable $callback): self
    {
        $result = array_map($callback, $this->toArray());

        return new self($result, $this->parent);
    }

    /**
     * Filter the data
     */
    /** @return self<TKey, TValue> */
    public function filter(?callable $callback = null): self
    {
        $result = $callback
            ? array_filter($this->toArray(), $callback)
            : array_filter($this->toArray());

        return new self($result, $this->parent);
    }

    /**
     * Get only specific keys
     */
    /**
     * @param  array<int, string>  $keys
     * @return self<TKey, TValue>
     */
    public function only(array $keys): self
    {
        $result = array_intersect_key(
            $this->toArray(),
            array_flip($keys)
        );

        return new self($result, $this->parent);
    }

    /**
     * Get all except specific keys
     */
    /**
     * @param  array<int, string>  $keys
     * @return self<TKey, TValue>
     */
    public function except(array $keys): self
    {
        $result = array_diff_key(
            $this->toArray(),
            array_flip($keys)
        );

        return new self($result, $this->parent);
    }

    /**
     * Check if the data is empty
     */
    public function isEmpty(): bool
    {
        if (is_array($this->items)) {
            return $this->items === [];
        }

        return $this->count() === 0;
    }

    /**
     * Check if the data is not empty
     */
    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * Convert to JSON string
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Create a new instance from an array
     */
    /** @return self<TKey, TValue> */
    public static function make(mixed $data): self
    {
        return new self($data);
    }
}
