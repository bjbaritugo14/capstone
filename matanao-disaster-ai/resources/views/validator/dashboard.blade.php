@extends('layouts.app')

@section('head')
<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin=""
>
<style>
    .validator-note {
        padding: 16px 18px;
        border: 1px solid rgba(31, 95, 74, 0.18);
        border-radius: 16px;
        background: linear-gradient(135deg, rgba(242, 248, 245, 0.95), rgba(255, 251, 245, 0.98));
        color: var(--muted);
        line-height: 1.55;
    }

    .validator-note strong {
        color: var(--text);
    }

    .validator-form-grid {
        display: grid;
        gap: 14px;
    }

    .validator-subgrid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .validator-subgrid.three-up {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    .validator-entry-list {
        display: grid;
        gap: 12px;
    }

    .validator-entry {
        border: 1px solid var(--border);
        border-radius: 18px;
        padding: 16px;
        background: rgba(255, 249, 242, 0.72);
    }

    .validator-entry-header {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: center;
        margin-bottom: 12px;
    }

    .validator-entry-header strong {
        display: block;
        margin-bottom: 4px;
    }

    .validator-muted {
        color: var(--muted);
        font-size: 13px;
        line-height: 1.5;
    }

    .validator-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .validator-photo-note {
        color: var(--muted);
        font-size: 12px;
        margin-top: -6px;
    }

    .validator-help-list {
        display: grid;
        gap: 12px;
    }

    .validator-help-item {
        padding: 14px 16px;
        border-radius: 14px;
        border: 1px solid var(--border);
        background: rgba(255, 249, 242, 0.72);
    }

    .validator-help-item strong,
    .validator-help-item span {
        display: block;
    }

    .validator-help-item span {
        color: var(--muted);
        font-size: 13px;
        margin-top: 6px;
        line-height: 1.5;
    }

    .validator-empty {
        padding: 18px;
        border-radius: 16px;
        border: 1px dashed var(--border);
        background: rgba(255, 255, 255, 0.6);
        color: var(--muted);
    }

    @media (max-width: 900px) {
        .validator-subgrid,
        .validator-subgrid.three-up {
            grid-template-columns: 1fr;
        }
    }
</style>
@endsection

@section('content')
@php
    $defaultFamily = [
        'firstName' => '',
        'lastName' => '',
        'familyHeadName' => '',
        'householdMembers' => '',
        'contactNumber' => '',
        'evacuationStatus' => 'Not Evacuated',
        'description' => '',
        'severity' => 'minor',
        'latitude' => '',
        'longitude' => '',
    ];

    $oldFamilies = old('families', [$defaultFamily]);
    if (! is_array($oldFamilies) || count($oldFamilies) === 0) {
        $oldFamilies = [$defaultFamily];
    }
    $oldFamilies = array_map(function (array $family): array {
        $family = array_merge($defaultFamily, $family);

        if (($family['firstName'] ?? '') === '' && ($family['lastName'] ?? '') === '' && ($family['familyHeadName'] ?? '') !== '') {
            $parts = preg_split('/\s+/', trim((string) $family['familyHeadName'])) ?: [];
            $family['lastName'] = count($parts) > 1 ? array_pop($parts) : '';
            $family['firstName'] = implode(' ', $parts);
        }

        return $family;
    }, $oldFamilies);

    $oldPeople = old('involvedPersons', []);
    if (! is_array($oldPeople)) {
        $oldPeople = [];
    }
    $oldPeople = array_map(function (array $person): array {
        $person = array_merge([
            'firstName' => '',
            'lastName' => '',
            'personName' => '',
            'role' => '',
            'contactNumber' => '',
        ], $person);

        if (($person['firstName'] ?? '') === '' && ($person['lastName'] ?? '') === '' && ($person['personName'] ?? '') !== '') {
            $parts = preg_split('/\s+/', trim((string) $person['personName'])) ?: [];
            $person['lastName'] = count($parts) > 1 ? array_pop($parts) : '';
            $person['firstName'] = implode(' ', $parts);
        }

        return $person;
    }, $oldPeople);

    $disasterTypes = ['Flood', 'Typhoon', 'Landslide', 'Earthquake', 'Fire', 'Other'];
    $severityLevels = ['minor', 'moderate', 'severe'];
    $evacuationStatuses = ['Not Evacuated', 'Evacuated', 'In Evacuation Center', 'Returned Home'];
@endphp

<div class="page-header">
    <div>
        <div class="pill">Field Officer Web Reporting</div>
        <h1>Field Officer Browser Reports</h1>
    </div>
    <div class="header-actions">
        <span class="badge badge-blue">Expo-style workflow</span>
    </div>
</div>

@if (session('status'))
    <div class="form-success">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="form-error">
        <strong>Please review the submission form.</strong>
        <ul style="margin: 8px 0 0 18px; padding: 0;">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="stats-grid">
    <div class="stat-card">
        <span>Disaster Reports</span>
        <strong>{{ $stats['disaster_reports'] }}</strong>
    </div>
    <div class="stat-card">
        <span>Accident Reports</span>
        <strong>{{ $stats['accident_reports'] }}</strong>
    </div>
    <div class="stat-card">
        <span>Pending Review</span>
        <strong>{{ $stats['pending_review'] }}</strong>
    </div>
    <div class="stat-card">
        <span>Family Records</span>
        <strong>{{ $stats['family_records'] }}</strong>
    </div>
</div>

<div class="content-grid two-columns">
    <section class="card">
        <div class="section-heading">
            <div>
                <h2>Recent Geotagged Submissions</h2>
            </div>
            <span class="badge badge-blue">Leaflet</span>
        </div>

        <div class="map-shell">
            <div id="validator-submission-map" class="map-canvas" aria-label="Field Officer submission map"></div>
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
                <h2>Browser Intake Forms</h2>
            </div>
        </div>

        <div class="mini-grid">
            <div><span>Disaster Intake</span><strong>Affected families</strong></div>
            <div><span>Accident Intake</span><strong>Road incidents</strong></div>
        </div>
    </section>
</div>

<div class="content-grid two-columns top-gap">
    <section class="card">
        <div class="section-heading">
            <div>
                <h2>Browser Disaster Damage Report</h2>
            </div>
            <span class="badge badge-green">Pending Validation</span>
        </div>

        <form method="POST" action="{{ route('field-officer.reports.store') }}" enctype="multipart/form-data" class="validator-form-grid" id="disaster-web-form">
            @csrf
            <input type="hidden" name="form_context" value="disaster">

            <div class="validator-subgrid">
                <div>
                    <label>Barangay</label>
                    <select name="barangay_id" required>
                        @foreach($barangays as $barangay)
                            <option value="{{ $barangay->barangay_id }}" @selected((string) old('barangay_id', (string) optional($barangays->first())->barangay_id) === (string) $barangay->barangay_id)>{{ $barangay->barangay_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Report Date</label>
                    <input type="date" name="reportDate" value="{{ old('reportDate', now()->format('Y-m-d')) }}" required>
                </div>
            </div>

            <div class="validator-subgrid">
                <div>
                    <label>Purok / Sitio</label>
                    <input type="text" name="purok" value="{{ old('purok') }}" placeholder="Ex. Purok 3 or Sitio Riverside">
                </div>
                <div>
                    <label>Disaster Type</label>
                    <select name="disasterType" required>
                        @foreach($disasterTypes as $type)
                            <option value="{{ $type }}" @selected(old('disasterType', 'Flood') === $type)>{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="validator-subgrid">
                <div>
                    <label>Overall Severity</label>
                    <select name="severity" required>
                        @foreach($severityLevels as $level)
                            <option value="{{ $level }}" @selected(old('severity', 'minor') === $level)>{{ ucfirst($level) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Affected Structures</label>
                    <input type="number" min="0" name="affectedStructures" value="{{ old('affectedStructures', 0) }}" placeholder="Number of damaged or affected structures">
                </div>
            </div>

            <div>
                <label>Situation Overview</label>
                <textarea name="description" rows="4" placeholder="Short summary of the incident, damage, and needs">{{ old('description') }}</textarea>
            </div>

            <div class="validator-entry js-geo-row">
                <div class="validator-entry-header">
                    <div>
                        <strong>Primary GPS Coordinates</strong>
                        <p class="validator-muted">Leave blank to use the first family GPS point.</p>
                    </div>
                    <button type="button" class="btn btn-secondary js-fill-location">Use Browser Location</button>
                </div>
                <div class="validator-subgrid">
                    <div>
                        <label>Latitude</label>
                        <input type="text" name="latitude" value="{{ old('latitude') }}" placeholder="6.688099" class="js-lat">
                    </div>
                    <div>
                        <label>Longitude</label>
                        <input type="text" name="longitude" value="{{ old('longitude') }}" placeholder="125.166607" class="js-lng">
                    </div>
                </div>
            </div>

            <div>
                <div class="section-heading compact-heading">
                    <div>
                        <h2>Affected Family Records</h2>
                    </div>
                </div>

                <div id="family-entry-list" class="validator-entry-list"></div>
                <div class="validator-actions top-gap">
                    <button type="button" class="btn btn-secondary" id="add-family-entry">Add Affected Family</button>
                </div>
            </div>

            <div>
                <label>General Report Photos</label>
                <input type="file" name="photos[]" accept="image/*" multiple>
                <p class="validator-photo-note">Choose overall incident photos here. If validation fails, browser security may require reselecting the files.</p>
            </div>

            <div class="validator-actions">
                <button type="submit" class="btn btn-primary">Submit Disaster Report</button>
            </div>
        </form>
    </section>

    <section class="card">
        <div class="section-heading">
            <div>
                <h2>Browser Vehicular Accident Report</h2>
            </div>
            <span class="badge badge-amber">Recorded Status</span>
        </div>

        <form method="POST" action="{{ route('field-officer.accidents.store') }}" enctype="multipart/form-data" class="validator-form-grid" id="accident-web-form">
            @csrf
            <input type="hidden" name="form_context" value="accident">

            <div class="validator-subgrid">
                <div>
                    <label>Barangay</label>
                    <select name="barangay_id" required>
                        @foreach($barangays as $barangay)
                            <option value="{{ $barangay->barangay_id }}" @selected((string) old('barangay_id', (string) optional($barangays->first())->barangay_id) === (string) $barangay->barangay_id)>{{ $barangay->barangay_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Incident Date</label>
                    <input type="date" name="incidentDate" value="{{ old('incidentDate', now()->format('Y-m-d')) }}" required>
                </div>
            </div>

            <div class="validator-subgrid">
                <div>
                    <label>Purok / Sitio</label>
                    <input type="text" name="purok" value="{{ old('purok') }}" placeholder="Ex. Purok 5">
                </div>
                <div>
                    <label>Road Segment</label>
                    <input type="text" name="roadSegment" value="{{ old('roadSegment') }}" placeholder="Ex. National Highway">
                </div>
            </div>

            <div class="validator-subgrid">
                <div>
                    <label>Accident Type</label>
                    <input type="text" name="accidentType" value="{{ old('accidentType') }}" placeholder="Ex. Collision, Hit and Run" required>
                </div>
                <div>
                    <label>Vehicle Type</label>
                    <input type="text" name="vehicleType" value="{{ old('vehicleType') }}" placeholder="Ex. Motorcycle, Truck, Car">
                </div>
            </div>

            <div>
                <label>Description</label>
                <textarea name="description" rows="4" placeholder="Describe what happened, the impact, and the scene details" required>{{ old('description') }}</textarea>
            </div>

            <div class="validator-subgrid three-up">
                <div>
                    <label>Vehicles Involved</label>
                    <input type="number" min="1" name="vehiclesInvolved" value="{{ old('vehiclesInvolved', 1) }}">
                </div>
                <div>
                    <label>Injured Count</label>
                    <input type="number" min="0" name="injuredCount" value="{{ old('injuredCount', 0) }}">
                </div>
                <div>
                    <label>Fatality Count</label>
                    <input type="number" min="0" name="fatalityCount" value="{{ old('fatalityCount', 0) }}">
                </div>
            </div>

            <div class="validator-entry js-geo-row">
                <div class="validator-entry-header">
                    <div>
                        <strong>Accident GPS Coordinates</strong>
                        <p class="validator-muted">Use browser location or type field-note coordinates.</p>
                    </div>
                    <button type="button" class="btn btn-secondary js-fill-location">Use Browser Location</button>
                </div>
                <div class="validator-subgrid">
                    <div>
                        <label>Latitude</label>
                        <input type="text" name="latitude" value="{{ old('latitude') }}" placeholder="6.688099" class="js-lat">
                    </div>
                    <div>
                        <label>Longitude</label>
                        <input type="text" name="longitude" value="{{ old('longitude') }}" placeholder="125.166607" class="js-lng">
                    </div>
                </div>
            </div>

            <div>
                <div class="section-heading compact-heading">
                    <div>
                        <h2>Involved Person Records</h2>
                    </div>
                </div>

                <div id="person-entry-list" class="validator-entry-list"></div>
                <div class="validator-actions top-gap">
                    <button type="button" class="btn btn-secondary" id="add-person-entry">Add Involved Person</button>
                </div>
            </div>

            <div>
                <label>Accident Photos</label>
                <input type="file" name="photos[]" accept="image/*" multiple>
                <p class="validator-photo-note">Upload browser-selected scene photos here before submitting.</p>
            </div>

            <div class="validator-actions">
                <button type="submit" class="btn btn-primary">Submit Accident Report</button>
            </div>
        </form>
    </section>
</div>

<section class="card top-gap">
    <div class="section-heading">
        <div>
            <h2>Recent Browser Submissions</h2>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Module</th>
                    <th>Title</th>
                    <th>Barangay</th>
                    <th>Details</th>
                    <th>Coordinates</th>
                    <th>Severity</th>
                    <th>Status</th>
                    <th>Date</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentSubmissions as $submission)
                    <tr>
                        <td>{{ $submission['code'] }}</td>
                        <td>{{ $submission['module'] }}</td>
                        <td>{{ $submission['title'] }}</td>
                        <td>{{ $submission['barangay'] }}</td>
                        <td>{{ $submission['details'] }}</td>
                        <td>{{ $submission['coordinates'] }}</td>
                        <td>{{ $submission['severity'] }}</td>
                        <td>{{ $submission['status'] }}</td>
                        <td>{{ $submission['submitted_at'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">No web submissions yet. Your new browser-submitted records will appear here after you send them.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<template id="family-entry-template">
    <div class="validator-entry">
        <div class="validator-entry-header">
            <div>
                <strong>Affected family <span class="js-entry-number">1</span></strong>
                <p class="validator-muted">Encode the same family-level details collected in the Expo app.</p>
            </div>
            <button type="button" class="btn btn-delete js-remove-entry">Remove</button>
        </div>

        <div class="validator-subgrid">
            <div>
                <label for="family-first-name-__INDEX__">First Name</label>
                <input type="text" id="family-first-name-__INDEX__" data-name-template="families[__INDEX__][firstName]" data-id-template="family-first-name-__INDEX__" data-field="firstName" required>
            </div>
            <div>
                <label for="family-last-name-__INDEX__">Last Name</label>
                <input type="text" id="family-last-name-__INDEX__" data-name-template="families[__INDEX__][lastName]" data-id-template="family-last-name-__INDEX__" data-field="lastName" required>
            </div>
        </div>

        <div class="validator-subgrid">
            <div>
                <label for="family-members-__INDEX__">Household Members</label>
                <input type="number" min="1" id="family-members-__INDEX__" data-name-template="families[__INDEX__][householdMembers]" data-id-template="family-members-__INDEX__" data-field="householdMembers">
            </div>
            <div></div>
        </div>

        <div class="validator-subgrid">
            <div>
                <label for="family-contact-__INDEX__">Contact Number</label>
                <input type="text" id="family-contact-__INDEX__" data-name-template="families[__INDEX__][contactNumber]" data-id-template="family-contact-__INDEX__" data-field="contactNumber">
            </div>
            <div>
                <label for="family-evacuation-__INDEX__">Evacuation Status</label>
                <select id="family-evacuation-__INDEX__" data-name-template="families[__INDEX__][evacuationStatus]" data-id-template="family-evacuation-__INDEX__" data-field="evacuationStatus">
                    @foreach($evacuationStatuses as $status)
                        <option value="{{ $status }}">{{ $status }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="validator-subgrid">
            <div>
                <label for="family-severity-__INDEX__">Family Severity</label>
                <select id="family-severity-__INDEX__" data-name-template="families[__INDEX__][severity]" data-id-template="family-severity-__INDEX__" data-field="severity">
                    @foreach($severityLevels as $level)
                        <option value="{{ $level }}">{{ ucfirst($level) }}</option>
                    @endforeach
                </select>
            </div>
            <div></div>
        </div>

        <div>
            <label for="family-description-__INDEX__">Family Description</label>
            <textarea id="family-description-__INDEX__" rows="3" data-name-template="families[__INDEX__][description]" data-id-template="family-description-__INDEX__" data-field="description"></textarea>
        </div>

        <div class="validator-entry js-geo-row">
            <div class="validator-entry-header">
                <div>
                    <strong>Family GPS Coordinates</strong>
                    <p class="validator-muted">Optional per-family geotagging for affected-area mapping.</p>
                </div>
                <button type="button" class="btn btn-secondary js-fill-location">Use Browser Location</button>
            </div>
            <div class="validator-subgrid">
                <div>
                    <label for="family-latitude-__INDEX__">Latitude</label>
                    <input type="text" id="family-latitude-__INDEX__" class="js-lat" data-name-template="families[__INDEX__][latitude]" data-id-template="family-latitude-__INDEX__" data-field="latitude">
                </div>
                <div>
                    <label for="family-longitude-__INDEX__">Longitude</label>
                    <input type="text" id="family-longitude-__INDEX__" class="js-lng" data-name-template="families[__INDEX__][longitude]" data-id-template="family-longitude-__INDEX__" data-field="longitude">
                </div>
            </div>
        </div>

        <div>
            <label for="family-photos-__INDEX__">Family Photos</label>
            <input type="file" id="family-photos-__INDEX__" accept="image/*" multiple data-name-template="family_photos___INDEX__[]" data-id-template="family-photos-__INDEX__">
            <p class="validator-photo-note">Upload specific photos for this family if you have them.</p>
        </div>
    </div>
</template>

<template id="person-entry-template">
    <div class="validator-entry">
        <div class="validator-entry-header">
            <div>
                <strong>Person #<span class="js-entry-number">1</span></strong>
                <p class="validator-muted">Optional repeated person entries from the browser dashboard.</p>
            </div>
            <button type="button" class="btn btn-delete js-remove-entry">Remove</button>
        </div>

        <div class="validator-subgrid">
            <div>
                <label for="person-first-name-__INDEX__">First Name</label>
                <input type="text" id="person-first-name-__INDEX__" data-name-template="involvedPersons[__INDEX__][firstName]" data-id-template="person-first-name-__INDEX__" data-field="firstName">
            </div>
            <div>
                <label for="person-last-name-__INDEX__">Last Name</label>
                <input type="text" id="person-last-name-__INDEX__" data-name-template="involvedPersons[__INDEX__][lastName]" data-id-template="person-last-name-__INDEX__" data-field="lastName">
            </div>
        </div>

        <div class="validator-subgrid">
            <div>
                <label for="person-role-__INDEX__">Role</label>
                <input type="text" id="person-role-__INDEX__" data-name-template="involvedPersons[__INDEX__][role]" data-id-template="person-role-__INDEX__" data-field="role" placeholder="Driver, Passenger, Pedestrian">
            </div>
            <div></div>
        </div>

        <div>
            <label for="person-contact-__INDEX__">Contact Number</label>
            <input type="text" id="person-contact-__INDEX__" data-name-template="involvedPersons[__INDEX__][contactNumber]" data-id-template="person-contact-__INDEX__" data-field="contactNumber">
        </div>
    </div>
</template>
@endsection

@section('scripts')
<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""
></script>
<script>
    const validatorMapCenter = @json($mapCenter);
    const validatorMapPoints = @json($mapPoints);
    const oldFamilies = @json(array_values($oldFamilies));
    const oldPeople = @json(array_values($oldPeople));

    const validatorMap = L.map('validator-submission-map', {
        zoomControl: true,
        scrollWheelZoom: true,
    }).setView([validatorMapCenter.lat, validatorMapCenter.lng], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(validatorMap);

    const validatorBounds = [];
    const severityStyles = {
        Low: { stroke: '#166534', fill: '#22c55e', badge: 'badge-green' },
        Medium: { stroke: '#b45309', fill: '#f59e0b', badge: 'badge-amber' },
        High: { stroke: '#b91c1c', fill: '#ef4444', badge: 'badge-red' },
    };

    function severityStyle(severity) {
        return severityStyles[severity] || severityStyles.Low;
    }

    validatorMapPoints.forEach((point) => {
        const isAccident = point.kind === 'accident';
        const style = severityStyle(point.severity);
        const marker = L.circleMarker([point.lat, point.lng], {
            radius: point.severity === 'High' ? 10 : 8,
            color: style.stroke,
            fillColor: style.fill,
            fillOpacity: 0.86,
            weight: 2,
        }).addTo(validatorMap);

        marker.bindPopup(
            `<strong class="map-popup-title">${point.title}</strong>
            <div class="map-popup-line">${point.subtitle}</div>
            <div class="map-popup-badges">
                <span class="badge ${style.badge}">${point.severity}</span>
                <span class="badge ${isAccident ? 'badge-amber' : 'badge-blue'}">${isAccident ? 'Accident' : 'Disaster'}</span>
                <span class="badge badge-green">${point.status}</span>
            </div>`
        );

        validatorBounds.push([point.lat, point.lng]);
    });

    if (validatorBounds.length > 0) {
        validatorMap.fitBounds(validatorBounds, { padding: [28, 28] });
    }

    function renumberEntries(containerSelector) {
        const container = document.querySelector(containerSelector);

        Array.from(container.children).forEach((entry, index) => {
            const numberLabel = entry.querySelector('.js-entry-number');
            if (numberLabel) {
                numberLabel.textContent = String(index + 1);
            }

            entry.querySelectorAll('[data-name-template]').forEach((field) => {
                field.name = field.dataset.nameTemplate.replaceAll('__INDEX__', index);
            });

            entry.querySelectorAll('[data-id-template]').forEach((field) => {
                const newId = field.dataset.idTemplate.replaceAll('__INDEX__', index);
                field.id = newId;
            });
        });
    }

    function hydrateField(row, fieldName, value) {
        const field = row.querySelector(`[data-field="${fieldName}"]`);
        if (!field) {
            return;
        }

        field.value = value ?? '';
    }

    function addEntry(containerSelector, templateSelector, payload) {
        const container = document.querySelector(containerSelector);
        const template = document.querySelector(templateSelector);
        const nextIndex = container.children.length;
        const html = template.innerHTML.replaceAll('__INDEX__', nextIndex);

        container.insertAdjacentHTML('beforeend', html);
        const row = container.lastElementChild;

        Object.entries(payload).forEach(([field, value]) => hydrateField(row, field, value));

        renumberEntries(containerSelector);
    }

    const familyContainerSelector = '#family-entry-list';
    const personContainerSelector = '#person-entry-list';

    oldFamilies.forEach((family) => addEntry(familyContainerSelector, '#family-entry-template', family));
    if (oldFamilies.length === 0) {
        addEntry(familyContainerSelector, '#family-entry-template', {});
    }

    oldPeople.forEach((person) => addEntry(personContainerSelector, '#person-entry-template', person));

    document.getElementById('add-family-entry').addEventListener('click', () => {
        addEntry(familyContainerSelector, '#family-entry-template', {
            evacuationStatus: 'Not Evacuated',
            severity: 'minor',
        });
    });

    document.getElementById('add-person-entry').addEventListener('click', () => {
        addEntry(personContainerSelector, '#person-entry-template', {});
    });

    document.addEventListener('click', async (event) => {
        const removeButton = event.target.closest('.js-remove-entry');
        if (removeButton) {
            const entry = removeButton.closest('.validator-entry');
            const familyContainer = document.querySelector(familyContainerSelector);
            const isFamilyEntry = familyContainer.contains(entry);

            entry.remove();

            if (isFamilyEntry && familyContainer.children.length === 0) {
                addEntry(familyContainerSelector, '#family-entry-template', {
                    evacuationStatus: 'Not Evacuated',
                    severity: 'minor',
                });
            } else {
                renumberEntries(isFamilyEntry ? familyContainerSelector : personContainerSelector);
            }

            return;
        }

        const locationButton = event.target.closest('.js-fill-location');
        if (!locationButton) {
            return;
        }

        const geoRow = locationButton.closest('.js-geo-row');
        const latInput = geoRow.querySelector('.js-lat');
        const lngInput = geoRow.querySelector('.js-lng');

        if (!navigator.geolocation) {
            window.alert('Browser geolocation is not available on this device.');
            return;
        }

        navigator.geolocation.getCurrentPosition((position) => {
            latInput.value = position.coords.latitude.toFixed(6);
            lngInput.value = position.coords.longitude.toFixed(6);
        }, () => {
            window.alert('Unable to read your browser location. You can still type the coordinates manually.');
        });
    });

    document.getElementById('disaster-web-form').addEventListener('submit', () => {
        renumberEntries(familyContainerSelector);
    });

    document.getElementById('accident-web-form').addEventListener('submit', () => {
        renumberEntries(personContainerSelector);
    });
</script>
@endsection
