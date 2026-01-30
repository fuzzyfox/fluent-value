<?php

use FuzzyFox\FluentValue;

it('can create from array', function (): void {
    $data = ['name' => 'John', 'age' => 30];
    $fluent = new FluentValue($data);

    $this->assertEquals('John', $fluent->name);
    $this->assertEquals(30, $fluent->age);
});

it('can create from object', function (): void {
    $object = (object) ['name' => 'Jane', 'age' => 25];
    $fluent = new FluentValue($object);

    $this->assertEquals('Jane', $fluent->name);
    $this->assertEquals(25, $fluent->age);
});

it('provides access to object properties without destructuring', function (): void {
    $object = new class
    {
        public string $name = 'Mila';

        public int $age = 29;
    };

    $fluent = new FluentValue($object);

    $this->assertEquals('Mila', $fluent->name);
    $this->assertEquals(29, $fluent->age);
});

it('falls back to magic getter access on objects', function (): void {
    $object = new class
    {
        /** @var array<string, string> */
        private array $data = ['label' => 'Magic'];

        public function __get(string $key): mixed
        {
            return $this->data[$key] ?? null;
        }
    };

    $fluent = new FluentValue($object);

    $this->assertEquals('Magic', $fluent->label);
});

it('uses object toArray during array conversion', function (): void {
    $money = new class
    {
        /** @return array<string, int|string> */
        public function toArray(): array
        {
            return ['amount' => 1500, 'currency' => 'USD'];
        }
    };

    $fluent = new FluentValue(['price' => $money]);

    $this->assertEquals([
        'price' => ['amount' => 1500, 'currency' => 'USD'],
    ], $fluent->toArray());
});

it('can access via array syntax', function (): void {
    $fluent = new FluentValue(['name' => 'Bob', 'age' => 40]);

    $this->assertEquals('Bob', $fluent['name']);
    $this->assertEquals(40, $fluent['age']);
});

it('can set via object syntax', function (): void {
    $fluent = new FluentValue(['name' => 'Alice']);
    $fluent->age = 35;

    $this->assertEquals(35, $fluent->age);
});

it('can set via array syntax', function (): void {
    $fluent = new FluentValue(['name' => 'Charlie']);
    $fluent['age'] = 45;

    $this->assertEquals(45, $fluent['age']);
});

it('deep nesting with arrays', function (): void {
    $data = [
        'user' => [
            'profile' => [
                'name' => 'David',
                'email' => 'david@example.com',
            ],
        ],
    ];

    $fluent = new FluentValue($data);

    $this->assertInstanceOf(FluentValue::class, $fluent->user);
    $this->assertInstanceOf(FluentValue::class, $fluent->user->profile);
    $this->assertEquals('David', $fluent->user->profile->name);
    $this->assertEquals('david@example.com', $fluent->user->profile->email);
});

it('deep nesting with array access', function (): void {
    $data = [
        'user' => [
            'profile' => [
                'name' => 'Eva',
            ],
        ],
    ];

    $fluent = new FluentValue($data);

    $this->assertEquals('Eva', $fluent['user']['profile']['name']);
});

it('closure is resolved on access', function (): void {
    $callCount = 0;
    $fluent = new FluentValue([
        'name' => 'Frank',
        'greeting' => function ($parent) use (&$callCount): string {
            $callCount++;

            return 'Hello, '.$parent->name;
        },
    ]);

    $this->assertEquals(0, $callCount);
    $this->assertEquals('Hello, Frank', $fluent->greeting);
    $this->assertEquals(1, $callCount);

    // Access again to ensure it's called each time
    $this->assertEquals('Hello, Frank', $fluent->greeting);
    $this->assertEquals(2, $callCount);
});

it('nested closure receives parent context', function (): void {
    $fluent = new FluentValue([
        'user' => [
            'firstName' => 'Grace',
            'lastName' => 'Hopper',
            'fullName' => fn ($parent): string => $parent->firstName.' '.$parent->lastName,
        ],
    ]);

    $this->assertEquals('Grace Hopper', $fluent->user->fullName);
});

