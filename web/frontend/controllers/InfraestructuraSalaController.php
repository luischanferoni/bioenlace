<?php

namespace frontend\controllers;

use common\models\InfraestructuraSala;
use Yii;
use yii\helpers\Json;
use yii\web\Controller;

/**
 * Endpoint auxiliar staff: salas por piso (depdrop / selects en internación).
 * CRUD de infraestructura → backend admin MVC.
 */
class InfraestructuraSalaController extends Controller
{
    /**
     * Select dependiente de salas por piso (JSON o options HTML).
     *
     * @no_intent_catalog
     */
    public function actionSalasPorPiso()
    {
        $out = [];

        if (isset($_POST['depdrop_parents'])) {
            $parents = $_POST['depdrop_parents'];
            if ($parents != null) {
                $cat_id = $parents[0];
                $out = InfraestructuraSala::find()->asArray()
                    ->select(['id' => 'id', 'name' => 'descripcion'])
                    ->where(['id_piso' => $cat_id])
                    ->all();
                echo Json::encode(['output' => $out, 'selected' => '']);

                return;
            }
        }
        if (isset($_POST['id_piso'])) {
            $salas = InfraestructuraSala::find()
                ->where(['id_piso' => $_POST['id_piso']])
                ->all();
            if (count($salas) > 0) {
                foreach ($salas as $sala) {
                    $selected = ($sala->id == $_POST['id']) ? 'selected' : '';
                    echo "<option value='$sala->id' $selected >" . $sala->descripcion . '</option>';
                }
            }

            return;
        }
        echo Json::encode(['output' => '', 'selected' => '']);
    }
}
