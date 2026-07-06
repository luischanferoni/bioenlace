<?php

namespace common\components\Domain\Organization\Service\ProfesionalEfectorServicio;

use common\components\Domain\Integrations\Scheduling\FhirHealthcareServiceCodeCatalog;
use common\models\Servicio;
use Yii;

/**
 * UI staff: catálogo códigos HealthcareService FHIR → servicio.
 */
final class FhirServiceCodeCatalogUiService
{
    /**
     * @param array<string, mixed> $fromClient
     * @return array<string, mixed>
     */
    public static function buildListValues(int $idEfector, array $fromClient): array
    {
        $catalog = new FhirHealthcareServiceCodeCatalog();
        $source = trim((string) ($fromClient['source_system'] ?? FhirHealthcareServiceCodeCatalog::DEFAULT_SOURCE_SYSTEM));

        return [
            'source_system' => $source,
            'items' => $catalog->listForEfector($idEfector, $source),
            'servicio_options' => self::servicioOptionsForEfector($idEfector),
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array{data: array<string, mixed>}
     */
    public static function submit(int $idEfector, array $post): array
    {
        $catalog = new FhirHealthcareServiceCodeCatalog();
        $payload = $post;
        if (!isset($payload['id_efector_scope'])) {
            $payload['id_efector_scope'] = $idEfector > 0 ? $idEfector : 0;
        }
        $result = $catalog->upsert($payload, $idEfector);

        return ['data' => $result];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private static function servicioOptionsForEfector(int $idEfector): array
    {
        $query = Servicio::find()->orderBy(['nombre' => SORT_ASC]);
        if ($idEfector > 0) {
            $query->innerJoin(
                'servicios_efector se',
                'se.id_servicio = servicios.id_servicio AND se.id_efector = :ef AND se.deleted_at IS NULL',
                [':ef' => $idEfector]
            );
        }

        return array_values(array_map(
            static fn (Servicio $s): array => [
                'value' => (string) (int) $s->id_servicio,
                'label' => (string) $s->nombre,
            ],
            $query->all()
        ));
    }
}