it('dot notation get', function (): void {
    $fluent = new FluentValue([
        'user' => [
            'profile' => [
                'name' => 'Henry',
            ],
        ],
    ]);

    $this->assertEquals('Henry', $fluent->get('user.profile.name'));
});

it('dot notation set', function (): void {
    $fluent = new FluentValue([]);
    $fluent->set('user.profile.name', 'Irene');

    $this->assertEquals('Irene', $fluent->user->profile->name);
});

it('dot notation has', function (): void {
    $fluent = new FluentValue([
        'user' => [
            'name' => 'Jack',
        ],
    ]);

    $this->assertTrue($fluent->has('user.name'));
    $this->assertFalse($fluent->has('user.email'));
});

it('to array resolves closures', function (): void {
    $fluent = new FluentValue([
        'name' => 'Kate',
        'greeting' => fn ($parent): string => 'Hello, '.$parent->name,
    ]);

    $array = $fluent->toArray();

    $this->assertEquals('Kate', $array['name']);
    $this->assertEquals('Hello, Kate', $array['greeting']);
});

it('raw does not resolve closures', function (): void {
    $closure = fn ($parent): string => 'Hello';
    $fluent = new FluentValue([
        'name' => 'Leo',
        'greeting' => $closure,
    ]);

    $raw = $fluent->raw();

    $this->assertSame($closure, $raw['greeting']);
});

it('json serialization', function (): void {
    $fluent = new FluentValue([
        'name' => 'Mia',
        'age' => 28,
    ]);

    $json = json_encode($fluent);
    $decoded = json_decode($json, true);

    $this->assertEquals('Mia', $decoded['name']);
    $this->assertEquals(28, $decoded['age']);
});

it('countable', function (): void {
    $fluent = new FluentValue([
        'a' => 1,
        'b' => 2,
        'c' => 3,
    ]);

    $this->assertCount(3, $fluent);
});

it('iterator', function (): void {
    $fluent = new FluentValue([
        'a' => 1,
        'b' => 2,
        'c' => 3,
    ]);

    $result = [];
    foreach ($fluent as $key => $value) {
        $result[$key] = $value;
    }

    $this->assertEquals(['a' => 1, 'b' => 2, 'c' => 3], $result);
});

it('map', function (): void {
    $fluent = new FluentValue([1, 2, 3]);
    $mapped = $fluent->map(fn ($value): int|float => $value * 2);

    $this->assertEquals([2, 4, 6], $mapped->toArray());
});

it('filter', function (): void {
    $fluent = new FluentValue([1, 2, 3, 4, 5]);
    $filtered = $fluent->filter(fn ($value): bool => $value > 2);

    $this->assertEquals([2 => 3, 3 => 4, 4 => 5], $filtered->toArray());
});

it('only', function (): void {
    $fluent = new FluentValue([
        'name' => 'Nina',
        'age' => 30,
        'email' => 'nina@example.com',
    ]);

    $only = $fluent->only(['name', 'age']);

    $this->assertEquals(['name' => 'Nina', 'age' => 30], $only->toArray());
});

it('except', function (): void {
    $fluent = new FluentValue([
        'name' => 'Oscar',
        'age' => 35,
        'email' => 'oscar@example.com',
    ]);

    $except = $fluent->except(['email']);

    $this->assertEquals(['name' => 'Oscar', 'age' => 35], $except->toArray());
});

it('is empty', function (): void {
    $empty = new FluentValue([]);
    $notEmpty = new FluentValue(['name' => 'Paul']);

    $this->assertTrue($empty->isEmpty());
    $this->assertFalse($notEmpty->isEmpty());
});

it('is not empty', function (): void {
    $empty = new FluentValue([]);
    $notEmpty = new FluentValue(['name' => 'Quinn']);

    $this->assertFalse($empty->isNotEmpty());
    $this->assertTrue($notEmpty->isNotEmpty());
});

