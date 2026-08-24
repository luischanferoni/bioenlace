<?php

namespace common\tests\unit\infra;

use Codeception\Test\Unit;
use common\components\Platform\Infra\Requests\RequestDeduplicator;

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

    public function testPromptsCortosSimilaresSeDeduplican(): void
    {
        $a = 'analizar consulta dolor de pecho leve';
        $b = 'analizar consulta dolor de pecho leve.';
        RequestDeduplicator::guardar($a, ['text' => 'ok'], 'analisis-consulta');

        $hit = RequestDeduplicator::buscarSimilar($b, 'analisis-consulta');
        $this->assertSame(['text' => 'ok'], $hit);
    }

    public function testPromptsLargosDeChatNoSeDeduplicanPorSimilitud(): void
    {
        $prefix = str_repeat('Sos el asistente de Bioenlace. ', 20);
        $primero = $prefix . "\nHistorial:\nPaciente: pinchazo\nMensaje actual del paciente:\nTengo un pinchazo en el pecho cuando respiro";
        $segundo = $prefix . "\nHistorial:\nPaciente: pinchazo\nAsistente: Lamento\nMensaje actual del paciente:\n¿Esto es para guardia o puedo esperar?";

        $this->assertGreaterThan(255, strlen($primero));
        $this->assertGreaterThan(255, strlen($segundo));

        RequestDeduplicator::guardar($primero, 'Lamento que sientas ese pinchazo', 'asistente-conversational');

        $hit = RequestDeduplicator::buscarSimilar($segundo, 'asistente-conversational');
        $this->assertNull($hit);
    }

    public function testMatchExactoSigueDeduplicando(): void
    {
        $prompt = str_repeat('x', 300) . 'mismo';
        RequestDeduplicator::guardar($prompt, 'cached', 'asistente-conversational');

        $this->assertSame('cached', RequestDeduplicator::buscarSimilar($prompt, 'asistente-conversational'));
    }
}
