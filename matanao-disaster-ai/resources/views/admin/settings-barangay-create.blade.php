@extends('layouts.app')

@section('content')
<div class="page-header">
    <div>
        <h1>Add Barangay Option</h1>
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
            <h2>Create Barangay</h2>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.settings.barangays.store') }}" class="form-grid">
        @csrf
        <div>
            <label>Barangay Name</label>
            <input name="barangay_name" value="{{ old('barangay_name') }}" required>
        </div>
        <div>
            <label>Municipality</label>
            <input name="municipality" value="{{ old('municipality', 'Matanao') }}" required>
        </div>
        <div>
            <label>Province</label>
            <input name="province" value="{{ old('province', 'Davao del Sur') }}" required>
        </div>
        <div>
            <label>Status</label>
            <select name="status" required>
                <option value="active" @selected(old('status') === 'active')>Active</option>
                <option value="inactive" @selected(old('status', 'inactive') === 'inactive')>Inactive</option>
            </select>
        </div>
        <div class="button-row full-span">
            <button type="submit" class="btn btn-primary">Create Barangay</button>
        </div>
    </form>
</section>
@endsection