it('parent access', function (): void {
    $fluent = new FluentValue([
        'name' => 'Root',
        'child' => [
            'name' => 'Child',
        ],
    ]);

    $child = $fluent->child;
    $this->assertInstanceOf(FluentValue::class, $child->parent());
    $this->assertEquals('Root', $child->parent()->name);
});

it('closure with deeply nested parent access', function (): void {
    $fluent = new FluentValue([
        'company' => 'ACME Corp',
        'department' => [
            'name' => 'Engineering',
            'team' => [
                'name' => 'Backend',
                'description' => fn ($parent): string => $parent->name.' team in '.
                       $parent->parent()->name.' at '.
                       $parent->parent()->parent()->company,
            ],
        ],
    ]);

    $this->assertEquals(
        'Backend team in Engineering at ACME Corp',
        $fluent->department->team->description
    );
});

it('make static method', function (): void {
    $fluent = FluentValue::make(['name' => 'Rachel']);

    $this->assertInstanceOf(FluentValue::class, $fluent);
    $this->assertEquals('Rachel', $fluent->name);
});

it('default value for missing keys', function (): void {
    $fluent = new FluentValue(['name' => 'Sam']);

    $this->assertEquals('Unknown', $fluent->get('email', 'Unknown'));
});

it('default value with closure', function (): void {
    $fluent = new FluentValue(['name' => 'Tina']);

    $default = $fluent->get('email', fn ($parent): string => $parent->name.'@example.com');

    $this->assertEquals('Tina@example.com', $default);
});

it('isset magic method', function (): void {
    $fluent = new FluentValue(['name' => 'Uma']);

    $this->assertTrue(isset($fluent->name));
    $this->assertFalse(isset($fluent->email));
});

it('unset via array access', function (): void {
    $fluent = new FluentValue(['name' => 'Victor', 'age' => 40]);
    unset($fluent['age']);

    $this->assertTrue(isset($fluent['name']));
    $this->assertFalse(isset($fluent['age']));
});

it('closure returning array is wrapped', function (): void {
    $fluent = new FluentValue([
        'config' => fn ($parent): array => [
            'enabled' => true,
            'settings' => [
                'theme' => 'dark',
            ],
        ],
    ]);

    $this->assertInstanceOf(FluentValue::class, $fluent->config);
    $this->assertTrue($fluent->config->enabled);
    $this->assertEquals('dark', $fluent->config->settings->theme);
});

it('can set on unresolved closure', function (): void {
    $fluent = new FluentValue([
        'config' => fn ($parent): array => [
            'enabled' => true,
            'theme' => 'dark',
        ],
    ]);

    // Set a value on the unresolved closure result
    $fluent->config->theme = 'light';
    $fluent->config->newKey = 'newValue';

    // Access should merge the override
    $this->assertEquals('light', $fluent->config->theme);
    $this->assertEquals('newValue', $fluent->config->newKey);
    $this->assertTrue($fluent->config->enabled);
});

it('pending overrides persist across accesses', function (): void {
    $fluent = new FluentValue([
        'data' => fn ($parent): array => [
            'count' => 0,
            'items' => [],
        ],
    ]);

    $fluent->data->count = 5;
    $fluent->data->name = 'Test';

    // First access
    $this->assertEquals(5, $fluent->data->count);
    $this->assertEquals('Test', $fluent->data->name);

    // Second access - overrides should still apply
    $this->assertEquals(5, $fluent->data->count);
    $this->assertEquals('Test', $fluent->data->name);
});

it('deep pending overrides', function (): void {
    $fluent = new FluentValue([
        'user' => fn ($parent): array => [
            'profile' => [
                'name' => 'Original',
                'settings' => [
                    'theme' => 'dark',
                ],
            ],
        ],
    ]);

    // Set deep nested value before resolution
    $fluent->user->profile->settings->theme = 'light';
    $fluent->user->profile->settings->fontSize = 14;
    $fluent->user->profile->email = 'new@example.com';

    $this->assertEquals('light', $fluent->user->profile->settings->theme);
    $this->assertEquals(14, $fluent->user->profile->settings->fontSize);
    $this->assertEquals('new@example.com', $fluent->user->profile->email);
    $this->assertEquals('Original', $fluent->user->profile->name);
});

