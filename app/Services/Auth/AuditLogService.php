<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class AuditLogService
{
    public function log(string $event, ?User $user = null, array $metadata = []): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $user?->id,
            'event' => $event,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => ! empty($metadata) ? json_encode($metadata) : null,
            'created_at' => now(),
        ]);
    }
}
