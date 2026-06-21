@extends('layouts.app')

@section('content')
<div class="page-header">
    <div>
        <div class="pill">DSWD Disaster Dashboard</div>
        <h1>Affected Families Overview</h1>
        <p class="muted">Barangay impact, household member totals, and family-level records from validated disaster intake.</p>
    </div>
    <div class="header-actions">
        <a href="{{ route('dswd.recommendations') }}" class="btn btn-primary">View Recommendations</a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <span>Disaster Reports</span>
        <strong>{{ $stats['disaster_reports'] }}</strong>
    </div>
    <div class="stat-card">
        <span>Affected Families</span>
        <strong>{{ $stats['affected_families'] }}</strong>
    </div>
    <div class="stat-card">
        <span>Household Members</span>
        <strong>{{ $stats['household_members'] }}</strong>
    </div>
    <div class="stat-card">
        <span>Recommendations</span>
        <strong>{{ $stats['recommendations'] }}</strong>
    </div>
</div>

<div class="content-grid two-columns">
    <section class="card">
        <div class="section-heading">
            <div>
                <h2>Barangay Impact</h2>
                <p class="muted">Family and household member totals grouped by barangay.</p>
            </div>
        </div>

        <div class="list-table">
            @forelse($barangayImpacts as $row)
                <div class="list-row">
                    <div>
                        <strong>{{ $row['barangay'] }}</strong>
                        <p>{{ $row['families'] }} families | {{ $row['members'] }} household members | {{ $row['reports'] }} reports</p>
                    </div>
                    <span class="badge badge-{{ strtolower($row['severity']) === 'high' ? 'red' : (strtolower($row['severity']) === 'medium' ? 'amber' : 'green') }}">{{ $row['severity'] }}</span>
                </div>
            @empty
                <div class="list-row">
                    <div>
                        <strong>No disaster impact records</strong>
                        <p>Family-level details will appear after MDRRMO records disaster reports.</p>
                    </div>
                </div>
            @endforelse
        </div>
    </section>

    <section class="card">
        <div class="section-heading">
            <div>
                <h2>Recent Affected Families</h2>
                <p class="muted">Family names and household sizes for relief validation.</p>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Family</th>
                        <th>Barangay</th>
                        <th>Members</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($families as $family)
                        <tr>
                            <td>{{ $family->family_head_name }}</td>
                            <td>{{ $family->report?->location?->barangay?->barangay_name ?? 'Unassigned' }}</td>
                            <td>{{ $family->household_members }}</td>
                            <td>{{ $family->evacuation_status ?: 'Not specified' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">No affected family records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
