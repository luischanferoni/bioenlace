<?php

namespace frontend\controllers\traits;

use Yii;

use yii\base\InvalidConfigException;
use \yii\web\Request;
use yii\helpers\Url;

use common\models\Adjunto;

trait AdjuntoTrait
{

    public static function subirArchivos($arrayArchivos, $parentClass, $parentId)
    {

        $errores = [];

        if (!file_exists('uploads')) {
            mkdir('uploads');
        }
        if (!file_exists('uploads/' . $parentClass . '')) {
            mkdir('uploads/' . $parentClass . '');
        }

        if (!file_exists('uploads/' . $parentClass . '/' . $parentId . '/')) {
            mkdir('uploads/' . $parentClass . '/' . $parentId, 0755);
        }

        foreach ($arrayArchivos as $file) {

            if (!file_exists('uploads/' . $parentClass . '/' . $parentId . '/' . $file->baseName . '.' . $file->extension)) {

                if ($file->saveAs('uploads/' . $parentClass . '/' . $parentId . '/' . $file->baseName . '.' . $file->extension)) {

                    $modelAdjunto = new Adjunto();
                    $modelAdjunto->nombre_archivo = $file->baseName;
                    $modelAdjunto->size_archivo = $file->size;
                    $modelAdjunto->tipo = $file->extension;
                    $modelAdjunto->path = 'uploads/' . $parentClass . '/' . $parentId . '/' . $file->baseName . '.' . $file->extension;
                    $modelAdjunto->parent_class = $parentClass;
                    $modelAdjunto->parent_id = $parentId;

                    if (!$modelAdjunto->save()) {

                        $errores[] = $file->baseName;
                        unlink(Yii::$app->basePath . '\web' . 'uploads/' . $parentClass . '/' . $parentId . '/' . $file->baseName . '.' . $file->extension);
                    }
                } else {

                    $errores[] = $file->baseName;
                }
            }
        }

        return $errores;
    }


    public static function cargarArchivos($parentClass, $parentId)
    {

        $adjuntos = Adjunto::find()->where(['parent_class' => $parentClass, 'parent_id' => $parentId])->all();
  
        $archivos = [];
        $previewInicial = [];

        foreach ($adjuntos as $archivo) {

            $archivos[] = Url::to('@web/' . $archivo->path, true);

            $previewInicial[] = [
                'caption' => $archivo->nombre_archivo . '.' . $archivo->tipo,
                'type' => $archivo->tipo,
                'size' => $archivo->size_archivo,
                'url' => Url::to(['consulta-practicas/eliminar-adjunto', 'id' => $archivo->id])
            ];
        }

        return [$archivos, $previewInicial];
    }

    public function eliminarArchivo($id)
    {

        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $adjunto = Adjunto::findOne($id);

        unlink(Yii::$app->basePath . '\web\\' . $adjunto->path);

        $adjunto->delete();

        return true;
    }
}
