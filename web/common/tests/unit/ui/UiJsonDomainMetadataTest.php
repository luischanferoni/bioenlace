<?php

namespace common\tests\unit\ui;

use Codeception\Test\Unit;
use common\components\Platform\Core\Product\UiJsonDomainMetadata;
use common\components\Platform\Core\Product\UiScreenParamsMetadata;
use common\components\Platform\Ui\UiJsonDomain;

class UiJsonDomainMetadataTest extends Unit
{
    protected function _after(): void
    {
        UiJsonDomainMetadata::resetCacheForTests();
        UiScreenParamsMetadata::resetCacheForTests();
    }

    public function testEntityDomainFromMetadata(): void
    {
        $this->assertSame('scheduling', UiJsonDomainMetadata::domainForEntity('turnos'));
        $this->assertSame('clinical', UiJsonDomainMetadata::domainForEntity('encounter'));
        $this->assertNull(UiJsonDomainMetadata::domainForEntity('desconocido'));
    }

    public function testClinicalActionIdParse(): void
    {
        $parsed = UiJsonDomain::parseActionId('clinical.internacion.mapa-camas');
        $this->assertNotNull($parsed);
        $this->assertSame('internacion', $parsed['entity']);
        $this->assertSame('mapa-camas', $parsed['action']);
    }

    public function testTemplateAliasFromMetadata(): void
    {
        $path = UiJsonDomain::resolveActionIdTemplatePath('clinical.encounter.ultima-atencion-ui-como-paciente');
        $this->assertNotNull($path);
        $this->assertStringContainsString('ver-resumen-atencion-como-paciente.json', str_replace('\\', '/', $path));
    }

    public function testScreenParamsSchedulingMatch(): void
    {
        $this->assertTrue(UiScreenParamsMetadata::matchesProvider(
            'scheduling',
            'turnos',
            'crear-como-paciente'
        ));
        $this->assertFalse(UiScreenParamsMetadata::matchesProvider(
            'scheduling',
            'turnos',
            'indicadores-agenda'
        ));
    }

    public function testCarePlansRestEntityResolvesCarePlanTemplateFolder(): void
    {
        $this->assertSame('care-plan', UiJsonDomainMetadata::templateFolderForEntity('care-plans'));
        $this->assertSame('clinical', UiJsonDomainMetadata::domainForEntity('care-plans'));

        $path = UiJsonDomain::resolveActionIdTemplatePath('clinical.care-plan.adherencia-resumen-staff');
        $this->assertNotNull($path);
        $this->assertStringContainsString('care-plan/adherencia-resumen-staff.json', str_replace('\\', '/', $path));

        $this->assertTrue(\common\components\Platform\Ui\UiDefinitionTemplateManager::hasTemplateForApiRoute(
            '/api/v1/clinical/care-plans/adherencia-resumen-staff'
        ));
    }

    public function testConsultasSeguimientoPasoResolvesSchedulingTemplate(): void
    {
        $this->assertSame('scheduling', UiJsonDomainMetadata::domainForEntity('consultas-seguimiento'));

        $path = UiJsonDomain::resolveActionIdTemplatePath('consultas-seguimiento.paso');
        $this->assertNotNull($path);
        $this->assertStringContainsString(
            'consultas-seguimiento/paso.json',
            str_replace('\\', '/', $path)
        );

        $this->assertTrue(\common\components\Platform\Ui\UiDefinitionTemplateManager::hasTemplateForApiRoute(
            '/api/v1/consultas-seguimiento/paso'
        ));
    }
}
