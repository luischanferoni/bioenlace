<?php

namespace common\tests\unit\platform;

use Codeception\Test\Unit;
use common\components\Platform\Core\Product\DomainMessageCatalog;
use common\components\Platform\Core\Product\ProductMetadataPaths;

final class DomainMessageCatalogTest extends Unit
{
    private function catalog(string $relative): DomainMessageCatalog
    {
        return new DomainMessageCatalog(
            ProductMetadataPaths::componentsDomainRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative)
        );
    }

    public function testReplacesPlaceholders(): void
    {
        $catalog = $this->catalog('Scheduling/Agenda/Application/Catalog/resolve-turno-messages.yaml');

        $this->assertSame('Turno reprogramado a las 10:30.', $catalog->text('turno_rescheduled', ['hora' => '10:30']));
    }

    public function testMissingKeyFails(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->catalog('Scheduling/Agenda/Application/Catalog/resolve-turno-messages.yaml')->text('inexistente');
    }

    public function testRepresentationCatalogsExposeTheirMessages(): void
    {
        $this->assertSame(
            'Tipo de vínculo no válido.',
            $this->catalog('Person/Representation/Application/Catalog/verified-guardianship-messages.yaml')->text('relationship_invalid')
        );
        $this->assertSame(
            'Permiso no válido: x',
            $this->catalog('Person/Representation/Application/Catalog/patient-delegation-messages.yaml')->text('permission_invalid', ['permission' => 'x'])
        );
    }
}
