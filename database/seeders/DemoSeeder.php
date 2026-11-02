<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\AcceptService;
use App\Actions\AdvanceServiceStatus;
use App\Actions\ReleaseDetails;
use App\Actions\ReleaseService;
use App\Actions\ServiceDetails;
use App\Contracts\UserRepository;
use App\Domain\Data\UserRecord;
use App\Domain\Enums\Role;
use App\Support\Audit\AuditContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * 30 realistic services for development and UAT (feature F-53).
 *
 * Uses the same actions as the UI, so the demo data obeys the workflow and has
 * a real audit trail. Refuses to run in production.
 */
final class DemoSeeder extends Seeder
{
    /** @var list<string> */
    private const MODELS = [
        'Dell Latitude 5420', 'Lenovo ThinkPad T490', 'HP EliteBook 840 G7',
        'Asus ZenBook 14', 'Acer Aspire 5', 'MacBook Air 2019',
        'MSI Modern 15', 'Dell XPS 13 9310', 'Lenovo IdeaPad 3',
        'Huawei MateBook D14',
    ];

    /** @var list<string> */
    private const ISSUES = [
        'Klient zgłasza brak ładowania — laptop nie startuje po podłączeniu zasilacza.',
        'Przegrzewa się i wyłącza po 15 minutach pracy.',
        'Nie działa klawiatura (kilka klawiszy nie reaguje).',
        'Uszkodzony zawias matrycy, ekran miga przy ruchu klapy.',
        'System nie wstaje — bluescreen 0x0000007B przy starcie.',
        'Wylała się kawa na klawiaturę — klawisze się kleją.',
        'Bateria trzyma 20 minut, do wymiany.',
        'Brak dźwięku w głośnikach, słuchawki działają.',
        'Wolno działa, podejrzenie dysku HDD/SSD do wymiany.',
        'Pęknięta matryca, pas na ekranie.',
    ];

    public function run(
        UserRepository $users,
        AcceptService $accept,
        AdvanceServiceStatus $advance,
        ReleaseService $release,
        ?int $count = null,
    ): void {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder nie może działać na produkcji.');
        }

        $total = $count ?? 30;

        $admin = $users->findByLogin('admin');

        if (! $admin instanceof UserRecord) {
            $admin = $users->save(UserRecord::fromArray([
                'name' => 'Administrator',
                'login' => 'admin',
                'password' => Hash::make(Str::password(16)),
                'role' => Role::Admin->value,
                'is_active' => true,
                'must_change_password' => true,
            ]));
        }

        $context = AuditContext::console();
        $now = CarbonImmutable::now('UTC')->startOfDay();

        for ($i = 0; $i < $total; $i++) {
            $model = self::MODELS[$i % count(self::MODELS)];
            $issue = self::ISSUES[$i % count(self::ISSUES)];
            $phone = sprintf('+485%08d', 100000 + $i * 137);
            $express = $i % 7 === 0;

            $service = $accept->handle(new ServiceDetails(
                phone: $phone,
                deviceModel: $model,
                serialNumber: 'SN-'.strtoupper(Str::random(8)),
                issue: $issue,
                devicePassword: $i % 4 === 0 ? 'haslo'.($i + 1) : null,
                notes: $i % 5 === 0 ? 'Klient prosi o telefon przed naprawą.' : null,
                isExpress: $express,
                plannedAt: $now->subDays($i % 10)->addDay()->setTime(15, 0),
                price: (string) (120 + $i * 15),
                cost: (string) (40 + $i * 7),
                sealNumber: sprintf('PLO-%03d-2026', $i + 1),
            ), $admin, $context);

            $steps = $i % 5;

            for ($step = 0; $step < $steps && $service->status->nextStatus() !== null; $step++) {
                $service = $advance->handle($service, $admin, $context);
            }

            if ($i % 4 === 0 && $service->status->isActive()) {
                $release->handle(
                    $service,
                    new ReleaseDetails(
                        releasedAt: CarbonImmutable::now('UTC')->subHours(2),
                        price: $service->price,
                        cost: $service->cost,
                        notes: $service->notes,
                        sealNumber: $service->sealNumber,
                        devicePassword: $service->devicePassword,
                        serialNumber: $service->serialNumber,
                        printProtocol: false,
                    ),
                    $admin,
                    $context,
                );
            }
        }
    }
}
