<?php

namespace App\Http\Controllers;

use App\Models\DamageReport;
use App\Models\ReportValidation;
use App\Models\VehicularAccident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ValidationController extends Controller
{
    public function index(): View
    {
        $pendingReports = DamageReport::query()
            ->with(['location.barangay', 'user'])
            ->where('status', 'pending')
            ->latest('created_at')
            ->get()
            ->map(fn (DamageReport $report) => [
                'id' => $report->report_id,
                'code' => 'REP-'.str_pad((string) $report->report_id, 4, '0', STR_PAD_LEFT),
                'type' => 'report',
                'barangay' => $report->location?->barangay?->barangay_name ?? 'Unassigned',
                'submitted_by' => $report->user?->full_name ?? 'Unknown user',
                'submitted_at' => $report->created_at,
                'disaster_type' => $report->disaster_type,
                'severity' => ucfirst($report->damage_severity),
                'families' => $report->affected_families,
                'module' => 'Disaster Assessment',
            ]);

        $recordedAccidents = VehicularAccident::query()
            ->with(['location.barangay', 'user'])
            ->where('status', 'recorded')
            ->latest('created_at')
            ->get()
            ->map(fn (VehicularAccident $accident) => [
                'id' => $accident->accident_id,
                'code' => 'ACC-'.str_pad((string) $accident->accident_id, 4, '0', STR_PAD_LEFT),
                'type' => 'accident',
                'barangay' => $accident->location?->barangay?->barangay_name ?? 'Unassigned',
                'submitted_by' => $accident->user?->full_name ?? 'Unknown user',
                'submitted_at' => $accident->created_at,
                'disaster_type' => $accident->accident_type ?? 'Vehicular Accident',
                'severity' => $accident->fatality_count > 0 ? 'Fatal' : ($accident->injured_count > 0 ? 'Injury' : 'Damage Only'),
                'families' => null,
                'module' => 'Vehicular Accident Monitoring',
            ]);

        $queue = $pendingReports->concat($recordedAccidents)->values()->all();

        $summary = [
            'pending' => count($queue),
            'returned' => DamageReport::where('status', 'returned')->count()
                + VehicularAccident::where('status', 'returned')->count(),
            'validated' => DamageReport::where('status', 'validated')->count()
                + VehicularAccident::where('status', 'validated')->count(),
        ];

        return view('validation.index', compact('queue', 'summary'));
    }

    public function showReport(DamageReport $report): View
    {
        $report->load(['location.barangay', 'user', 'images', 'affectedFamilyRecords.images', 'validations.validator']);

        return view('validation.show-report', compact('report'));
    }

    public function showAccident(VehicularAccident $accident): View
    {
        $accident->load(['location.barangay', 'user', 'involvedPersons', 'images']);

        return view('validation.show-accident', compact('accident'));
    }

    public function validateReport(Request $request, DamageReport $report): RedirectResponse
    {
        $report->update(['status' => 'validated']);

        ReportValidation::updateOrCreate(
            ['report_id' => $report->report_id],
            [
                'validated_by' => Auth::id(),
                'validation_status' => 'validated',
                'remarks' => 'Validated by MDRRMO.',
                'validated_at' => now(),
            ],
        );

        return redirect()
            ->route('validation.index')
            ->with('status', 'Report REP-'.str_pad((string) $report->report_id, 4, '0', STR_PAD_LEFT).' has been validated.');
    }

    public function returnReport(Request $request, DamageReport $report): RedirectResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $report->update(['status' => 'returned']);

        ReportValidation::updateOrCreate(
            ['report_id' => $report->report_id],
            [
                'validated_by' => Auth::id(),
                'validation_status' => 'returned',
                'remarks' => $request->input('reason'),
                'validated_at' => now(),
            ],
        );

        return redirect()
            ->route('validation.index')
            ->with('status', 'Report REP-'.str_pad((string) $report->report_id, 4, '0', STR_PAD_LEFT).' has been returned to mobile.');
    }

    public function validateAccident(Request $request, VehicularAccident $accident): RedirectResponse
    {
        $accident->update(['status' => 'validated']);

        return redirect()
            ->route('validation.index')
            ->with('status', 'Accident ACC-'.str_pad((string) $accident->accident_id, 4, '0', STR_PAD_LEFT).' has been validated.');
    }

    public function returnAccident(Request $request, VehicularAccident $accident): RedirectResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $accident->update(['status' => 'returned']);

        return redirect()
            ->route('validation.index')
            ->with('status', 'Accident ACC-'.str_pad((string) $accident->accident_id, 4, '0', STR_PAD_LEFT).' has been returned to mobile.');
    }
}
