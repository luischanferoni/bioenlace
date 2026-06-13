<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
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
            'profesional-efector-servicio.crear-flow',
            ['agenda', 'cargar agenda']
        );

        $msg = 'necesito modificar la agenda de un profesional';
        $messageLower = mb_strtolower($msg, 'UTF-8');

        $this->assertTrue(IntentClassifier::messageSuggestsStaffAgendaEdit($msg));
        $editarScore = IntentClassifier::scoreItemPublic($messageLower, $editar);
        $this->assertGreaterThan(
            IntentClassifier::scoreItemPublic($messageLower, $crear),
            $editarScore
        );
        $this->assertGreaterThanOrEqual(70, $editarScore);
    }

    public function testCrearAgendaIsNotStaffAgendaEdit(): void
    {
        $this->assertFalse(IntentClassifier::messageSuggestsStaffAgendaEdit('crear agenda para un profesional nuevo'));
    }

    public function testModificarFormasAtencionPrefersEditar(): void
    {
        $editar = $this->catalogItem(
            'data-access.editar',
            ['modificar', 'formas de atencion', 'editar', 'modificar agenda']
        );
        $licencia = $this->catalogItem(
            'licencia.cargar-para-profesional-flow',
            ['licencia profesional', 'cargar licencia', 'permiso profesional']
        );
        $crear = $this->catalogItem(
            'profesional-efector-servicio.crear-flow',
            ['agenda', 'cargar agenda']
        );

        foreach ([
            'necesito modificar las formas de atencion',
            'necesito modificar las formas de atencion de un profesional',
        ] as $msg) {
            $messageLower = mb_strtolower($msg, 'UTF-8');
            $this->assertTrue(
                IntentClassifier::messageSuggestsStaffAgendaEdit($msg),
                'Debe detectar edición staff de formas de atención: ' . $msg
            );
            $editarScore = IntentClassifier::scoreItemPublic($messageLower, $editar);
            $this->assertGreaterThan(
                IntentClassifier::scoreItemPublic($messageLower, $licencia),
                $editarScore,
                'Licencia no debe ganar a editar: ' . $msg
            );
            $this->assertGreaterThan(
                IntentClassifier::scoreItemPublic($messageLower, $crear),
                $editarScore,
                'Crear-flow no debe ganar a editar: ' . $msg
            );
            $this->assertGreaterThanOrEqual(30, $editarScore, 'Editar debe superar umbral: ' . $msg);
        }
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
