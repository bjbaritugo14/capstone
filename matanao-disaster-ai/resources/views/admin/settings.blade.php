@extends('layouts.app')

@section('content')
<div class="page-header">
    <div>
        <div class="pill">Super Admin</div>
        <h1>Study Setup</h1>
        <p class="muted">Sample configuration values aligned with the disaster assessment capstone.</p>
    </div>
</div>

<section class="card">
    <div class="section-heading">
        <div>
            <h2>Prototype Configuration</h2>
            <p class="muted">Display-only study options for the sample UI.</p>
        </div>
    </div>

    <div class="list-table">
        @foreach($settings as $setting)
            <div class="list-row">
                <div>
                    <strong>{{ $setting['label'] }}</strong>
                </div>
                <span>{{ $setting['value'] }}</span>
            </div>
        @endforeach
    </div>
</section>
@endsection
