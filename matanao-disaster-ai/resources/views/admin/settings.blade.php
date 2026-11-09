@extends('layouts.app')

@section('content')
<div class="page-header">
    <div>
        <h1>System Settings</h1>
    </div>
</div>

@if(session('status'))
    <div class="form-success">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="form-error">{{ $errors->first() }}</div>
@endif

<div class="stats-grid">
    <div class="stat-card">
        <span>Active Barangays</span>
        <strong>{{ $stats['active_barangays'] }}</strong>
    </div>
    <div class="stat-card">
        <span>Inactive Barangays</span>
        <strong>{{ $stats['inactive_barangays'] }}</strong>
    </div>
    <div class="stat-card">
        <span>Ollama Assistance</span>
        <strong>{{ $stats['ollama_mode'] }}</strong>
    </div>
    <div class="stat-card">
        <span>Default Household Size</span>
        <strong>{{ $stats['default_household_size'] }}</strong>
    </div>
</div>

<div class="content-grid two-columns top-gap">
    <section class="card">
        <a href="{{ route('admin.settings.recommendations') }}" class="barangay-selector-card">
            <div>
                <strong>Recommendation Configuration</strong>
            </div>
        </a>
    </section>

    <section class="card">
        <a href="{{ route('admin.settings.barangays.create') }}" class="barangay-selector-card">
            <div>
                <strong>Add Barangay Option</strong>
            </div>
        </a>
    </section>
</div>

<section class="card top-gap">
    <div class="section-heading">
        <div>
            <h2>Barangay Management</h2>
        </div>
        <span class="badge badge-blue">{{ $barangays->count() }} barangays</span>
    </div>

    <div class="barangay-selector-list">
        @foreach($barangays as $barangay)
            <a
                href="{{ route('admin.settings.barangays.show', $barangay) }}"
                class="barangay-selector-card"
            >
                <div>
                    <strong>{{ $barangay->barangay_name }}</strong>
                    <p>{{ $barangay->municipality }}, {{ $barangay->province }}</p>
                </div>
                <span class="badge badge-{{ $barangay->status === 'active' ? 'green' : 'amber' }}">{{ ucfirst($barangay->status) }}</span>
            </a>
        @endforeach
    </div>
</section>
@endsection
