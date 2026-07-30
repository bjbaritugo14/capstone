<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditTrailService
{
    public function log(
        Request $request,
        string $module,
        string $action,
        string $description,
        ?Model $target = null,
        array $details = [],
        ?string $targetLabel = null,
    ): AuditLog {
        $actor = $request->user();

        return AuditLog::create([
            'actor_id' => $actor?->user_id,
            'actor_name' => $actor?->full_name,
            'actor_role' => $actor?->role?->role_name,
            'module' => $module,
            'action' => $action,
            'target_type' => $target ? class_basename($target) : null,
            'target_id' => $target ? (int) $target->getKey() : null,
            'target_label' => $targetLabel,
            'description' => $description,
            'details' => $details,
            'ip_address' => $request->ip(),
            'created_at' => now(),
        ]);
    }
}
