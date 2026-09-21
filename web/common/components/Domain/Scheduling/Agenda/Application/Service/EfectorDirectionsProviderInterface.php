<?php

namespace common\components\Domain\Scheduling\Agenda\Application\Service;

use common\models\Organization\Efector;

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
