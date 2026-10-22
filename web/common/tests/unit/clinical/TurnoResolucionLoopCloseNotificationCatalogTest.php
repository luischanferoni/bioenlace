<?php

namespace common\tests\unit\clinical;

use common\components\Domain\Scheduling\Agenda\Application\Catalog\TurnoResolucionLoopCloseNotificationCatalog;
use PHPUnit\Framework\TestCase;

final class TurnoResolucionLoopCloseNotificationCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        TurnoResolucionLoopCloseNotificationCatalog::resetCacheForTests();
    }

    public function testLoadsPatientAndStaffCopyWithoutChangingTemplates(): void
    {
        $catalog = new TurnoResolucionLoopCloseNotificationCatalog();

        self::assertSame([
            'title' => 'Resolución sin respuesta — seguimiento prioritario',
            'body_template' => 'Turno del {{fecha}} sin reubicar tras el plazo de contacto. Paciente banda {{urgency_band}}.',
        ], $catalog->forMessage('staff_escalation'));
        self::assertSame([
            'title' => 'Turno liberado',
            'body' => 'No recibimos respuesta para reubicar tu turno del {{fecha}}. Volvé a reservar cuando puedas desde la app.',
        ], $catalog->forMessage('cancel_turno'));
        self::assertSame([
            'title' => 'Seguimos con tu turno',
            'body' => 'Aún estamos gestionando un nuevo horario para tu turno del {{fecha}}. Te avisaremos cuando haya novedades.',
        ], $catalog->forMessage('keep_in_resolution'));
    }

    public function testUnknownMessageFailsExplicitly(): void
    {
        $this->expectException(\RuntimeException::class);

        (new TurnoResolucionLoopCloseNotificationCatalog())->forMessage('unknown');
    }
}
