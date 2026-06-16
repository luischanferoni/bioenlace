<?php

namespace common\components\Platform\Core\DataAccess;

use common\components\Platform\Core\Permission\IntentEditSurfaceIndex;
use common\components\Platform\Core\Permission\IntentMetricIndex;

/**
 * Indica si métricas y superficies edit migraron a intents concretos (retiro catálogo data-access.*).
 */
final class DataAccessGenericChannelRetirement
{
    public static function areGenericChannelsRetired(): bool
    {
        $catalog = new AttributeGroupCatalog();

        foreach (array_keys($catalog->listMetricsForDisplay()) as $metricId) {
            if (!is_string($metricId) || $metricId === '') {
                continue;
            }
            if (IntentMetricIndex::intentForMetric($metricId) === null) {
                return false;
            }
        }

        foreach (array_keys($catalog->listEditSurfacesForDisplay()) as $surfaceId) {
            if (!is_string($surfaceId) || $surfaceId === '') {
                continue;
            }
            if (!IntentEditSurfaceIndex::isSurfaceMigrated($surfaceId)) {
                return false;
            }
        }

        return true;
    }
}
