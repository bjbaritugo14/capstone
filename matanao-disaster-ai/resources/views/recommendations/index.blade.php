@extends('layouts.app')

@section('content')
<div class="page-header">
    <div>
        <div class="pill">AI Recommendation Module</div>
        <h1>Assistance Recommendations</h1>
        <p class="muted">Ollama-assisted recommendation outputs for cash assistance, food packs, and medicine.</p>
    </div>
</div>

<div class="content-grid two-columns">
    <section class="card">
        <div class="section-heading">
            <div>
                <h2>Recommendation Output</h2>
                <p class="muted">Barangay-based assistance summary generated from database reports.</p>
            </div>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Barangay</th>
                        <th>Report</th>
                        <th>Families</th>
                        <th>Food Packs</th>
                        <th>Medical Kits</th>
                        <th>Cash Assistance</th>
                        <th>Priority</th>
                        <th>Rule Trigger</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr>
                            <td>{{ $item['barangay'] }}</td>
                            <td>{{ $item['report'] }}</td>
                            <td>{{ $item['families'] }}</td>
                            <td>{{ $item['food_packs'] }}</td>
                            <td>{{ $item['medical_kits'] }}</td>
                            <td>Php {{ number_format($item['cash_assistance']) }}</td>
                            <td>{{ $item['priority'] }}</td>
                            <td>{{ $item['rule'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">No recommendations found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="section-heading">
            <div>
                <h2>Generate With Ollama</h2>
                <p class="muted">Create AI-assisted recommendations from disaster reports that do not have recommendations yet.</p>
            </div>
        </div>

        <div class="stack-list">
            @forelse($reportsForGeneration as $report)
                <div class="stack-item">
                    <div class="stack-head">
                        <strong>REP-{{ str_pad((string) $report->report_id, 4, '0', STR_PAD_LEFT) }} - {{ $report->location?->barangay?->barangay_name ?? 'Unassigned' }}</strong>
                        <span class="badge badge-{{ $report->damage_severity === 'severe' ? 'red' : ($report->damage_severity === 'moderate' ? 'amber' : 'green') }}">{{ ucfirst($report->damage_severity) }}</span>
                    </div>
                    <p class="muted">{{ $report->disaster_type }} | {{ $report->affectedFamilyRecords->count() ?: $report->affected_families }} families | {{ $report->affected_structures }} structures</p>
                    <form method="POST" action="{{ route('recommendations.generate', $report) }}" class="inline-form">
                        @csrf
                        <button type="submit" class="btn btn-primary">Generate AI Recommendation</button>
                    </form>
                </div>
            @empty
                <div class="stack-item">
                    <strong>All reports already have recommendations.</strong>
                    <p class="muted">New disaster reports will appear here after they are encoded.</p>
                </div>
            @endforelse
        </div>
    </section>
</div>


@endsection
