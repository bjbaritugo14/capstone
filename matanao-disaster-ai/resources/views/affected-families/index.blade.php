@extends('layouts.app')

@section('content')
<div class="page-header">
    <div>
        <div class="pill">Affected Family Registry</div>
        <h1>Affected Families Per Barangay</h1>
        <p class="muted">View family names, household size, damage report details, location, and attached report images.</p>
    </div>
</div>

@if(session('status'))
    <div class="form-success">{{ session('status') }}</div>
@endif

<section class="card bottom-gap">
    <form method="GET" action="{{ route('affected-families.index') }}" class="filter-form">
        <div>
            <label>Search Family Name</label>
            <input name="name" value="{{ $name }}" placeholder="Type affected family name">
        </div>
        <div>
            <label>Barangay</label>
            <select name="barangay_id">
                <option value="">All barangays</option>
                @foreach($allBarangays as $barangay)
                    <option value="{{ $barangay->barangay_id }}" @selected((int) $selectedBarangayId === $barangay->barangay_id)>
                        {{ $barangay->barangay_name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Search</button>
            <a href="{{ route('affected-families.index') }}" class="btn btn-secondary">Clear</a>
        </div>
    </form>
</section>

<div class="stack-list">
    @forelse($barangays as $group)
        <section class="card">
            <div class="section-heading">
                <div>
                    <h2>{{ $group['barangay']->barangay_name }}</h2>
                    <p class="muted">
                        {{ $group['family_count'] }} affected families
                        @if($group['member_count'] > 0)
                            | {{ $group['member_count'] }} household members
                        @endif
                    </p>
                </div>
                <span class="badge badge-blue">{{ $group['reports']->count() }} reports</span>
            </div>

            @forelse($group['reports'] as $report)
                <div class="registry-report-block">
                    <div class="stack-head">
                        <div>
                            <strong>REP-{{ str_pad((string) $report->report_id, 4, '0', STR_PAD_LEFT) }} | {{ $report->disaster_type }}</strong>
                            <p class="muted">
                                {{ ucfirst($report->damage_severity) }} | {{ ucfirst($report->status) }}
                                | {{ optional($report->incident_datetime)->format('Y-m-d h:i A') }}
                                | {{ $report->location?->sitio_purok ?: 'No sitio/purok' }}
                            </p>
                        </div>
                        <div class="button-row">
                            <span class="badge badge-{{ $report->damage_severity === 'severe' ? 'red' : ($report->damage_severity === 'moderate' ? 'amber' : 'green') }}">
                                {{ ucfirst($report->damage_severity) }}
                            </span>
                            <form method="POST" action="{{ route('affected-families.destroy', $report->report_id) }}" onsubmit="return confirm('Are you sure you want to delete this report and all its affected family records?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-delete">Delete</button>
                            </form>
                        </div>
                    </div>

                    <p class="item-note">{{ $report->description ?: 'No description provided.' }}</p>

                    <div class="table-wrap top-gap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Affected Family</th>
                                    <th>Household Members</th>
                                    <th>Contact</th>
                                    <th>Evacuation Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($report->affectedFamilyRecords as $family)
                                    <tr>
                                        <td>{{ $family->family_head_name }}</td>
                                        <td>{{ $family->household_members }}</td>
                                        <td>{{ $family->contact_number ?: 'N/A' }}</td>
                                        <td>{{ $family->evacuation_status ?: 'Not specified' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td>Family names not encoded</td>
                                        <td colspan="3">{{ $report->affected_families }} affected families recorded as total only.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($report->affectedFamilyRecords->where(fn ($family) => $family->images->isNotEmpty())->isNotEmpty())
                        <div class="top-gap">
                            <div class="section-heading compact-heading">
                                <div>
                                    <h2>Family Photos</h2>
                                    <p class="muted">Uploaded photos grouped under each affected family.</p>
                                </div>
                            </div>
                            <div class="stack-list">
                                @foreach($report->affectedFamilyRecords as $family)
                                    @if($family->images->isNotEmpty())
                                        <div class="stack-item">
                                            <strong>{{ $family->family_head_name }}</strong>
                                            <div class="report-image-grid top-gap">
                                                @foreach($family->images as $image)
                                                    <a href="{{ asset('storage/'.$image->image_path) }}" target="_blank" class="report-image-link">
                                                        <img src="{{ asset('storage/'.$image->image_path) }}" alt="{{ $family->family_head_name }} photo {{ $loop->iteration }}" style="width: 100%; border-radius: 8px; object-fit: cover; max-height: 180px;">
                                                        <small>{{ $image->image_path }}</small>
                                                    </a>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="top-gap">
                        <div class="section-heading compact-heading">
                            <div>
                                <h2>Report Photos</h2>
                                <p class="muted">General photos attached to this disaster report.</p>
                            </div>
                        </div>
                        <div class="report-image-grid">
                            @forelse($report->images->whereNull('family_id') as $image)
                                <a href="{{ asset('storage/'.$image->image_path) }}" target="_blank" class="report-image-link">
                                    <img src="{{ asset('storage/'.$image->image_path) }}" alt="Report photo {{ $loop->iteration }}" style="width: 100%; border-radius: 8px; object-fit: cover; max-height: 180px;">
                                    <small>{{ $image->image_path }}</small>
                                </a>
                            @empty
                                <div class="muted">No report images attached.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @empty
                <div class="stack-item">
                    <strong>No disaster reports in this barangay.</strong>
                    <p class="muted">Reports encoded under this barangay will appear here.</p>
                </div>
            @endforelse
        </section>
    @empty
        <section class="card">
            <h2>No barangays found.</h2>
            <p class="muted">Add barangays and disaster reports first.</p>
        </section>
    @endforelse
</div>
@endsection
