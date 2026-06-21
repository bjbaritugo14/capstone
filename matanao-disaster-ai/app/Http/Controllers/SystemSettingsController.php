<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class SystemSettingsController extends Controller
{
    public function index(): View
    {
        $settings = [
            ['label' => 'System Mode', 'value' => 'Sample UI Prototype'],
            ['label' => 'Map Provider', 'value' => 'Leaflet / OpenStreetMap'],
            ['label' => 'Decision Logic', 'value' => 'Decision Tree Ruleset'],
            ['label' => 'Web Input', 'value' => 'Geotagging and photo upload'],
            ['label' => 'Data Sync', 'value' => 'Simulated records'],
        ];

        return view('admin.settings', compact('settings'));
    }
}
