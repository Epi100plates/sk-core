<?php

declare(strict_types=1);

namespace App\Support\Legacy;

use App\Contracts\ServiceEventRepository;
use App\Contracts\ServicePhotoRepository;
use App\Contracts\ServiceRepository;
use App\Contracts\UserRepository;
use App\Domain\Data\ServiceEventRecord;
use App\Domain\Data\ServicePhotoRecord;
use App\Domain\Data\ServiceRecord;
use App\Domain\Data\UserRecord;
use App\Domain\Enums\Role;
use App\Domain\Enums\ServiceEventType;
use App\Domain\Enums\ServiceStatus;
use App\Domain\ValueObjects\Money;
use App\Domain\ValueObjects\Phone;
use App\Support\Crypto\SecretBox;
use App\Support\Photos\PhotoStore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Imports the old `laptopservice` / `laptop_images` / `service_history` / `users`
 * rows (task T2.7, plan section 9.2).
 *
 * The import is idempotent — records are matched by `legacy_id` and by login —
 * and every ambiguous value is written to the report instead of being guessed
 * silently. It is optional: the application starts on empty data and this code
 * simply waits for the owner's dump.
 */
final class LegacyImporter
{
    public function __construct(
        private readonly ServiceRepository $services,
        private readonly ServiceEventRepository $events,
        private readonly ServicePhotoRepository $photos,
        private readonly UserRepository $users,
        private readonly SecretBox $secrets,
    ) {}

    /**
     * @param  array<string, list<array<string, string|null>>>  $tables
     */
    public function run(array $tables, bool $dryRun, ?string $uploadsPath = null): LegacyReport
    {
        $report = new LegacyReport;
        $technicalId = $this->ensureTechnicalAccount($dryRun);
        $userMap = $this->importUsers($tables['users'] ?? [], $dryRun, $report);
        $serviceMap = $this->importServices($tables['laptopservice'] ?? [], $userMap, $technicalId, $dryRun, $report);

        if ($uploadsPath !== null) {
            $this->importPhotos($tables['laptop_images'] ?? [], $serviceMap, $uploadsPath, $dryRun, $report);
        } else {
            $report->warn('Nie podano katalogu uploads — pominięto import zdjęć (użyj --uploads=...).');
        }

        $this->importHistory($tables['service_history'] ?? [], $serviceMap, $userMap, $dryRun, $report);

        return $report;
    }

    private function ensureTechnicalAccount(bool $dryRun): int
    {
        $existing = $this->users->findByLogin('legacy-import');

        if ($existing instanceof UserRecord) {
            return $existing->id;
        }

        if ($dryRun) {
            // A dry run must not write; any id will do for the report.
            return 0;
        }

        $created = $this->users->save(UserRecord::fromArray([
            'name' => 'Import ze starej aplikacji',
            'login' => 'legacy-import',
            'password' => Hash::make(bin2hex(random_bytes(16))),
            'role' => Role::Technician->value,
            'is_active' => false,
            'must_change_password' => true,
        ]));

        return $created->id;
    }

    /**
     * @param  list<array<string, string|null>>  $rows
     * @return array<string, int> legacy login => new user id
     */
    private function importUsers(array $rows, bool $dryRun, LegacyReport $report): array
    {
        $map = [];

        foreach ($rows as $row) {
            $login = trim((string) ($row['Login'] ?? ''));

            if ($login === '') {
                $report->count('users_skipped');

                continue;
            }

            $existing = $this->users->findByLogin($login);

            if ($existing instanceof UserRecord) {
                $map[$login] = $existing->id;
                $report->count('users_existing');

                continue;
            }

            $plaintext = (string) ($row['Haslo'] ?? '');

            if ($plaintext === '') {
                $report->warn('Konto „'.$login.'” bez hasła — pominięte.');
                $report->count('users_skipped');

                continue;
            }

            $report->count('users_imported');

            if ($dryRun) {
                continue;
            }

            $created = $this->users->save(UserRecord::fromArray([
                'name' => $login,
                'login' => $login,
                'password' => Hash::make($plaintext),
                'role' => Role::Employee->value,
                'is_active' => true,
                'must_change_password' => true,
            ]));

            $map[$login] = $created->id;
        }

        return $map;
    }

