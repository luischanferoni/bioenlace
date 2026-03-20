<?php

namespace common\components\Services\Quirofano;

use common\models\Cirugia;
use yii\base\Component;

/**
 * Reglas de agenda quirúrgica agnósticas a HTTP (reutilizable desde API, web, consola).
 */
class CirugiaAgendaService extends Component
{
    public function canTransition(string $from, string $to): bool
    {
        $map = [
            Cirugia::ESTADO_LISTA_ESPERA => [
                Cirugia::ESTADO_CONFIRMADA,
                Cirugia::ESTADO_CANCELADA,
            ],
            Cirugia::ESTADO_CONFIRMADA => [
                Cirugia::ESTADO_EN_CURSO,
                Cirugia::ESTADO_CANCELADA,
                Cirugia::ESTADO_SUSPENDIDA,
                Cirugia::ESTADO_LISTA_ESPERA,
            ],
            Cirugia::ESTADO_EN_CURSO => [
                Cirugia::ESTADO_REALIZADA,
                Cirugia::ESTADO_CANCELADA,
                Cirugia::ESTADO_SUSPENDIDA,
            ],
            Cirugia::ESTADO_SUSPENDIDA => [
                Cirugia::ESTADO_CONFIRMADA,
                Cirugia::ESTADO_LISTA_ESPERA,
                Cirugia::ESTADO_CANCELADA,
            ],
            Cirugia::ESTADO_REALIZADA => [],
            Cirugia::ESTADO_CANCELADA => [],
        ];

        return in_array($to, $map[$from] ?? [], true);
    }

    /**
     * @throws \InvalidArgumentException transición no permitida
     */
    public function applyEstado(Cirugia $model, string $nuevoEstado): void
    {
        if (!$this->canTransition($model->estado, $nuevoEstado)) {
            throw new \InvalidArgumentException(
                'Transición de estado no permitida: ' . $model->estado . ' → ' . $nuevoEstado
            );
        }
        $model->estado = $nuevoEstado;
    }

    /**
     * Si el estado de la cirugía ocupa franja en sala, indica si hay solapamiento con otras cirugías activas.
     */
    public function haySolapamientoParaCirugia(Cirugia $model, ?int $excludeCirugiaId = null): bool
    {
        if (!in_array($model->estado, Cirugia::ESTADOS_OCUPAN_SALA, true)) {
            return false;
        }

        return Cirugia::existsSolapamientoEnSala(
            (int) $model->id_quirofano_sala,
            $model->fecha_hora_inicio,
            $model->fecha_hora_fin_estimada,
            $excludeCirugiaId
        );
    }
}
