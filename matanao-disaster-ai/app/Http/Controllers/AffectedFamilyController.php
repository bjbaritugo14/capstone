<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Models\DamageReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AffectedFamilyController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'name' => ['nullable', 'string', 'max:150'],
        ]);

        $name = trim((string) ($filters['name'] ?? ''));
        $isValidatedOnlyView = $this->isDswdView($request);

        $barangaySummaries = Barangay::query()
            ->with([
                'locations.disasterReports' => fn ($query) => $query
                    ->when($isValidatedOnlyView, fn ($reportQuery) => $reportQuery->where('status', 'validated'))
                    ->latest('incident_datetime'),
                'locations.disasterReports.affectedFamilyRecords' => fn ($query) => $query
                    ->when($name !== '', fn ($familyQuery) => $familyQuery->where('family_head_name', 'like', "%{$name}%")),
            ])
            ->orderBy('barangay_name')
            ->get()
            ->map(function (Barangay $barangay) use ($name) {
                $reports = $barangay->locations
                    ->flatMap->disasterReports
                    ->filter(fn ($report) => $name === '' || $report->affectedFamilyRecords->isNotEmpty())
                    ->sortByDesc('incident_datetime')
                    ->values();

                return [
                    'barangay' => $barangay,
                    'reports' => $reports,
                    'family_count' => $reports->sum(fn ($report) => $report->affectedFamilyRecords->count() ?: $report->affected_families),
                    'member_count' => $reports->sum(fn ($report) => $report->affectedFamilyRecords->sum('household_members')),
                ];
            })
            ->filter(function (array $group) use ($name, $isValidatedOnlyView) {
                if ($group['family_count'] > 0 || $group['reports']->isNotEmpty()) {
                    return true;
                }

                return $name === '' && ! $isValidatedOnlyView;
            })
            ->values();

        return view('affected-families.index', [
            'barangaySummaries' => $barangaySummaries,
            'name' => $name,
            'isValidatedOnlyView' => $isValidatedOnlyView,
        ]);
    }

    public function show(Request $request, Barangay $barangay): View
    {
        $filters = $request->validate([
            'name' => ['nullable', 'string', 'max:150'],
        ]);

        $name = trim((string) ($filters['name'] ?? ''));
        $isValidatedOnlyView = $this->isDswdView($request);

        $barangay->load([
            'locations.disasterReports' => fn ($query) => $query
                ->when($isValidatedOnlyView, fn ($reportQuery) => $reportQuery->where('status', 'validated'))
                ->latest('incident_datetime'),
            'locations.disasterReports.affectedFamilyRecords' => fn ($query) => $query
                ->when($name !== '', fn ($familyQuery) => $familyQuery->where('family_head_name', 'like', "%{$name}%")),
            'locations.disasterReports.affectedFamilyRecords.images',
            'locations.disasterReports.images',
            'locations.disasterReports.user',
        ]);

        $reports = $barangay->locations
            ->flatMap->disasterReports
            ->filter(fn ($report) => $name === '' || $report->affectedFamilyRecords->isNotEmpty())
            ->sortByDesc('incident_datetime')
            ->values();

        $selectedBarangayGroup = [
            'barangay' => $barangay,
            'reports' => $reports,
            'family_count' => $reports->sum(fn ($report) => $report->affectedFamilyRecords->count() ?: $report->affected_families),
            'member_count' => $reports->sum(fn ($report) => $report->affectedFamilyRecords->sum('household_members')),
        ];

        return view('affected-families.show', [
            'selectedBarangayGroup' => $selectedBarangayGroup,
            'name' => $name,
            'canDelete' => ! $isValidatedOnlyView,
            'isValidatedOnlyView' => $isValidatedOnlyView,
        ]);
    }

    public function destroy(Request $request, DamageReport $report): RedirectResponse
    {
        // Delete associated images from storage
        foreach ($report->images as $image) {
            Storage::disk('public')->delete($image->image_path);
        }

        // Delete related records
        $report->images()->delete();
        $report->affectedFamilyRecords()->delete();
        $report->validations()->delete();
        $report->recommendation()->delete();
        $report->delete();

        return redirect()
            ->route(
                $request->filled('barangay_id') ? 'affected-families.show' : 'affected-families.index',
                array_filter([
                    'barangay' => $request->input('barangay_id'),
                    'name' => $request->input('name'),
                ], fn ($value) => filled($value))
            )
            ->with('status', 'Report deleted successfully.');
    }

    protected function isDswdView(Request $request): bool
    {
        return (string) ($request->user()?->role?->role_name ?? '') === 'dswd';
    }
}
