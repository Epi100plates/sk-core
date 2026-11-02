@extends('layouts.app')

@section('title', $title)

@section('content')
    <div class="app-card">
        <div class="app-card-body">
            <h1 class="h5 mb-2">{{ $title }}</h1>
            <p class="mb-3">{{ $message }}</p>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary" href="{{ url()->previous() }}">Wróć</a>
                <a class="btn btn-primary" href="{{ route('services.queue') }}">Kolejka</a>
            </div>
        </div>
    </div>
@endsection
