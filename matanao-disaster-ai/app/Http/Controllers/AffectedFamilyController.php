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
            'barangay_id' => ['nullable', 'integer', 'exists:barangays,barangay_id'],
        ]);

        $name = trim((string) ($filters['name'] ?? ''));
        $selectedBarangayId = $filters['barangay_id'] ?? null;

        $barangays = Barangay::query()
            ->when($selectedBarangayId, fn ($query) => $query->where('barangay_id', $selectedBarangayId))
            ->with([
                'locations.disasterReports.affectedFamilyRecords' => fn ($query) => $query
                    ->when($name !== '', fn ($familyQuery) => $familyQuery->where('family_head_name', 'like', "%{$name}%")),
                'locations.disasterReports.affectedFamilyRecords.images',
                'locations.disasterReports.images',
                'locations.disasterReports.user',
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
            ->filter(fn (array $group) => $group['reports']->isNotEmpty() || $name === '')
            ->values();

        $allBarangays = Barangay::query()
            ->orderBy('barangay_name')
            ->get();

        return view('affected-families.index', compact('barangays', 'allBarangays', 'name', 'selectedBarangayId'));
    }

    public function destroy(DamageReport $report): RedirectResponse
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
            ->route('affected-families.index')
            ->with('status', 'Report deleted successfully.');
    }
}
