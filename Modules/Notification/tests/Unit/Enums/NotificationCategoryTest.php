<?php

use Modules\Notification\Enums\NotificationCategory;

uses(Tests\TestCase::class);

test('has expected cases', function () {
    $cases = NotificationCategory::cases();

    expect($cases)->toHaveCount(5);
    expect($cases)->toContain(NotificationCategory::COMMUNICATION);
    expect($cases)->toContain(NotificationCategory::MARKETING);
    expect($cases)->toContain(NotificationCategory::SECURITY);
    expect($cases)->toContain(NotificationCategory::SYSTEM);
    expect($cases)->toContain(NotificationCategory::TRANSACTIONAL);
});

test('has correct values', function () {
    expect(NotificationCategory::COMMUNICATION->value)->toBe('communication');
    expect(NotificationCategory::MARKETING->value)->toBe('marketing');
    expect(NotificationCategory::SECURITY->value)->toBe('security');
    expect(NotificationCategory::SYSTEM->value)->toBe('system');
    expect(NotificationCategory::TRANSACTIONAL->value)->toBe('transactional');
});

test('label returns human readable string', function () {
    expect(NotificationCategory::COMMUNICATION->label())->toBe('Communication');
    expect(NotificationCategory::MARKETING->label())->toBe('Marketing');
    expect(NotificationCategory::SECURITY->label())->toBe('Security');
    expect(NotificationCategory::SYSTEM->label())->toBe('System Alerts');
    expect(NotificationCategory::TRANSACTIONAL->label())->toBe('Transactional');
});

test('description returns string', function () {
    foreach (NotificationCategory::cases() as $category) {
        expect($category->description())->toBeString()->not->toBeEmpty();
    }
});

test('icon returns string', function () {
    foreach (NotificationCategory::cases() as $category) {
        expect($category->icon())->toBeString()->not->toBeEmpty();
    }
});

test('can be created from value', function () {
    $category = NotificationCategory::from('security');

    expect($category)->toBe(NotificationCategory::SECURITY);
});

test('try from returns null for invalid value', function () {
    $category = NotificationCategory::tryFrom('invalid');

    expect($category)->toBeNull();
});
