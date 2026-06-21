@extends('layouts.app')

@section('head')
<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin=""
>
@endsection

@section('content')
<div class="page-header">
    <div>
        <h1>MDRRMO Assessment Dashboard</h1>
        <p class="muted">GIS-based monitoring, validated field reports, and Decision Tree recommendation outputs.</p>
    </div>
    <div class="header-actions">
        <a href="{{ route('reports.index') }}" class="btn btn-primary">Open Field Records</a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <span>Web Submissions</span>
        <strong>{{ $stats['web_submissions'] }}</strong>
    </div>
    <div class="stat-card">
        <span>Validated</span>
        <strong>{{ $stats['validated_reports'] }}</strong>
    </div>
    <div class="stat-card">
        <span>Affected Households</span>
        <strong>{{ $stats['affected_households'] }}</strong>
    </div>
    <div class="stat-card">
        <span>Accident Logs</span>
        <strong>{{ $stats['accident_logs'] }}</strong>
    </div>
</div>

<div class="content-grid two-columns">
    <section class="card">
        <div class="section-heading">
            <div>
                <h2>GIS Monitoring Map</h2>
                <p class="muted">Geotagged disaster and vehicular accident records inside Matanao.</p>
            </div>
            <span class="badge badge-blue">Leaflet</span>
        </div>
        <div class="map-shell">
            <div id="matanao-map" class="map-canvas" aria-label="Map of Matanao"></div>
        </div>
    </section>

    <section class="card">
        <div class="section-heading">
            <div>
                <h2>Decision Tree Recommendations</h2>
                <p class="muted">Assistance outputs for validated disaster-related reports.</p>
            </div>
            <span class="badge badge-green">Decision Tree</span>
        </div>

        <div class="stack-list">
            @foreach($recommendations as $item)
                <div class="stack-item">
                    <div class="stack-head">
                        <strong>{{ $item['barangay'] }}</strong>
                        <span class="badge badge-{{ strtolower($item['priority']) === 'high' ? 'red' : (strtolower($item['priority']) === 'medium' ? 'amber' : 'green') }}">{{ $item['priority'] }}</span>
                    </div>
                    <div class="mini-grid">
                        <div><span>Food Packs</span><strong>{{ $item['food_packs'] }}</strong></div>
                        <div><span>Medical Kits</span><strong>{{ $item['medical_kits'] }}</strong></div>
                        <div><span>Cash Assistance</span><strong>Php {{ number_format($item['cash_assistance']) }}</strong></div>
                    </div>
                    <p class="item-note">{{ $item['basis'] }}</p>
                </div>
            @endforeach
        </div>
    </section>
</div>

<div class="content-grid two-columns bottom-gap">
    <section class="card">
        <div class="section-heading">
            <div>
                <h2>Affected Barangays</h2>
                <p class="muted">Impact profile and corresponding response emphasis.</p>
            </div>
        </div>
        <div class="list-table">
            @foreach($impactByBarangay as $row)
                <div class="list-row">
                    <div>
                        <strong>{{ $row['name'] }}</strong>
                        <p>{{ $row['families'] }} families | {{ $row['recommendation'] }}</p>
                    </div>
                    <span class="badge badge-{{ strtolower($row['severity']) === 'high' ? 'red' : (strtolower($row['severity']) === 'medium' ? 'amber' : 'green') }}">{{ $row['severity'] }}</span>
                </div>
            @endforeach
        </div>
    </section>

    <section class="card">
        <div class="section-heading">
            <div>
                <h2>Recent Incident Entries</h2>
                <p class="muted">Latest geotagged submissions for dashboard monitoring.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Module</th>
                        <th>Barangay</th>
                        <th>Severity</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentReports as $report)
                        <tr>
                            <td>{{ $report['code'] }}</td>
                            <td>{{ $report['incident_type'] }}</td>
                            <td>{{ $report['barangay'] }}</td>
                            <td>{{ $report['severity'] }}</td>
                            <td>{{ $report['status'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="content-grid two-columns bottom-gap">
    <section class="card">
        <div class="section-heading">
            <div>
                <h2>Vehicular Accident Monitoring</h2>
                <p class="muted">Separate module for geotagged accident recording and hotspot review.</p>
            </div>
        </div>
        <div class="list-table">
            @foreach($accidentSummaries as $summary)
                <div class="list-row">
                    <div>
                        <strong>{{ $summary['location'] }}</strong>
                        <p>{{ $summary['incidents'] }} recorded incidents</p>
                    </div>
                    <span class="badge badge-{{ strtolower($summary['trend']) === 'high' ? 'red' : (strtolower($summary['trend']) === 'medium' ? 'amber' : 'green') }}">{{ $summary['trend'] }}</span>
                </div>
            @endforeach
        </div>
    </section>

    <section class="card">
        <div class="section-heading">
            <div>
                <h2>Process Overview</h2>
                <p class="muted">How reports move from submission to validation and recommendation output.</p>
            </div>
        </div>
        <div class="timeline-list">
            @foreach($workflow as $step)
                <div class="timeline-item">
                    <span>{{ $loop->iteration }}</span>
                    <p>{{ $step }}</p>
                </div>
            @endforeach
        </div>
    </section>
</div>
@endsection

@section('scripts')
<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""
></script>
<script>
    const mapCenter = @json($mapCenter);
    const mapPoints = @json($mapPoints);

    const map = L.map('matanao-map', {
        zoomControl: true,
        scrollWheelZoom: false,
    }).setView([mapCenter.lat, mapCenter.lng], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    const bounds = [];

    mapPoints.forEach((point) => {
        const marker = L.marker([point.lat, point.lng]).addTo(map);
        marker.bindPopup(
            `<strong>${point.barangay}</strong><br>${point.code}<br>${point.incident_type}<br>${point.severity} | ${point.status}`
        );
        bounds.push([point.lat, point.lng]);
    });

    bounds.push([mapCenter.lat, mapCenter.lng]);
    map.fitBounds(bounds, { padding: [28, 28] });
</script>
@endsection
