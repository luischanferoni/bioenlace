<?php

namespace frontend\modules\api\v1;

/**
 * v1 module definition class
 */
class Module extends \yii\base\Module
{
    
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
}
