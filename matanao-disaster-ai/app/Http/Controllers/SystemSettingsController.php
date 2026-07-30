<?php

namespace App\Http\Controllers;

use App\Models\Barangay;
use App\Services\AuditTrailService;
use App\Services\SystemSettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SystemSettingsController extends Controller
{
    public function __construct(
        protected SystemSettingService $settings,
    ) {}

    public function index(): View
    {
        $settings = $this->settings->all();
        $barangays = Barangay::query()
            ->orderByRaw("case when status = 'active' then 0 else 1 end")
            ->orderBy('barangay_name')
            ->get();

        $stats = [
            'active_barangays' => $barangays->where('status', 'active')->count(),
            'inactive_barangays' => $barangays->where('status', 'inactive')->count(),
            'ollama_mode' => $settings['recommendation_use_ollama_assistance'] ? 'Enabled' : 'Disabled',
            'default_household_size' => $settings['recommendation_default_household_size'],
        ];

        return view('admin.settings', compact('settings', 'barangays', 'stats'));
    }

    public function showRecommendations(): View
    {
        $settings = $this->settings->all();

        return view('admin.settings-recommendations', compact('settings'));
    }

    public function createBarangay(): View
    {
        return view('admin.settings-barangay-create');
    }

    public function showBarangay(Barangay $barangay): View
    {
        return view('admin.settings-barangay', compact('barangay'));
    }

    public function update(Request $request, AuditTrailService $auditTrail): RedirectResponse
    {
        $validated = $request->validate($this->settingRules());
        $before = $this->settings->subset(array_keys($this->settingRules()));

        $this->settings->updateMany($validated);
        $after = $this->settings->subset(array_keys($this->settingRules()));

        $changes = [];

        foreach ($after as $key => $value) {
            if ((string) $before[$key] === (string) $value) {
                continue;
            }

            $changes[$this->settings->label($key)] = [
                'before' => $this->settings->format($key, $before[$key]),
                'after' => $this->settings->format($key, $value),
            ];
        }

        $auditTrail->log(
            $request,
            'System Settings',
            'settings_updated',
            'Updated system settings for recommendations, thresholds, and assistance configuration.',
            null,
            $changes === [] ? ['note' => 'Settings were saved without value changes.'] : $changes,
            'SYS-SETTINGS',
        );

        return redirect()->route('admin.settings.recommendations')->with('status', 'System settings updated.');
    }

    public function storeBarangay(Request $request, AuditTrailService $auditTrail): RedirectResponse
    {
        $validated = $request->validate([
            'barangay_name' => ['required', 'string', 'max:100', 'unique:barangays,barangay_name'],
            'municipality' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $barangay = Barangay::create($validated);

        $auditTrail->log(
            $request,
            'System Settings',
            'barangay_created',
            'Created barangay option '.$barangay->barangay_name.'.',
            $barangay,
            [
                'municipality' => $barangay->municipality,
                'province' => $barangay->province,
                'status' => $barangay->status,
            ],
            $this->barangayCode($barangay),
        );

        return redirect()->route('admin.settings.barangays.create')->with('status', 'Barangay option created.');
    }

    public function updateBarangay(Request $request, Barangay $barangay, AuditTrailService $auditTrail): RedirectResponse
    {
        $before = $barangay->only(['barangay_name', 'municipality', 'province', 'status']);

        $validated = $request->validate([
            'barangay_name' => ['required', 'string', 'max:100', Rule::unique('barangays', 'barangay_name')->ignore($barangay->barangay_id, 'barangay_id')],
            'municipality' => ['required', 'string', 'max:100'],
            'province' => ['required', 'string', 'max:100'],
            'status' => ['required', 'in:active,inactive'],
        ]);

        $barangay->update($validated);

        $changes = [];

        foreach ($validated as $key => $value) {
            if ((string) $before[$key] === (string) $value) {
                continue;
            }

            $changes[$key] = [
                'before' => (string) $before[$key],
                'after' => (string) $value,
            ];
        }

        $auditTrail->log(
            $request,
            'System Settings',
            'barangay_updated',
            'Updated barangay option '.$barangay->barangay_name.'.',
            $barangay,
            $changes === [] ? ['note' => 'Barangay was saved without field changes.'] : $changes,
            $this->barangayCode($barangay),
        );

        return redirect()->route('admin.settings')->with('status', 'Barangay option updated.');
    }

    protected function settingRules(): array
    {
        return [
            'recommendation_use_ollama_assistance' => ['required', 'boolean'],
            'recommendation_default_household_size' => ['required', 'integer', 'min:1'],
            'recommendation_food_multiplier_minor' => ['required', 'numeric', 'min:0.1'],
            'recommendation_food_multiplier_moderate' => ['required', 'numeric', 'min:0.1'],
            'recommendation_food_multiplier_severe' => ['required', 'numeric', 'min:0.1'],
            'recommendation_medicine_divisor_minor' => ['required', 'integer', 'min:1'],
            'recommendation_medicine_divisor_moderate' => ['required', 'integer', 'min:1'],
            'recommendation_medicine_divisor_severe' => ['required', 'integer', 'min:1'],
            'recommendation_medicine_multiplier_severe' => ['required', 'numeric', 'min:0.1'],
            'recommendation_cash_per_family_minor' => ['required', 'integer', 'min:0'],
            'recommendation_cash_per_family_moderate' => ['required', 'integer', 'min:0'],
            'recommendation_cash_per_family_severe' => ['required', 'integer', 'min:0'],
            'recommendation_cash_per_structure_minor' => ['required', 'integer', 'min:0'],
            'recommendation_cash_per_structure_moderate' => ['required', 'integer', 'min:0'],
            'recommendation_cash_per_structure_severe' => ['required', 'integer', 'min:0'],
            'impact_focused_family_threshold' => ['required', 'integer', 'min:0'],
            'impact_focused_structure_threshold' => ['required', 'integer', 'min:0'],
            'impact_immediate_family_threshold' => ['required', 'integer', 'min:0'],
            'impact_immediate_structure_threshold' => ['required', 'integer', 'min:0'],
        ];
    }

    protected function barangayCode(Barangay $barangay): string
    {
        return 'BRG-'.str_pad((string) $barangay->barangay_id, 4, '0', STR_PAD_LEFT);
    }
}
