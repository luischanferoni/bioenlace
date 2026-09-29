<?php

namespace common\components\Platform\Assistant\Catalog;

use common\components\Domain\Scheduling\Agenda\Domain\Catalog\ReservaModalidadAtencionCatalog;
use common\components\Domain\Scheduling\Agenda\Domain\Catalog\ReservaTriageCatalog;

/**
 * Opciones cerradas que la guía puede nombrar.
 *
 * El flow declara el id en `meta.guide_options`. Las etiquetas salen del catálogo de dominio.
 */
final class GuideStepOptionCatalog
{
    /**
     * @return list<string>
     */
    public static function labels(string $ref): array
    {
        $ref = trim($ref);
        if ($ref === 'reserva_modalidad') {
            return ReservaModalidadAtencionCatalog::labelsShortForGuide();
        }
        $step = self::triageStep($ref);
        if ($step === '') {
            return [];
        }

        return ReservaTriageCatalog::labelsForStep($step);
    }

    public static function labelForCode(string $ref, string $code): string
    {
        $ref = trim($ref);
        if ($ref === 'reserva_modalidad') {
            return ReservaModalidadAtencionCatalog::labelShortForCode($code);
        }
        if (self::triageStep($ref) === '') {
            return '';
        }

        return ReservaTriageCatalog::labelForCode($code);
    }

    /**
     * Qué muestra el sistema al elegir ese código, cuando el catálogo lo declara.
     */
    public static function helpForCode(string $ref, string $code): string
    {
        if (self::triageStep($ref) === '') {
            return '';
        }

        return ReservaTriageCatalog::haltMessageForCode($code);
    }

    private static function triageStep(string $ref): string
    {
        $ref = trim($ref);
        if ($ref === 'reserva_triage.raiz') {
            return 'raiz';
        }
        if ($ref === 'reserva_triage.zona') {
            return 'zona';
        }

        return '';
    }
}
