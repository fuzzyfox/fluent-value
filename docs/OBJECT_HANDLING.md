# Object Handling

FluentValue preserves objects rather than converting them to arrays. This is
important when you want to keep framework models or value objects intact.

## Behavior Summary
- Arrays are wrapped into FluentValue for fluent access.
- Objects remain objects and are accessed through properties or magic accessors.
- Objects with a `toArray()` method are converted during `toArray()`.
- Objects without `toArray()` remain objects inside the resulting array.

## Property Access

FluentValue reads object values in this order:
1. Public properties
2. `__get` magic accessors
3. ArrayAccess offsets

```php
use FuzzyFox\FluentValue;

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

## toArray Conversion

When converting to arrays, FluentValue checks for `toArray()` on objects and
uses it when available.

```php
use FuzzyFox\FluentValue;

$money = new class {
    public function toArray(): array
    {
        return ['amount' => 1500, 'currency' => 'USD'];
    }
};

$data = new FluentValue(['price' => $money]);

print_r($data->toArray());
// ['price' => ['amount' => 1500, 'currency' => 'USD']]
```

## Object Roots

You can create a FluentValue from an object directly. Access works the same
way, and `toArray()` will call `toArray()` on the object when available.

```php
$data = new FluentValue($model);
echo $data->name;
```
