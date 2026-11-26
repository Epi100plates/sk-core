@if (session('status'))
    <div class="alert alert-success" role="status">{{ session('status') }}</div>
@endif

@if (session('warning'))
    <div class="alert alert-warning" role="alert">{{ session('warning') }}</div>
@endif

@if ($errors->any() && ! \Illuminate\Support\Facades\View::hasSection('own-errors'))
    <div class="alert alert-danger" role="alert">
        <p class="fw-semibold mb-1">Popraw poniższe pola:</p>
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
