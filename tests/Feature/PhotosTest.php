<?php

declare(strict_types=1);

use App\Contracts\ServiceEventRepository;
use App\Contracts\ServicePhotoRepository;
use App\Domain\Enums\Role;
use App\Domain\Enums\ServiceEventType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| F-18, F-37, D-52 — photos stay private and deletions are audited;
| the photos card is an async region that swaps in place (add/delete without
| a page reload, see the async UI contract)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    Storage::fake('local');
});

/**
 * Cuts the photos card region out of the rendered page. The advance and cancel
 * forms elsewhere on the page keep their own submit guard, so the async
 * attributes are asserted against this region only.
 */
function photosRegion(string $html): string
{
    $start = strpos($html, 'data-async-region="service-photos"');

    if ($start === false) {
        return '';
    }

    $end = strpos($html, '</section>', $start);

    return substr($html, $start, $end === false ? null : $end - $start);
}

it('stores a photo outside the web root and creates a thumbnail', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user);

    $this->actingAsUser($user)
        ->post('/services/'.$service->id.'/photos', [
            'photos' => [UploadedFile::fake()->image('laptop.jpg', 1200, 900)],
        ])
        ->assertRedirect();

    $photos = app(ServicePhotoRepository::class)->forService($service->id);

    expect($photos)->toHaveCount(1);

    $photo = $photos[0];

    expect($photo->path)->toStartWith('photos/'.$service->id.'/')
        ->and($photo->path)->toEndWith('.jpg')
        ->and($photo->mime)->toBe('image/jpeg')
        ->and($photo->width)->toBe(1200)
        ->and($photo->height)->toBe(900)
        ->and($photo->checksum)->toHaveLength(64);

    Storage::disk('local')->assertExists($photo->path);
    Storage::disk('local')->assertExists($photo->thumbPath);

    $events = app(ServiceEventRepository::class)->forService($service->id)->items;
    $photoEvents = array_values(array_filter(
        $events,
        static fn ($event): bool => $event->type === ServiceEventType::PhotoAdded,
    ));

    expect($photoEvents)->toHaveCount(1);
})->with('stores');

it('serves a photo only to a signed-in user, privately', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user);

    $this->actingAsUser($user)->post('/services/'.$service->id.'/photos', [
        'photos' => [UploadedFile::fake()->image('laptop.jpg', 600, 400)],
    ]);

    $photo = app(ServicePhotoRepository::class)->forService($service->id)[0];

    auth()->logout();

    $this->get('/photos/'.$photo->id)->assertRedirect(route('login'));

    $response = $this->actingAsUser($user)->get('/photos/'.$photo->id);

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/jpeg');

    expect((string) $response->headers->get('Cache-Control'))
        ->toContain('private')
        ->toContain('max-age=0');

    $this->actingAsUser($user)->get('/photos/'.$photo->id.'/thumb')->assertOk();
})->with('stores');

it('rejects a file that is not an image', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user);

    $this->actingAsUser($user)
        ->post('/services/'.$service->id.'/photos', [
            'photos' => [UploadedFile::fake()->create('skrypt.php', 10, 'text/x-php')],
        ])
        ->assertSessionHasErrors('photos.0');

    expect(app(ServicePhotoRepository::class)->forService($service->id))->toBe([]);
})->with('stores');

it('deletes a photo with its files and an audit entry', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user);

    $this->actingAsUser($user)->post('/services/'.$service->id.'/photos', [
        'photos' => [UploadedFile::fake()->image('laptop.jpg', 600, 400)],
    ]);

    $photo = app(ServicePhotoRepository::class)->forService($service->id)[0];
    $path = $photo->path;

    $this->actingAsUser($user)
        ->delete('/services/'.$service->id.'/photos/'.$photo->id)
        ->assertRedirect();

    expect(app(ServicePhotoRepository::class)->find($photo->id))->toBeNull();
    Storage::disk('local')->assertMissing($path);

    $events = app(ServiceEventRepository::class)->forService($service->id)->items;
    $deleted = array_values(array_filter(
        $events,
        static fn ($event): bool => $event->type === ServiceEventType::PhotoDeleted,
    ));

    expect($deleted)->toHaveCount(1);
})->with('stores');

