<?php

namespace App\Listeners;

use App\Models\AuditLog;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

class LogSuccessfulLogin
{
    public function __construct(private Request $request)
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        AuditLog::create([
            'user_id' => $event->user->getAuthIdentifier(),
            'action' => 'user_login',
            'ip_address' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ]);
    }
}
