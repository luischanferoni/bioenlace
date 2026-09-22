<?php

namespace common\components\Domain\Clinical\Emergency\Application\Service;

use common\components\Domain\Clinical\Emergency\Domain\BoardState;
use common\components\Domain\Clinical\Emergency\Domain\BoardEventType;
use common\models\Clinical\Emergency\EmergencyBoardEvent;
use common\models\Clinical\Emergency\EmergencyEpisode;
use Yii;

final class EmergencyBoardService
{
    public function effectiveEstado(EmergencyEpisode $guardia): string
    {
        $stored = trim((string) ($guardia->circuito_estado ?? ''));
        if ($stored !== '') {
            return $stored;
        }

        if ($guardia->estado === EmergencyEpisode::ESTADO_FINALIZADA) {
            return BoardState::FINALIZADO;
        }
        if ($guardia->estado === EmergencyEpisode::ESTADO_ATENDIDA) {
            return BoardState::ATENDIDO;
        }

        return BoardState::ESPERA_TRIAGE;
    }

    public function assertCanRegisterTriage(EmergencyEpisode $guardia, bool $isUpdate = false): void
    {
        $estado = $this->effectiveEstado($guardia);
        if ($estado === BoardState::FINALIZADO) {
            throw new \InvalidArgumentException('No se puede registrar triage en una guardia finalizada.');
        }
        if ($estado === BoardState::DERIVADO) {
            throw new \InvalidArgumentException('No se puede registrar triage en una guardia derivada.');
        }
    }

    public function afterIngreso(EmergencyEpisode $guardia): void
    {
        $now = date('Y-m-d H:i:s');
        $guardia->circuito_estado = BoardState::ESPERA_TRIAGE;
        $guardia->ingreso_at = $now;
        $guardia->updateAttributes([
            'circuito_estado' => $guardia->circuito_estado,
            'ingreso_at' => $guardia->ingreso_at,
        ]);
        $this->recordEvent($guardia->id, BoardEventType::INGRESO, null, [
            'id_persona' => (int) $guardia->id_persona,
        ]);
    }

    public function afterTriage(EmergencyEpisode $guardia, int $level, ?int $pesId): void
    {
        $guardia->prioridad_triage = $level;
        $guardia->circuito_estado = BoardState::ESPERA_MEDICO;
        $guardia->updateAttributes([
            'prioridad_triage' => $level,
            'circuito_estado' => $guardia->circuito_estado,
        ]);
        $this->recordEvent($guardia->id, BoardEventType::TRIAGE, $pesId, ['level' => $level]);
    }

    /**
     * Cierre clínico tras captura (alta / control / documentación completa).
     * No aplica si el episodio ya está derivado o finalizado.
     *
     * @param array<string, mixed>|null $payload
     */
    public function afterDocumentacionClinica(EmergencyEpisode $guardia, ?int $pesId = null, ?array $payload = null): void
    {
        $estado = $this->effectiveEstado($guardia);
        if (in_array($estado, [BoardState::ATENDIDO, BoardState::DERIVADO, BoardState::FINALIZADO], true)) {
            return;
        }

        $guardia->circuito_estado = BoardState::ATENDIDO;
        $guardia->estado = EmergencyEpisode::ESTADO_ATENDIDA;
        $guardia->updateAttributes([
            'circuito_estado' => $guardia->circuito_estado,
            'estado' => $guardia->estado,
        ]);
        $this->recordEvent((int) $guardia->id, BoardEventType::FIN_ATENCION, $pesId, $payload ?? [
            'source' => 'encounter_documentation',
        ]);
    }

    /**
     * Derivación institucional deducida desde la captura.
     *
     * @param array<string, mixed>|null $payload
     */
    public function afterDocumentacionDerivacion(EmergencyEpisode $guardia, ?int $pesId = null, ?array $payload = null): void
    {
        $estado = $this->effectiveEstado($guardia);
        if (in_array($estado, [BoardState::DERIVADO, BoardState::FINALIZADO], true)) {
            return;
        }

        $guardia->circuito_estado = BoardState::DERIVADO;
        $guardia->estado = EmergencyEpisode::ESTADO_ATENDIDA;
        $guardia->updateAttributes([
            'circuito_estado' => $guardia->circuito_estado,
            'estado' => $guardia->estado,
        ]);
        $this->recordEvent((int) $guardia->id, BoardEventType::DERIVACION, $pesId, $payload ?? [
            'source' => 'encounter_documentation',
        ]);
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public function recordEvent(int $guardiaId, string $tipo, ?int $pesId = null, ?array $payload = null): void
    {
        $createdBy = Yii::$app->has('user') && !Yii::$app->user->isGuest
            ? (int) Yii::$app->user->id
            : null;
        EmergencyBoardEvent::registrar($guardiaId, $tipo, $pesId, $payload, $createdBy);
    }
}