it('refuses to delete a photo through another service id', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $first = $this->acceptService($user, ['device_model' => 'Pierwszy']);
    $second = $this->acceptService($user, ['device_model' => 'Drugi']);

    $this->actingAsUser($user)->post('/services/'.$first->id.'/photos', [
        'photos' => [UploadedFile::fake()->image('laptop.jpg', 300, 300)],
    ]);

    $photo = app(ServicePhotoRepository::class)->forService($first->id)[0];

    $this->actingAsUser($user)
        ->delete('/services/'.$second->id.'/photos/'.$photo->id)
        ->assertNotFound();

    expect(app(ServicePhotoRepository::class)->find($photo->id))->not->toBeNull();
})->with('stores');

it('rejects more than ten photos at once', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user);

    $files = [];

    for ($i = 0; $i < 11; $i++) {
        $files[] = UploadedFile::fake()->image('foto'.$i.'.jpg', 100, 100);
    }

    $this->actingAsUser($user)
        ->post('/services/'.$service->id.'/photos', ['photos' => $files])
        ->assertSessionHasErrors('photos');

    expect(app(ServicePhotoRepository::class)->forService($service->id))->toBe([]);
})->with('stores');

it('renders the photos card as one async region with an async upload form', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user);

    $response = $this->actingAsUser($user)->get('/services/'.$service->id);

    $response->assertOk()->assertSee('data-async-region="service-photos"', false);

    $region = photosRegion((string) $response->getContent());

    expect($region)->not->toBe('')
        ->and(substr_count($region, 'data-async-region'))->toBe(1)
        ->and($region)->toContain('Brak zdjęć.')
        ->and($region)->toContain('multiple required');

    preg_match('/<form class="photo-upload"[^>]*>/', $region, $upload);

    $uploadTag = $upload[0] ?? '';

    expect($uploadTag)->toContain('data-async-form')
        ->and($uploadTag)->toContain('data-offline-blocked')
        ->and($uploadTag)->toContain('enctype="multipart/form-data"')
        ->and($uploadTag)->not->toContain('data-guard-submit');
})->with('stores');

it('renders photo delete forms as async forms that spoof DELETE', function (string $store) {
    usingStore($this, $store);

    $user = $this->createUser('jan', Role::Employee);
    $service = $this->acceptService($user);

    $this->actingAsUser($user)->post('/services/'.$service->id.'/photos', [
        'photos' => [
            UploadedFile::fake()->image('pierwsze.jpg', 600, 400),
            UploadedFile::fake()->image('drugie.jpg', 600, 400),
        ],
    ]);

    $response = $this->actingAsUser($user)->get('/services/'.$service->id);

    $response->assertOk();

    $region = photosRegion((string) $response->getContent());

    preg_match('/<form[^>]*action="[^"]*\/photos\/[^"]*"[^>]*>.*?<\/form>/s', $region, $form);
    $deleteForm = $form[0] ?? '';

    expect($deleteForm)->toContain('data-async-form')
        ->and($deleteForm)->toContain('data-confirm="Usunąć to zdjęcie?"')
        ->and($deleteForm)->toContain('<input type="hidden" name="_method" value="DELETE">')
        ->and($deleteForm)->not->toContain('data-guard-submit');

    // Both photos got their own delete button, each labelled uniquely.
    expect($region)->toContain('aria-label="Usuń zdjęcie 1"')
        ->and($region)->toContain('aria-label="Usuń zdjęcie 2"')
        ->and($region)->toContain('aria-label="Powiększ zdjęcie 2"');

    // The count in the card header sits inside the region, so it is swapped
    // together with the gallery and the upload form.
    expect($region)->toContain('<span class="service-meta">2</span>');
})->with('stores');
