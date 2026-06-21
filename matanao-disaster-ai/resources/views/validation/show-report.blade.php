@extends('layouts.app')

@section('content')
<div class="page-header">
    <div>
        <div class="pill">Validation Review</div>
        <h1>REP-{{ str_pad((string) $report->report_id, 4, '0', STR_PAD_LEFT) }}</h1>
        <p class="muted">Disaster Assessment submitted from mobile. Review all data below then validate or return.</p>
    </div>
    <a href="{{ route('validation.index') }}" class="btn btn-secondary">← Back to Queue</a>
</div>

<div class="content-grid two-columns">
    {{-- Left column: All submitted data --}}
    <div>
        {{-- Report Details --}}
        <section class="card">
            <div class="section-heading">
                <div>
                    <h2>Report Details</h2>
                    <p class="muted">Core information submitted by the field reporter.</p>
                </div>
                <span class="badge badge-{{ $report->damage_severity === 'severe' ? 'red' : ($report->damage_severity === 'moderate' ? 'amber' : 'green') }}">{{ ucfirst($report->damage_severity) }}</span>
            </div>

            <div class="mini-grid">
                <div><span>Disaster Type</span><strong>{{ $report->disaster_type }}</strong></div>
                <div><span>Severity</span><strong>{{ ucfirst($report->damage_severity) }}</strong></div>
                <div><span>Incident Date</span><strong>{{ optional($report->incident_datetime)->format('M d, Y') ?? 'N/A' }}</strong></div>
            </div>
            <div class="mini-grid" style="margin-top: 12px;">
                <div><span>Affected Families</span><strong>{{ $report->affected_families }}</strong></div>
                <div><span>Affected Structures</span><strong>{{ $report->affected_structures }}</strong></div>
                <div><span>Submitted</span><strong>{{ $report->created_at }}</strong></div>
            </div>

            <div style="margin-top: 16px;">
                <span style="display:block; color: var(--muted); font-size: 13px; margin-bottom: 6px;">Description</span>
                <p>{{ $report->description }}</p>
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
                <div><span>Barangay</span><strong>{{ $report->location?->barangay?->barangay_name ?? 'Unassigned' }}</strong></div>
                <div><span>Purok / Sitio</span><strong>{{ $report->location?->sitio_purok ?? 'N/A' }}</strong></div>
                <div><span>Coordinates</span><strong>{{ $report->location?->latitude }}, {{ $report->location?->longitude }}</strong></div>
            </div>
        </section>

        {{-- Affected Families --}}
        <section class="card top-gap">
            <div class="section-heading compact-heading">
                <div>
                    <h2>Affected Families ({{ $report->affectedFamilyRecords->count() }})</h2>
                </div>
            </div>

            @if($report->affectedFamilyRecords->count())
                <div class="stack-list">
                    @foreach($report->affectedFamilyRecords as $family)
                        <div class="stack-item">
                            <div class="stack-head">
                                <strong>Family #{{ $loop->iteration }} — {{ $family->family_head_name }}</strong>
                                @if($family->damage_severity)
                                    <span class="badge badge-{{ $family->damage_severity === 'severe' ? 'red' : ($family->damage_severity === 'moderate' ? 'amber' : 'green') }}">{{ ucfirst($family->damage_severity) }}</span>
                                @endif
                            </div>
                            <div class="mini-grid">
                                <div><span>Household Members</span><strong>{{ $family->household_members }}</strong></div>
                                <div><span>Contact</span><strong>{{ $family->contact_number ?? 'N/A' }}</strong></div>
                                <div><span>Evacuation Status</span><strong>{{ $family->evacuation_status ?? 'N/A' }}</strong></div>
                            </div>
                            @if($family->description)
                                <div style="margin-top: 10px;">
                                    <span style="display:block; color: var(--muted); font-size: 13px; margin-bottom: 4px;">Description</span>
                                    <p>{{ $family->description }}</p>
                                </div>
                            @endif
                            @if($family->latitude && $family->longitude)
                                <div style="margin-top: 10px;">
                                    <span style="display:block; color: var(--muted); font-size: 13px; margin-bottom: 4px;">Coordinates</span>
                                    <p>{{ $family->latitude }}, {{ $family->longitude }}</p>
                                </div>
                            @endif
                            @if($family->images && $family->images->count())
                                <div style="margin-top: 10px;">
                                    <span style="display:block; color: var(--muted); font-size: 13px; margin-bottom: 6px;">Photos ({{ $family->images->count() }})</span>
                                    <div class="report-image-grid">
                                        @foreach($family->images as $image)
                                            <div class="report-image-link">
                                                <img src="{{ asset('storage/'.$image->image_path) }}" alt="Family {{ $loop->parent->iteration }} photo" style="width: 100%; border-radius: 8px; object-fit: cover; max-height: 140px;">
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <p class="muted">No affected family records submitted.</p>
            @endif
        </section>

        {{-- Photos --}}
        <section class="card top-gap">
            <div class="section-heading compact-heading">
                <div>
                    <h2>Photos ({{ $report->images->count() }})</h2>
                </div>
            </div>

            @if($report->images->count())
                <div class="report-image-grid">
                    @foreach($report->images as $image)
                        <div class="report-image-link">
                            <img src="{{ asset('storage/'.$image->image_path) }}" alt="Report photo {{ $loop->iteration }}" style="width: 100%; border-radius: 8px; object-fit: cover; max-height: 180px;">
                            <small>{{ $image->image_path }}</small>
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
                <div><span>Name</span><strong>{{ $report->user?->full_name ?? 'Unknown' }}</strong></div>
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
            <form method="POST" action="{{ route('validation.validate-report', $report) }}" style="margin-bottom: 20px;">
                @csrf
                <button type="submit" class="btn btn-primary" style="width: 100%;">✓ Validate Report</button>
            </form>

            {{-- Return Form --}}
            <div style="border-top: 1px solid var(--border); padding-top: 20px;">
                <strong style="display: block; margin-bottom: 8px;">Return to Mobile</strong>
                <p class="muted" style="margin-bottom: 12px;">Provide a reason why this report is being returned for correction.</p>

                <form method="POST" action="{{ route('validation.return-report', $report) }}">
                    @csrf
                    <div style="margin-bottom: 12px;">
                        <textarea name="reason" rows="4" placeholder="Enter reason for returning this report..." required>{{ old('reason') }}</textarea>
                        @error('reason')
                            <p style="color: var(--red); font-size: 13px; margin-top: 6px;">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-secondary" style="width: 100%;">↩ Return Report</button>
                </form>
            </div>
        </section>

        {{-- Previous Validation History --}}
        @if($report->validations->count())
            <section class="card top-gap">
                <div class="section-heading compact-heading">
                    <div>
                        <h2>Validation History</h2>
                    </div>
                </div>
                <div class="stack-list">
                    @foreach($report->validations as $validation)
                        <div class="stack-item">
                            <div class="stack-head">
                                <strong>{{ ucfirst($validation->validation_status) }}</strong>
                                <span class="badge badge-{{ $validation->validation_status === 'validated' ? 'green' : ($validation->validation_status === 'returned' ? 'amber' : 'red') }}">{{ ucfirst($validation->validation_status) }}</span>
                            </div>
                            <p class="muted">By: {{ $validation->validator?->full_name ?? 'System' }}</p>
                            @if($validation->remarks)
                                <p style="margin-top: 8px;">{{ $validation->remarks }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</div>
@endsection
