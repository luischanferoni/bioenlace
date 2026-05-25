<?php

namespace common\components\Emergency;

use common\models\Emergency\GuardiaCircuitoEvent;
use common\models\Guardia;
use Yii;

final class GuardiaCircuitoService
{
    public function effectiveEstado(Guardia $guardia): string
    {
        $stored = trim((string) ($guardia->circuito_estado ?? ''));
        if ($stored !== '') {
            return $stored;
        }

        if ($guardia->estado === Guardia::ESTADO_FINALIZADA) {
            return CircuitoEstado::FINALIZADO;
        }
        if ($guardia->estado === Guardia::ESTADO_ATENDIDA) {
            return CircuitoEstado::ATENDIDO;
        }

        return CircuitoEstado::ESPERA_TRIAGE;
    }

    public function assertCanRegisterTriage(Guardia $guardia): void
    {
        $estado = $this->effectiveEstado($guardia);
        if ($estado === CircuitoEstado::FINALIZADO) {
            throw new \InvalidArgumentException('No se puede registrar triage en una guardia finalizada.');
        }
        if ($estado === CircuitoEstado::DERIVADO) {
            throw new \InvalidArgumentException('No se puede registrar triage en una guardia derivada.');
        }
    }

    public function afterIngreso(Guardia $guardia): void
    {
        $now = date('Y-m-d H:i:s');
        $guardia->circuito_estado = CircuitoEstado::ESPERA_TRIAGE;
        $guardia->ingreso_at = $now;
        $guardia->updateAttributes([
            'circuito_estado' => $guardia->circuito_estado,
            'ingreso_at' => $guardia->ingreso_at,
        ]);
        $this->recordEvent($guardia->id, CircuitoEventType::INGRESO, null, [
            'id_persona' => (int) $guardia->id_persona,
        ]);
    }

    public function afterTriage(Guardia $guardia, int $level, ?int $pesId): void
    {
        $guardia->prioridad_triage = $level;
        $guardia->circuito_estado = CircuitoEstado::ESPERA_MEDICO;
        $guardia->updateAttributes([
            'prioridad_triage' => $level,
            'circuito_estado' => $guardia->circuito_estado,
        ]);
        $this->recordEvent($guardia->id, CircuitoEventType::TRIAGE, $pesId, ['level' => $level]);
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public function recordEvent(int $guardiaId, string $tipo, ?int $pesId = null, ?array $payload = null): void
    {
        $createdBy = Yii::$app->has('user') && !Yii::$app->user->isGuest
            ? (int) Yii::$app->user->id
            : null;
        GuardiaCircuitoEvent::registrar($guardiaId, $tipo, $pesId, $payload, $createdBy);
    }
}
