<?php

namespace common\components\Domain\Clinical\Prescription\Application;

use common\components\Platform\Assistant\Catalog\UiActionCatalogDef;
use common\components\Platform\Assistant\Catalog\UiActionCatalogProviderInterface;
use common\components\Platform\Assistant\Catalog\YamlIntentCatalogService;

/**
 * Acciones API receta electrónica para el catálogo del asistente.
 */
final class PrescriptionUiActionCatalog implements UiActionCatalogProviderInterface
{
    /** @var list<array<string, mixed>>|null */
    private static ?array $definitions = null;

    public static function discoverAll(): array
    {
        if (self::$definitions !== null) {
            return self::$definitions;
        }

        self::$definitions = [
            UiActionCatalogDef::make(
                'clinical.electronic-prescription.mis-recetas-como-paciente',
                'Ver recetas electrónicas (UI)',
                'Listado UI JSON de recetas emitidas del paciente autenticado.',
                '/api/clinical/electronic-prescription/mis-recetas-como-paciente',
                ['mis recetas', 'receta electrónica', 'medicación'],
                'clinical',
                ['clinical', 'prescription', 'paciente'],
                true
            ),
            UiActionCatalogDef::make(
                'clinical.electronic-prescription.ver-receta-como-paciente',
                'Detalle receta electrónica (UI)',
                'Medicación prescrita y descarga PDF de la receta elegida.',
                '/api/clinical/electronic-prescription/ver-receta-como-paciente',
                ['detalle receta', 'ver receta', 'pdf receta'],
                'clinical',
                ['clinical', 'prescription', 'paciente'],
                true
            ),
            UiActionCatalogDef::make(
                'clinical.electronic-prescription.descargar-pdf-como-paciente',
                'Descargar PDF de receta',
                'PDF generado en servidor para una receta emitida del paciente.',
                '/api/clinical/electronic-prescription/descargar-pdf-como-paciente',
                ['pdf receta', 'descargar receta'],
                'clinical',
                ['clinical', 'prescription', 'paciente']
            ),
            UiActionCatalogDef::make(
                'clinical.electronic-prescription.verificar-receta',
                'Verificar receta por token',
                'Consulta de vigencia e integridad por código de verificación.',
                '/api/clinical/electronic-prescription/verificar-receta',
                ['verificar receta', 'código receta', 'farmacia'],
                'clinical',
                ['clinical', 'prescription']
            ),
        ];

        return self::$definitions;
    }

    public static function forUser(int $userId): array
    {
        return YamlIntentCatalogService::filterByRbac(self::discoverAll(), $userId);
    }

    public static function httpRouteForActionId(string $actionId): string
    {
        return UiActionCatalogDef::httpRouteFromDefinitions(self::discoverAll(), $actionId);
    }

    public static function clientOpenForActionId(string $actionId): ?array
    {
        return UiActionCatalogDef::clientOpenFromDefinitions(self::discoverAll(), $actionId);
    }
}
