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
        <h1>Affected Families Overview</h1>
        <p class="muted">Barangay impact, household member totals, and family-level records from validated disaster intake.</p>
    </div>
    <div class="header-actions">
        <a href="{{ route('dswd.validated-areas') }}" class="btn btn-secondary">Validated Areas GIS</a>
        <a href="{{ route('dswd.recommendations') }}" class="btn btn-primary">View Recommendations</a>
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
                <h2>Barangay Impact</h2>
                <p class="muted">Family and household member totals grouped by barangay.</p>
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
                <h2>Recent Affected Families</h2>
                <p class="muted">Family names and household sizes for relief validation.</p>
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
                <h2>Validated Affected Areas</h2>
                <p class="muted">Validated area coverage with GIS-ready family pins for DSWD relief planning.</p>
            </div>
            <span class="badge badge-blue">GIS Module</span>
        </div>

        <div class="mini-grid">
            <div><span>Validated Areas</span><strong>{{ $validatedAreaStats['areas'] }}</strong></div>
            <div><span>Covered Barangays</span><strong>{{ $validatedAreaStats['barangays'] }}</strong></div>
            <div><span>Family Pins</span><strong>{{ $validatedAreaStats['family_pins'] }}</strong></div>
        </div>

        <p class="item-note">The dedicated validated-area module maps only validated affected areas and shows per-family coordinates when field teams captured them.</p>

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
            <a href="{{ route('dswd.validated-areas') }}" class="btn btn-primary">Open Validated Areas Module</a>
        </div>
    </section>

    <section class="card">
        <div class="section-heading">
            <div>
                <h2>GIS Preview</h2>
                <p class="muted">Map preview of validated affected areas and family-level coordinate pins inside the DSWD dashboard.</p>
            </div>
            <span class="badge badge-blue">Map View</span>
        </div>

        <div class="map-shell">
            <div id="dswd-validated-area-preview-map" class="map-canvas" aria-label="Validated affected areas preview map"></div>
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

    previewMapPoints.forEach((point) => {
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

        layer.addTo(previewMap).bindPopup(
            `<strong>${point.title}</strong><br>${point.subtitle}<br>${point.note}<br>${point.coordinates}`
        );

        previewBounds.push([point.lat, point.lng]);
    });

    if (previewBounds.length > 0) {
        previewMap.fitBounds(previewBounds, { padding: [28, 28] });
    }
</script>
@endsection
