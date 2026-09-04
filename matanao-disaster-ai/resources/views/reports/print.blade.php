@extends('layouts.app')

@section('content')
<div class="page-header">
    <div>
        <div class="pill">Printable Output</div>
        <h1>{{ $selectedBarangay ? $selectedBarangay->barangay_name.' Assistance List' : 'Barangay Assistance List' }}</h1>
        <p class="muted">Printable list of affected families grouped per barangay, including recommended food packs, medical kits, and money assistance.</p>
    </div>
    <div class="header-actions no-print">
        <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
    </div>
</div>

<section class="card bottom-gap no-print">
    <form method="GET" action="{{ route('reports.print') }}" class="filter-form print-filter-form">
        <div>
            <label>Print Barangay</label>
            <select name="barangay_id">
                <option value="">All Barangays</option>
                @foreach($barangays as $barangay)
                    <option value="{{ $barangay->barangay_id }}" @selected($selectedBarangayId === $barangay->barangay_id)>
                        {{ $barangay->barangay_name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Open List</button>
            <a href="{{ route('reports.print') }}" class="btn btn-secondary">Clear</a>
        </div>
    </form>
</section>

<div class="print-report-heading">
    <strong>Municipality of Matanao</strong>
    <span>{{ $selectedBarangay ? 'Barangay '.$selectedBarangay->barangay_name : 'All Barangays' }}</span>
    <small>Generated {{ now()->format('M d, Y h:i A') }}</small>
</div>

<div class="stats-grid print-stats">
    <div class="stat-card">
        <span>Reports</span>
        <strong>{{ $totals['reports'] }}</strong>
    </div>
    <div class="stat-card">
        <span>Affected Families</span>
        <strong>{{ $totals['families'] }}</strong>
    </div>
    <div class="stat-card">
        <span>Food Packs</span>
        <strong>{{ $totals['food_packs'] }}</strong>
    </div>
    <div class="stat-card">
        <span>Medical Kits</span>
        <strong>{{ $totals['medicine_kits'] }}</strong>
    </div>
    <div class="stat-card">
        <span>Money Assistance</span>
        <strong>Php {{ number_format($totals['cash_assistance'], 2) }}</strong>
    </div>
</div>

@forelse($barangayGroups as $group)
    <section class="card print-barangay-section">
        <div class="section-heading">
            <div>
                <h2>{{ $group['barangay'] }}</h2>
                <p class="muted">
                    {{ $group['totals']['reports'] }} reports |
                    {{ $group['totals']['families'] }} affected families |
                    {{ $group['totals']['food_packs'] }} food packs |
                    {{ $group['totals']['medicine_kits'] }} medical kits |
                    Php {{ number_format($group['totals']['cash_assistance'], 2) }}
                </p>
            </div>
        </div>

        @foreach($group['reports'] as $report)
            <div class="print-report-block">
                <div class="stack-head">
                    <div>
                        <strong>{{ $report['id'] }} | {{ $report['type'] }}</strong>
                        <p class="muted">
                            {{ $report['date'] }} {{ $report['time'] }} |
                            {{ $report['sitio_purok'] }} |
                            {{ $report['severity'] }} |
                            {{ $report['status'] }}
                        </p>
                    </div>
                    <div class="print-assistance-total">
                        <span>{{ $report['food_packs'] }} food packs</span>
                        <span>{{ $report['medicine_kits'] }} medical kits</span>
                        <strong>Php {{ number_format($report['cash_assistance'], 2) }}</strong>
                    </div>
                </div>

                <div class="table-wrap top-gap">
                    <table>
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Affected Family Name</th>
                                <th>Members</th>
                                <th>Contact</th>
                                <th>Evacuation Status</th>
                                <th>Severity</th>
                                <th>Food Packs</th>
                                <th>Medical Kits</th>
                                <th>Money</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($report['family_assistance'] as $family)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $family['name'] }}</td>
                                    <td>{{ $family['household_members'] }}</td>
                                    <td>{{ $family['contact_number'] }}</td>
                                    <td>{{ $family['evacuation_status'] }}</td>
                                    <td>{{ $family['severity'] }}</td>
                                    <td>{{ $family['food_packs'] }}</td>
                                    <td>{{ $family['medicine_kits'] }}</td>
                                    <td>Php {{ number_format($family['cash_assistance'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td>1</td>
                                    <td>Family names not encoded</td>
                                    <td colspan="3">{{ $report['families'] }} affected families recorded as total only.</td>
                                    <td>{{ $report['severity'] }}</td>
                                    <td>{{ $report['food_packs'] }}</td>
                                    <td>{{ $report['medicine_kits'] }}</td>
                                    <td>Php {{ number_format($report['cash_assistance'], 2) }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @unless($report['has_recommendation'])
                    <p class="muted print-note">No recommendation has been generated yet for this report, so food packs, medical kits, and money show as zero.</p>
                @endunless
            </div>
        @endforeach
    </section>
@empty
    <section class="card">
        <div class="stack-item">
            <strong>No printable records found.</strong>
            <p class="muted">Try clearing the barangay filter or adding disaster reports first.</p>
        </div>
    </section>
@endforelse
@endsection
