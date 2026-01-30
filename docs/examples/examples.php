<?php

require_once __DIR__.'/vendor/autoload.php';

use FuzzyFox\FluentValue;

echo "=== Fluent Value Object Examples ===\n\n";

// Example 1: Basic usage
echo "1. Basic Usage:\n";
$user = new FluentValue([
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'age' => 30,
]);

echo "Name (object): {$user->name}\n";
echo "Email (array): {$user['email']}\n";
echo "Age (get): {$user->get('age')}\n\n";

// Example 2: Deep nesting
echo "2. Deep Nesting:\n";
$config = new FluentValue([
    'app' => [
        'name' => 'My Application',
        'settings' => [
            'theme' => 'dark',
            'locale' => 'en_US',
        ],
    ],
]);

echo "Theme: {$config->app->settings->theme}\n";
echo "Using dot notation: {$config->get('app.settings.locale')}\n\n";

// Example 3: Lazy evaluation with closures
echo "3. Lazy Evaluation:\n";
$person = new FluentValue([
    'firstName' => 'Jane',
    'lastName' => 'Smith',
    'fullName' => function ($parent) {
        echo "  [Closure executed]\n";

        return $parent->firstName.' '.$parent->lastName;
    },
]);

echo "First access: {$person->fullName}\n";
echo "Second access: {$person->fullName}\n\n";

// Example 4: Nested closures with parent access
echo "4. Nested Closures with Parent Access:\n";
$company = new FluentValue([
    'name' => 'ACME Corp',
    'department' => [
        'name' => 'Engineering',
        'team' => [
            'name' => 'Backend',
            'fullPath' => function ($parent) {
                return $parent->parent()->parent()->name.
                       ' > '.$parent->parent()->name.
                       ' > '.$parent->name;
            },
        ],
    ],
]);

echo "Full path: {$company->department->team->fullPath}\n\n";

// Example 5: Closures returning arrays
echo "5. Closures Returning Arrays (auto-wrapped):\n";
$dynamic = new FluentValue([
    'user' => 'admin',
    'permissions' => function ($parent) {
        if ($parent->user === 'admin') {
            return [
                'read' => true,
                'write' => true,
                'delete' => true,
                'summary' => fn ($p) => 'Admin has all permissions',
            ];
        }

        return ['read' => true];
    },
]);

echo 'Can write: '.($dynamic->permissions->write ? 'Yes' : 'No')."\n";
echo "Summary: {$dynamic->permissions->summary}\n\n";

// Example 6: Collection methods
echo "6. Collection Methods:\n";
$data = new FluentValue([
    'name' => 'Charlie',
    'age' => 25,
    'email' => 'charlie@example.com',
    'verified' => true,
    'role' => 'admin',
]);

$publicData = $data->only(['name', 'email']);
echo 'Only name and email: '.json_encode($publicData->toArray())."\n";

$filtered = $data->except(['verified']);
echo 'Except verified: '.json_encode($filtered->toArray())."\n\n";

// Example 7: Mapping and filtering
echo "7. Mapping and Filtering:\n";
$numbers = FluentValue::make([1, 2, 3, 4, 5, 6]);
$doubled = $numbers->map(fn ($n) => $n * 2);
echo 'Doubled: '.json_encode($doubled->toArray())."\n";

$evens = $numbers->filter(fn ($n) => $n % 2 === 0);
echo 'Evens: '.json_encode($evens->toArray())."\n\n";

// Example 8: Modifying data
echo "8. Modifying Data:\n";
$mutable = new FluentValue(['name' => 'Bob']);
$mutable->age = 35;
$mutable['email'] = 'bob@example.com';
$mutable->set('profile.avatar', 'avatar.jpg');

echo 'Modified: '.$mutable->toJson(JSON_PRETTY_PRINT)."\n\n";

// Example 9: Iteration
echo "9. Iteration:\n";
$items = new FluentValue(['apple' => 1, 'banana' => 2, 'cherry' => 3]);
foreach ($items as $fruit => $count) {
    echo "  $fruit: $count\n";
}
echo 'Total items: '.count($items)."\n\n";

// Example 10: Real-world API response
echo "10. Real-world API Response:\n";
$apiResponse = new FluentValue([
    'status' => 'success',
    'data' => [
        'user' => [
            'id' => 123,
            'name' => 'Alice Johnson',
            'email' => 'alice@example.com',
            'created_at' => '2024-01-15T10:30:00Z',
        ],
        'posts' => [
            ['id' => 1, 'title' => 'First Post'],
            ['id' => 2, 'title' => 'Second Post'],
        ],
    ],
    'meta' => [
        'timestamp' => fn () => date('Y-m-d H:i:s'),
        'request_id' => fn () => uniqid('req_'),
    ],
]);

if ($apiResponse->status === 'success') {
    $user = $apiResponse->data->user;
    echo "User: {$user->name} ({$user->email})\n";
    echo 'Posts: '.count($apiResponse->data->posts)."\n";
    echo "Request at: {$apiResponse->meta->timestamp}\n";
    echo "Request ID: {$apiResponse->meta->request_id}\n";
}

echo "\n=== Examples Complete ===\n";
