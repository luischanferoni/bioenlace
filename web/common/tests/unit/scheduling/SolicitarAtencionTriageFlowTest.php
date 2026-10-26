<?php

namespace common\tests\unit\scheduling;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\SubIntentEngine\SubIntentEngine;

/**
 * Regresión: estudio prellenado abre Motivo primero; confirmar avanza sin loop.
 */
class SolicitarAtencionTriageFlowTest extends Unit
{
    private const INTENT = 'atencion.necesito-atencion';

    /**
     * @return array<string, mixed>
     */
    private function estudioDraft(): array
    {
        return [
            'triage_raiz' => 'estudio_pedido',
            'pedido_acto' => 'http://snomed.info/sct|16310003',
            'reserva_triage_halt' => '0',
        ];
    }

    public function testEstudioPrefilledAbreMotivoPrimero(): void
    {
        $response = SubIntentEngine::process([
            'intent_id' => self::INTENT,
            'draft' => $this->estudioDraft(),
        ], 0);

        $this->assertTrue($response['success'] ?? false);
        $this->assertSame('triage_raiz', $response['subintent_id'] ?? null);
        $this->assertSame(
            'turnos.reserva-triage-paso',
            $response['open_ui']['action_id'] ?? null
        );
    }

    public function testConfirmarMotivoConSubintentAvanzaAActo(): void
    {
        $response = SubIntentEngine::process([
            'intent_id' => self::INTENT,
            'subintent_id' => 'triage_raiz',
            'draft' => $this->estudioDraft(),
        ], 0);

        $this->assertTrue($response['success'] ?? false);
        $this->assertSame('select_pedido_acto', $response['subintent_id'] ?? null);
    }

    public function testConfirmarActoConSubintentAvanzaAModalidad(): void
    {
        $response = SubIntentEngine::process([
            'intent_id' => self::INTENT,
            'subintent_id' => 'select_pedido_acto',
            'draft' => $this->estudioDraft(),
        ], 0);

        $this->assertTrue($response['success'] ?? false);
        $this->assertSame('select_tipo_atencion', $response['subintent_id'] ?? null);
    }

    public function testConfirmarZonaAvanzaAModalidadCuandoEsRequerida(): void
    {
        $response = SubIntentEngine::process([
            'intent_id' => self::INTENT,
            'subintent_id' => 'triage_zona',
            'draft' => [
                'triage_raiz' => 'malestar_nuevo',
                'triage_zona' => 'zona_musculoesqueletico',
                'modalidad_paso_requerido' => '1',
                'reserva_triage_halt' => '0',
            ],
        ], 0);

        $this->assertTrue($response['success'] ?? false);
        $this->assertSame('select_tipo_atencion', $response['subintent_id'] ?? null);
    }

    public function testConfirmarZonaAvanzaAServicioCuandoModalidadNoEsRequerida(): void
    {
        $response = SubIntentEngine::process([
            'intent_id' => self::INTENT,
            'subintent_id' => 'triage_zona',
            'draft' => [
                'triage_raiz' => 'malestar_nuevo',
                'triage_zona' => 'zona_musculoesqueletico',
                'modalidad_paso_requerido' => '0',
                'reserva_triage_halt' => '0',
            ],
        ], 0);

        $this->assertTrue($response['success'] ?? false);
        $this->assertSame('select_servicio', $response['subintent_id'] ?? null);
    }

    public function testConfirmarModalidadAvanzaAServicioParaMalestarNuevo(): void
    {
        $response = SubIntentEngine::process([
            'intent_id' => self::INTENT,
            'subintent_id' => 'select_tipo_atencion',
            'draft' => [
                'triage_raiz' => 'malestar_nuevo',
                'triage_zona' => 'zona_musculoesqueletico',
                'tipo_atencion' => 'presencial',
                'reserva_triage_halt' => '0',
            ],
        ], 0);

        $this->assertTrue($response['success'] ?? false);
        $this->assertSame('select_servicio', $response['subintent_id'] ?? null);
    }
}
