<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AdminAuditTrailController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'module' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:150'],
        ]);

        $module = trim((string) ($filters['module'] ?? ''));
        $action = trim((string) ($filters['action'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));

        $filteredQuery = AuditLog::query()->with('actor');
        $this->applyFilters($filteredQuery, $module, $action, $search);

        $stats = [
            'total' => (clone $filteredQuery)->count(),
            'user_management' => (clone $filteredQuery)->where('module', 'User Management')->count(),
            'validation' => (clone $filteredQuery)->where('module', 'Validation')->count(),
            'recommendation' => (clone $filteredQuery)->where('module', 'Recommendation')->count(),
        ];

        $logs = $filteredQuery
            ->latest('created_at')
            ->limit(150)
            ->get()
            ->map(function (AuditLog $log): array {
                return [
                    'timestamp' => $this->formatTimestamp($log->created_at, 'Timestamp unavailable'),
                    'actor_name' => $log->actor_name ?: 'Unknown user',
                    'actor_role' => $log->actor_role ?: 'Unknown role',
                    'module' => $log->module,
                    'action' => Str::headline(str_replace('_', ' ', $log->action)),
                    'target' => $log->target_label ?: ($log->target_type ? $log->target_type.' #'.$log->target_id : 'System'),
                    'description' => $log->description,
                    'details' => collect($log->details ?? [])
                        ->map(fn ($value, $key) => [
                            'label' => Str::headline(str_replace('_', ' ', (string) $key)),
                            'value' => $this->detailValue($value),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->all();

        $modules = AuditLog::query()
            ->select('module')
            ->whereNotNull('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module')
            ->all();

        $actions = AuditLog::query()
            ->select('action')
            ->when($module !== '', fn ($query) => $query->where('module', $module))
            ->whereNotNull('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action')
            ->all();

        return view('admin.audit-trail', compact('logs', 'stats', 'modules', 'actions', 'module', 'action', 'search'));
    }

    protected function applyFilters(Builder $query, string $module, string $action, string $search): void
    {
        if ($module !== '') {
            $query->where('module', $module);
        }

        if ($action !== '') {
            $query->where('action', $action);
        }

        if ($search !== '') {
            $query->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery
                    ->where('actor_name', 'like', "%{$search}%")
                    ->orWhere('actor_role', 'like', "%{$search}%")
                    ->orWhere('target_label', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }
    }

    protected function detailValue(mixed $value): string
    {
        if (is_array($value)) {
            $before = $value['before'] ?? null;
            $after = $value['after'] ?? null;

            if ($before !== null || $after !== null) {
                return 'Before: '.$this->scalarValue($before).' | After: '.$this->scalarValue($after);
            }

            return collect($value)
                ->map(fn ($nestedValue, $nestedKey) => Str::headline((string) $nestedKey).': '.$this->scalarValue($nestedValue))
                ->implode(' | ');
        }

        return $this->scalarValue($value);
    }

    protected function scalarValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'N/A';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return (string) $value;
    }

    protected function formatTimestamp(mixed $value, string $fallback): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('M d, Y h:i A');
        }

        if (blank($value)) {
            return $fallback;
        }

        return date('M d, Y h:i A', strtotime((string) $value));
    }
}
