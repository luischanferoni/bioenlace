<?php

namespace common\tests\unit\clinical;

use common\components\Domain\Scheduling\Agenda\Application\Catalog\TurnoAntinoshowNotificationCatalog;
use PHPUnit\Framework\TestCase;

final class TurnoAntinoshowNotificationCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        TurnoAntinoshowNotificationCatalog::resetCacheForTests();
    }

    public function testLoadsEveryPatientMessageWithoutChangingItsCopy(): void
    {
        $catalog = new TurnoAntinoshowNotificationCatalog();

        self::assertSame([
            'title' => 'Confirmá tu turno',
            'body' => 'Confirmá asistencia al turno del {{fecha}} {{hora}}.',
        ], $catalog->forMessage('extra_confirm'));
        self::assertSame([
            'title' => 'Confirmá tu turno',
            'body' => 'Confirmá asistencia al turno del {{fecha}} {{hora}}',
        ], $catalog->forMessage('scheduled_confirm_request'));
        self::assertSame([
            'title' => 'Tu turno es pronto',
            'body' => 'Recordá tu turno del {{fecha}} a las {{hora}}.',
        ], $catalog->forMessage('reminder'));
        self::assertSame([
            'title' => 'Turno liberado',
            'body' => 'No confirmaste tu turno del {{fecha}}. El cupo quedó disponible; podés reservar otro horario desde la app.',
        ], $catalog->forMessage('slot_released'));
    }

    public function testUnknownMessageFailsExplicitly(): void
    {
        $this->expectException(\RuntimeException::class);

        (new TurnoAntinoshowNotificationCatalog())->forMessage('unknown');
    }
}
