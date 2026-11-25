@extends('layouts.app')

@section('title', 'Użytkownicy')

@section('content')
    <div class="page-head">
        <div class="page-head__main">
            <span class="eyebrow">Administracja · {{ \App\Domain\FieldLabels::plural($page->total, 'konto', 'konta', 'kont') }}</span>
            <h1 class="page-title">Użytkownicy</h1>
            <p class="page-head__meta">Osoby pracujące w serwisie i ich dostęp do aplikacji.</p>
        </div>
        <div class="page-head__aside">
            <a class="btn btn-primary btn-sm" href="{{ route('admin.users.create') }}">
                <x-icon name="plus" :size="15" /> Nowe konto
            </a>
        </div>
    </div>

    <div class="app-card mb-3">
        <div class="app-card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th scope="col">Nazwa</th>
                            <th scope="col">Login</th>
                            <th scope="col">Rola</th>
                            <th scope="col">Dostęp</th>
                            <th scope="col">Ostatnio</th>
                            <th scope="col"><span class="visually-hidden">Akcje</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($page->items as $user)
                            <tr>
                                <td>{{ $user->name }}</td>
                                <td class="font-monospace">{{ $user->login }}</td>
                                <td>{{ $user->role->label() }}</td>
                                <td>
                                    @if ($user->isActive)
                                        <span class="badge text-bg-info">aktywne</span>
                                    @else
                                        <span class="badge text-bg-danger">zablokowane</span>
                                    @endif
                                    @if ($user->mustChangePassword)
                                        <span class="badge text-bg-warning">zmiana hasła</span>
                                    @endif
                                </td>
                                <td class="service-meta">
                                    {{ $user->lastLoginAt !== null ? \App\Domain\FieldLabels::formatDate($user->lastLoginAt) : '—' }}
                                </td>
                                <td class="text-end text-nowrap">
                                    <span class="table-actions">
                                        <a class="btn btn-sm btn-link" href="{{ route('admin.users.edit', $user) }}">Edytuj</a>
                                        <form method="post" action="{{ route('admin.users.toggle', $user) }}"
                                              data-confirm="{{ $user->isActive ? 'Zablokować to konto?' : 'Odblokować to konto?' }}">
                                            @csrf
                                            <button class="btn btn-sm btn-link" type="submit">
                                                {{ $user->isActive ? 'Zablokuj' : 'Odblokuj' }}
                                            </button>
                                        </form>
                                    </span>
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
