@extends('layouts.app')

@section('content')
<div class="page-header">
    <div>
        <div class="pill">MDRRMO Review</div>
        <h1>Validation Queue</h1>
        <p class="muted">Review all data submitted from mobile. Validate or return reports with a reason.</p>
    </div>
</div>

@if(session('status'))
    <div class="form-success">{{ session('status') }}</div>
@endif

<div class="stats-grid validation-stats">
    <div class="stat-card">
        <span>Pending</span>
        <strong>{{ $summary['pending'] }}</strong>
    </div>
    <div class="stat-card">
        <span>Returned</span>
        <strong>{{ $summary['returned'] }}</strong>
    </div>
    <div class="stat-card">
        <span>Validated</span>
        <strong>{{ $summary['validated'] }}</strong>
    </div>
</div>

<section class="card">
    <div class="section-heading">
        <div>
            <h2>Pending Submissions</h2>
            <p class="muted">Click on a record to view all submitted data and take action.</p>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Module</th>
                    <th>Barangay</th>
                    <th>Type / Severity</th>
                    <th>Submitted By</th>
                    <th>Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($queue as $row)
                    <tr>
                        <td><strong>{{ $row['code'] }}</strong></td>
                        <td>{{ $row['module'] }}</td>
                        <td>{{ $row['barangay'] }}</td>
                        <td>
                            {{ $row['disaster_type'] }}
                            <span class="badge badge-{{ strtolower($row['severity']) === 'severe' || strtolower($row['severity']) === 'fatal' ? 'red' : (strtolower($row['severity']) === 'moderate' || strtolower($row['severity']) === 'injury' ? 'amber' : 'green') }}">{{ $row['severity'] }}</span>
                        </td>
                        <td>{{ $row['submitted_by'] }}</td>
                        <td>{{ $row['submitted_at'] }}</td>
                        <td>
                            @if($row['type'] === 'report')
                                <a href="{{ route('validation.show-report', $row['id']) }}" class="btn btn-primary">Review</a>
                            @else
                                <a href="{{ route('validation.show-accident', $row['id']) }}" class="btn btn-primary">Review</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">No pending submissions. All records have been reviewed.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
