<?php

namespace common\tests\unit\clinical;

use common\components\Domain\Scheduling\Agenda\Application\Catalog\TurnoAdvanceOfferNotificationCatalog;
use PHPUnit\Framework\TestCase;

final class TurnoAdvanceOfferNotificationCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        TurnoAdvanceOfferNotificationCatalog::resetCacheForTests();
    }

    public function testLoadsOfferCopyAndDatePlaceholders(): void
    {
        $copy = (new TurnoAdvanceOfferNotificationCatalog())->offer();

        self::assertSame('Se liberó un turno más temprano', $copy['title']);
        self::assertSame('Adelantar mi turno', $copy['action_label']);
        self::assertSame(
            'Hay un horario el {fecha} a las {hora}. Podés adelantar tu consulta si todavía está disponible.',
            $copy['body_template']
        );
    }
}
