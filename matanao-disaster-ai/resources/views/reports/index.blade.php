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
        <div class="pill">MDRRMO Disaster Damage Module</div>
        <h1>Matanao Disaster Damage Map</h1>
    </div>
    <div class="header-actions">
        <a href="{{ route('reports.print') }}" class="btn btn-secondary">Open Printable Barangay List</a>
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
                <h2>Geotagged Damage Reports</h2>
            </div>
            <span class="badge badge-blue">Leaflet</span>
        </div>
        <div class="map-shell">
            <div id="damage-map" class="map-canvas" aria-label="Disaster damage map"></div>
        </div>
        <div class="map-severity-legend" aria-label="Severity legend">
            <span><i class="map-severity-dot low"></i><span class="badge badge-green">Low</span></span>
            <span><i class="map-severity-dot medium"></i><span class="badge badge-amber">Medium</span></span>
            <span><i class="map-severity-dot high"></i><span class="badge badge-red">High</span></span>
        </div>
    </section>

    <section class="card">
        <div class="section-heading">
            <div>
                <h2>Barangay Impact Priorities</h2>
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
                <h2>Damage Severity Totals</h2>
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
                <h2>Damage Reports by Disaster Type</h2>
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
            <h2>Submitted Disaster Damage Records</h2>
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
    src="{{ asset('vendor/leaflet/leaflet.js') }}"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""
></script>
<script>
    const mapCenter = @json($mapCenter);
    const mapPoints = @json($mapPoints);

    const map = L.map('damage-map', {
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
        const isFamilyPin = point.kind === 'family_pin';
        const marker = L.circleMarker([point.lat, point.lng], isFamilyPin
            ? {
                radius: 10,
                color: style.stroke,
                fillColor: style.fill,
                fillOpacity: 0.95,
                weight: 3,
            }
            : {
                radius: point.severity === 'High' ? 10 : 8,
                color: style.stroke,
                fillColor: style.fill,
                fillOpacity: 0.86,
                weight: 2,
            }
        ).addTo(map);

        marker.bindPopup(
            isFamilyPin
            ? `<strong class="map-popup-title">${point.name}</strong>
            <div class="map-popup-line">${point.id} | ${point.barangay}</div>
            <div class="map-popup-line">${point.household_members} members | ${point.evacuation_status}</div>
            <div class="map-popup-badges">
                <span class="badge ${style.badge}">${point.severity}</span>
                <span class="badge badge-blue">Family Pin</span>
                <span class="badge badge-green">${point.status}</span>
            </div>`
            : `<strong class="map-popup-title">${point.id} | ${point.type}</strong>
            <div class="map-popup-line">${point.barangay}</div>
            <div class="map-popup-line">${point.road_segment}</div>
            <div class="map-popup-line">${point.families} families | ${point.structures} structures</div>
            <div class="map-popup-badges">
                <span class="badge ${style.badge}">${point.severity}</span>
                <span class="badge badge-blue">${point.status}</span>
            </div>`
        );

        if (isFamilyPin) {
            marker.on('mouseover', () => marker.openPopup());
            marker.bringToFront();
        }

        bounds.push([point.lat, point.lng]);
    });

    bounds.push([mapCenter.lat, mapCenter.lng]);
    map.fitBounds(bounds, { padding: [28, 28] });

</script>
@endsection
