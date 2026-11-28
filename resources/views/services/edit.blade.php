@extends('layouts.app')

@section('title', 'Edycja '.$service->reference)
@section('own-errors', '1')

@section('content')
    @php
        $approvalPending = $service->status === \App\Domain\Enums\ServiceStatus::AwaitingApproval;
    @endphp

    @if ($approvalPending)
        {{-- The owner works in the normal edit panel: the acceptance is the primary
             action here, saving the fields stays available next to it. --}}
        @can('approveRelease', $service)
            <section class="app-card approval-panel mb-3" aria-labelledby="approval-title">
                <div class="app-card-body">
                    <div class="approval-panel__row">
                        <div class="approval-panel__text">
                            <span class="eyebrow">Akceptacja wydania</span>
                            <p class="h6 mb-0" id="approval-title">Zlecenie czeka na Twoją akceptację.</p>
                        </div>

                        <div class="approval-panel__actions">
                            <form method="post" action="{{ route('services.approval.approve', $service) }}"
                                  data-guard-submit>
                                @csrf
                                <button class="btn btn-primary" type="submit">Potwierdź</button>
                            </form>
                        </div>
                    </div>

                    <details class="approval-panel__return">
                        <summary class="approval-panel__return-summary">Zwróć z uwagami</summary>
                        <form method="post" action="{{ route('services.approval.return', $service) }}"
                              data-guard-submit>
                            @csrf
                            <label class="form-label" for="approval_remarks">Uwagi do poprawy</label>
                            <textarea class="form-control mb-2" id="approval_remarks" name="approval_remarks"
                                      rows="2" maxlength="1000" required
                                      placeholder="Krótko: co jeszcze poprawić.">{{ old('approval_remarks') }}</textarea>
                            @error('approval_remarks')<p class="invalid-feedback d-block">{{ $message }}</p>@enderror
                            <button class="btn btn-outline-primary btn-sm" type="submit">Zwróć do kolejki</button>
                        </form>
                    </details>
                </div>
            </section>
        @endcan
    @endif

    <x-service-form
        :service="$service"
        :planned-at-input="$plannedAtInput"
        :approval-pending="$approvalPending && \Illuminate\Support\Facades\Gate::allows('approveRelease', $service)"
        action="{{ route('services.update', $service) }}"
        method="PUT"
        submit-label="Zapisz zmiany" />
@endsection
