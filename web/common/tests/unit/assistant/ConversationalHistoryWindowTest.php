<?php

namespace common\tests\unit\assistant;

use common\components\Assistant\EntryPoints\Chat\Channels\Conversational\ConversationalHistoryWindow;
use common\models\AsistenteInteraccion;

class ConversationalHistoryWindowTest extends \Codeception\Test\Unit
{
    private function row(string $senderId, string $texto, int $id = 1): AsistenteInteraccion
    {
        $m = new AsistenteInteraccion();
        $m->id = $id;
        $m->sender_id = $senderId;
        $m->texto = $texto;

        return $m;
    }

    public function testExcluyeMensajeActualDuplicado()
    {
        $rows = [
            $this->row('42', 'Tengo fiebre', 3),
            $this->row('BOT', '¿Desde cuándo?', 2),
            $this->row('42', 'Me duele la garganta', 1),
        ];

        $out = ConversationalHistoryWindow::buildFromInteractions($rows, '42', 'Tengo fiebre', 5, 3200);

        verify($out)->equals("Paciente: Me duele la garganta\nAsistente: ¿Desde cuándo?");
    }

    public function testCortaEnSaltoOperativo()
    {
        $rows = [
            $this->row('42', 'Sí, con fiebre', 4),
            $this->row('BOT', 'Gracias por aclarar.', 3),
            $this->row('42', '[action_id:turnos.crear]', 2),
            $this->row('BOT', 'Abrir turnos', 1),
        ];

        $out = ConversationalHistoryWindow::buildFromInteractions($rows, '42', 'Sí, con fiebre', 5, 3200);

        verify($out)->equals("Asistente: Gracias por aclarar.");
    }

    public function testRecortaPorCaracteres()
    {
        $long = str_repeat('a', 2000);
        $rows = [
            $this->row('42', 'Nuevo', 3),
            $this->row('BOT', $long, 2),
            $this->row('42', 'Viejo', 1),
        ];

        $out = ConversationalHistoryWindow::buildFromInteractions($rows, '42', 'Nuevo', 5, 500);

        verify(strpos($out, 'Viejo') === false)->true();
        verify(strpos($out, 'Nuevo') === false)->true();
    }

    public function testTrimToBudgetPorTurnos()
    {
        $lines = [
            'Paciente: uno',
            'Asistente: dos',
            'Paciente: tres',
            'Asistente: cuatro',
            'Paciente: cinco',
            'Asistente: seis',
        ];

        $trimmed = ConversationalHistoryWindow::trimToBudget($lines, 2, 10000);

        verify(count($trimmed))->equals(4);
        verify($trimmed[0])->equals('Paciente: tres');
    }
}
