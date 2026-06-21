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
        <div class="pill">Vehicular Accident Module</div>
        <h1>Accident Monitoring Map</h1>
        <p class="muted">Dedicated module for geotagged vehicular accident records, hotspot monitoring, and location-based review.</p>
    </div>
</div>

@if (session('status'))
    <div class="form-success">{{ session('status') }}</div>
@endif

<div class="stats-grid">
    <div class="stat-card">
        <span>Total Records</span>
        <strong>{{ $stats['total'] }}</strong>
    </div>
    <div class="stat-card">
        <span>High Severity</span>
        <strong>{{ $stats['high_risk'] }}</strong>
    </div>
    <div class="stat-card">
        <span>Under Review</span>
        <strong>{{ $stats['under_review'] }}</strong>
    </div>
    <div class="stat-card">
        <span>Hotspot Areas</span>
        <strong>{{ $stats['hotspots'] }}</strong>
    </div>
</div>

<div class="content-grid two-columns">
    <section class="card">
        <div class="section-heading">
            <div>
                <h2>Accident Location Map</h2>
                <p class="muted">Mapped vehicular accident points across Matanao for quick visual monitoring.</p>
            </div>
            <span class="badge badge-blue">Leaflet</span>
        </div>
        <div class="map-shell">
            <div id="accident-map" class="map-canvas" aria-label="Vehicular accident map"></div>
        </div>
    </section>

    <section class="card">
        <div class="section-heading">
            <div>
                <h2>Accident Hotspots</h2>
                <p class="muted">Summary of accident-prone locations based on recorded database entries.</p>
            </div>
        </div>
        <div class="list-table">
            @forelse($hotspots as $hotspot)
                <div class="list-row">
                    <div>
                        <strong>{{ $hotspot['location'] }}</strong>
                        <p>{{ $hotspot['incidents'] }} recorded incidents</p>
                    </div>
                    <span class="badge badge-{{ strtolower($hotspot['trend']) === 'high' ? 'red' : (strtolower($hotspot['trend']) === 'medium' ? 'amber' : 'green') }}">{{ $hotspot['trend'] }}</span>
                </div>
            @empty
                <div class="list-row">
                    <div>
                        <strong>No hotspot data</strong>
                        <p>Add accident records to build hotspot summaries.</p>
                    </div>
                </div>
            @endforelse
        </div>
    </section>
</div>

<section class="card top-gap">
    <div class="section-heading">
        <div>
            <h2>Vehicular Accident Records</h2>
            <p class="muted">Recorded incidents with date, time, type, road segment, and geotagged location.</p>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Accident ID</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Barangay</th>
                    <th>Road Segment</th>
                    <th>Vehicle Type</th>
                    <th>Person Involved</th>
                    <th>Incident Type</th>
                    <th>Coordinates</th>
                    <th>Severity</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($accidents as $accident)
                    <tr>
                        <td>{{ $accident['id'] }}</td>
                        <td>{{ $accident['date'] }}</td>
                        <td>{{ $accident['time'] }}</td>
                        <td>{{ $accident['barangay'] }}</td>
                        <td>{{ $accident['road_segment'] }}</td>
                        <td>{{ $accident['vehicle_type'] }}</td>
                        <td>{{ $accident['person_name'] }}</td>
                        <td>{{ $accident['incident_type'] }}</td>
                        <td>{{ $accident['coordinates'] }}</td>
                        <td>{{ $accident['severity'] }}</td>
                        <td>{{ $accident['status'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11">No vehicular accident records found.</td>
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

    const map = L.map('accident-map', {
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
            `<strong>${point.id}</strong><br>${point.incident_type}<br>${point.road_segment}<br>${point.barangay}<br>${point.severity} | ${point.status}`
        );

        bounds.push([point.lat, point.lng]);
    });

    bounds.push([mapCenter.lat, mapCenter.lng]);
    map.fitBounds(bounds, { padding: [28, 28] });
</script>
@endsection
