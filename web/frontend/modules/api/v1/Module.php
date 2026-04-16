<?php

namespace frontend\modules\api\v1;

use Yii;

/**
 * v1 module definition class
 */
class Module extends \yii\base\Module
{
    public $controllerNamespace = 'frontend\modules\api\v1\controllers';
    
    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();
        
        \Yii::$app->setComponents([
            'user'=>[
                'class'=>'frontend\components\ApiUser',
                //'identityClass' => 'frontend\models\Clientes',
                'enableSession' => false,
                'enableAutoLogin' => false,
                'loginUrl' => null,
            ],
        ]);

        $target = new \Yii\log\FileTarget();
        $target->logFile = \Yii::getAlias('@runtime') . '/logs/chats/chats.log';
        $target->levels = ['error', 'info'];
        
        //$targetDebug = new \Yii\log\FileTarget();
        //$targetDebug->logFile = \Yii::getAlias('@runtime') . '/logs/chats/chats.log';
        //$targetDebug->levels = ['warning'];

        \Yii::$app->getLog()->targets = [$target/*, $targetDebug*/];        
    }

    /**
     * Obtener orígenes permitidos para CORS
     * Incluye el origen actual, localhost y variantes comunes de desarrollo
     * @param bool $includeCurrentOrigin Incluir el origen de la petición actual
     * @return array Lista de orígenes permitidos
     */
    public static function getAllowedOrigins($includeCurrentOrigin = true)
    {
        $allowedOrigins = [
            'http://localhost',
            'http://127.0.0.1',
        ];

        // Si se solicita, incluir el origen de la petición actual
        if ($includeCurrentOrigin) {
            // Preferir el header crudo (algunos proxies no preservan getOrigin correctamente)
            $origin = (string) Yii::$app->request->headers->get('Origin', '');
            if ($origin === '') {
                $origin = (string) (Yii::$app->request->getOrigin() ?: '');
            }
            if ($origin === '') {
                $origin = (string) (Yii::$app->request->getHeaders()->get('origin', '') ?: '');
            }
            if ($origin === '' && isset($_SERVER['HTTP_ORIGIN'])) {
                $origin = (string) $_SERVER['HTTP_ORIGIN'];
            }
            if (empty($origin)) {
                $origin = Yii::$app->request->getHostInfo();
            }
            if (!empty($origin)) {
                $allowedOrigins[] = $origin;
            }
        }

        // Agregar puertos comunes de desarrollo si es localhost
        $origin = (string) Yii::$app->request->headers->get('Origin', '');
        if ($origin === '') {
            $origin = (string) (Yii::$app->request->getOrigin() ?: '');
        }
        if ($origin === '') {
            $origin = (string) Yii::$app->request->getHostInfo();
        }
        if (strpos($origin, 'localhost') !== false || strpos($origin, '127.0.0.1') !== false) {
            $allowedOrigins[] = 'http://localhost:8080';
            $allowedOrigins[] = 'http://127.0.0.1:8080';
            $allowedOrigins[] = 'http://localhost:3000';
            $allowedOrigins[] = 'http://127.0.0.1:3000';
        }

        // Eliminar duplicados y valores vacíos
        $allowedOrigins = array_filter(array_unique($allowedOrigins));

        return array_values($allowedOrigins);
    }
}
