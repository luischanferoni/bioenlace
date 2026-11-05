<?php

namespace common\tests\unit\shared\infrastructure;

use Codeception\Test\Unit;
use common\components\Shared\Infrastructure\Requests\RequestDeduplicator;

/**
 * Contrato: el match exacto decide; el difuso solo se observa.
 *
 * @see RequestDeduplicator
 */
class RequestDeduplicatorTest extends Unit
{
    protected function _before(): void
    {
        RequestDeduplicator::resetCacheForTests();
    }

    protected function _after(): void
    {
        RequestDeduplicator::resetCacheForTests();
    }

    public function testPromptIdenticoDevuelveLoCacheado(): void
    {
        RequestDeduplicator::guardar('analizar consulta dolor de pecho leve', ['text' => 'ok'], 'analisis-consulta');

        $this->assertSame(
            ['text' => 'ok'],
            RequestDeduplicator::buscarExacto('analizar consulta dolor de pecho leve', 'analisis-consulta')
        );
    }

    public function testEspaciosDistintosSonElMismoPrompt(): void
    {
        RequestDeduplicator::guardar("analizar   consulta\n dolor ", 'ok', 'analisis-consulta');

        $this->assertSame('ok', RequestDeduplicator::buscarExacto('analizar consulta dolor', 'analisis-consulta'));
    }

    public function testPuntuacionDistintaNoDevuelveContenido(): void
    {
        RequestDeduplicator::guardar('dolor de pecho leve', 'respuesta-para-leve', 'analisis-consulta');

        $this->assertNull(
            RequestDeduplicator::buscarExacto('dolor de pecho leve.', 'analisis-consulta'),
            'Un cambio de puntuación puede cambiar el sentido clínico: no se reutiliza la respuesta.'
        );

        // Además queda por debajo del umbral de observación (≈0.79): el difuso tampoco lo marca.
        $observacion = RequestDeduplicator::observarSimilitud('dolor de pecho leve.', 'analisis-consulta');
        $this->assertLessThan(RequestDeduplicator::SIMILITUD_MINIMA, $observacion['max']);
    }

    public function testDiferenciaDeMayusculasSeObservaSinDevolverContenido(): void
    {
        RequestDeduplicator::guardar('Analizar consulta dolor de pecho leve', 'respuesta', 'analisis-consulta');

        $this->assertNull(RequestDeduplicator::buscarExacto('analizar consulta dolor de pecho leve', 'analisis-consulta'));

        $observacion = RequestDeduplicator::observarSimilitud('analizar consulta dolor de pecho leve', 'analisis-consulta');
        $this->assertSame(1, $observacion['candidatos']);
        $this->assertGreaterThanOrEqual(RequestDeduplicator::SIMILITUD_MINIMA, $observacion['max']);
    }

    public function testTipoDistintoNoDevuelveContenido(): void
    {
        RequestDeduplicator::guardar('mismo texto', 'de-guide', 'asistente-guide');

        $this->assertNull(RequestDeduplicator::buscarExacto('mismo texto', 'analisis-consulta'));
    }

    public function testMatchDifusoSeObservaSinDevolverContenido(): void
    {
        $a = 'Analizar consulta dolor de pecho leve';
        $b = 'analizar consulta dolor de pecho leve';
        RequestDeduplicator::guardar($a, 'respuesta-para-a', 'analisis-consulta');

        $observacion = RequestDeduplicator::observarSimilitud($b, 'analisis-consulta');

        $this->assertNull(RequestDeduplicator::buscarExacto($b, 'analisis-consulta'));
        $this->assertSame(1, $observacion['candidatos']);
        $this->assertGreaterThanOrEqual(RequestDeduplicator::SIMILITUD_MINIMA, $observacion['max']);
    }

    public function testObservacionIgnoraOtroTipo(): void
    {
        RequestDeduplicator::guardar('analizar consulta dolor de pecho leve', 'x', 'analisis-consulta');

        $observacion = RequestDeduplicator::observarSimilitud('analizar consulta dolor de pecho leve', 'asistente-guide');

        $this->assertSame(0, $observacion['candidatos']);
        $this->assertSame(0.0, $observacion['max']);
    }

    public function testPromptsLargosDeChatNoSeObservanComoSimilares(): void
    {
        $prefix = str_repeat('Sos el asistente de Bioenlace. ', 20);
        $primero = $prefix . "\nHistorial:\nPaciente: pinchazo\nMensaje actual del paciente:\nTengo un pinchazo en el pecho cuando respiro";
        $segundo = $prefix . "\nHistorial:\nPaciente: pinchazo\nAsistente: Lamento\nMensaje actual del paciente:\n¿Esto es para guardia o puedo esperar?";

        $this->assertGreaterThan(255, strlen($primero));
        RequestDeduplicator::guardar($primero, 'Lamento que sientas ese pinchazo', 'asistente-guide');

        $observacion = RequestDeduplicator::observarSimilitud($segundo, 'asistente-guide');

        $this->assertSame(1, $observacion['candidatos']);
        $this->assertSame(0.0, $observacion['max']);
    }
}