    /**
     * @param  list<array<string, string|null>>  $rows
     * @param  array<string, int>  $userMap
     * @return array<int, int> legacy service id => new service id
     */
    private function importServices(array $rows, array $userMap, int $fallbackUserId, bool $dryRun, LegacyReport $report): array
    {
        $map = [];

        foreach ($rows as $row) {
            $legacyId = (int) ($row['Id'] ?? 0);

            if ($legacyId <= 0) {
                $report->count('services_skipped');

                continue;
            }

            // Match by legacy_id using a full scan through `all()` — the import is
            // a one-off operation, clarity beats a dedicated query method.
            $alreadyImported = null;

            foreach ($this->services->all() as $candidate) {
                if ($candidate->legacyId === $legacyId) {
                    $alreadyImported = $candidate;

                    break;
                }
            }

            if ($alreadyImported instanceof ServiceRecord) {
                $map[$legacyId] = $alreadyImported->id;
                $report->count('services_existing');

                continue;
            }

            $record = $this->mapService($row, $userMap, $fallbackUserId, $report);

            if ($dryRun) {
                $report->count('services_imported');

                continue;
            }

            $created = $this->services->save($record);
            $map[$legacyId] = $created->id;
            $report->count('services_imported');

            $this->events->append(new ServiceEventRecord(
                id: 0,
                serviceId: $created->id,
                type: ServiceEventType::Accepted,
                actorId: $created->acceptedBy,
                fromStatus: null,
                toStatus: $created->status->value,
                changes: [],
                description: 'Import ze starej aplikacji (Id='.$legacyId.', przyjął: '.($row['przyjety_przez'] ?? 'nieznany').')',
                ip: null,
                userAgent: 'legacy-import',
                createdAt: $created->acceptedAt,
            ));
        }

        return $map;
    }

