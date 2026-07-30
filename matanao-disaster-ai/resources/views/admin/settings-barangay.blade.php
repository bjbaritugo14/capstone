@extends('layouts.app')

@section('content')
<div class="page-header">
    <div>
        <div class="pill">Admin Oversight</div>
        <h1>{{ $barangay->barangay_name }}</h1>
        <p class="muted">Manage this barangay option and control whether it remains selectable for web and mobile reporting.</p>
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
            <h2>Edit Barangay</h2>
            <p class="muted">Update the barangay details below. Inactive barangays are hidden from new submissions.</p>
        </div>
        <span class="badge badge-{{ $barangay->status === 'active' ? 'green' : 'amber' }}">{{ ucfirst($barangay->status) }}</span>
    </div>

    <form method="POST" action="{{ route('admin.settings.barangays.update', $barangay) }}" class="form-grid">
        @csrf
        @method('PUT')
        <div>
            <label>Barangay Name</label>
            <input name="barangay_name" value="{{ old('barangay_name', $barangay->barangay_name) }}" required>
        </div>
        <div>
            <label>Municipality</label>
            <input name="municipality" value="{{ old('municipality', $barangay->municipality) }}" required>
        </div>
        <div>
            <label>Province</label>
            <input name="province" value="{{ old('province', $barangay->province) }}" required>
        </div>
        <div>
            <label>Status</label>
            <select name="status" required>
                <option value="active" @selected($barangay->status === 'active')>Active</option>
                <option value="inactive" @selected($barangay->status === 'inactive')>Inactive</option>
            </select>
        </div>
        <div class="button-row full-span">
            <button type="submit" class="btn btn-primary">Save Barangay</button>
        </div>
    </form>
</section>
@endsection
