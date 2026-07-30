@extends('layouts.app')

@section('content')
<div class="page-header">
    <div>
        <div class="pill">Admin Oversight</div>
        <h1>Audit Trail</h1>
        <p class="muted">Review account changes, validation actions, and recommendation generation records across the system.</p>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <span>Total Events</span>
        <strong>{{ $stats['total'] }}</strong>
    </div>
    <div class="stat-card">
        <span>User Management</span>
        <strong>{{ $stats['user_management'] }}</strong>
    </div>
    <div class="stat-card">
        <span>Validation Actions</span>
        <strong>{{ $stats['validation'] }}</strong>
    </div>
    <div class="stat-card">
        <span>Recommendations</span>
        <strong>{{ $stats['recommendation'] }}</strong>
    </div>
</div>

<section class="card bottom-gap">
    <form method="GET" action="{{ route('admin.audit-trail') }}" class="audit-filter-form">
        <div>
            <label>Search</label>
            <input name="search" value="{{ $search }}" placeholder="Actor, target code, or description">
        </div>
        <div>
            <label>Module</label>
            <select name="module">
                <option value="">All modules</option>
                @foreach($modules as $availableModule)
                    <option value="{{ $availableModule }}" @selected($module === $availableModule)>{{ $availableModule }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label>Action</label>
            <select name="action">
                <option value="">All actions</option>
                @foreach($actions as $availableAction)
                    <option value="{{ $availableAction }}" @selected($action === $availableAction)>{{ \Illuminate\Support\Str::headline(str_replace('_', ' ', $availableAction)) }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="{{ route('admin.audit-trail') }}" class="btn btn-secondary">Clear</a>
        </div>
    </form>
</section>

<div class="content-grid two-columns">
    <section class="card">
        <div class="section-heading">
            <div>
                <h2>Audit Event Table</h2>
                <p class="muted">Latest matching events with actor, module, action, target, and summary.</p>
            </div>
            <span class="badge badge-blue">{{ count($logs) }} shown</span>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Actor</th>
                        <th>Module</th>
                        <th>Action</th>
                        <th>Target</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td>{{ $log['timestamp'] }}</td>
                            <td>{{ $log['actor_name'] }}<br><small>{{ $log['actor_role'] }}</small></td>
                            <td>{{ $log['module'] }}</td>
                            <td>{{ $log['action'] }}</td>
                            <td>{{ $log['target'] }}</td>
                            <td>{{ $log['description'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">No audit events found for the current filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card">
        <div class="section-heading">
            <div>
                <h2>Audit Detail Review</h2>
                <p class="muted">Expanded event details to help explain what changed and who performed the action.</p>
            </div>
        </div>

        <div class="stack-list">
            @forelse($logs as $log)
                @break($loop->index >= 10)
                <div class="stack-item">
                    <div class="stack-head">
                        <div>
                            <strong>{{ $log['target'] }}</strong>
                            <p class="muted">{{ $log['timestamp'] }} | {{ $log['actor_name'] }} | {{ $log['actor_role'] }}</p>
                        </div>
                        <span class="badge badge-{{ $log['module'] === 'User Management' ? 'blue' : ($log['module'] === 'Validation' ? 'amber' : 'green') }}">{{ $log['module'] }}</span>
                    </div>

                    <p><strong>{{ $log['action'] }}</strong></p>
                    <p class="item-note">{{ $log['description'] }}</p>

                    @if($log['details'] !== [])
                        <div class="detail-chip-list">
                            @foreach($log['details'] as $detail)
                                <div class="detail-chip">
                                    <strong>{{ $detail['label'] }}</strong>
                                    <span>{{ $detail['value'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                <div class="stack-item">
                    <strong>No audit detail records found.</strong>
                    <p class="muted">Perform an account update, validation action, or recommendation generation to populate this review panel.</p>
                </div>
            @endforelse
        </div>
    </section>
</div>
@endsection
