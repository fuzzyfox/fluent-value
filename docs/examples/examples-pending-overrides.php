<?php

require_once __DIR__.'/vendor/autoload.php';

use FuzzyFox\FluentValue;

echo "=== Pending Overrides Feature Examples ===\n\n";

// Example 1: Basic pending override
echo "1. Basic Pending Override:\n";
$config = new FluentValue([
    'database' => fn ($parent) => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'myapp',
    ],
]);

echo "Before override - host: {$config->database->host}\n";

// Set a value on the unresolved closure
$config->database->host = '127.0.0.1';
$config->database->username = 'root';

echo "After override - host: {$config->database->host}\n";
echo "New key - username: {$config->database->username}\n";
echo "Original value still there - port: {$config->database->port}\n\n";

// Example 2: Deep nesting with pending overrides
echo "2. Deep Nesting with Pending Overrides:\n";
$app = new FluentValue([
    'settings' => fn ($parent) => [
        'ui' => [
            'theme' => 'dark',
            'layout' => [
                'sidebar' => 'left',
                'header' => 'fixed',
            ],
        ],
    ],
]);

// Set deep nested values before the closure is resolved
$app->settings->ui->layout->sidebar = 'right';
$app->settings->ui->layout->footer = 'sticky';
$app->settings->ui->fontSize = 14;

echo "Theme (original): {$app->settings->ui->theme}\n";
echo "Sidebar (overridden): {$app->settings->ui->layout->sidebar}\n";
echo "Footer (new): {$app->settings->ui->layout->footer}\n";
echo "Font size (new): {$app->settings->ui->fontSize}\n\n";

// Example 3: Overrides persist across accesses
echo "3. Overrides Persist Across Multiple Accesses:\n";
$data = new FluentValue([
    'counter' => fn ($parent) => [
        'value' => 0,
        'label' => 'Count',
    ],
]);

$data->counter->value = 5;

echo "First access: {$data->counter->value}\n";
echo "Second access: {$data->counter->value}\n";
echo "Third access: {$data->counter->value}\n";
echo "Label still there: {$data->counter->label}\n\n";

// Example 4: Building up configuration incrementally
echo "4. Building Configuration Incrementally:\n";
$apiConfig = new FluentValue([
    'api' => fn ($parent) => [
        'version' => 'v1',
        'timeout' => 30,
    ],
]);

// Build up the config step by step
$apiConfig->api->timeout = 60;
$apiConfig->api->retries = 3;
$apiConfig->api->headers = [
    'Accept' => 'application/json',
    'User-Agent' => 'MyApp/1.0',
];

echo "Configuration built incrementally:\n";
print_r($apiConfig->toArray());
echo "\n";

// Example 5: Dynamic form with computed and overridden values
echo "5. Dynamic Form with Computed and Overridden Values:\n";
$form = new FluentValue([
    'user' => fn ($parent) => [
        'firstName' => '',
        'lastName' => '',
        'email' => '',
        'fullName' => fn ($p) => trim($p->firstName.' '.$p->lastName),
    ],
]);

// User fills out the form
$form->user->firstName = 'John';
$form->user->lastName = 'Doe';
$form->user->email = 'john@example.com';
$form->user->verified = true; // Add a new field

echo "Full name (computed): {$form->user->fullName}\n";
echo "Email (set): {$form->user->email}\n";
echo 'Verified (new field): '.($form->user->verified ? 'Yes' : 'No')."\n\n";

// Example 6: Nested closures with overrides at multiple levels
echo "6. Nested Closures with Overrides at Multiple Levels:\n";
$complex = new FluentValue([
    'app' => fn ($parent) => [
        'name' => 'MyApp',
        'environment' => fn ($p) => [
            'type' => 'production',
            'settings' => [
                'debug' => false,
            ],
        ],
    ],
]);

// Override at multiple nesting levels
$complex->app->version = '1.0.0';
$complex->app->environment->type = 'development';
$complex->app->environment->settings->debug = true;
$complex->app->environment->settings->logLevel = 'verbose';

