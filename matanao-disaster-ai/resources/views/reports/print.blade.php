@extends('layouts.app')

@section('content')
<div class="page-header">
    <div>
        <div class="pill">Printable Output</div>
        <h1>Damage Assessment Summary</h1>
        <p class="muted">Printable summary from the mdrrmo database for MDRRMO reporting and coordination.</p>
    </div>
    <div class="header-actions no-print">
        <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
    </div>
</div>

<div class="stats-grid print-stats">
    <div class="stat-card">
        <span>Reports</span>
        <strong>{{ $totals['reports'] }}</strong>
    </div>
    <div class="stat-card">
        <span>Families</span>
        <strong>{{ $totals['families'] }}</strong>
    </div>
    <div class="stat-card">
        <span>Houses</span>
        <strong>{{ $totals['houses'] }}</strong>
    </div>
</div>

<section class="card">
    <div class="section-heading">
        <div>
            <h2>Validated Disaster Reports</h2>
            <p class="muted">Prepared summary of validated and pending disaster report records.</p>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Event</th>
                    <th>Barangay</th>
                    <th>Type</th>
                    <th>Severity</th>
                    <th>Families</th>
                    <th>Houses</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($reports as $report)
                    <tr>
                        <td>{{ $report['id'] }}</td>
                        <td>{{ $report['event'] }}</td>
                        <td>{{ $report['barangay'] }}</td>
                        <td>{{ $report['type'] }}</td>
                        <td>{{ $report['severity'] }}</td>
                        <td>{{ $report['families'] }}</td>
                        <td>{{ $report['houses'] }}</td>
                        <td>{{ $report['status'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection
