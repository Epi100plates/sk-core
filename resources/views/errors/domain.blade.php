@extends('layouts.app')

@section('title', $title)

@section('content')
    <div class="page-head">
        <div class="page-head__main">
            <span class="eyebrow">Akcja wstrzymana</span>
            <h1 class="page-title">{{ $title }}</h1>
        </div>
    </div>

    <div class="app-card">
        <div class="app-card-body">
            <p class="mb-3">{{ $message }}</p>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-outline-secondary" href="{{ url()->previous() }}">Wróć</a>
                <a class="btn btn-primary" href="{{ route('services.queue') }}">Przejdź do kolejki</a>
            </div>
        </div>
    </div>
@endsection
