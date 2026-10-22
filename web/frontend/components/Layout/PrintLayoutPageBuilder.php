<?php

namespace frontend\components\Layout;

use Yii;

final class PrintLayoutPageBuilder
{
    /** @return array{baseUrl: string} */
    public static function build(): array
    {
        return [
            'baseUrl' => Yii::$app->urlManager->createAbsoluteUrl(['/']),
        ];
    }
}