echo "App name (original): {$complex->app->name}\n";
echo "App version (new): {$complex->app->version}\n";
echo "Env type (overridden): {$complex->app->environment->type}\n";
echo 'Debug (overridden): '.($complex->app->environment->settings->debug ? 'true' : 'false')."\n";
echo "Log level (new): {$complex->app->environment->settings->logLevel}\n\n";

// Example 7: Practical use case - API response with defaults and overrides
echo "7. API Response with Defaults and Overrides:\n";
$response = new FluentValue([
    'data' => fn ($parent) => [
        'user' => [
            'id' => 123,
            'name' => 'Default User',
            'preferences' => [
                'notifications' => true,
                'theme' => 'system',
            ],
        ],
        'meta' => [
            'cached' => false,
            'timestamp' => time(),
        ],
    ],
]);

// Override with user-specific data
$response->data->user->name = 'Alice Johnson';
$response->data->user->preferences->theme = 'dark';
$response->data->user->avatar = 'avatar.jpg';
$response->data->meta->cached = true;

echo "User: {$response->data->user->name}\n";
echo "User ID (original): {$response->data->user->id}\n";
echo "Theme (overridden): {$response->data->user->preferences->theme}\n";
echo "Avatar (new): {$response->data->user->avatar}\n";
echo 'Cached (overridden): '.($response->data->meta->cached ? 'Yes' : 'No')."\n\n";

// Example 8: Using dot notation with pending overrides
echo "8. Dot Notation with Pending Overrides:\n";
$dotConfig = new FluentValue([
    'settings' => fn ($parent) => [
        'display' => [
            'width' => 1920,
            'height' => 1080,
        ],
    ],
]);

$dotConfig->set('settings.display.width', 2560);
$dotConfig->set('settings.display.height', 1440);
$dotConfig->set('settings.display.refreshRate', 144);

echo "Width (overridden): {$dotConfig->get('settings.display.width')}\n";
echo "Height (overridden): {$dotConfig->get('settings.display.height')}\n";
echo "Refresh rate (new): {$dotConfig->get('settings.display.refreshRate')}\n\n";

// Example 9: Unset clears overrides
echo "9. Unset Clears Pending Overrides:\n";
$resetable = new FluentValue([
    'config' => fn ($parent) => ['value' => 'original'],
]);

$resetable->config->value = 'modified';
echo "Before unset: {$resetable->get('config.value')}\n";

unset($resetable['config']);
$resetable->config = fn ($parent) => ['value' => 'original'];

echo "After reset: {$resetable->get('config.value')}\n\n";

// Example 10: Real-world Laravel-like use case
echo "10. Laravel-like Configuration Merging:\n";
$laravelConfig = new FluentValue([
    'cache' => fn ($parent) => [
        'default' => 'file',
        'stores' => [
            'file' => [
                'driver' => 'file',
                'path' => storage_path('framework/cache/data'),
            ],
            'redis' => [
                'driver' => 'redis',
                'connection' => 'cache',
            ],
        ],
    ],
]);

// Override for testing environment
$laravelConfig->cache->default = 'array';
$laravelConfig->cache->stores->array = [
    'driver' => 'array',
    'serialize' => false,
];

// Runtime override for specific connection
$laravelConfig->cache->stores->redis->connection = 'cache-cluster';
$laravelConfig->cache->stores->redis->timeout = 60;

echo "Default driver: {$laravelConfig->cache->default}\n";
echo "Redis connection: {$laravelConfig->cache->stores->redis->connection}\n";
echo "Redis timeout (new): {$laravelConfig->cache->stores->redis->timeout}\n";
echo "\nFull config:\n";
print_r($laravelConfig->toArray());

echo "\n=== Examples Complete ===\n";

// Helper function (would normally be in Laravel)
function storage_path($path)
{
    return '/storage/'.$path;
}
