<?php

namespace common\components\Domain\Organization\Assistant;

use common\components\Platform\Ui\UiSelectOptionSourceProviderInterface;
use common\models\Efector;
use common\models\Servicio;
use common\models\UserEfector;
use Yii;

/**
 * Opciones de selects: efectores y servicios del centro.
 */
final class OrganizationUiSelectOptionSourceProvider implements UiSelectOptionSourceProviderInterface
{
    public static function providerKey(): string
    {
        return 'organization';
    }

    /**
     * @param mixed $filter
     * @param array<string, mixed> $params
     * @param array<string, mixed> $optionConfig
     * @return list<array<string, mixed>>
     */
    public static function resolve(string $sourceKey, $filter, array $params, array $optionConfig): array
    {
        return match ($sourceKey) {
            'efectores' => self::resolveEfectores($filter, $params),
            'servicios' => self::resolveServicios($filter, $params),
            default => [],
        };
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    private static function resolveEfectores($filter, array $params): array
    {
        $userId = Yii::$app->user->id ?? null;

        $idServicio = null;
        if (isset($params['id_servicio']) && $params['id_servicio'] !== null && $params['id_servicio'] !== '') {
            $idServicio = (int) $params['id_servicio'];
        } elseif (isset($params['id_servicio_asignado']) && $params['id_servicio_asignado'] !== null && $params['id_servicio_asignado'] !== '') {
            $idServicio = (int) $params['id_servicio_asignado'];
        }

        if ($filter === 'user_efectores' && $userId) {
            $q = UserEfector::find()
                ->joinWith('efector')
                ->where(['user_efector.id_user' => $userId])
                ->andWhere('efectores.deleted_at IS NULL');

            if ($idServicio) {
                $q->innerJoin('servicios_efector se', 'se.id_efector = efectores.id_efector')
                    ->andWhere(['se.id_servicio' => $idServicio])
                    ->distinct();
            }

            $efectores = $q->orderBy('efectores.nombre')->all();

            $options = [];
            foreach ($efectores as $efector) {
                $options[] = [
                    'id' => $efector->efector->id_efector,
                    'name' => $efector->efector->nombre,
                ];
            }

            return $options;
        }

        if ($idServicio) {
            $efectores = Efector::find()
                ->innerJoin('servicios_efector se', 'se.id_efector = efectores.id_efector')
                ->where('efectores.deleted_at IS NULL')
                ->andWhere(['se.id_servicio' => $idServicio])
                ->distinct()
                ->orderBy('efectores.nombre')
                ->all();
        } else {
            $efectores = Efector::find()
                ->where('deleted_at IS NULL')
                ->orderBy('nombre')
                ->all();
        }

        $options = [];
        foreach ($efectores as $efector) {
            $options[] = [
                'id' => $efector->id_efector,
                'name' => $efector->nombre,
            ];
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    private static function resolveServicios($filter, array $params): array
    {
        if ($filter === 'efector_servicios' && isset($params['id_efector']) && $params['id_efector'] !== null && $params['id_efector'] !== '') {
            $servicios = Servicio::find()
                ->innerJoin('servicios_efector se', 'se.id_servicio = servicios.id_servicio')
                ->where(['se.id_efector' => $params['id_efector']])
                ->andWhere('se.deleted_at IS NULL')
                ->orderBy('servicios.nombre')
                ->all();

            $options = [];
            foreach ($servicios as $servicio) {
                $options[] = [
                    'id' => (string) $servicio->id_servicio,
                    'name' => $servicio->nombre,
                ];
            }

            return $options;
        }

        $servicios = Servicio::find()
            ->orderBy('nombre')
            ->all();

        $options = [];
        foreach ($servicios as $servicio) {
            $options[] = [
                'id' => (string) $servicio->id_servicio,
                'name' => $servicio->nombre,
            ];
        }

        return $options;
    }
}
