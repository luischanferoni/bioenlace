<?php

namespace common\tests\unit\clinical;

use common\components\Domain\Scheduling\Agenda\Application\Catalog\EncounterAsyncPushNotificationCatalog;
use PHPUnit\Framework\TestCase;

final class EncounterAsyncPushNotificationCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        EncounterAsyncPushNotificationCatalog::resetCacheForTests();
    }

    public function testRendersPatientAndStaffPushCopy(): void
    {
        $catalog = new EncounterAsyncPushNotificationCatalog();

        self::assertSame([
            'title' => 'Consulta cerrada',
            'body' => 'Servicio — Podés continuar.',
        ], $catalog->event('cerrada_patient', [
            '{{servicio}}' => 'Servicio',
            '{{resolution_label}}' => 'Podés continuar.',
        ]));
        self::assertSame([
            'title' => 'Mensaje del paciente',
            'body' => 'Ana Pérez — Cardiología',
        ], $catalog->event('mensaje_paciente_staff', [
            '{{paciente}}' => 'Ana Pérez',
            '{{servicio}}' => 'Cardiología',
        ]));
    }

    public function testRejectsUnknownOrIncompleteEvent(): void
    {
        $this->expectException(\RuntimeException::class);

        (new EncounterAsyncPushNotificationCatalog())->event('unknown_event');
    }
}
