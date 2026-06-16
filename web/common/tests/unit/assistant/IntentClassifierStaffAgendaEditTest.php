<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\IntentEngine\IntentClassifier;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalogItem;

class IntentClassifierStaffAgendaEditTest extends Unit
{
    public function testModificarAgendaProfesionalPrefersConfigurarStaff(): void
    {
        $configurar = $this->catalogItem(
            'profesional-agenda.configurar-staff',
            ['modificar agenda de un profesional', 'configurar agenda profesional', 'horarios del profesional']
        );
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
        $configurarScore = IntentClassifier::scoreItemPublic($messageLower, $configurar);
        $this->assertGreaterThan(
            IntentClassifier::scoreItemPublic($messageLower, $crear),
            $configurarScore
        );
        $this->assertGreaterThan(
            IntentClassifier::scoreItemPublic($messageLower, $editar),
            $configurarScore
        );
        $this->assertGreaterThanOrEqual(70, $configurarScore);
    }

    public function testCrearAgendaIsNotStaffAgendaEdit(): void
    {
        $this->assertFalse(IntentClassifier::messageSuggestsStaffAgendaEdit('crear agenda para un profesional nuevo'));
    }

    public function testModificarFormasAtencionPrefersConfigurarStaff(): void
    {
        $configurar = $this->catalogItem(
            'profesional-agenda.configurar-staff',
            ['formas de atencion', 'configurar agenda profesional', 'modificar agenda']
        );
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
            $configurarScore = IntentClassifier::scoreItemPublic($messageLower, $configurar);
            $this->assertGreaterThan(
                IntentClassifier::scoreItemPublic($messageLower, $licencia),
                $configurarScore,
                'Licencia no debe ganar a configurar staff: ' . $msg
            );
            $this->assertGreaterThan(
                IntentClassifier::scoreItemPublic($messageLower, $crear),
                $configurarScore,
                'Crear-flow no debe ganar a configurar staff: ' . $msg
            );
            $this->assertGreaterThan(
                IntentClassifier::scoreItemPublic($messageLower, $editar),
                $configurarScore,
                'data-access.editar no debe ganar a intent concreto: ' . $msg
            );
            $this->assertGreaterThanOrEqual(30, $configurarScore, 'Configurar staff debe superar umbral: ' . $msg);
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
