<?php

namespace frontend\components\Layout;

use Yii;

final class SpaLayoutPageBuilder
{
    /**
     * @return array<string, mixed>
     */
    public static function build(): array
    {
        $app = Yii::$app;
        $isGuest = $app->user->isGuest;

        return [
            'isGuest' => $isGuest,
            'language' => $app->language,
            'charset' => $app->charset,
            'userPerTabConfig' => !$isGuest ? $app->user->getPerTabSessions() : null,
            'apiAuthToken' => !$isGuest ? $app->session->get('apiJwtToken') : null,
            'spaConfig' => [
                'baseUrl' => rtrim($app->urlManager->createAbsoluteUrl(['/']), '/'),
                'csrfToken' => $app->request->csrfToken,
                'appVersion' => $app->params['spaWebAppVersion'] ?? '1.0.0',
            ],
        ];
    }
}
