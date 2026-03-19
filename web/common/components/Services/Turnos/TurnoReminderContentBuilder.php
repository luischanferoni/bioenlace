<?php

namespace common\components\Services\Turnos;

use common\models\Turno;
use common\models\Efector;

/**
 * Arma título/cuerpo y payload enriquecido para recordatorios (ubicación / transporte stub).
 */
class TurnoReminderContentBuilder
{
    /** @var EfectorDirectionsProviderInterface */
    private $directions;

    public function __construct(EfectorDirectionsProviderInterface $directions = null)
    {
        $this->directions = $directions ?: new NullEfectorDirectionsProvider();
    }

    /**
     * @return array{title: string, body: string, data: array}
     */
    public function buildForTurno(Turno $turno)
    {
        $efector = $turno->efector ?: Efector::findOne($turno->id_efector);
        $servicio = $turno->servicio ? $turno->servicio->nombre : 'Consulta';
        $fecha = $turno->fecha;
        $hora = $turno->hora;
        $title = 'Recordatorio de turno';
        $body = sprintf('%s — %s %s', $servicio, $fecha, $hora);

        $dir = $efector ? $this->directions->getDirectionsForEfector($efector) : [
            'lat' => null,
            'lng' => null,
            'texto_indicaciones' => '',
            'fuente_datos' => 'none',
        ];

        $transporte = 'Consultá en la app "Cómo llegar" o colectivos según tu ubicación (datos de transporte público pendiente de integración).';

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
            'transporte_hint' => $transporte,
        ];

        $body .= "\n" . $dir['texto_indicaciones'];

        return ['title' => $title, 'body' => $body, 'data' => $data];
    }
}
