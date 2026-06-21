<?php

namespace App\Http\Controllers;

use App\Models\AffectedFamily;
use App\Models\DamageReport;
use App\Models\ResourceRecommendation;
use Illuminate\View\View;

class DswdDashboardController extends Controller
{
    public function index(): View
    {
        $reports = DamageReport::query()
            ->with(['location.barangay', 'affectedFamilyRecords', 'recommendation'])
            ->latest('incident_datetime')
            ->get();

        $stats = [
            'disaster_reports' => $reports->count(),
            'affected_families' => AffectedFamily::count() > 0 ? AffectedFamily::count() : $reports->sum('affected_families'),
            'household_members' => AffectedFamily::sum('household_members'),
            'recommendations' => ResourceRecommendation::count(),
        ];

        $barangayImpacts = $reports
            ->groupBy(fn (DamageReport $report) => $report->location?->barangay?->barangay_name ?? 'Unassigned')
            ->map(fn ($items, $barangay) => [
                'barangay' => $barangay,
                'reports' => $items->count(),
                'families' => $items->sum(fn (DamageReport $report) => $report->affectedFamilyRecords->count() ?: $report->affected_families),
                'members' => $items->sum(fn (DamageReport $report) => $report->affectedFamilyRecords->sum('household_members')),
                'severity' => $this->highestSeverity($items->pluck('damage_severity')->all()),
            ])
            ->values()
            ->all();

        $families = AffectedFamily::query()
            ->with(['report.location.barangay'])
            ->latest('created_at')
            ->limit(20)
            ->get();

        return view('dswd.dashboard', compact('stats', 'barangayImpacts', 'families'));
    }

    protected function highestSeverity(array $severities): string
    {
        if (in_array('severe', $severities, true)) {
            return 'High';
        }

        if (in_array('moderate', $severities, true)) {
            return 'Medium';
        }

        return 'Low';
    }
}
