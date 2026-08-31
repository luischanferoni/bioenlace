<?php

namespace common\tests\unit\assistant;

use Codeception\Test\Unit;
use common\components\Platform\Assistant\Chat\Channels\Guide\GuideHistoryWindow;
use common\models\AsistenteInteraccion;

class GuideHistoryWindowTest extends Unit
{
    private function row(string $sender, string $text, int $id, string $threadTag, ?array $guideFocus = null): AsistenteInteraccion
    {
        $meta = ['thread_tag' => $threadTag];
        if ($guideFocus !== null) {
            $meta['guide_focus'] = $guideFocus;
        }
        $row = new AsistenteInteraccion();
        $row->id = $id;
        $row->sender_id = $sender;
        $row->texto = $text;
        $row->metadata = json_encode($meta, JSON_UNESCAPED_UNICODE);

        return $row;
    }

    public function testFiltraPorGuideFocus(): void
    {
        $focus = [
            'primary_area' => 'appointments',
            'active_areas' => ['appointments'],
        ];
        $rows = [
            $this->row('42', 'cómo represento a mi sobrino', 5, 'guide:representation', [
                'primary_area' => 'representation',
                'active_areas' => ['representation'],
            ]),
            $this->row('42', 'Me duele el pecho', 3, 'guide:appointments', $focus),
            $this->row('BOT', '¿Desde cuándo?', 2, 'guide:appointments', $focus),
            $this->row('42', 'Desde ayer', 1, 'guide:appointments', $focus),
        ];

        $history = GuideHistoryWindow::buildFromInteractions(
            $rows,
            '42',
            'mensaje nuevo',
            5,
            3200,
            'appointments'
        );
        $this->assertStringContainsString('Me duele el pecho', $history);
        $this->assertStringNotContainsString('sobrino', $history);
    }

    public function testLegacyThreadTagGuideAppointments(): void
    {
        $rows = [
            $this->row('42', 'llego tarde al turno', 2, 'guide:appointments'),
            $this->row('BOT', 'Podés avisar al centro', 1, 'guide:appointments'),
        ];

        $history = GuideHistoryWindow::buildFromInteractions(
            $rows,
            '42',
            'y si llego 5 min más tarde',
            5,
            3200,
            'appointments'
        );
        $this->assertStringContainsString('llego tarde', $history);
    }
}
