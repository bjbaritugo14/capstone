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
        <div class="pill">DSWD Disaster Dashboard</div>
        <h1>Validated Affected Families Overview</h1>
    </div>
    <div class="header-actions">
        <a href="{{ route('dswd.validated-areas') }}" class="btn btn-secondary">Open Area Map</a>
        <a href="{{ route('dswd.recommendations') }}" class="btn btn-primary">Relief Recommendations</a>
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
                <h2>Barangay Family Impact</h2>
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
                <h2>Latest Validated Families</h2>
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

<div class="content-grid two-columns top-gap">
    <section class="card">
        <div class="section-heading">
            <div>
                <h2>Mapped Validated Areas</h2>
            </div>
            <span class="badge badge-blue">GIS Module</span>
        </div>

        <div class="mini-grid">
            <div><span>Validated Areas</span><strong>{{ $validatedAreaStats['areas'] }}</strong></div>
            <div><span>Covered Barangays</span><strong>{{ $validatedAreaStats['barangays'] }}</strong></div>
            <div><span>Family Pins</span><strong>{{ $validatedAreaStats['family_pins'] }}</strong></div>
        </div>

        <div class="list-table top-gap">
            @forelse($validatedAreaPreview as $area)
                <div class="list-row">
                    <div>
                        <strong>{{ $area['id'] }} | {{ $area['barangay'] }}</strong>
                        <p>{{ $area['disaster_type'] }} | {{ $area['affected_families'] }} families | {{ $area['pinpointed_families'] }} family pins</p>
                    </div>
                    <span class="badge badge-{{ strtolower($area['severity']) === 'high' ? 'red' : (strtolower($area['severity']) === 'medium' ? 'amber' : 'green') }}">{{ $area['severity'] }}</span>
                </div>
            @empty
                <div class="list-row">
                    <div>
                        <strong>No validated area records yet</strong>
                        <p>Validated disaster records with coordinates will appear here after MDRRMO review.</p>
                    </div>
                </div>
            @endforelse
        </div>

        <div class="top-gap">
            <a href="{{ route('dswd.validated-areas') }}" class="btn btn-primary">Open Area Map</a>
        </div>
    </section>

    <section class="card">
        <div class="section-heading">
            <div>
                <h2>Validated Area Map Preview</h2>
            </div>
            <span class="badge badge-blue">Map View</span>
        </div>

        <div class="map-shell">
            <div id="dswd-validated-area-preview-map" class="map-canvas" aria-label="Validated affected areas preview map"></div>
        </div>
        <div class="map-severity-legend" aria-label="Map legend">
            <span><i class="map-severity-dot low"></i><span class="badge badge-green">Low</span></span>
            <span><i class="map-severity-dot medium"></i><span class="badge badge-amber">Medium</span></span>
            <span><i class="map-severity-dot high"></i><span class="badge badge-red">High</span></span>
        </div>

        <div class="mini-grid top-gap">
            <div><span>Covered Barangays</span><strong>{{ $validatedAreaStats['barangays'] }}</strong></div>
            <div><span>Household Members</span><strong>{{ $validatedAreaStats['household_members'] }}</strong></div>
            <div><span>Family Pins</span><strong>{{ $validatedAreaStats['family_pins'] }}</strong></div>
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
    const previewMapCenter = @json($validatedAreaMapCenter);
    const previewMapPoints = @json($validatedAreaMapPoints);

    const previewMap = L.map('dswd-validated-area-preview-map', {
        zoomControl: true,
        scrollWheelZoom: true,
    }).setView([previewMapCenter.lat, previewMapCenter.lng], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(previewMap);

    const previewBounds = [];
    const severityStyles = {
        Low: { stroke: '#166534', fill: '#22c55e', badge: 'badge-green' },
        Medium: { stroke: '#b45309', fill: '#f59e0b', badge: 'badge-amber' },
        High: { stroke: '#b91c1c', fill: '#ef4444', badge: 'badge-red' },
    };

    function severityStyle(severity) {
        return severityStyles[severity] || severityStyles.Low;
    }

    previewMapPoints.forEach((point) => {
        let layer;
        const style = severityStyle(point.severity);

        if (point.kind === 'family_pin') {
            layer = L.circleMarker([point.lat, point.lng], {
                radius: 10,
                color: style.stroke,
                fillColor: style.fill,
                fillOpacity: 0.95,
                weight: 3,
            });
        } else {
            layer = L.circleMarker([point.lat, point.lng], {
                radius: point.severity === 'High' ? 11 : 9,
                color: style.stroke,
                fillColor: style.fill,
                fillOpacity: 0.86,
                weight: 2,
            });
        }

        layer.addTo(previewMap).bindPopup(
            `<strong class="map-popup-title">${point.title}</strong>
            <div class="map-popup-line">${point.subtitle}</div>
            <div class="map-popup-line">${point.note}</div>
            <div class="map-popup-line">${point.coordinates}</div>
            <div class="map-popup-badges">
                <span class="badge ${style.badge}">${point.severity}</span>
                ${point.kind === 'family_pin' ? '<span class="badge badge-blue">Family Pin</span>' : '<span class="badge badge-green">Validated Area</span>'}
            </div>`
        );

        if (point.kind === 'family_pin') {
            layer.on('mouseover', () => layer.openPopup());
            layer.bringToFront();
        }

        previewBounds.push([point.lat, point.lng]);
    });

    if (previewBounds.length > 0) {
        previewMap.fitBounds(previewBounds, { padding: [28, 28] });
    }
</script>
@endsection
