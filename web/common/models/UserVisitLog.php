<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * Registro de visitas post-login (tabla `user_visit_log`).
 */
class UserVisitLog extends ActiveRecord
{
    public const SESSION_TOKEN = '__visitorToken';

    public static function tableName(): string
    {
        return 'user_visit_log';
    }

    public static function newVisitor(int $userId): void
    {
        if ($userId <= 0 || !Yii::$app->has('session')) {
            return;
        }

        $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $model = new self();
        $model->user_id = $userId;
        $model->token = uniqid('', true);
        $model->ip = self::resolveClientIp();
        $model->language = isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])
            ? substr((string) $_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2)
            : '';
        $model->browser = '';
        $model->os = '';
        $model->user_agent = substr($userAgent, 0, 255);
        $model->visit_time = time();
        $model->save(false);

        Yii::$app->session->set(self::SESSION_TOKEN, $model->token);
    }

    private static function resolveClientIp(): string
    {
        if (Yii::$app->request instanceof \yii\web\Request) {
            return substr((string) Yii::$app->request->userIP, 0, 15);
        }

        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 15);
    }
}
