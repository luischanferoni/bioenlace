<?php

namespace common\components\Services\Turnos;

use common\models\Efector;

/**
 * Provee datos de ubicación / indicaciones para recordatorios.
 *
 * Implementación futura: coords desde localidades + geocoding (Google/OSM),
 * o campos extendidos en efectores.
 */
interface EfectorDirectionsProviderInterface
{
    /**
     * @return array{lat: ?float, lng: ?float, texto_indicaciones: string, fuente_datos: string}
     */
    public function getDirectionsForEfector(Efector $efector);
}
