# Fluent Value Object

[![CI](https://github.com/fuzzyfox/fluent-value/actions/workflows/ci.yml/badge.svg)](https://github.com/fuzzyfox/fluent-value/actions/workflows/ci.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/fuzzyfox/fluent-value.svg)](https://packagist.org/packages/fuzzyfox/fluent-value)
[![Total Downloads](https://img.shields.io/packagist/dt/fuzzyfox/fluent-value.svg)](https://packagist.org/packages/fuzzyfox/fluent-value)
[![PHP Version](https://img.shields.io/packagist/php-v/fuzzyfox/fluent-value.svg)](https://packagist.org/packages/fuzzyfox/fluent-value)
[![License](https://img.shields.io/github/license/fuzzyfox/fluent-value.svg)](https://github.com/fuzzyfox/fluent-value/blob/main/LICENSE)
[![GitHub Stars](https://img.shields.io/github/stars/fuzzyfox/fluent-value.svg?style=social)](https://github.com/fuzzyfox/fluent-value/stargazers)

A PHP package for creating fluent value objects that wrap arrays or preserve objects, providing both object syntax and ArrayAccess syntax with deep nesting and lazy evaluation support.

## Features

- ✨ **Dual Syntax**: Access data using both object (`$data->key`) and array (`$data['key']`) syntax
- 🪆 **Deep Nesting**: Automatically wraps nested arrays; objects are preserved and accessible
- ⚡ **Lazy Evaluation**: Closures are resolved only when accessed, receiving parent context
- 🔍 **Dot Notation**: Get, set, and check values using dot notation (`user.profile.name`)
- 🛠️ **Collection Methods**: Includes `map`, `filter`, `only`, `except`, and more
- 📦 **Serializable**: Implements `JsonSerializable`, `Countable`, and `IteratorAggregate`

## Installation

```bash
composer require fuzzyfox/fluent-value
```

## Basic Usage

### Creating a FluentValue

```php
use FuzzyFox\FluentValue;

// From array
$data = new FluentValue([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30
]);

// Using static make method
$data = FluentValue::make(['name' => 'Jane']);

// Using helper function (if autoloaded)
$data = fluent(['name' => 'Bob']);
```

### Accessing Data

```php
$data = new FluentValue([
    'name' => 'Alice',
    'email' => 'alice@example.com'
]);

// Object syntax
echo $data->name; // "Alice"

// Array syntax
echo $data['email']; // "alice@example.com"

// Dot notation
echo $data->get('name'); // "Alice"
```

### Deep Nesting

```php
$data = new FluentValue([
    'user' => [
        'profile' => [
            'name' => 'David',
            'settings' => [
                'theme' => 'dark'
            ]
        ]
    ]
]);

// All nested arrays are automatically wrapped
echo $data->user->profile->name; // "David"
echo $data['user']['profile']['settings']['theme']; // "dark"

// Mix and match syntax
echo $data->user['profile']->settings['theme']; // "dark"
```

### Objects and Value Objects

Objects are preserved (not converted to arrays). You can access public properties or magic accessors:

```php
$model = new class {
    public string $name = 'Ada Lovelace';

    public function __get(string $key): mixed
    {
        return $key === 'role' ? 'admin' : null;
    }
};

$data = new FluentValue(['user' => $model]);

echo $data->user->name; // "Ada Lovelace"
echo $data->user->role; // "admin"
```

When converting to arrays, objects with a `toArray()` method are converted using that method.

### Lazy Evaluation with Closures

Closures are resolved only when accessed and receive their parent context:

```php
$data = new FluentValue([
    'firstName' => 'John',
    'lastName' => 'Doe',
    'fullName' => function ($parent) {
        return $parent->firstName . ' ' . $parent->lastName;
    }
]);

echo $data->fullName; // "John Doe"
```

### Nested Closures with Parent Access

```php
$data = new FluentValue([
    'company' => 'ACME Corp',
    'department' => [
        'name' => 'Engineering',
        'team' => [
            'name' => 'Backend',
            'description' => function ($parent) {
                return $parent->name . ' team in ' . 
                       $parent->parent()->name . ' at ' . 
                       $parent->parent()->parent()->company;
            }
        ]
    ]
]);

echo $data->department->team->description;
// "Backend team in Engineering at ACME Corp"
```

### Closures Returning Arrays

Closures that return arrays are automatically wrapped. Objects are preserved:

```php
$data = new FluentValue([
    'config' => fn ($parent) => [
        'enabled' => true,
        'settings' => [
            'theme' => 'dark'
        ]
    ]
]);

// Closure result is wrapped in FluentValue
echo $data->config->settings->theme; // "dark"
```

### Pending Overrides (Advanced Feature)

You can set values on unresolved closures, and these overrides will be merged when the closure is resolved:

```php
$data = new FluentValue([
    'config' => fn ($parent) => [
        'theme' => 'dark',
        'timeout' => 30
    ]
]);

// Set values BEFORE the closure is resolved
$data->config->theme = 'light';
$data->config->retries = 3;

// When accessed, overrides are merged with resolved values
echo $data->config->theme;   // "light" (overridden)
echo $data->config->timeout; // 30 (from closure)
echo $data->config->retries; // 3 (new value)
```

**Deep Nesting with Pending Overrides:**

```php
$app = new FluentValue([
    'settings' => fn($parent) => [
        'ui' => [
            'theme' => 'dark',
            'layout' => [
                'sidebar' => 'left'
            ]
        ]
    ]
]);

// Set deep nested values before resolution
$app->settings->ui->layout->sidebar = 'right';
$app->settings->ui->layout->footer = 'sticky';
$app->settings->ui->fontSize = 14;

// All overrides are applied when accessed
echo $app->settings->ui->layout->sidebar; // "right"
echo $app->settings->ui->layout->footer;  // "sticky"
echo $app->settings->ui->fontSize;        // 14
```

**Persistence:**

Pending overrides persist across multiple accesses:

```php
$data->config->value = 5;

echo $data->config->value; // 5
echo $data->config->value; // Still 5
```

**Unsetting:**

Calling `unset()` on a key clears both the value and any pending overrides:

```php
$data->config->value = 'modified';
unset($data['config']);
// Pending overrides for 'config' are cleared
```

## Advanced Features

### Dot Notation

```php
$data = new FluentValue([]);

// Set using dot notation
$data->set('user.profile.name', 'Alice');

// Get using dot notation
echo $data->get('user.profile.name'); // "Alice"

// Check existence
if ($data->has('user.profile.name')) {
    // ...
}

// Get with default
echo $data->get('user.email', 'unknown@example.com');

// Default can be a closure
$email = $data->get('user.email', fn ($parent) => $parent->name . '@example.com');
```

### Modifying Data

```php
$data = new FluentValue(['name' => 'Bob']);

// Object syntax
$data->age = 30;

// Array syntax
$data['email'] = 'bob@example.com';

// Dot notation
$data->set('profile.avatar', 'avatar.jpg');
```

### Collection Methods

```php
$data = new FluentValue([
    'name' => 'Charlie',
    'age' => 25,
    'email' => 'charlie@example.com',
    'verified' => true
]);

// Get only specific keys
$subset = $data->only(['name', 'email']);

// Get all except specific keys
$subset = $data->except(['verified']);

// Map over values
$numbers = FluentValue::make([1, 2, 3, 4, 5]);
$doubled = $numbers->map(fn ($n) => $n * 2);

// Filter values
$filtered = $numbers->filter(fn ($n) => $n > 2);
```

### Checking Empty State

```php
$data = new FluentValue([]);

if ($data->isEmpty()) {
    // ...
}

if ($data->isNotEmpty()) {
    // ...
}
```

### Converting to Array

```php
$data = new FluentValue([
    'name' => 'Diana',
    'greeting' => fn ($parent) => 'Hello, ' . $parent->name
]);

// toArray() resolves all closures
$array = $data->toArray();
// ['name' => 'Diana', 'greeting' => 'Hello, Diana']

// raw() returns data without resolving closures
$raw = $data->raw();
// ['name' => 'Diana', 'greeting' => Closure]

// Objects with toArray() are converted during toArray()
$money = new class {
    public function toArray(): array
    {
        return ['amount' => 1500, 'currency' => 'USD'];
    }
};

$data = new FluentValue(['price' => $money]);
// ['price' => ['amount' => 1500, 'currency' => 'USD']]
```

### JSON Serialization

```php
$data = new FluentValue([
    'name' => 'Eve',
    'age' => 28
]);

// Using json_encode
$json = json_encode($data);

// Using toJson method
$json = $data->toJson(JSON_PRETTY_PRINT);
```

### Iteration

```php
$data = new FluentValue([
    'apple' => 1,
    'banana' => 2,
    'cherry' => 3
]);

// Foreach
foreach ($data as $key => $value) {
    echo "$key: $value\n";
}

// Count
echo count($data); // 3
```

## Real-World Examples

### API Response Handling

```php
$response = new FluentValue([
    'status' => 'success',
    'data' => [
        'user' => [
            'id' => 123,
            'name' => 'Alice',
            'email' => 'alice@example.com'
        ]
    ],
    'meta' => [
        'timestamp' => fn () => time()
    ]
]);

if ($response->status === 'success') {
    $user = $response->data->user;
    echo $user->name; // "Alice"
}
```

### Configuration Management

```php
$config = new FluentValue([
    'app' => [
        'name' => 'My App',
        'env' => 'production',
        'debug' => false,
        'url' => fn ($parent) => $parent->env === 'production' 
            ? 'https://myapp.com' 
            : 'http://localhost:8000'
    ],
    'database' => [
        'host' => 'localhost',
        'name' => 'myapp',
        'connection_string' => fn ($parent) => 
            "mysql:host={$parent->host};dbname={$parent->name}"
    ]
]);

echo $config->app->url; // "https://myapp.com"
echo $config->database->connection_string; // "mysql:host=localhost;dbname=myapp"
```

### Form Data Processing

```php
$form = new FluentValue([
    'firstName' => 'John',
    'lastName' => 'Doe',
    'email' => 'john@example.com',
    'fullName' => fn ($parent) => trim($parent->firstName . ' ' . $parent->lastName),
    'displayName' => fn ($parent) => $parent->fullName . ' (' . $parent->email . ')'
]);

echo $form->displayName; // "John Doe (john@example.com)"
```

### Dynamic Configuration Building

Use pending overrides to build up configuration incrementally:

```php
$config = new FluentValue([
    'database' => fn ($parent) => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'myapp'
    ]
]);

// Override defaults and add new values
$config->database->host = '127.0.0.1';
$config->database->username = 'root';
$config->database->password = env('DB_PASSWORD');
$config->database->options = [
    'charset' => 'utf8mb4'
];

// All values are merged when accessed
$dbConfig = $config->database->toArray();
// [
//   'host' => '127.0.0.1',        // overridden
//   'port' => 3306,                // from closure
//   'name' => 'myapp',             // from closure
//   'username' => 'root',          // added
//   'password' => '...',           // added
//   'options' => ['charset' => ...]// added
// ]
```

## Requirements

- PHP 8.2 or higher

## License

This Source Code Form is subject to the terms of the Mozilla Public
License, v. 2.0. If a copy of the MPL was not distributed with this
file, You can obtain one at https://mozilla.org/MPL/2.0/.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.
