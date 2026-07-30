@extends('layouts.app')

@section('content')
<div class="page-header">
    <div>
        <div class="pill">Admin Oversight</div>
        <h1>Recommendation Configuration</h1>
        <p class="muted">Manage the Decision Tree settings, assistance computation values, impact thresholds, and optional Ollama refinement.</p>
    </div>
    <div class="header-actions">
        <a href="{{ route('admin.settings') }}" class="btn btn-secondary">Back to System Settings</a>
    </div>
</div>

@if(session('status'))
    <div class="form-success">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="form-error">{{ $errors->first() }}</div>
@endif

<section class="card">
    <div class="section-heading">
        <div>
            <h2>Recommendation Rules</h2>
            <p class="muted">Decision Tree remains the primary algorithm. These settings control assistance computation and whether optional Ollama refinement is allowed.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.settings.update') }}" class="form-grid">
        @csrf
        @method('PUT')

        <div>
            <label>Ollama Assistance</label>
            <select name="recommendation_use_ollama_assistance" required>
                <option value="1" @selected((bool) $settings['recommendation_use_ollama_assistance'])>Enabled</option>
                <option value="0" @selected(! (bool) $settings['recommendation_use_ollama_assistance'])>Disabled</option>
            </select>
        </div>
        <div>
            <label>Default Household Size</label>
            <input type="number" name="recommendation_default_household_size" min="1" value="{{ old('recommendation_default_household_size', $settings['recommendation_default_household_size']) }}" required>
        </div>

        <div>
            <label>Minor Food Multiplier</label>
            <input type="number" step="0.1" min="0.1" name="recommendation_food_multiplier_minor" value="{{ old('recommendation_food_multiplier_minor', $settings['recommendation_food_multiplier_minor']) }}" required>
        </div>
        <div>
            <label>Moderate Food Multiplier</label>
            <input type="number" step="0.1" min="0.1" name="recommendation_food_multiplier_moderate" value="{{ old('recommendation_food_multiplier_moderate', $settings['recommendation_food_multiplier_moderate']) }}" required>
        </div>
        <div>
            <label>Severe Food Multiplier</label>
            <input type="number" step="0.1" min="0.1" name="recommendation_food_multiplier_severe" value="{{ old('recommendation_food_multiplier_severe', $settings['recommendation_food_multiplier_severe']) }}" required>
        </div>

        <div>
            <label>Minor Medicine Divisor</label>
            <input type="number" min="1" name="recommendation_medicine_divisor_minor" value="{{ old('recommendation_medicine_divisor_minor', $settings['recommendation_medicine_divisor_minor']) }}" required>
        </div>
        <div>
            <label>Moderate Medicine Divisor</label>
            <input type="number" min="1" name="recommendation_medicine_divisor_moderate" value="{{ old('recommendation_medicine_divisor_moderate', $settings['recommendation_medicine_divisor_moderate']) }}" required>
        </div>
        <div>
            <label>Severe Medicine Divisor</label>
            <input type="number" min="1" name="recommendation_medicine_divisor_severe" value="{{ old('recommendation_medicine_divisor_severe', $settings['recommendation_medicine_divisor_severe']) }}" required>
        </div>
        <div>
            <label>Severe Medicine Multiplier</label>
            <input type="number" step="0.1" min="0.1" name="recommendation_medicine_multiplier_severe" value="{{ old('recommendation_medicine_multiplier_severe', $settings['recommendation_medicine_multiplier_severe']) }}" required>
        </div>

        <div>
            <label>Minor Cash Per Family</label>
            <input type="number" min="0" name="recommendation_cash_per_family_minor" value="{{ old('recommendation_cash_per_family_minor', $settings['recommendation_cash_per_family_minor']) }}" required>
        </div>
        <div>
            <label>Moderate Cash Per Family</label>
            <input type="number" min="0" name="recommendation_cash_per_family_moderate" value="{{ old('recommendation_cash_per_family_moderate', $settings['recommendation_cash_per_family_moderate']) }}" required>
        </div>
        <div>
            <label>Severe Cash Per Family</label>
            <input type="number" min="0" name="recommendation_cash_per_family_severe" value="{{ old('recommendation_cash_per_family_severe', $settings['recommendation_cash_per_family_severe']) }}" required>
        </div>

        <div>
            <label>Minor Cash Per Structure</label>
            <input type="number" min="0" name="recommendation_cash_per_structure_minor" value="{{ old('recommendation_cash_per_structure_minor', $settings['recommendation_cash_per_structure_minor']) }}" required>
        </div>
        <div>
            <label>Moderate Cash Per Structure</label>
            <input type="number" min="0" name="recommendation_cash_per_structure_moderate" value="{{ old('recommendation_cash_per_structure_moderate', $settings['recommendation_cash_per_structure_moderate']) }}" required>
        </div>
        <div>
            <label>Severe Cash Per Structure</label>
            <input type="number" min="0" name="recommendation_cash_per_structure_severe" value="{{ old('recommendation_cash_per_structure_severe', $settings['recommendation_cash_per_structure_severe']) }}" required>
        </div>

        <div>
            <label>Focused Validation Family Threshold</label>
            <input type="number" min="0" name="impact_focused_family_threshold" value="{{ old('impact_focused_family_threshold', $settings['impact_focused_family_threshold']) }}" required>
        </div>
        <div>
            <label>Focused Validation Structure Threshold</label>
            <input type="number" min="0" name="impact_focused_structure_threshold" value="{{ old('impact_focused_structure_threshold', $settings['impact_focused_structure_threshold']) }}" required>
        </div>
        <div>
            <label>Immediate Response Family Threshold</label>
            <input type="number" min="0" name="impact_immediate_family_threshold" value="{{ old('impact_immediate_family_threshold', $settings['impact_immediate_family_threshold']) }}" required>
        </div>
        <div>
            <label>Immediate Response Structure Threshold</label>
            <input type="number" min="0" name="impact_immediate_structure_threshold" value="{{ old('impact_immediate_structure_threshold', $settings['impact_immediate_structure_threshold']) }}" required>
        </div>

        <div class="button-row full-span">
            <button type="submit" class="btn btn-primary">Save System Settings</button>
        </div>
    </form>
</section>
@endsection
