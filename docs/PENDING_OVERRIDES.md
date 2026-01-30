# Pending Overrides Feature - Implementation Summary

## What Was Added

The pending overrides feature allows you to set values on FluentValue instances that wrap closures (unresolved values), and have those overrides merged when the closure is eventually resolved.

## How It Works

### 1. Internal State Tracking
- Added `$pendingOverrides` property to track overrides for unresolved closures
- Stores a hierarchical array structure matching the path to the override

### 2. Smart Detection
When you set a value using `$fluent->key->nested->value = 'something'`:
- The system checks if `$fluent->key` is a closure
- If it is, the override is stored as pending
- If it's already resolved, it sets normally

### 3. Merge on Access
When you access a value that has pending overrides:
- The closure is resolved first
- Pending overrides are merged over the resolved values
- The result is wrapped in a new FluentValue with the merged data

### 4. Persistence
- Overrides are stored until explicitly unset
- They apply every time the value is accessed
- You can change overrides by setting them again

## Implementation Details

### Key Methods Modified/Added

1. **`$pendingOverrides` property**
   - Stores overrides in hierarchical structure
   - Format: `['key' => ['nested' => ['value' => 'override']]]`

2. **`wrap(mixed $value, string $key = null)`**
   - Now accepts optional key parameter
   - Applies pending overrides when wrapping
   - Creates new instances with merged data

3. **`set(string $key, mixed $value)`**
   - Detects when setting on unresolved closure
   - Routes to `setPendingOverride()` for closures
   - Normal behavior for resolved values

4. **`setPendingOverride(string $key, array $path, mixed $value)`**
   - Stores override in `$pendingOverrides` array
   - Handles nested paths correctly

5. **`mergePendingOverrides(array $overrides)`**
   - Merges pending overrides into instance data
   - Handles recursive merging for nested arrays

6. **`toArray()`**
   - Applies pending overrides when converting to array
   - Ensures overrides are included in serialization

7. **`offsetUnset()`**
   - Clears pending overrides when unsetting

## Usage Examples

Use the library namespace in your code:

```php
use FuzzyFox\FluentValue;
```

### Basic Override
```php
$data = new FluentValue([
    'config' => fn() => ['theme' => 'dark']
]);

$data->config->theme = 'light';     // Override
$data->config->newKey = 'value';    // Add new

// Result: ['theme' => 'light', 'newKey' => 'value']
```

### Deep Nesting
```php
$app = new FluentValue([
    'settings' => fn() => [
        'ui' => ['theme' => 'dark', 'layout' => ['sidebar' => 'left']]
    ]
]);

$app->settings->ui->layout->sidebar = 'right';
$app->settings->ui->fontSize = 14;

// Merges at all levels when accessed
```

### Incremental Building
```php
$config = new FluentValue([
    'db' => fn() => ['host' => 'localhost', 'port' => 3306]
]);

// Build up configuration
$config->db->host = '127.0.0.1';
$config->db->user = 'root';
$config->db->options = ['charset' => 'utf8mb4'];

// All merged when converted to array
```

## Use Cases

1. **Configuration Management**
   - Start with defaults in closures
   - Override/extend based on environment or runtime conditions

2. **API Response Building**
   - Base response structure from closure
   - Add/override fields based on user permissions or context

3. **Form Data Processing**
   - Default values from closure
   - User input overrides defaults

4. **Dynamic Feature Flags**
   - Default features from configuration closure
   - Runtime overrides based on user/tenant

## Technical Considerations

### Memory
- Overrides are stored in memory until unset
- Each FluentValue instance tracks its own overrides
- Minimal overhead for typical usage

### Performance
- No performance impact when not using overrides
- Merge happens on access (lazy)
- O(n) merge complexity where n is number of overrides

### Edge Cases Handled
- ✅ Multiple levels of nesting
- ✅ Overrides persist across accesses
- ✅ Nested closures with overrides
- ✅ Array/object value overrides
- ✅ Dot notation support
- ✅ Unset clears overrides
- ✅ toArray() applies overrides

## Testing Coverage

Added comprehensive tests for:
- Basic pending overrides
- Deep nested overrides
- Persistence across accesses
- Dot notation with overrides
- toArray() with overrides
- Unset clearing overrides
- Multiple overrides on same closure
- Nested closures with overrides
- Array value overrides

All tests validate that:
1. Original closure values are preserved
2. Overrides are properly merged
3. New keys can be added
4. Overrides persist until unset
