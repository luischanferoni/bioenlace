<?php

namespace common\tests\unit\clinical;

use common\models\Scheduling\TurnoNotificacionProgramada;
use PHPUnit\Framework\TestCase;

final class ScheduledEncounterNotificationTypesTest extends TestCase
{
    public function testPostEncounterJourneyTypesUsePersistedNotificationCodes(): void
    {
        self::assertSame(
            [
                TurnoNotificacionProgramada::TIPO_JOURNEY_POSTCONSULTA_DISPONIBLE,
                TurnoNotificacionProgramada::TIPO_JOURNEY_POSTCONSULTA_RECORDATORIO,
            ],
            TurnoNotificacionProgramada::postEncounterJourneyTypes()
        );
        self::assertContains(
            TurnoNotificacionProgramada::TIPO_JOURNEY_POSTCONSULTA_DISPONIBLE,
            TurnoNotificacionProgramada::journeyTipos()
        );
    }
}
