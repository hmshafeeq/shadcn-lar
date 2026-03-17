<?php

use Modules\Notification\Enums\NotificationChannel;

uses(Tests\TestCase::class);

test('has expected cases', function () {
    $cases = NotificationChannel::cases();

    expect($cases)->toHaveCount(4);
    expect($cases)->toContain(NotificationChannel::DATABASE);
    expect($cases)->toContain(NotificationChannel::EMAIL);
    expect($cases)->toContain(NotificationChannel::SMS);
    expect($cases)->toContain(NotificationChannel::PUSH);
});

test('has correct values', function () {
    expect(NotificationChannel::DATABASE->value)->toBe('database');
    expect(NotificationChannel::EMAIL->value)->toBe('email');
    expect(NotificationChannel::SMS->value)->toBe('sms');
    expect(NotificationChannel::PUSH->value)->toBe('push');
});

test('label returns human readable string', function () {
    expect(NotificationChannel::DATABASE->label())->toBe('In-App');
    expect(NotificationChannel::EMAIL->label())->toBe('Email');
    expect(NotificationChannel::SMS->label())->toBe('SMS');
    expect(NotificationChannel::PUSH->label())->toBe('Push');
});

test('description returns string', function () {
    foreach (NotificationChannel::cases() as $channel) {
        expect($channel->description())->toBeString()->not->toBeEmpty();
    }
});

test('icon returns string', function () {
    foreach (NotificationChannel::cases() as $channel) {
        expect($channel->icon())->toBeString()->not->toBeEmpty();
    }
});

test('driver returns correct driver', function () {
    expect(NotificationChannel::DATABASE->driver())->toBe('database');
    expect(NotificationChannel::EMAIL->driver())->toBe('mail');
    expect(NotificationChannel::SMS->driver())->toBe('vonage');
    expect(NotificationChannel::PUSH->driver())->toBe('fcm');
});

test('can be created from value', function () {
    $channel = NotificationChannel::from('email');

    expect($channel)->toBe(NotificationChannel::EMAIL);
});

test('try from returns null for invalid value', function () {
    $channel = NotificationChannel::tryFrom('invalid');

    expect($channel)->toBeNull();
});
