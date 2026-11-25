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
            <a class="btn btn-primary" href="{{ url('/') }}">Przejdź do kolejki</a>
        </div>
    </div>
@endsection