    /**
     * @param  array<string, string|null>  $row
     * @param  array<string, int>  $userMap
     */
    private function mapService(array $row, array $userMap, int $fallbackUserId, LegacyReport $report): ServiceRecord
    {
        $legacyId = (int) ($row['Id'] ?? 0);
        $rawPhone = (string) ($row['nr_tel'] ?? '');
        $phone = Phone::fromInput($rawPhone);

        if (! $phone->isValid()) {
            $report->warn('Zlecenie '.$legacyId.': nie można znormalizować telefonu „'.$rawPhone.'”.');
        }

        $acceptedAt = $this->parseDate($row['created_at'] ?? null);

        if ($acceptedAt === null) {
            $acceptedAt = $this->parseDate($row['updated_at'] ?? null) ?? CarbonImmutable::now('UTC');
            $report->warn('Zlecenie '.$legacyId.': brak created_at — użyto updated_at.');
        }

        $plannedAt = $this->parseDate($row['planowany_czas_wydania'] ?? null);

        if ($plannedAt === null) {
            $plannedAt = $acceptedAt->addDays(3);
            $report->warn('Zlecenie '.$legacyId.': brak terminu — ustawiono datę przyjęcia + 3 dni.');
        }

        $status = ServiceStatus::fromLegacy($row['status'] ?? null);
        $rawStatus = trim((string) ($row['status'] ?? ''));

        if ($rawStatus !== '' && ServiceStatus::tryFrom($rawStatus) === null) {
            $report->warn('Zlecenie '.$legacyId.': nieznany status „'.$rawStatus.'” → pending.');
        }

        $releasedAt = $this->parseDate($row['godzina_wydania'] ?? null);

        if ($status === ServiceStatus::Released && $releasedAt === null) {
            $releasedAt = $acceptedAt;
            $report->warn('Zlecenie '.$legacyId.': wydane bez daty wydania — użyto daty przyjęcia.');
        }

        $confirmationIssuedAt = null;

        if ((int) ($row['potwierdzenie_odbioru'] ?? 0) === 1) {
            $confirmationIssuedAt = $acceptedAt;
            $report->warn('Zlecenie '.$legacyId.': potwierdzenie_odbioru=1 → confirmation_issued_at = data przyjęcia.');
        }

        $acceptedByLogin = trim((string) ($row['przyjety_przez'] ?? ''));
        $acceptedBy = $userMap[$acceptedByLogin] ?? $fallbackUserId;

        if (! isset($userMap[$acceptedByLogin])) {
            $report->warn('Zlecenie '.$legacyId.': brak konta „'.$acceptedByLogin.'” — przypisano konto techniczne legacy-import.');
        }

        $price = Money::tryFromInput($row['cena'] ?? '0') ?? Money::zero();
        $cost = Money::tryFromInput($row['koszty'] ?? '0') ?? Money::zero();
        $seal = trim((string) ($row['plomba'] ?? ''));

        if (($row['cena'] ?? null) !== null && (string) $row['cena'] !== $price->toDecimalString()) {
            $report->warn('Zlecenie '.$legacyId.': cena „'.$row['cena'].'” zapisana jako '.$price->toDecimalString().'.');
        }

        return ServiceRecord::fromArray([
            'id' => 0,
            'legacy_id' => $legacyId,
            'reference' => $this->services->nextReference((int) $acceptedAt->format('Y')),
            'phone' => $rawPhone,
            'phone_normalized' => $phone->normalized,
            'device_model' => mb_substr(trim((string) ($row['model_laptopa'] ?? '')), 0, 100),
            'serial_number' => $this->nullable($row['serial_number'] ?? null),
            'issue' => (string) ($row['usterka'] ?? ''),
            'device_password' => $this->secrets->decrypt($this->nullable($row['haslo'] ?? null)),
            'notes' => $this->nullable($row['uwagi'] ?? null),
            'is_express' => (int) ($row['express'] ?? 0) === 1,
            'planned_at' => $plannedAt->format('Y-m-d H:i:s'),
            'price' => $price->toDecimalString(),
            'cost' => $cost->toDecimalString(),
            'seal_number' => $seal === '' ? null : mb_strtoupper($seal),
            'status' => $status->value,
            'accepted_by' => $acceptedBy,
            'accepted_at' => $acceptedAt->format('Y-m-d H:i:s'),
            'confirmation_issued_at' => $confirmationIssuedAt?->format('Y-m-d H:i:s'),
            'released_at' => $releasedAt?->format('Y-m-d H:i:s'),
            'released_by' => $releasedAt === null ? null : $acceptedBy,
            'version' => 0,
            'created_at' => $acceptedAt->format('Y-m-d H:i:s'),
            'updated_at' => $acceptedAt->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param  list<array<string, string|null>>  $rows
     * @param  array<int, int>  $serviceMap
     */
    private function importPhotos(array $rows, array $serviceMap, string $uploadsPath, bool $dryRun, LegacyReport $report): void
    {
        foreach ($rows as $row) {
            $legacyServiceId = (int) ($row['laptop_id'] ?? 0);
            $newServiceId = $serviceMap[$legacyServiceId] ?? null;
            $path = trim((string) ($row['image_path'] ?? ''));

            if ($newServiceId === null || $path === '') {
                $report->count('photos_skipped');

                continue;
            }

            $absolute = $uploadsPath.DIRECTORY_SEPARATOR.ltrim(str_replace(['uploads/', '/serwis/uploads/', '/uploads/'], '', $path), '/');

            if (! is_file($absolute)) {
                $report->warn('Zdjęcie „'.$path.'” nie istnieje w katalogu uploads — rekord pominięty.');
                $report->count('photos_skipped');

                continue;
            }

            $report->count('photos_imported');

            if ($dryRun) {
                continue;
            }

            $contents = file_get_contents($absolute);

            if ($contents === false) {
                continue;
            }

            $name = bin2hex(random_bytes(12));
            $relative = sprintf('photos/%d/%s.jpg', $newServiceId, $name);
            $thumb = sprintf('photos/%d/thumbs/%s.jpg', $newServiceId, $name);

            $image = @imagecreatefromstring($contents);

            if ($image === false) {
                $report->warn('Zdjęcie „'.$path.'” nie jest obrazem — pominięte.');

                continue;
            }

            $width = imagesx($image);
            $height = imagesy($image);

            ob_start();
            imagejpeg($image, null, 88);
            $encoded = (string) ob_get_clean();

            $targetWidth = min(PhotoStore::THUMB_WIDTH, $width);
            $targetHeight = max(1, (int) round($height * ($targetWidth / $width)));
            $thumbImage = imagecreatetruecolor($targetWidth, $targetHeight);
            imagecopyresampled($thumbImage, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

            ob_start();
            imagejpeg($thumbImage, null, 88);
            $thumbEncoded = (string) ob_get_clean();

            imagedestroy($thumbImage);
            imagedestroy($image);

            Storage::disk(PhotoStore::DISK)->put($relative, $encoded);
            Storage::disk(PhotoStore::DISK)->put($thumb, $thumbEncoded);

            $this->photos->save(new ServicePhotoRecord(
                id: 0,
                serviceId: $newServiceId,
                disk: PhotoStore::DISK,
                path: $relative,
                thumbPath: $thumb,
                mime: 'image/jpeg',
                size: strlen($encoded),
                width: $width,
                height: $height,
                checksum: hash('sha256', $encoded),
                uploadedBy: 1,
                createdAt: CarbonImmutable::now('UTC'),
            ));
        }
    }

    /**
     * @param  list<array<string, string|null>>  $rows
     * @param  array<int, int>  $serviceMap
     * @param  array<string, int>  $userMap
     */
    private function importHistory(array $rows, array $serviceMap, array $userMap, bool $dryRun, LegacyReport $report): void
    {
        $map = [
            'accepted' => ServiceEventType::Accepted,
            'edited' => ServiceEventType::Updated,
            'status_changed' => ServiceEventType::StatusChanged,
            'released' => ServiceEventType::Released,
        ];

        foreach ($rows as $row) {
            $legacyServiceId = (int) ($row['service_id'] ?? 0);
            $newServiceId = $serviceMap[$legacyServiceId] ?? null;

            if ($newServiceId === null) {
                $report->count('history_skipped');

                continue;
            }

            $type = $map[trim((string) ($row['action_type'] ?? ''))] ?? ServiceEventType::Updated;
            $actorLogin = trim((string) ($row['performed_by'] ?? ''));

            $report->count('history_imported');

            if ($dryRun) {
                continue;
            }

            $this->events->append(new ServiceEventRecord(
                id: 0,
                serviceId: $newServiceId,
                type: $type,
                actorId: $userMap[$actorLogin] ?? null,
                fromStatus: $this->nullable($row['old_status'] ?? null),
                toStatus: $this->nullable($row['new_status'] ?? null),
                changes: $this->decodeChanges($row),
                description: $this->nullable($row['description'] ?? null),
                ip: null,
                userAgent: 'legacy-import',
                createdAt: $this->parseDate($row['performed_at'] ?? null) ?? CarbonImmutable::now('UTC'),
            ));
        }
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function decodeChanges(array $row): array
    {
        $previous = json_decode((string) ($row['previous_values'] ?? ''), true);
        $next = json_decode((string) ($row['new_values'] ?? ''), true);

        if (! is_array($previous) || ! is_array($next)) {
            return [];
        }

        $changes = [];

        foreach ($next as $field => $value) {
            if (! is_string($field)) {
                continue;
            }

            $before = $previous[$field] ?? null;

            if ($before === $value) {
                continue;
            }

            $changes[$field] = ['from' => $before, 'to' => $value];
        }

        return $changes;
    }

    private function parseDate(?string $value): ?CarbonImmutable
    {
        if ($value === null || trim($value) === '' || str_starts_with(trim($value), '0000-00-00')) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, 'Europe/Warsaw')->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    private function nullable(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
