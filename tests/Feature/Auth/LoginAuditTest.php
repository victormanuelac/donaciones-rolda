<?php

use App\Models\AuditLog;
use App\Models\User;

test('un login exitoso queda registrado en audit_logs con ip y user agent', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasNoErrors();

    $log = AuditLog::where('user_id', $user->id)->where('action', 'user_login')->first();

    expect($log)->not->toBeNull()
        ->and($log->ip_address)->not->toBeNull()
        ->and($log->user_agent)->not->toBeNull();
});

test('un intento de login fallido no genera auditoría', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    expect(AuditLog::where('user_id', $user->id)->where('action', 'user_login')->exists())->toBeFalse();
});
