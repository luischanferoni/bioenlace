<?php

namespace common\components\Services\Turnos;

use common\models\Efector;

/**
 * Stub: solo texto desde efector (domicilio, formas_acceso, dias_horario).
 *
 * Datos futuros: id_localidad → tabla localidades (lat/lng si existen) o API externa.
 */
class NullEfectorDirectionsProvider implements EfectorDirectionsProviderInterface
{
    public function getDirectionsForEfector(Efector $efector)
    {
        $partes = array_filter([
            $efector->domicilio,
            $efector->formas_acceso,
            $efector->dias_horario,
        ]);
        return [
            'lat' => null,
            'lng' => null,
            'texto_indicaciones' => implode("\n", $partes),
            'fuente_datos' => 'efector:domicilio+formas_acceso+dias_horario',
        ];
    }
}
