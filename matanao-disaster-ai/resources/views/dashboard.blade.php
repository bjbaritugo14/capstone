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
        <h1>Matanao Incident Assessment Dashboard</h1>
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
                <h2>Matanao GIS Incident Map</h2>
            </div>
            <span class="badge badge-blue">Leaflet</span>
        </div>
        <div class="map-shell">
            <div id="matanao-map" class="map-canvas" aria-label="Map of Matanao"></div>
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
                <h2>Relief Recommendations by Barangay</h2>
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
                <h2>Affected Barangay Priorities</h2>
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
                <h2>Latest Submitted Incidents</h2>
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
                <h2>Vehicular Accident Hotspots</h2>
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
                <h2>Submission-to-Validation Flow</h2>
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
            <div class="map-popup-line">${point.code} | ${point.barangay}</div>
            <div class="map-popup-line">${point.household_members} members | ${point.evacuation_status}</div>
            <div class="map-popup-badges">
                <span class="badge ${style.badge}">${point.severity}</span>
                <span class="badge badge-blue">Family Pin</span>
                <span class="badge badge-green">${point.status}</span>
            </div>`
            : `<strong class="map-popup-title">${point.barangay}</strong>
            <div class="map-popup-line">${point.code}</div>
            <div class="map-popup-line">${point.incident_type}</div>
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
