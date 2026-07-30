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
        <div class="pill">DSWD Validated Area Module</div>
        <h1>Validated Affected Areas GIS Map</h1>
        <p class="muted">Review validated affected areas, inspect mapped family pins, and monitor relief coverage by barangay.</p>
    </div>
    <div class="header-actions">
        <a href="{{ route('dswd.dashboard') }}" class="btn btn-secondary">Back to DSWD Dashboard</a>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <span>Validated Areas</span>
        <strong>{{ $stats['validated_areas'] }}</strong>
    </div>
    <div class="stat-card">
        <span>Covered Barangays</span>
        <strong>{{ $stats['covered_barangays'] }}</strong>
    </div>
    <div class="stat-card">
        <span>Affected Families</span>
        <strong>{{ $stats['affected_families'] }}</strong>
    </div>
    <div class="stat-card">
        <span>Family GIS Pins</span>
        <strong>{{ $stats['family_pins'] }}</strong>
    </div>
</div>

<div class="content-grid two-columns">
    <section class="card">
        <div class="section-heading">
            <div>
                <h2>Validated Area GIS Map</h2>
                <p class="muted">Red, amber, and green circles represent validated affected areas. Small blue markers represent geotagged family locations.</p>
            </div>
            <span class="badge badge-blue">Leaflet</span>
        </div>

        <div class="map-shell">
            <div id="validated-areas-map" class="map-canvas" aria-label="Validated affected areas GIS map"></div>
        </div>

        <div class="top-gap">
            <div class="mini-grid">
                <div><span>Map Pins</span><strong>{{ count($mapPoints) }}</strong></div>
                <div><span>Area Markers</span><strong>{{ collect($mapPoints)->where('kind', 'validated_area')->count() }}</strong></div>
                <div><span>Family Markers</span><strong>{{ collect($mapPoints)->where('kind', 'family_pin')->count() }}</strong></div>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="section-heading">
            <div>
                <h2>Barangay Coverage</h2>
                <p class="muted">Validated-area totals per barangay to help DSWD prioritize site visits and relief dispatch.</p>
            </div>
        </div>

        <div class="list-table">
            @forelse($barangayCoverage as $row)
                <div class="list-row">
                    <div>
                        <strong>{{ $row['barangay'] }}</strong>
                        <p>{{ $row['areas'] }} areas | {{ $row['families'] }} families | {{ $row['members'] }} members | {{ $row['family_pins'] }} family pins</p>
                    </div>
                    <span class="badge badge-{{ strtolower($row['severity']) === 'high' ? 'red' : (strtolower($row['severity']) === 'medium' ? 'amber' : 'green') }}">{{ $row['severity'] }}</span>
                </div>
            @empty
                <div class="list-row">
                    <div>
                        <strong>No validated coverage data</strong>
                        <p>Validate disaster reports first to populate barangay-level GIS coverage.</p>
                    </div>
                </div>
            @endforelse
        </div>
    </section>
</div>

