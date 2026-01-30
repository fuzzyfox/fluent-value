<?php

use FuzzyFox\FluentValue;

/**
 * @param  FluentValue<array-key, mixed>  $instance
 * @param  array<int, mixed>  $args
 */
function callPrivate(FluentValue $instance, string $method, array $args = []): mixed
{
    $bound = Closure::bind(fn (string $method, array $args): mixed => $this->$method(...$args), $instance, $instance::class);

    return $bound($method, $args);
}

/**
 * @param  FluentValue<array-key, mixed>  $instance
 * @param  array<string, mixed>  $overrides
 */
function setPendingOverrides(FluentValue $instance, array $overrides): void
{
    $bound = Closure::bind(function (array $overrides): void {
        $this->pendingOverrides = $overrides;
    }, $instance, $instance::class);

    $bound($overrides);
}

/** @implements ArrayAccess<string, mixed> */
class ArrayAccessContainer implements ArrayAccess
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->data);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->data[$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[$offset]);
    }
}

class MagicAccessContainer
{
    /** @var array<string, mixed> */
    private array $data = ['magic' => 'value'];

    public bool $unsetCalled = false;

    public function __get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function __set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function __isset(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function __unset(string $key): void
    {
        $this->unsetCalled = true;
        unset($this->data[$key]);
    }
}

it('normalizes scalar input into value array', function (): void {
    $fluent = new FluentValue('hello');

    $this->assertEquals(['value' => 'hello'], $fluent->raw());
});

it('normalizes FluentValue input using raw data', function (): void {
    $inner = new FluentValue(['a' => 1]);
    $outer = new FluentValue($inner);

    $this->assertEquals(['a' => 1], $outer->raw());
});

it('handles getContainerValue for arrays and non-objects', function (): void {
    $fluent = new FluentValue([]);

    $this->assertEquals([true, 1], callPrivate($fluent, 'getContainerValue', [['a' => 1], 'a']));
    $this->assertEquals([false, null], callPrivate($fluent, 'getContainerValue', [['a' => 1], 'b']));
    $this->assertEquals([false, null], callPrivate($fluent, 'getContainerValue', ['not-object', 'a']));
});

it('handles getContainerValue with object access variants', function (): void {
    $fluent = new FluentValue([]);

    $std = new stdClass;
    $std->name = 'std';
    $this->assertEquals([true, 'std'], callPrivate($fluent, 'getContainerValue', [$std, 'name']));

    $publicObject = new class
    {
        public string $name = 'public';
    };
    $this->assertEquals([true, 'public'], callPrivate($fluent, 'getContainerValue', [$publicObject, 'name']));

    $magic = new MagicAccessContainer;
    $this->assertEquals([true, 'value'], callPrivate($fluent, 'getContainerValue', [$magic, 'magic']));

    $arrayAccess = new ArrayAccessContainer;
    $arrayAccess['key'] = 'stored';
    $this->assertEquals([true, 'stored'], callPrivate($fluent, 'getContainerValue', [$arrayAccess, 'key']));
});

it('checks has across container types', function (): void {
    $this->assertTrue((new FluentValue(['a' => 1]))->has('a'));
    $this->assertFalse((new FluentValue(['a' => 1]))->has('b'));

    $std = new stdClass;
    $std->foo = 'bar';
    $this->assertTrue((new FluentValue($std))->has('foo'));

    $publicObject = new class
    {
        public string $name = 'public';
    };
    $this->assertTrue((new FluentValue($publicObject))->has('name'));

    $magic = new MagicAccessContainer;
    $this->assertTrue((new FluentValue($magic))->has('magic'));

    $arrayAccess = new ArrayAccessContainer;
    $arrayAccess['key'] = 'stored';
    $this->assertTrue((new FluentValue($arrayAccess))->has('key'));

    $noAccess = new class {};
    $this->assertFalse((new FluentValue($noAccess))->has('missing'));

    $this->assertFalse((new FluentValue('value'))->has('a'));
});

it('sets and unsets values across container types', function (): void {
    $fluent = new FluentValue([]);

    $array = [];
    $this->assertTrue(callPrivate($fluent, 'setContainerValue', [&$array, 'a', 1]));
    $this->assertEquals(['a' => 1], $array);
    $this->assertTrue(callPrivate($fluent, 'unsetContainerValue', [&$array, 'a']));
    $this->assertEquals([], $array);

    $std = new stdClass;
    $this->assertTrue(callPrivate($fluent, 'setContainerValue', [&$std, 'name', 'std']));
    $this->assertEquals('std', $std->name);
    $this->assertTrue(callPrivate($fluent, 'unsetContainerValue', [&$std, 'name']));

    $publicObject = new class
    {
        public string $name = 'initial';
    };
    $this->assertTrue(callPrivate($fluent, 'setContainerValue', [&$publicObject, 'name', 'updated']));
    $this->assertEquals('updated', $publicObject->name);

    $magic = new MagicAccessContainer;
    $this->assertTrue(callPrivate($fluent, 'setContainerValue', [&$magic, 'extra', 'value']));
    $this->assertTrue((new FluentValue($magic))->has('extra'));
    $this->assertTrue(callPrivate($fluent, 'unsetContainerValue', [&$magic, 'extra']));
    $this->assertTrue($magic->unsetCalled);

    $arrayAccess = new ArrayAccessContainer;
    $this->assertTrue(callPrivate($fluent, 'setContainerValue', [&$arrayAccess, 'key', 'stored']));
    $this->assertTrue(callPrivate($fluent, 'unsetContainerValue', [&$arrayAccess, 'key']));

    $notObject = 'nope';
    $this->assertFalse(callPrivate($fluent, 'setContainerValue', [&$notObject, 'a', 1]));
    $this->assertFalse(callPrivate($fluent, 'unsetContainerValue', [&$notObject, 'a']));

    $noAccess = new class {};
    $this->assertFalse(callPrivate($fluent, 'unsetContainerValue', [&$noAccess, 'a']));
});

it('sets nested values and handles blocked assignment', function (): void {
    $fluent = new FluentValue([]);

    $data = [];
    callPrivate($fluent, 'setNestedValue', [&$data, ['a', 'b', 'c'], 1]);
    $this->assertEquals(['a' => ['b' => ['c' => 1]]], $data);

    $blocked = new class {};
    callPrivate($fluent, 'setNestedValue', [&$blocked, ['a', 'b'], 1]);
    $this->assertEquals([], get_object_vars($blocked));

    /** @var array{obj: object} $container */
    $container = ['obj' => (object) ['child' => []]];
    callPrivate($fluent, 'setNestedValue', [&$container, ['obj', 'child', 'value'], 5]);
    /** @var array<string, int> $child */
    $child = $container['obj']->child;
    $this->assertEquals(5, $child['value']);
});

it('wraps arrays without a key into FluentValue', function (): void {
    $fluent = new FluentValue([]);

    $wrapped = callPrivate($fluent, 'wrap', [['a' => 1]]);
    $this->assertInstanceOf(FluentValue::class, $wrapped);
});

it('returns defaults when traversing into non-container values', function (): void {
    $fluent = new FluentValue(['a' => 1]);

    $this->assertNull($fluent->get('a.b'));
    $this->assertFalse($fluent->has('a.b'));
});

it('returns false when isset and value access diverge', function (): void {
    $object = new class
    {
        public function __isset(string $key): bool
        {
            return true;
        }
    };

    $fluent = new FluentValue(['obj' => $object]);
    $this->assertFalse($fluent->has('obj.missing'));
});

it('merges pending overrides into scalar values', function (): void {
    $fluent = new FluentValue(['a' => 'scalar']);

    callPrivate($fluent, 'mergePendingOverrides', [['a' => ['b' => 1]]]);
    $this->assertEquals(['a' => ['b' => 1]], $fluent->raw());
});

it('sets local nested values', function (): void {
    $fluent = new FluentValue([]);

    callPrivate($fluent, 'setLocal', [['a', 'b'], 1]);
    $this->assertEquals(['a' => ['b' => 1]], $fluent->raw());
});

it('appends values with array access when items are arrays', function (): void {
    $fluent = new FluentValue([]);

    $fluent[] = 'value';
    $this->assertEquals(['value'], $fluent->raw());
});

it('handles nested array unset when path is missing', function (): void {
    $fluent = new FluentValue(['a' => 'scalar']);

    unset($fluent['a.b']);
    $this->assertEquals(['a' => 'scalar'], $fluent->raw());
});

it('unsets existing nested array values', function (): void {
    $fluent = new FluentValue(['a' => ['b' => 1]]);

    unset($fluent['a.b']);
    $this->assertEquals(['a' => []], $fluent->raw());
});

it('returns early when unsetting nested keys on objects', function (): void {
    $fluent = new FluentValue((object) ['a' => 1]);

    unset($fluent['a.b']);
    $this->assertEquals((object) ['a' => 1], $fluent->raw());
});

it('converts object-backed items when calling toArray', function (): void {
    $object = new class
    {
        /** @return array<string, int> */
        public function toArray(): array
        {
            return ['a' => 1];
        }
    };

    $fluent = new FluentValue($object);
    $this->assertEquals(['a' => 1], $fluent->toArray());
});

it('converts FluentValue instances inside arrays', function (): void {
    $inner = new FluentValue(['a' => 1]);
    $fluent = new FluentValue(['inner' => $inner]);

    $this->assertEquals(['inner' => ['a' => 1]], $fluent->toArray());
});

it('converts objects in arrays with and without toArray', function (): void {
    $objectWithString = new class
    {
        public function toArray(): string
        {
            return 'scalar';
        }
    };

    $plain = new stdClass;
    $fluent = new FluentValue(['one' => $objectWithString, 'two' => $plain]);

    $this->assertEquals('scalar', $fluent->toArray()['one']);
    $this->assertSame($plain, $fluent->toArray()['two']);
});

it('applies pending overrides when converting object to array', function (): void {
    $fluent = new FluentValue([]);
    setPendingOverrides($fluent, ['a' => ['b' => 1]]);

    $object = new class
    {
        /** @return array<string, array<string, mixed>> */
        public function toArray(): array
        {
            return ['a' => []];
        }
    };

    $this->assertEquals(['a' => ['b' => 1]], callPrivate($fluent, 'convertObjectToArray', [$object]));
});

it('applies pending overrides when object values are resolved', function (): void {
    $money = new class
    {
        /** @return array<string, string> */
        public function toArray(): array
        {
            return ['currency' => 'USD'];
        }
    };

    $fluent = new FluentValue([
        'price' => fn (): object => $money,
    ]);

    $fluent->price->currency = 'EUR';

    $this->assertEquals(['price' => ['currency' => 'EUR']], $fluent->toArray());
});

it('converts objects to arrays with toArray when available', function (): void {
    $fluent = new FluentValue([]);

    $objectWithArray = new class
    {
        /** @return array<string, int> */
        public function toArray(): array
        {
            return ['a' => 1];
        }
    };

    $this->assertEquals(['a' => 1], callPrivate($fluent, 'convertObjectToArray', [$objectWithArray]));

    $objectWithScalar = new class
    {
        public function toArray(): string
        {
            return 'scalar';
        }
    };

    $this->assertEquals(['value' => 'scalar'], callPrivate($fluent, 'convertObjectToArray', [$objectWithScalar]));

    $plain = new stdClass;
    $this->assertEquals(['value' => $plain], callPrivate($fluent, 'convertObjectToArray', [$plain]));
});

it('handles count and offsetSet for object-backed values', function (): void {
    $countable = new class implements Countable
    {
        public function count(): int
        {
            return 3;
        }
    };

    $fluent = new FluentValue($countable);
    $this->assertCount(3, $fluent);

    $object = new stdClass;
    $fluent = new FluentValue($object);
    $fluent[] = 'ignored';
    $this->assertEquals($object, $fluent->raw());

    $object->name = 'value';
    $this->assertCount(1, $fluent);
    $this->assertFalse($fluent->isEmpty());
});

it('returns defaults for missing properties and json conversion', function (): void {
    $fluent = new FluentValue(['a' => 1]);

    $this->assertNull($fluent->missing);
    $this->assertEquals('{"a":1}', $fluent->toJson());
});

it('filters without a callback', function (): void {
    $fluent = new FluentValue([0, 1, 2]);

    $filtered = $fluent->filter();
    $this->assertEquals([1 => 1, 2 => 2], $filtered->toArray());
});
