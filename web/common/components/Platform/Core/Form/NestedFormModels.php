<?php

namespace common\components\Platform\Core\Form;

use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;

/**
 * Utilidad para formularios dinámicos con múltiples filas del mismo modelo.
 */
final class NestedFormModels extends Model
{
    /**
     * @template T of Model
     * @param class-string<T> $modelClass
     * @param list<T> $multipleModels
     * @return list<T>
     */
    public static function createMultiple(string $modelClass, string $id, array $multipleModels = []): array
    {
        $model = new $modelClass();
        $formName = $model->formName();
        $post = Yii::$app->request->post($formName);
        $models = [];

        if (!empty($multipleModels)) {
            $keys = array_keys(ArrayHelper::map($multipleModels, $id, $id));
            $multipleModels = array_combine($keys, $multipleModels);
        }

        if ($post && is_array($post)) {
            foreach ($post as $item) {
                if (isset($item[$id]) && !empty($item[$id]) && isset($multipleModels[$item[$id]])) {
                    $models[] = $multipleModels[$item[$id]];
                } else {
                    $models[] = new $modelClass();
                }
            }
        }

        return $models;
    }
}
