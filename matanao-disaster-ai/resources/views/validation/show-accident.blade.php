@extends('layouts.app')

@section('content')
<div class="page-header">
    <div>
        <div class="pill">Validation Review</div>
        <h1>ACC-{{ str_pad((string) $accident->accident_id, 4, '0', STR_PAD_LEFT) }}</h1>
        <p class="muted">Vehicular Accident record submitted from mobile. Review all data below then validate or return.</p>
    </div>
    <a href="{{ route('validation.index') }}" class="btn btn-secondary">← Back to Queue</a>
</div>

<div class="content-grid two-columns">
    {{-- Left column: All submitted data --}}
    <div>
        {{-- Accident Details --}}
        <section class="card">
            <div class="section-heading">
                <div>
                    <h2>Accident Details</h2>
                    <p class="muted">Core information submitted by the field reporter.</p>
                </div>
                <span class="badge badge-{{ $accident->fatality_count > 0 ? 'red' : ($accident->injured_count > 0 ? 'amber' : 'green') }}">
                    {{ $accident->fatality_count > 0 ? 'Fatal' : ($accident->injured_count > 0 ? 'Injury' : 'Damage Only') }}
                </span>
            </div>

            <div class="mini-grid">
                <div><span>Accident Type</span><strong>{{ $accident->accident_type ?? 'N/A' }}</strong></div>
                <div><span>Vehicle Type</span><strong>{{ $accident->vehicle_type ?? 'N/A' }}</strong></div>
                <div><span>Incident Date</span><strong>{{ optional($accident->incident_datetime)->format('M d, Y h:i A') ?? 'N/A' }}</strong></div>
            </div>
            <div class="mini-grid" style="margin-top: 12px;">
                <div><span>Vehicles Involved</span><strong>{{ $accident->vehicles_involved }}</strong></div>
                <div><span>Injured</span><strong>{{ $accident->injured_count }}</strong></div>
                <div><span>Fatalities</span><strong>{{ $accident->fatality_count }}</strong></div>
            </div>

            @if($accident->involved_person_name)
                <div style="margin-top: 16px;">
                    <span style="display:block; color: var(--muted); font-size: 13px; margin-bottom: 6px;">Primary Involved Person</span>
                    <p><strong>{{ $accident->involved_person_name }}</strong></p>
                </div>
            @endif

            <div style="margin-top: 16px;">
                <span style="display:block; color: var(--muted); font-size: 13px; margin-bottom: 6px;">Description</span>
                <p>{{ $accident->description ?? 'No description provided.' }}</p>
            </div>
        </section>

        {{-- Location --}}
        <section class="card top-gap">
            <div class="section-heading compact-heading">
                <div>
                    <h2>Location</h2>
                </div>
            </div>
            <div class="mini-grid">
                <div><span>Barangay</span><strong>{{ $accident->location?->barangay?->barangay_name ?? 'Unassigned' }}</strong></div>
                <div><span>Road Segment</span><strong>{{ $accident->location?->road_segment ?? 'N/A' }}</strong></div>
                <div><span>Coordinates</span><strong>{{ $accident->location?->latitude }}, {{ $accident->location?->longitude }}</strong></div>
            </div>
        </section>

        {{-- Involved Persons --}}
        <section class="card top-gap">
            <div class="section-heading compact-heading">
                <div>
                    <h2>Involved Persons ({{ $accident->involvedPersons->count() }})</h2>
                </div>
            </div>

            @if($accident->involvedPersons->count())
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Contact Number</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($accident->involvedPersons as $person)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $person->person_name }}</td>
                                    <td>{{ $person->role ?? 'N/A' }}</td>
                                    <td>{{ $person->contact_number ?? 'N/A' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <p class="muted">No involved persons recorded.</p>
            @endif
        </section>

        {{-- Photos --}}
        <section class="card top-gap">
            <div class="section-heading compact-heading">
                <div>
                    <h2>Photos ({{ $accident->images->count() }})</h2>
                </div>
            </div>

            @if($accident->images->count())
                <div class="report-image-grid">
                    @foreach($accident->images as $image)
                        <div class="report-image-link">
                            <img src="{{ asset('storage/'.$image->image_path) }}" alt="Accident photo {{ $loop->iteration }}" style="width: 100%; border-radius: 8px; object-fit: cover; max-height: 180px;">
                        </div>
                    @endforeach
                </div>
            @else
                <p class="muted">No photos uploaded.</p>
            @endif
        </section>

        {{-- Submitted By --}}
        <section class="card top-gap">
            <div class="section-heading compact-heading">
                <div>
                    <h2>Submitted By</h2>
                </div>
            </div>
            <div class="mini-grid single-column">
                <div><span>Name</span><strong>{{ $accident->user?->full_name ?? 'Unknown' }}</strong></div>
            </div>
        </section>
    </div>

    {{-- Right column: Actions --}}
    <div>
        <section class="card">
            <div class="section-heading">
                <div>
                    <h2>Validation Action</h2>
                    <p class="muted">Validate to approve or return with a reason.</p>
                </div>
            </div>

            {{-- Validate Button --}}
            <form method="POST" action="{{ route('validation.validate-accident', $accident) }}" style="margin-bottom: 20px;">
                @csrf
                <button type="submit" class="btn btn-primary" style="width: 100%;">✓ Validate Accident Record</button>
            </form>

            {{-- Return Form --}}
            <div style="border-top: 1px solid var(--border); padding-top: 20px;">
                <strong style="display: block; margin-bottom: 8px;">Return to Mobile</strong>
                <p class="muted" style="margin-bottom: 12px;">Provide a reason why this record is being returned for correction.</p>

                <form method="POST" action="{{ route('validation.return-accident', $accident) }}">
                    @csrf
                    <div style="margin-bottom: 12px;">
                        <textarea name="reason" rows="4" placeholder="Enter reason for returning this record..." required>{{ old('reason') }}</textarea>
                        @error('reason')
                            <p style="color: var(--red); font-size: 13px; margin-top: 6px;">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-secondary" style="width: 100%;">↩ Return Record</button>
                </form>
            </div>
        </section>
    </div>
</div>
@endsection