<section class="card top-gap">
    <div class="section-heading">
        <div>
            <h2>Validated Affected Area Records</h2>
            <p class="muted">Only validated disaster reports appear here, including their latest validation details and available family-level coordinates.</p>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Area ID</th>
                    <th>Barangay</th>
                    <th>Sitio/Purok</th>
                    <th>Disaster Type</th>
                    <th>Severity</th>
                    <th>Families</th>
                    <th>Members</th>
                    <th>Family Pins</th>
                    <th>Validated By</th>
                    <th>Validated At</th>
                </tr>
            </thead>
            <tbody>
                @forelse($areas as $area)
                    <tr>
                        <td>{{ $area['id'] }}</td>
                        <td>{{ $area['barangay'] }}</td>
                        <td>{{ $area['sitio_purok'] }}</td>
                        <td>{{ $area['disaster_type'] }}</td>
                        <td>{{ $area['severity'] }}</td>
                        <td>{{ $area['affected_families'] }}</td>
                        <td>{{ $area['household_members'] }}</td>
                        <td>{{ $area['pinpointed_families'] }}</td>
                        <td>{{ $area['validated_by'] }}</td>
                        <td>{{ $area['validated_at_label'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10">No validated affected area records found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<div class="content-grid two-columns top-gap">
    <section class="card">
        <div class="section-heading">
            <div>
                <h2>Latest Validated Areas</h2>
                <p class="muted">Area-by-area snapshots for rapid case review during DSWD coordination.</p>
            </div>
        </div>

        <div class="stack-list">
            @forelse($areas as $area)
                @break($loop->index >= 6)
                <div class="stack-item">
                    <div class="stack-head">
                        <div>
                            <strong>{{ $area['id'] }} | {{ $area['barangay'] }}</strong>
                            <p class="muted">{{ $area['incident_at_label'] }} | {{ $area['validated_at_label'] }}</p>
                        </div>
                        <span class="badge badge-{{ strtolower($area['severity']) === 'high' ? 'red' : (strtolower($area['severity']) === 'medium' ? 'amber' : 'green') }}">{{ $area['severity'] }}</span>
                    </div>

                    <div class="mini-grid">
                        <div><span>Families</span><strong>{{ $area['affected_families'] }}</strong></div>
                        <div><span>Members</span><strong>{{ $area['household_members'] }}</strong></div>
                        <div><span>GIS Family Pins</span><strong>{{ $area['pinpointed_families'] }}</strong></div>
                    </div>

                    <p class="item-note">{{ $area['description'] }}</p>
                </div>
            @empty
                <div class="stack-item">
                    <strong>No validated area snapshots yet.</strong>
                    <p class="muted">Validated disaster reports will appear here once reviewed by MDRRMO.</p>
                </div>
            @endforelse
        </div>
    </section>

    <section class="card">
        <div class="section-heading">
            <div>
                <h2>Family Coordinate Availability</h2>
                <p class="muted">Quick guide to how precise the validated-area GIS coverage currently is.</p>
            </div>
        </div>

        <div class="analytics-strip">
            <div class="analytics-card">
                <span class="badge badge-green">Validated</span>
                <strong>{{ $stats['validated_areas'] }}</strong>
                <p>validated affected areas are ready for DSWD review</p>
                <small>Each one is sourced from an MDRRMO-validated disaster report.</small>
            </div>
            <div class="analytics-card">
                <span class="badge badge-blue">Pinpointed</span>
                <strong>{{ $stats['family_pins'] }}</strong>
                <p>family-level coordinates can be opened directly on the GIS map</p>
                <small>These are especially useful for hard-to-reach sitio support.</small>
            </div>
            <div class="analytics-card">
                <span class="badge badge-amber">Coverage</span>
                <strong>{{ $stats['covered_barangays'] }}</strong>
                <p>barangays currently represented in the validated DSWD view</p>
                <small>Use this to spot gaps before dispatching assistance.</small>
            </div>
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

    const map = L.map('validated-areas-map', {
        zoomControl: true,
        scrollWheelZoom: true,
    }).setView([mapCenter.lat, mapCenter.lng], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    const bounds = [];

    mapPoints.forEach((point) => {
        let layer;

        if (point.kind === 'family_pin') {
            layer = L.circleMarker([point.lat, point.lng], {
                radius: 6,
                color: '#1d4ed8',
                fillColor: '#60a5fa',
                fillOpacity: 0.9,
                weight: 2,
            });
        } else {
            const stroke = point.severity === 'High' ? '#991b1b' : (point.severity === 'Medium' ? '#9a3412' : '#166534');
            const fill = point.severity === 'High' ? '#ef4444' : (point.severity === 'Medium' ? '#f59e0b' : '#22c55e');

            layer = L.circleMarker([point.lat, point.lng], {
                radius: 10,
                color: stroke,
                fillColor: fill,
                fillOpacity: 0.82,
                weight: 2,
            });
        }

        layer.addTo(map).bindPopup(
            `<strong>${point.title}</strong><br>${point.subtitle}<br>${point.note}<br>${point.coordinates}`
        );

        bounds.push([point.lat, point.lng]);
    });

    if (bounds.length > 0) {
        map.fitBounds(bounds, { padding: [28, 28] });
    }
</script>
@endsection
