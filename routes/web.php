<?php

declare(strict_types=1);

use App\Contracts\ServicePhotoRepository;
use App\Contracts\ServiceRepository;
use App\Contracts\UserRepository;
use App\Domain\Data\ServicePhotoRecord;
use App\Domain\Data\ServiceRecord;
use App\Domain\Data\UserRecord;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AdvanceStatusController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CancelController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\OfflineController;
use App\Http\Controllers\PrintController;
use App\Http\Controllers\QueueController;
use App\Http\Controllers\ReleaseController;
use App\Http\Controllers\ReleasedController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ServicePhotoController;
use App\Http\Controllers\WebInstallController;
use App\Http\Middleware\RequirePasswordChange;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes
|--------------------------------------------------------------------------
|
| Everything except `/login`, the manifest, the offline shell and the one-time
| installer lives behind `auth`. There is no public status lookup (decision D4),
| so the old `reklamacja*` / `wyswietlanie*` / `wydane.php` pages do not exist.
|
*/

Route::bind('service', static function (string $value): ServiceRecord {
    $service = is_numeric($value) ? app(ServiceRepository::class)->find((int) $value) : null;

    return $service ?? abort(404);
});

Route::bind('photo', static function (string $value): ServicePhotoRecord {
    $photo = is_numeric($value) ? app(ServicePhotoRepository::class)->find((int) $value) : null;

    return $photo ?? abort(404);
});

Route::bind('user', static function (string $value): UserRecord {
    $user = is_numeric($value) ? app(UserRepository::class)->find((int) $value) : null;

    return $user ?? abort(404);
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('login.attempt');
});

Route::get('/manifest.webmanifest', ManifestController::class)->name('manifest');
Route::get('/offline', OfflineController::class)->name('offline');
Route::get('/__install', WebInstallController::class)->name('install');

Route::middleware(['auth', RequirePasswordChange::class])->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', QueueController::class)->name('services.queue');
    Route::get('/search', SearchController::class)->name('services.search');
    Route::get('/released', ReleasedController::class)->name('services.released');

    Route::get('/services/create', [ServiceController::class, 'create'])->name('services.create');
    Route::post('/services', [ServiceController::class, 'store'])
        ->middleware('throttle:mutations')
        ->name('services.store');

    Route::get('/services/{service}', [ServiceController::class, 'show'])->name('services.show');
    Route::get('/services/{service}/edit', [ServiceController::class, 'edit'])->name('services.edit');
    Route::put('/services/{service}', [ServiceController::class, 'update'])
        ->middleware('throttle:mutations')
        ->name('services.update');

    Route::post('/services/{service}/advance', AdvanceStatusController::class)
        ->middleware('throttle:mutations')
        ->name('services.advance');

    Route::get('/services/{service}/release', [ReleaseController::class, 'show'])->name('services.release');
    Route::post('/services/{service}/release', [ReleaseController::class, 'store'])
        ->middleware('throttle:mutations')
        ->name('services.release.store');

    Route::post('/services/{service}/cancel', CancelController::class)
        ->middleware('throttle:mutations')
        ->name('services.cancel');

    Route::post('/services/{service}/photos', [ServicePhotoController::class, 'store'])
        ->middleware('throttle:mutations')
        ->name('services.photos.store');
    Route::delete('/services/{service}/photos/{photo}', [ServicePhotoController::class, 'destroy'])
        ->middleware('throttle:mutations')
        ->name('services.photos.destroy');

    Route::get('/photos/{photo}', [ServicePhotoController::class, 'show'])->name('photos.show');
    Route::get('/photos/{photo}/thumb', [ServicePhotoController::class, 'thumb'])->name('photos.thumb');

    Route::get('/services/{service}/print/acceptance', [PrintController::class, 'acceptance'])
        ->name('print.acceptance');
    Route::get('/services/{service}/print/release', [PrintController::class, 'release'])
        ->name('print.release');

    Route::get('/admin/users', [UserController::class, 'index'])->name('admin.users.index');
    Route::get('/admin/users/create', [UserController::class, 'create'])->name('admin.users.create');
    Route::post('/admin/users', [UserController::class, 'store'])
        ->middleware('throttle:mutations')
        ->name('admin.users.store');
    Route::get('/admin/users/{user}/edit', [UserController::class, 'edit'])->name('admin.users.edit');
    Route::put('/admin/users/{user}', [UserController::class, 'update'])
        ->middleware('throttle:mutations')
        ->name('admin.users.update');
    Route::post('/admin/users/{user}/toggle', [UserController::class, 'toggle'])
        ->middleware('throttle:mutations')
        ->name('admin.users.toggle');

    Route::get('/account/password', [AccountController::class, 'edit'])->name('account.password');
    Route::put('/account/password', [AccountController::class, 'update'])->name('account.password.update');
});
