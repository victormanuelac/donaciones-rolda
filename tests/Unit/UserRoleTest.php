<?php

use App\Enums\UserRole;

test('tiene los 6 roles vigentes del sistema', function () {
    expect(array_column(UserRole::cases(), 'value'))->toBe([
        'admin', 'operator', 'coordinator', 'doctor', 'donor', 'municipal',
    ]);
});

test('cada rol tiene una etiqueta en español', function (UserRole $role) {
    expect($role->label())->toBeString()->not->toBeEmpty();
})->with(UserRole::cases());
