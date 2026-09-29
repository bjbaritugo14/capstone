@extends('layouts.app')

@section('head')
<link
    rel="stylesheet"
    href="{{ asset('vendor/leaflet/leaflet.css') }}"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin=""
>
@endsection

@section('content')
<div class="page-header">
    <div>
        <div class="pill">Vehicular Accident Module</div>
        <h1>Matanao Vehicular Accident Map</h1>
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
                <h2>Geotagged Accident Reports</h2>
            </div>
            <span class="badge badge-blue">Leaflet</span>
        </div>
        <div class="map-shell">
            <div id="accident-map" class="map-canvas" aria-label="Vehicular accident map"></div>
        </div>
        <div class="map-severity-legend" aria-label="Severity legend">
            <span><i class="map-severity-dot low"></i><span class="badge badge-green">Low</span></span>
            <span><i class="map-severity-dot medium"></i><span class="badge badge-amber">Medium</span></span>
            <span><i class="map-severity-dot high"></i><span class="badge badge-red">High</span></span>
        </div>
    </section>
</div>

<div class="content-grid two-columns top-gap">
    <section class="card">
        <div class="section-heading">
            <div>
                <h2>Accident Totals by Barangay</h2>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Barangay</th>
                        <th>Total Accidents</th>
                        <th>High Severity</th>
                        <th>Trend</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($barangaySummaries as $summary)
                        <tr>
                            <td>{{ $summary['barangay'] }}</td>
                            <td>{{ $summary['incidents'] }}</td>
                            <td>{{ $summary['high_severity'] }}</td>
                            <td>{{ $summary['trend'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">No barangay accident summary available.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="section-heading">
            <div>
                <h2>High-Frequency Accident Locations</h2>
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
            <h2>Submitted Vehicular Accident Records</h2>
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
    src="{{ asset('vendor/leaflet/leaflet.js') }}"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""
></script>
<script>
    const mapCenter = @json($mapCenter);
    const mapPoints = @json($mapPoints);

    const map = L.map('accident-map', {
        zoomControl: true,
        scrollWheelZoom: true,
    }).setView([mapCenter.lat, mapCenter.lng], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    const bounds = [];
    const severityStyles = {
        Low: { stroke: '#166534', fill: '#22c55e', badge: 'badge-green' },
        Medium: { stroke: '#b45309', fill: '#f59e0b', badge: 'badge-amber' },
        High: { stroke: '#b91c1c', fill: '#ef4444', badge: 'badge-red' },
    };

    function severityStyle(severity) {
        return severityStyles[severity] || severityStyles.Low;
    }

    mapPoints.forEach((point) => {
        const style = severityStyle(point.severity);
        const marker = L.circleMarker([point.lat, point.lng], {
            radius: point.severity === 'High' ? 10 : 8,
            color: style.stroke,
            fillColor: style.fill,
            fillOpacity: 0.86,
            weight: 2,
        }).addTo(map);

        marker.bindPopup(
            `<strong class="map-popup-title">${point.id} | ${point.incident_type}</strong>
            <div class="map-popup-line">${point.road_segment}</div>
            <div class="map-popup-line">${point.barangay}</div>
            <div class="map-popup-badges">
                <span class="badge ${style.badge}">${point.severity}</span>
                <span class="badge badge-blue">${point.status}</span>
            </div>`
        );

        bounds.push([point.lat, point.lng]);
    });

    bounds.push([mapCenter.lat, mapCenter.lng]);
    map.fitBounds(bounds, { padding: [28, 28] });
</script>
@endsection
