<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\IntentEngine\IntentClassifier;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalog;
use common\components\Platform\Assistant\IntentEngine\UiActionCatalogItem;

/**
 * Clasificación por keywords del intent + desambiguación genérica (sin score_adjustments).
 */
class IntentClassifierKeywordRoutingTest extends Unit
{
    public function testExactKeywordWinsClearly(): void
    {
        $catalog = $this->catalog([
            ['turnos.ver-mis-turnos-como-paciente', 'Ver mis turnos', ['mis turnos', 'ver mis turnos']],
            ['turnos.crear-como-paciente', 'Reservar turno', ['necesito un turno', 'reservar turno']],
        ]);

        $out = IntentClassifier::classifyAmongItems('mis turnos', $catalog->items, $catalog, 0);
        $this->assertNotNull($out);
        $this->assertArrayNotHasKey('disambiguation', $out);
        $this->assertSame('turnos.ver-mis-turnos-como-paciente', $out['item']->action_id);
        $this->assertGreaterThanOrEqual(0.7, $out['confidence']);
    }

    public function testNecesitoUnTurnoRoutesByKeyword(): void
    {
        $catalog = $this->catalog([
            ['turnos.crear-como-paciente', 'Reservar turno', ['necesito un turno', 'quiero un turno']],
            ['turnos.ver-mis-turnos-como-paciente', 'Ver mis turnos', ['mis turnos']],
            ['atencion.necesito-atencion', 'Solicitar Atención', ['necesito atención']],
        ]);

        $out = IntentClassifier::classifyAmongItems('necesito un turno', $catalog->items, $catalog, 0);
        $this->assertNotNull($out);
        $this->assertSame('turnos.crear-como-paciente', $out['item']->action_id);
    }

    public function testLongerKeywordBeatsShortToken(): void
    {
        $msg = 'necesito modificar la agenda de un profesional';
        $configurar = $this->item(
            'profesional-agenda.configurar-staff',
            'Agenda ambulatoria',
            ['modificar la agenda de un profesional', 'modificar agenda de un profesional']
        );
        $editar = $this->item('data-access.editar', 'Editar', ['modificar', 'agenda', 'editar']);
        $crear = $this->item('profesional-efector-servicio.crear-flow', 'Alta', ['agenda']);

        $lower = mb_strtolower($msg, 'UTF-8');
        $this->assertGreaterThan(
            IntentClassifier::scoreItemPublic($lower, $editar),
            IntentClassifier::scoreItemPublic($lower, $configurar)
        );
        $this->assertGreaterThan(
            IntentClassifier::scoreItemPublic($lower, $crear),
            IntentClassifier::scoreItemPublic($lower, $configurar)
        );
    }

    public function testCloseScoresProduceDisambiguation(): void
    {
        $catalog = $this->catalog([
            ['intent.a', 'Opción A', ['horarios del profesional']],
            ['intent.b', 'Opción B', ['horarios del profesional']],
        ]);

        $out = IntentClassifier::classifyAmongItems('horarios del profesional', $catalog->items, $catalog, 0);
        $this->assertNotNull($out);
        $this->assertArrayHasKey('disambiguation', $out);
        $this->assertCount(2, $out['disambiguation']['remediation']);
    }

    public function testCargarMiCoberturaPrefersHorariosHub(): void
    {
        $catalog = $this->catalog([
            [
                'profesional-horarios.gestionar-propio',
                'Configurar mis horarios',
                ['cargar mi cobertura', 'mis horarios', 'modificar mi agenda'],
            ],
            [
                'profesional-agenda.configurar-staff',
                'Agenda ambulatoria de un profesional',
                ['modificar agenda de un profesional'],
            ],
            [
                'profesional-horarios.gestionar-staff',
                'Horarios de guardia / internación de un profesional',
                [],
            ],
        ]);

        $out = IntentClassifier::classifyAmongItems('Cargar mi cobertura', $catalog->items, $catalog, 0);
        $this->assertNotNull($out);
        $this->assertSame('profesional-horarios.gestionar-propio', $out['item']->action_id);
    }

    public function testDelegarGestionTurnosPrefersRepresentante(): void
    {
        $catalog = $this->catalog([
            [
                'personas.designar-representante-flow',
                'Designar representante',
                ['delegar gestión de turnos', 'designar representante'],
            ],
            [
                'turnos.crear-como-paciente',
                'Reservar turno',
                ['turno', 'reservar turno'],
            ],
        ]);

        $out = IntentClassifier::classifyAmongItems('Delegar gestión de turnos', $catalog->items, $catalog, 0);
        $this->assertNotNull($out);
        $this->assertSame('personas.designar-representante-flow', $out['item']->action_id);
    }

    /**
     * @param list<array{0:string,1:string,2:list<string>}> $rows
     */
    private function catalog(array $rows): UiActionCatalog
    {
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->item($row[0], $row[1], $row[2]);
        }
        $catalog = UiActionCatalog::fromItems($items, []);
        foreach ($items as $it) {
            $catalog->byActionId[$it->action_id] = $it;
        }

        return $catalog;
    }

    /**
     * @param list<string> $keywords
     */
    private function item(string $id, string $name, array $keywords): UiActionCatalogItem
    {
        return new UiActionCatalogItem($id, $name, '', null, '/api/test', $keywords, []);
    }
}
