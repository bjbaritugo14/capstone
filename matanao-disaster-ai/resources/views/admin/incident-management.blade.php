@extends('layouts.app')

@section('content')
<div class="page-header">
    <div>
        <div class="pill">Admin Oversight</div>
        <h1>Incident/Data Management</h1>
        <p class="muted">View submitted reports, edit incorrect records, archive old records, and search or filter across disaster and accident data.</p>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <span>Total Records</span>
        <strong>{{ $stats['total'] }}</strong>
    </div>
    <div class="stat-card">
        <span>Disaster Reports</span>
        <strong>{{ $stats['reports'] }}</strong>
    </div>
    <div class="stat-card">
        <span>Accident Records</span>
        <strong>{{ $stats['accidents'] }}</strong>
    </div>
    <div class="stat-card">
        <span>Archived Records</span>
        <strong>{{ $stats['archived'] }}</strong>
    </div>
</div>

<section class="card bottom-gap">
    <form method="GET" action="{{ route('admin.incident-management') }}" class="filter-form">
        <div>
            <label>Search Records</label>
            <input name="search" value="{{ $search }}" placeholder="Search by type, barangay, description, or submitter">
        </div>
        <div>
            <label>Module</label>
            <select name="module">
                <option value="all" @selected($module === 'all')>All modules</option>
                <option value="report" @selected($module === 'report')>Disaster reports</option>
                <option value="accident" @selected($module === 'accident')>Accident records</option>
            </select>
        </div>
        <div>
            <label>Barangay</label>
            <select name="barangay_id">
                <option value="">All barangays</option>
                @foreach($barangays as $barangay)
                    <option value="{{ $barangay->barangay_id }}" @selected((int) $barangayId === $barangay->barangay_id)>{{ $barangay->barangay_name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Archive Status</label>
            <select name="archive">
                <option value="active" @selected($archive === 'active')>Active only</option>
                <option value="archived" @selected($archive === 'archived')>Archived only</option>
                <option value="all" @selected($archive === 'all')>All records</option>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Apply Filters</button>
            <a href="{{ route('admin.incident-management') }}" class="btn btn-secondary">Clear</a>
        </div>
    </form>
</section>

<div class="stack-list">
    @forelse($records as $record)
        <section class="card">
            <div class="section-heading">
                <div>
                    <h2>{{ $record['code'] }} | {{ $record['title'] }}</h2>
                    <p class="muted">
                        {{ strtoupper($record['type']) }} | {{ $record['barangay'] }} | {{ optional($record['incident_datetime'])->format('Y-m-d h:i A') }}
                        | Submitted by {{ $record['submitted_by'] }}
                    </p>
                </div>
                <div class="button-row">
                    <span class="badge badge-{{ strtolower($record['severity']) === 'high' ? 'red' : (strtolower($record['severity']) === 'medium' ? 'amber' : 'green') }}">{{ $record['severity'] }}</span>
                    <span class="badge badge-blue">{{ $record['status'] }}</span>
                    @if($record['archived_at'])
                        <span class="badge badge-amber">Archived</span>
                    @endif
                </div>
            </div>

            <p class="item-note">{{ $record['description'] }}</p>

            <div class="mini-grid" style="margin-top: 12px;">
                <div><span>Sitio/Purok</span><strong>{{ $record['sitio_purok'] }}</strong></div>
                <div><span>Road Segment</span><strong>{{ $record['road_segment'] }}</strong></div>
                <div><span>{{ $record['type'] === 'report' ? 'Affected Structures' : 'Vehicles Involved' }}</span><strong>{{ $record['structures'] }}</strong></div>
            </div>

            @if($record['type'] === 'report')
                <form method="POST" action="{{ route('admin.incident-management.reports.update', $record['id']) }}" class="form-grid top-gap">
                    @csrf
                    @method('PUT')
                    <div>
                        <label>Disaster Type</label>
                        <input name="disaster_type" value="{{ $record['title'] }}" required>
                    </div>
                    <div>
                        <label>Severity</label>
                        <select name="damage_severity" required>
                            <option value="minor" @selected(strtolower($record['severity']) === 'low')>Minor</option>
                            <option value="moderate" @selected(strtolower($record['severity']) === 'medium')>Moderate</option>
                            <option value="severe" @selected(strtolower($record['severity']) === 'high')>Severe</option>
                        </select>
                    </div>
                    <div>
                        <label>Status</label>
                        <select name="status" required>
                            <option value="pending" @selected(strtolower($record['status']) === 'pending')>Pending</option>
                            <option value="validated" @selected(strtolower($record['status']) === 'validated')>Validated</option>
                            <option value="returned" @selected(strtolower($record['status']) === 'returned')>Returned</option>
                            <option value="rejected" @selected(strtolower($record['status']) === 'rejected')>Rejected</option>
                        </select>
                    </div>
                    <div>
                        <label>Incident Date and Time</label>
                        <input type="datetime-local" name="incident_datetime" value="{{ optional($record['incident_datetime'])->format('Y-m-d\TH:i') }}" required>
                    </div>
                    <div>
                        <label>Affected Families</label>
                        <input type="number" name="affected_families" min="0" value="{{ $record['families'] ?? 0 }}" required>
                    </div>
                    <div>
                        <label>Affected Structures</label>
                        <input type="number" name="affected_structures" min="0" value="{{ $record['structures'] }}" required>
                    </div>
                    <div class="full-span">
                        <label>Description</label>
                        <textarea name="description" rows="3">{{ $record['description'] }}</textarea>
                    </div>
                    <div class="button-row full-span">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('admin.incident-management.reports.archive', $record['id']) }}" class="inline-form">
                    @csrf
                    <button type="submit" class="btn btn-secondary">{{ $record['archived_at'] ? 'Restore Record' : 'Archive Record' }}</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.incident-management.accidents.update', $record['id']) }}" class="form-grid top-gap">
                    @csrf
                    @method('PUT')
                    <div>
                        <label>Accident Type</label>
                        <input name="accident_type" value="{{ $record['title'] }}" required>
                    </div>
                    <div>
                        <label>Vehicle Type</label>
                        <input name="vehicle_type" value="{{ $record['vehicle_type'] }}">
                    </div>
                    <div>
                        <label>Status</label>
                        <select name="status" required>
                            <option value="recorded" @selected(strtolower($record['status']) === 'recorded')>Recorded</option>
                            <option value="validated" @selected(strtolower($record['status']) === 'validated')>Validated</option>
                            <option value="returned" @selected(strtolower($record['status']) === 'returned')>Returned</option>
                            <option value="verified" @selected(strtolower($record['status']) === 'verified')>Verified</option>
                            <option value="closed" @selected(strtolower($record['status']) === 'closed')>Closed</option>
                        </select>
                    </div>
                    <div>
                        <label>Incident Date and Time</label>
                        <input type="datetime-local" name="incident_datetime" value="{{ optional($record['incident_datetime'])->format('Y-m-d\TH:i') }}" required>
                    </div>
                    <div>
                        <label>Vehicles Involved</label>
                        <input type="number" name="vehicles_involved" min="1" value="{{ $record['structures'] }}" required>
                    </div>
                    <div>
                        <label>Injured Count</label>
                        <input type="number" name="injured_count" min="0" value="{{ $record['injured_count'] }}" required>
                    </div>
                    <div>
                        <label>Fatality Count</label>
                        <input type="number" name="fatality_count" min="0" value="{{ $record['fatality_count'] }}" required>
                    </div>
                    <div class="full-span">
                        <label>Description</label>
                        <textarea name="description" rows="3">{{ $record['description'] }}</textarea>
                    </div>
                    <div class="button-row full-span">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('admin.incident-management.accidents.archive', $record['id']) }}" class="inline-form">
                    @csrf
                    <button type="submit" class="btn btn-secondary">{{ $record['archived_at'] ? 'Restore Record' : 'Archive Record' }}</button>
                </form>
            @endif
        </section>
    @empty
        <section class="card">
            <h2>No records found.</h2>
            <p class="muted">Try changing the search text or filters to view submitted incidents.</p>
        </section>
    @endforelse
</div>
@endsection
