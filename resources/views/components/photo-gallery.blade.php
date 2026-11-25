@props(['photos' => [], 'editable' => false, 'service' => null, 'group' => 'service-photos'])

@if (count($photos) === 0)
    <p class="service-meta mb-0">Brak zdjęć.</p>
@else
    <div class="photo-grid">
        @foreach ($photos as $photo)
            <div class="photo-item">
                <a href="{{ route('photos.show', $photo) }}"
                   data-lightbox="{{ $group }}"
                   data-alt="Zdjęcie zlecenia {{ $service?->reference ?? '' }}"
                   aria-label="Powiększ zdjęcie {{ $loop->iteration }}">
                    <img class="photo-thumb" src="{{ route('photos.thumb', $photo) }}"
                         alt="Miniatura zdjęcia {{ $loop->iteration }}" loading="lazy" width="160" height="160">
                </a>

                @if ($editable && $service !== null)
                    <form method="post" action="{{ route('services.photos.destroy', [$service, $photo]) }}"
                          data-confirm="Usunąć to zdjęcie?" data-guard-submit>
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-light photo-remove" type="submit"
                                aria-label="Usuń zdjęcie {{ $loop->iteration }}">
                            <x-icon name="x" :size="13" />
                        </button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
@endif
