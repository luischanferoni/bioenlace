<?php

namespace common\tests\unit\platform\ui;

use Codeception\Test\Unit;
use common\components\Platform\Core\Product\UiScreenParamsMetadata;
use common\components\Platform\Ui\UiJsonDomain;
use common\components\Platform\Ui\UiJsonDomainIndex;

/**
 * El dominio de una entidad UI sale del árbol `views/json/<dominio>/<entidad>/`,
 * no de un mapa a mano ({@see UiJsonDomainIndex}).
 */
class UiJsonDomainIndexTest extends Unit
{
    protected function _after(): void
    {
        UiJsonDomainIndex::resetCacheForTests();
        UiScreenParamsMetadata::resetCacheForTests();
    }

    public function testEntityDomainFromTree(): void
    {
        $this->assertSame('scheduling', UiJsonDomainIndex::domainForEntity('turnos'));
        $this->assertSame('clinical', UiJsonDomainIndex::domainForEntity('encounter'));
        $this->assertNull(UiJsonDomainIndex::domainForEntity('desconocido'));
    }

    public function testDomainPrefixedActionIdParse(): void
    {
        $parsed = UiJsonDomain::parseActionId('clinical.internacion.mapa-camas');
        $this->assertNotNull($parsed);
        $this->assertSame('internacion', $parsed['entity']);
        $this->assertSame('mapa-camas', $parsed['action']);
        $this->assertTrue(UiJsonDomainIndex::isDomain('clinical'));
        $this->assertTrue(UiJsonDomainIndex::isDomain('scheduling'));
        $this->assertFalse(UiJsonDomainIndex::isDomain('desconocido'));
    }

    public function testTemplateAliasFromIndex(): void
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
        $this->assertSame('care-plan', UiJsonDomainIndex::templateFolderForEntity('care-plans'));
        $this->assertSame('clinical', UiJsonDomainIndex::domainForEntity('care-plans'));
        // El dominio de la carpeta (care-plan) también resuelve.
        $this->assertSame('clinical', UiJsonDomainIndex::domainForEntity('care-plan'));

        $path = UiJsonDomain::resolveActionIdTemplatePath('clinical.care-plan.adherencia-resumen-staff');
        $this->assertNotNull($path);
        $this->assertStringContainsString('care-plan/adherencia-resumen-staff.json', str_replace('\\', '/', $path));

        $this->assertTrue(\common\components\Platform\Ui\UiDefinitionTemplateManager::hasTemplateForApiRoute(
            '/api/v1/clinical/care-plans/adherencia-resumen-staff'
        ));
    }

    public function testPersonRepresentationStaffTutelaTemplate(): void
    {
        $this->assertSame('person', UiJsonDomainIndex::domainForEntity('person-representation'));

        $path = UiJsonDomain::resolveActionIdTemplatePath(
            'person-representation.solicitudes-tutela-pendientes-para-staff'
        );
        $this->assertNotNull($path);
        $this->assertStringContainsString(
            'person-representation/solicitudes-tutela-pendientes-para-staff.json',
            str_replace('\\', '/', $path)
        );

        $this->assertTrue(\common\components\Platform\Ui\UiDefinitionTemplateManager::hasTemplateForApiRoute(
            '/api/v1/person-representation/solicitudes-tutela-pendientes-para-staff'
        ));
    }

    public function testConsultasSeguimientoPasoResolvesSchedulingTemplate(): void
    {
        $this->assertSame('scheduling', UiJsonDomainIndex::domainForEntity('consultas-seguimiento'));

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
