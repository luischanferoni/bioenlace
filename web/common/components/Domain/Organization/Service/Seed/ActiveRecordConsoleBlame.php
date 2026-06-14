<?php

namespace common\components\Domain\Organization\Service\Seed;

use yii\db\ActiveRecord;

/**
 * Rellena created_by/updated_by en consola (sin sesión web) y evita que el behavior blames lo pise con null.
 */
final class ActiveRecordConsoleBlame
{
    public static function prepareForSave(ActiveRecord $model, int $userId): void
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('userId requerido para auditoría (created_by) en consola.');
        }

        $columns = array_flip($model->getTableSchema()->columnNames);
        if ($model->getIsNewRecord() && isset($columns['created_by'])) {
            $model->setAttribute('created_by', $userId);
        }
        if (isset($columns['updated_by'])) {
            $model->setAttribute('updated_by', $userId);
        }
        if ($model->getBehavior('blames') !== null) {
            $model->detachBehavior('blames');
        }
    }

    public static function save(ActiveRecord $model, int $userId, string $context): void
    {
        self::prepareForSave($model, $userId);
        if (!$model->save(false)) {
            throw new \RuntimeException($context . ': ' . json_encode($model->getErrors()));
        }
    }
}
