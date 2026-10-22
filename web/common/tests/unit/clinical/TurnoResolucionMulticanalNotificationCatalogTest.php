<?php

namespace common\tests\unit\clinical;

use common\components\Domain\Scheduling\Agenda\Application\Catalog\TurnoResolucionMulticanalNotificationCatalog;
use PHPUnit\Framework\TestCase;

final class TurnoResolucionMulticanalNotificationCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        TurnoResolucionMulticanalNotificationCatalog::resetCacheForTests();
    }

    public function testLoadsEmailAndSmsTemplatesWithoutChangingTheirCopy(): void
    {
        $catalog = new TurnoResolucionMulticanalNotificationCatalog();

        self::assertSame([
            'subject' => 'Tu turno requiere una nueva cita',
            'body' => 'Hola {{nombre}}, tu turno del {{fecha}} a las {{hora}} necesita una nueva cita. Abrí el enlace para cambiar el horario: {{link}}',
        ], $catalog->forChannel('email'));
        self::assertSame([
            'body' => 'Bioenlace: tu turno del {{fecha}} {{hora}} necesita nueva cita. Cambiá el horario: {{link}}',
        ], $catalog->forChannel('sms'));
    }

    public function testUnsupportedChannelFailsExplicitly(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new TurnoResolucionMulticanalNotificationCatalog())->forChannel('push');
    }
}
