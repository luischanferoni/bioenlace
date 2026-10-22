<?php

namespace common\components\Domain\Scheduling\Agenda\Application\Service;

use common\components\Domain\Scheduling\Agenda\Application\Catalog\TurnoLifecycleNotificationCatalog;
use common\components\Domain\Scheduling\Agenda\Domain\Port\EfectorDirectionsProvider;
use common\components\Domain\Scheduling\Agenda\Infrastructure\Persistence\NullEfectorDirectionsProvider;
use common\models\Scheduling\Turno;
use common\models\Organization\Efector;

/**
 * Arma título/cuerpo y payload enriquecido para recordatorios (ubicación / transporte stub).
 */
class TurnoReminderContentService
{
    /** @var EfectorDirectionsProvider */
    private $directions;

    private TurnoLifecycleNotificationCatalog $notificationCatalog;

    public function __construct(
        EfectorDirectionsProvider $directions = null,
        ?TurnoLifecycleNotificationCatalog $notificationCatalog = null
    ) {
        $this->directions = $directions ?: new NullEfectorDirectionsProvider();
        $this->notificationCatalog = $notificationCatalog ?? new TurnoLifecycleNotificationCatalog();
    }

    /**
     * @return array{title: string, body: string, data: array}
     */
    public function buildForTurno(Turno $turno)
    {
        $efector = $turno->efector ?: Efector::findOne($turno->id_efector);
        $servicio = $turno->servicio
            ? $turno->servicio->nombre
            : $this->notificationCatalog->reminderServiceFallback();
        $fecha = $turno->fecha;
        $hora = $turno->hora;

        $dir = $efector ? $this->directions->getDirectionsForEfector($efector) : [
            'lat' => null,
            'lng' => null,
            'texto_indicaciones' => '',
            'fuente_datos' => 'none',
        ];

        $copy = $this->notificationCatalog->reminder(
            (string) $servicio,
            (string) $fecha,
            (string) $hora,
            (string) $dir['texto_indicaciones']
        );

        $data = [
            'type' => 'TURNO_REMINDER',
            'id_turno' => (string) $turno->id_turnos,
            'fecha' => $fecha,
            'hora' => $hora,
            'id_efector' => (string) $turno->id_efector,
            'ubicacion_texto' => $dir['texto_indicaciones'],
            'ubicacion_lat' => $dir['lat'] !== null ? (string) $dir['lat'] : '',
            'ubicacion_lng' => $dir['lng'] !== null ? (string) $dir['lng'] : '',
            'ubicacion_fuente' => $dir['fuente_datos'],
            'transporte_hint' => $copy['transport_hint'],
        ];

        return ['title' => $copy['title'], 'body' => $copy['body'], 'data' => $data];
    }
}
