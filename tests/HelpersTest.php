<?php

use FuzzyFox\FluentValue;

it('fluent helper creates a FluentValue instance', function (): void {
    $fluent = fluent(['name' => 'Ada']);

    $this->assertInstanceOf(FluentValue::class, $fluent);
    $this->assertEquals('Ada', $fluent->name);
});

it('value helper returns plain values', function (): void {
    $this->assertEquals('plain', value('plain'));
});

it('value helper resolves closures with context', function (): void {
    $context = (object) ['name' => 'Turing'];

    $result = value(fn ($parent) => $parent->name, $context);

    $this->assertEquals('Turing', $result);
});