it('pending overrides with dot notation', function (): void {
    $fluent = new FluentValue([
        'api' => fn ($parent): array => [
            'config' => [
                'timeout' => 30,
                'retry' => 3,
            ],
        ],
    ]);

    $fluent->set('api.config.timeout', 60);
    $fluent->set('api.config.maxRetries', 5);

    $this->assertEquals(60, $fluent->get('api.config.timeout'));
    $this->assertEquals(5, $fluent->get('api.config.maxRetries'));
    $this->assertEquals(3, $fluent->get('api.config.retry'));
});

it('pending overrides in to array', function (): void {
    $fluent = new FluentValue([
        'settings' => fn ($parent): array => [
            'theme' => 'dark',
            'locale' => 'en',
        ],
    ]);

    $fluent->settings->theme = 'light';
    $fluent->settings->notifications = true;

    $array = $fluent->toArray();

    $this->assertEquals('light', $array['settings']['theme']);
    $this->assertTrue($array['settings']['notifications']);
    $this->assertEquals('en', $array['settings']['locale']);
});

it('unset clears pending overrides', function (): void {
    $fluent = new FluentValue([
        'data' => fn ($parent): array => [
            'value' => 'original',
        ],
    ]);

    $fluent->data->value = 'modified';
    $this->assertEquals('modified', $fluent->data->value);

    // Unset the parent key
    unset($fluent['data']);

    // Re-add the closure
    $fluent->data = fn ($parent): array => ['value' => 'original'];

    // Should not have the old override
    /** @var FluentValue<array-key, mixed> $data */
    $data = $fluent->get('data');
    $this->assertEquals('original', $data->value);
});

it('override persists unless explicitly changed', function (): void {
    $fluent = new FluentValue([
        'counter' => fn ($parent): array => ['count' => 0],
    ]);

    $fluent->counter->count = 5;
    $this->assertEquals(5, $fluent->counter->count);

    // Change it again
    $fluent->counter->count = 10;
    $this->assertEquals(10, $fluent->counter->count);
});

it('nested closures with pending overrides', function (): void {
    $fluent = new FluentValue([
        'app' => fn ($parent): array => [
            'name' => 'MyApp',
            'database' => fn ($p): array => [
                'host' => 'localhost',
                'port' => 3306,
            ],
        ],
    ]);

    $fluent->app->database->host = '127.0.0.1';
    $fluent->app->database->username = 'root';

    $this->assertEquals('127.0.0.1', $fluent->app->database->host);
    $this->assertEquals('root', $fluent->app->database->username);
    $this->assertEquals(3306, $fluent->app->database->port);
});

it('pending override with array value', function (): void {
    $fluent = new FluentValue([
        'config' => fn ($parent): array => [
            'features' => ['feature1'],
        ],
    ]);

    $fluent->config->features = ['feature1', 'feature2', 'feature3'];
    $fluent->config->enabled = true;

    $array = $fluent->toArray();
    $this->assertEquals(['feature1', 'feature2', 'feature3'], $array['config']['features']);
    $this->assertTrue($array['config']['enabled']);
});

it('multiple pending overrides on same closure', function (): void {
    $fluent = new FluentValue([
        'settings' => fn ($parent): array => [
            'a' => 1,
            'b' => 2,
        ],
    ]);

    $fluent->settings->a = 10;
    $fluent->settings->c = 3;
    $fluent->settings->d = 4;

    $array = $fluent->toArray();
    $this->assertEquals(10, $array['settings']['a']);
    $this->assertEquals(2, $array['settings']['b']);
    $this->assertEquals(3, $array['settings']['c']);
    $this->assertEquals(4, $array['settings']['d']);
});
