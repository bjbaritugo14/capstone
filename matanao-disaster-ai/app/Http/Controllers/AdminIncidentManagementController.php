<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\DamageReport;
use App\Models\VehicularAccident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminIncidentManagementController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:150'],
            'module' => ['nullable', 'in:all,report,accident'],
            'barangay_id' => ['nullable', 'integer', 'exists:barangays,barangay_id'],
            'archive' => ['nullable', 'in:active,archived,all'],
        ]);

        $search = trim((string) ($filters['search'] ?? ''));
        $module = $filters['module'] ?? 'all';
        $archive = $filters['archive'] ?? 'active';
        $barangayId = $filters['barangay_id'] ?? null;

        $reports = collect();
        $accidents = collect();

        if (in_array($module, ['all', 'report'], true)) {
            $reports = DamageReport::query()
                ->with(['location.barangay', 'user', 'affectedFamilyRecords'])
                ->when($barangayId, fn ($query) => $query->whereHas('location', fn ($locationQuery) => $locationQuery->where('barangay_id', $barangayId)))
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($inner) use ($search) {
                        $inner->where('disaster_type', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%")
                            ->orWhereHas('location.barangay', fn ($barangayQuery) => $barangayQuery->where('barangay_name', 'like', "%{$search}%"))
                            ->orWhereHas('user', fn ($userQuery) => $userQuery->where('full_name', 'like', "%{$search}%"));
                    });
                })
                ->when($archive === 'active', fn ($query) => $query->whereNull('archived_at'))
                ->when($archive === 'archived', fn ($query) => $query->whereNotNull('archived_at'))
                ->latest('incident_datetime')
                ->get()
                ->map(fn (DamageReport $report) => [
                    'type' => 'report',
                    'id' => $report->report_id,
                    'code' => 'REP-'.str_pad((string) $report->report_id, 4, '0', STR_PAD_LEFT),
                    'barangay' => $report->location?->barangay?->barangay_name ?? 'Unassigned',
                    'title' => $report->disaster_type,
                    'description' => $report->description ?? 'No description provided.',
                    'severity' => ucfirst($report->damage_severity),
                    'status' => ucfirst($report->status),
                    'submitted_by' => $report->user?->full_name ?? 'Unknown user',
                    'incident_datetime' => $report->incident_datetime,
                    'road_segment' => $report->location?->road_segment ?? 'N/A',
                    'sitio_purok' => $report->location?->sitio_purok ?? 'N/A',
                    'families' => $report->affectedFamilyRecords->count() ?: $report->affected_families,
                    'structures' => $report->affected_structures,
                    'archived_at' => $report->archived_at,
                ]);
        }

        if (in_array($module, ['all', 'accident'], true)) {
            $accidents = VehicularAccident::query()
                ->with(['location.barangay', 'user', 'involvedPersons'])
                ->when($barangayId, fn ($query) => $query->whereHas('location', fn ($locationQuery) => $locationQuery->where('barangay_id', $barangayId)))
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($inner) use ($search) {
                        $inner->where('accident_type', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%")
                            ->orWhere('vehicle_type', 'like', "%{$search}%")
                            ->orWhere('involved_person_name', 'like', "%{$search}%")
                            ->orWhereHas('location.barangay', fn ($barangayQuery) => $barangayQuery->where('barangay_name', 'like', "%{$search}%"))
                            ->orWhereHas('user', fn ($userQuery) => $userQuery->where('full_name', 'like', "%{$search}%"));
                    });
                })
                ->when($archive === 'active', fn ($query) => $query->whereNull('archived_at'))
                ->when($archive === 'archived', fn ($query) => $query->whereNotNull('archived_at'))
                ->latest('incident_datetime')
                ->get()
                ->map(fn (VehicularAccident $accident) => [
                    'type' => 'accident',
                    'id' => $accident->accident_id,
                    'code' => 'ACC-'.str_pad((string) $accident->accident_id, 4, '0', STR_PAD_LEFT),
                    'barangay' => $accident->location?->barangay?->barangay_name ?? 'Unassigned',
                    'title' => $accident->accident_type,
                    'description' => $accident->description ?? 'No description provided.',
                    'severity' => $this->accidentSeverity($accident->injured_count, $accident->fatality_count),
                    'status' => ucfirst($accident->status),
                    'submitted_by' => $accident->user?->full_name ?? 'Unknown user',
                    'incident_datetime' => $accident->incident_datetime,
                    'vehicle_type' => $accident->vehicle_type ?? '',
                    'injured_count' => $accident->injured_count,
                    'fatality_count' => $accident->fatality_count,
                    'road_segment' => $accident->location?->road_segment ?? 'N/A',
                    'sitio_purok' => $accident->location?->sitio_purok ?? 'N/A',
                    'families' => null,
                    'structures' => $accident->vehicles_involved,
                    'archived_at' => $accident->archived_at,
                ]);
        }

        $records = $reports
            ->concat($accidents)
            ->sortByDesc('incident_datetime')
            ->values()
            ->all();

        $stats = [
            'total' => count($records),
            'reports' => count(array_filter($records, fn (array $record) => $record['type'] === 'report')),
            'accidents' => count(array_filter($records, fn (array $record) => $record['type'] === 'accident')),
            'archived' => count(array_filter($records, fn (array $record) => $record['archived_at'] !== null)),
        ];

        $barangays = Barangay::query()->orderBy('barangay_name')->get();

        return view('admin.incident-management', compact('records', 'stats', 'barangays', 'search', 'module', 'archive', 'barangayId'));
    }

    public function updateReport(Request $request, DamageReport $report): RedirectResponse
    {
        $validated = $request->validate([
            'disaster_type' => ['required', 'string', 'max:100'],
            'damage_severity' => ['required', 'in:minor,moderate,severe'],
            'status' => ['required', 'in:pending,validated,rejected,returned'],
            'affected_families' => ['required', 'integer', 'min:0'],
            'affected_structures' => ['required', 'integer', 'min:0'],
            'incident_datetime' => ['required', 'date'],
            'description' => ['nullable', 'string'],
        ]);

        $report->update($validated);

        return redirect()->route('admin.incident-management')->with('status', 'Disaster report updated.');
    }

    public function updateAccident(Request $request, VehicularAccident $accident): RedirectResponse
    {
        $validated = $request->validate([
            'accident_type' => ['required', 'string', 'max:100'],
            'vehicle_type' => ['nullable', 'string', 'max:100'],
            'status' => ['required', 'in:recorded,verified,closed,validated,returned'],
            'vehicles_involved' => ['required', 'integer', 'min:1'],
            'injured_count' => ['required', 'integer', 'min:0'],
            'fatality_count' => ['required', 'integer', 'min:0'],
            'incident_datetime' => ['required', 'date'],
            'description' => ['nullable', 'string'],
        ]);

        $accident->update($validated);

        return redirect()->route('admin.incident-management')->with('status', 'Accident record updated.');
    }

    public function archiveReport(DamageReport $report): RedirectResponse
    {
        $report->update(['archived_at' => $report->archived_at ? null : now()]);

        return redirect()->route('admin.incident-management')->with('status', $report->archived_at ? 'Disaster report archived.' : 'Disaster report restored.');
    }

    public function archiveAccident(VehicularAccident $accident): RedirectResponse
    {
        $accident->update(['archived_at' => $accident->archived_at ? null : now()]);

        return redirect()->route('admin.incident-management')->with('status', $accident->archived_at ? 'Accident record archived.' : 'Accident record restored.');
    }

    protected function accidentSeverity(int $injuredCount, int $fatalityCount): string
    {
        if ($fatalityCount > 0) {
            return 'High';
        }

        if ($injuredCount > 0) {
            return 'Medium';
        }

        return 'Low';
    }
}
