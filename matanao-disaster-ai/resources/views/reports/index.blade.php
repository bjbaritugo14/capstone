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
        <div class="pill">MDRRMO Disaster Damage Module</div>
        <h1>Disaster Damage Monitoring Map</h1>
        <p class="muted">Dedicated module for geotagged disaster damage data, location-based monitoring, and response analytics.</p>
    </div>
    <div class="header-actions">
        <a href="{{ route('reports.print') }}" class="btn btn-secondary">Open Printable Summary</a>
    </div>
</div>

@if (session('status'))
    <div class="form-success">{{ session('status') }}</div>
@endif

<div class="stats-grid">
    <div class="stat-card">
        <span>Total Damage Reports</span>
        <strong>{{ $stats['total'] }}</strong>
    </div>
    <div class="stat-card">
        <span>High Severity</span>
        <strong>{{ $stats['high_severity'] }}</strong>
    </div>
    <div class="stat-card">
        <span>Affected Families</span>
        <strong>{{ $stats['affected_families'] }}</strong>
    </div>
    <div class="stat-card">
        <span>Validated Reports</span>
        <strong>{{ $stats['validated'] }}</strong>
    </div>
</div>

<div class="content-grid two-columns">
    <section class="card">
        <div class="section-heading">
            <div>
                <h2>Disaster Damage Map</h2>
                <p class="muted">Mapped disaster damage reports across Matanao for rapid area monitoring and field verification.</p>
            </div>
            <span class="badge badge-blue">Leaflet</span>
        </div>
        <div class="map-shell">
            <div id="damage-map" class="map-canvas" aria-label="Disaster damage map"></div>
        </div>
    </section>

    <section class="card">
        <div class="section-heading">
            <div>
                <h2>Priority Impact Areas</h2>
                <p class="muted">Barangays ranked by affected families, structures, and highest recorded damage severity.</p>
            </div>
        </div>
        <div class="list-table">
            @forelse($impactAreas as $area)
                <div class="list-row">
                    <div>
                        <strong>{{ $area['location'] }}</strong>
                        <p>{{ $area['reports'] }} reports | {{ $area['families'] }} families | {{ $area['structures'] }} structures | {{ $area['priority'] }}</p>
                    </div>
                    <span class="badge badge-{{ strtolower($area['severity']) === 'high' ? 'red' : (strtolower($area['severity']) === 'medium' ? 'amber' : 'green') }}">{{ $area['severity'] }}</span>
                </div>
            @empty
                <div class="list-row">
                    <div>
                        <strong>No impact data</strong>
                        <p>Add disaster damage reports to generate barangay-level analytics.</p>
                    </div>
                </div>
            @endforelse
        </div>
    </section>
</div>

<div class="content-grid two-columns top-gap">
    <section class="card">
        <div class="section-heading">
            <div>
                <h2>Damage Analytics</h2>
                <p class="muted">Severity-based summary of recorded incidents, affected families, and structures.</p>
            </div>
        </div>
        <div class="analytics-strip">
            @foreach($severityAnalytics as $row)
                <div class="analytics-card">
                    <span class="badge badge-{{ strtolower($row['label']) === 'high' ? 'red' : (strtolower($row['label']) === 'medium' ? 'amber' : 'green') }}">{{ $row['label'] }}</span>
                    <strong>{{ $row['reports'] }}</strong>
                    <p>{{ $row['families'] }} families affected</p>
                    <small>{{ $row['structures'] }} structures affected</small>
                </div>
            @endforeach
        </div>
    </section>

    <section class="card">
        <div class="section-heading">
            <div>
                <h2>Disaster Type Breakdown</h2>
                <p class="muted">Distribution of damage reports per disaster type with impact totals.</p>
            </div>
        </div>
        <div class="list-table">
            @forelse($disasterTypes as $type)
                <div class="list-row">
                    <div>
                        <strong>{{ $type['type'] }}</strong>
                        <p>{{ $type['reports'] }} reports | {{ $type['families'] }} families | {{ $type['structures'] }} structures</p>
                    </div>
                    <span class="badge badge-{{ strtolower($type['severity']) === 'high' ? 'red' : (strtolower($type['severity']) === 'medium' ? 'amber' : 'green') }}">{{ $type['severity'] }}</span>
                </div>
            @empty
                <div class="list-row">
                    <div>
                        <strong>No disaster type data</strong>
                        <p>Create disaster reports to populate disaster-type analytics.</p>
                    </div>
                </div>
            @endforelse
        </div>
    </section>
</div>

<section class="card top-gap">
    <div class="section-heading">
        <div>
            <h2>Disaster Damage Records</h2>
            <p class="muted">Recorded incidents with disaster type, affected households, structures, location, and response notes.</p>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Report ID</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Barangay</th>
                    <th>Sitio/Purok</th>
                    <th>Road Segment</th>
                    <th>Disaster Type</th>
                    <th>Severity</th>
                    <th>Families</th>
                    <th>Structures</th>
                    <th>Needs</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reports as $report)
                    <tr>
                        <td>{{ $report['id'] }}</td>
                        <td>{{ $report['date'] }}</td>
                        <td>{{ $report['time'] }}</td>
                        <td>{{ $report['barangay'] }}</td>
                        <td>{{ $report['sitio_purok'] }}</td>
                        <td>{{ $report['road_segment'] }}</td>
                        <td>{{ $report['type'] }}</td>
                        <td>{{ $report['severity'] }}</td>
                        <td>{{ $report['families'] }}</td>
                        <td>{{ $report['houses'] }}</td>
                        <td>{{ $report['needs'] }}</td>
                        <td>{{ $report['status'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12">No disaster damage records found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

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

    const map = L.map('damage-map', {
        zoomControl: true,
        scrollWheelZoom: false,
    }).setView([mapCenter.lat, mapCenter.lng], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    const bounds = [];

    mapPoints.forEach((point) => {
        const marker = L.circleMarker([point.lat, point.lng], {
            radius: 8,
            color: point.severity === 'High' ? '#b91c1c' : (point.severity === 'Medium' ? '#b45309' : '#166534'),
            fillColor: point.severity === 'High' ? '#ef4444' : (point.severity === 'Medium' ? '#f59e0b' : '#22c55e'),
            fillOpacity: 0.8,
            weight: 2,
        }).addTo(map);

        marker.bindPopup(
            `<strong>${point.id}</strong><br>${point.type}<br>${point.barangay}<br>${point.road_segment}<br>${point.families} families | ${point.structures} structures<br>${point.severity} | ${point.status}`
        );

        bounds.push([point.lat, point.lng]);
    });

    bounds.push([mapCenter.lat, mapCenter.lng]);
    map.fitBounds(bounds, { padding: [28, 28] });

</script>
@endsection
