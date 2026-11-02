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
| F-18, F-37, D-52 — photos stay private and deletions are audited
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    Storage::fake('local');
});

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
