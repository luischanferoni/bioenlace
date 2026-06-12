<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Assistant\Catalog\IntentIdAliasResolver;
use common\components\Assistant\IntentEngine\IntentClassifier;
use common\components\Assistant\IntentEngine\UiActionCatalogItem;

class IntentClassifierStaffAgendaEditTest extends Unit
{
    public function testModificarAgendaProfesionalPrefersEditar(): void
    {
        $editar = $this->catalogItem(
            'data-access.editar',
            ['modificar', 'agenda', 'editar', 'modificar agenda']
        );
        $crear = $this->catalogItem(
            'agenda.crear-profesional-flow',
            ['agenda', 'cargar agenda']
        );

        $msg = 'necesito modificar la agenda de un profesional';
        $messageLower = mb_strtolower($msg, 'UTF-8');

        $this->assertTrue(IntentClassifier::messageSuggestsStaffAgendaEdit($msg));
        $this->assertGreaterThan(
            IntentClassifier::scoreItemPublic($messageLower, $crear),
            IntentClassifier::scoreItemPublic($messageLower, $editar)
        );
        $this->assertGreaterThanOrEqual(30, IntentClassifier::scoreItemPublic($messageLower, $editar));
    }

    public function testCrearAgendaIsNotStaffAgendaEdit(): void
    {
        $this->assertFalse(IntentClassifier::messageSuggestsStaffAgendaEdit('crear agenda para un profesional nuevo'));
    }

    public function testModificarProfesionalFlowAliasResolvesToEditar(): void
    {
        $this->assertSame(
            'data-access.editar',
            IntentIdAliasResolver::resolve('agenda.modificar-profesional-flow')
        );
    }

    /**
     * @param list<string> $keywords
     */
    private function catalogItem(string $actionId, array $keywords): UiActionCatalogItem
    {
        return new UiActionCatalogItem(
            $actionId,
            $actionId,
            '',
            null,
            '/api/test',
            $keywords,
            []
        );
    }
}
