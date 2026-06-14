<?php

namespace common\tests\unit\costos;

use Yii;
use common\components\Platform\Ai\Cost\AICostTracker;

/**
 * Tests del tracker de costos de IA.
 * Los tests nunca llaman a la IA real; siempre se simula.
 */
class AICostTrackerTest extends \Codeception\Test\Unit
{
    /**
     * @var \common\tests\UnitTester
     */
    protected $tester;

    protected function _before()
    {
        AICostTracker::finalizarEjecucionPrueba();
        AICostTracker::reset();
    }

    protected function _after()
    {
        AICostTracker::finalizarEjecucionPrueba();
        AICostTracker::reset();
    }

    public function testResetLimpiaContadores()
    {
        AICostTracker::registrarEvitada('cache', 'test');
        AICostTracker::registrarLlamadaSimulada('test');
        AICostTracker::reset();
        $r = AICostTracker::getResumen();
        verify($r['evitada_por_cache'])->equals(0);
        verify($r['llamada_simulada'])->equals(0);
    }

    public function testIniciarEjecucionPruebaActivaSimulacion()
    {
        verify(AICostTracker::debeSimularIA())->false();
        AICostTracker::iniciarEjecucionPrueba();
        verify(AICostTracker::debeSimularIA())->true();
        AICostTracker::finalizarEjecucionPrueba();
        verify(AICostTracker::debeSimularIA())->false();
    }

    public function testRegistrarEvitadasYGetResumen()
    {
        AICostTracker::registrarEvitada('cache', 'c1');
        AICostTracker::registrarEvitada('cache', 'c2');
        AICostTracker::registrarEvitada('dedup', 'd1');
        AICostTracker::registrarEvitada('validacion', 'v1');
        $r = AICostTracker::getResumen();
        verify($r['evitada_por_cache'])->equals(2);
        verify($r['evitada_por_dedup'])->equals(1);
        verify($r['evitada_por_validacion'])->equals(1);
        verify($r['total_evitadas'])->equals(4);
    }

    public function testRegistrarLlamadaSimulada()
    {
        AICostTracker::registrarLlamadaSimulada('ctx', 'analysis');
        AICostTracker::registrarLlamadaSimulada('ctx2');
        $r = AICostTracker::getResumen();
        verify($r['llamada_simulada'])->equals(2);
    }

    public function testRegistrarUsoGeminiConEjecucionPrueba()
    {
        AICostTracker::iniciarEjecucionPrueba();
        $json = json_encode([
            'candidates' => [['content' => ['parts' => [['text' => 'ok']]]]]],
            'usageMetadata' => [
                'promptTokenCount' => 1000,
                'cachedContentTokenCount' => 800,
                'candidatesTokenCount' => 500,
                'totalTokenCount' => 1500,
            ],
        ]);
        AICostTracker::registrarUsoDesdeRespuestaGemini($json, 'asistente-preprocess');
        AICostTracker::registrarLlamadaReal('asistente-preprocess');
        $r = AICostTracker::getResumen();
        verify($r['llamada_real'])->equals(1);
        verify($r['tokens']['prompt_token_count'])->equals(1000);
        verify($r['tokens']['cached_content_token_count'])->equals(800);
        verify($r['tokens']['billable_input_token_count'])->equals(200);
        verify($r['tokens']['ratio_input_en_cache'])->equals(0.8);
        verify($r['por_contexto']['asistente-preprocess']['cached_tokens'])->equals(800);
        AICostTracker::finalizarEjecucionPrueba();
    }

    /**
     * Con ejecución de prueba activa, una llamada a consultarIA debe quedar como simulada (no HTTP).
     * Requiere que Yii::$app->iamanager esté disponible (config de tests).
     */
    public function testFlujoConIAManagerSimulaYRegistra()
    {
        if (!isset(Yii::$app->iamanager)) {
            $this->markTestSkipped('Requiere aplicación con componente iamanager (config tests).');
        }
        AICostTracker::iniciarEjecucionPrueba();
        AICostTracker::reset();

        $result = Yii::$app->iamanager->consultar('Un prompt de prueba para análisis', 'test-contexto');

        $resumen = AICostTracker::getResumen();
        AICostTracker::finalizarEjecucionPrueba();

        verify($resumen['total_evitadas'])->greaterThanOrEqual(0);
        verify($resumen['llamada_simulada'])->greaterThanOrEqual(0);
        verify(array_key_exists('evitada_por_cache', $resumen))->true();
        verify(array_key_exists('evitada_por_dedup', $resumen))->true();
        verify(array_key_exists('llamada_simulada', $resumen))->true();
    }

    public function testRegistrarCacheSimulada()
    {
        AICostTracker::iniciarEjecucionPrueba();
        AICostTracker::registrarCacheSimulada(120, 'asistente-preprocess');
        $r = AICostTracker::getResumen();
        verify($r['tokens']['cached_content_token_count'])->equals(120);
        verify($r['tokens']['cached_content_token_count_simulado'])->equals(120);
        verify($r['por_contexto']['asistente-preprocess']['cached_tokens'])->equals(120);
        AICostTracker::finalizarEjecucionPrueba();
    }
}
