<?php

namespace common\tests\unit\integrations\scheduling;

use Codeception\Test\Unit;
use common\components\Domain\Integrations\Scheduling\FhirHealthcareServiceCodeCatalog;
use common\models\Integration\IntegrationFhirServiceCode;

/**
 * @group integrations
 */
class FhirHealthcareServiceCodeCatalogTest extends Unit
{
    public function testResolveUniqueGlobalMapping(): void
    {
        if (!\Yii::$app->db->schema->getTableSchema(IntegrationFhirServiceCode::tableName(), true)) {
            $this->markTestSkipped('Tabla integration_fhir_service_code no disponible.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $row = new IntegrationFhirServiceCode();
        $row->source_system = 'test-fhir';
        $row->code_system = 'http://snomed.info/sct';
        $row->code_value = '394814009';
        $row->id_servicio = 1;
        $row->id_efector_scope = 0;
        $row->created_at = $now;
        $row->updated_at = $now;
        if (!$row->save()) {
            $this->markTestSkipped('No se pudo insertar fixture catálogo: ' . json_encode($row->getErrors()));
        }

        $catalog = new FhirHealthcareServiceCodeCatalog();
        $id = $catalog->resolveIdServicio(
            'http://snomed.info/sct',
            '394814009',
            0,
            'test-fhir'
        );

        $this->assertSame(1, $id);

        $row->delete();
    }

    public function testAmbiguousReturnsNull(): void
    {
        $catalog = new FhirHealthcareServiceCodeCatalog();
        $this->assertNull($catalog->resolveIdServicio('', 'x'));
    }
}
