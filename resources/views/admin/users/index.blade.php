@extends('layouts.app')

@section('title', 'Użytkownicy')

@section('content')
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <h1 class="h5 mb-0">Użytkownicy</h1>
        <a class="btn btn-primary btn-sm" href="{{ route('admin.users.create') }}">+ Nowe konto</a>
    </div>

    <div class="app-card mb-3">
        <div class="app-card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Login</th>
                            <th scope="col">Nazwa</th>
                            <th scope="col">Rola</th>
                            <th scope="col">Status</th>
                            <th scope="col">Ostatnie logowanie</th>
                            <th scope="col"><span class="visually-hidden">Akcje</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($page->items as $user)
                            <tr>
                                <td class="font-monospace">{{ $user->login }}</td>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->role->label() }}</td>
                                <td>
                                    @if ($user->isActive)
                                        <span class="badge text-bg-success">aktywne</span>
                                    @else
                                        <span class="badge text-bg-secondary">zablokowane</span>
                                    @endif
                                    @if ($user->mustChangePassword)
                                        <span class="badge text-bg-warning">zmiana hasła</span>
                                    @endif
                                </td>
                                <td class="service-meta">
                                    {{ $user->lastLoginAt !== null ? \App\Domain\FieldLabels::formatDate($user->lastLoginAt) : '—' }}
                                </td>
                                <td class="text-end">
                                    <div class="d-flex gap-1 justify-content-end">
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('admin.users.edit', $user) }}">Edytuj</a>
                                        <form method="post" action="{{ route('admin.users.toggle', $user) }}"
                                              data-confirm="{{ $user->isActive ? 'Zablokować to konto?' : 'Odblokować to konto?' }}">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-secondary" type="submit">
                                                {{ $user->isActive ? 'Zablokuj' : 'Odblokuj' }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <x-pagination :page="$page" />
@endsection
