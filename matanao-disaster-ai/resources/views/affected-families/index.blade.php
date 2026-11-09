@extends('layouts.app')

@section('content')
<div class="page-header">
    <div>
        <h1>Affected Families Per Barangay</h1>
    </div>
</div>

@if(session('status'))
    <div class="form-success">{{ session('status') }}</div>
@endif

<section class="card bottom-gap">
    <form method="GET" action="{{ route('affected-families.index') }}" class="filter-form">
        <div class="full-span">
            <label>Search Family Name</label>
            <input name="name" value="{{ $name }}" placeholder="Type affected family name">
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Search</button>
            <a href="{{ route('affected-families.index') }}" class="btn btn-secondary">Clear</a>
        </div>
    </form>
</section>

<section class="card">
    <div class="section-heading">
        <div>
            <h2>Barangays</h2>
        </div>
        <span class="badge badge-blue">{{ $barangaySummaries->count() }} listed</span>
    </div>

    <div class="barangay-selector-list">
        @forelse($barangaySummaries as $group)
            <a
                href="{{ route('affected-families.show', array_filter(['barangay' => $group['barangay']->barangay_id, 'name' => $name])) }}"
                class="barangay-selector-card"
            >
                <div>
                    <strong>{{ $group['barangay']->barangay_name }}</strong>
                    <p>
                        {{ $group['family_count'] }} affected families
                        @if($group['member_count'] > 0)
                            | {{ $group['member_count'] }} household members
                        @endif
                    </p>
                </div>
                <span class="badge badge-amber">{{ $group['reports']->count() }} reports</span>
            </a>
        @empty
            <div class="stack-item">
                <strong>No barangays found.</strong>
                <p class="muted">
                    @if($isValidatedOnlyView)
                        Validate disaster reports first to populate the DSWD family registry.
                    @else
                        Add barangays and disaster reports first.
                    @endif
                </p>
            </div>
        @endforelse
    </div>
</section>
@endsection
