<?php

namespace common\components\Platform\Ui;

use yii\helpers\Url;
use yii\web\View;

/**
 * Favicon Bioenlace (rombo + enlace) en @web de cada aplicación Yii.
 */
final class BioenlaceFavicon
{
    /** Color marca para theme-color (teal del rombo). */
    private const THEME_COLOR = '#093e4d';

    public static function register(View $view): void
    {
        $view->registerLinkTag([
            'rel' => 'icon',
            'type' => 'image/svg+xml',
            'href' => Url::to('@web/favicon.svg', true),
        ]);
        $view->registerLinkTag([
            'rel' => 'icon',
            'type' => 'image/png',
            'sizes' => '32x32',
            'href' => Url::to('@web/favicon-32x32.png', true),
        ]);
        $view->registerLinkTag([
            'rel' => 'icon',
            'type' => 'image/png',
            'sizes' => '16x16',
            'href' => Url::to('@web/favicon-16x16.png', true),
        ]);
        $view->registerLinkTag([
            'rel' => 'apple-touch-icon',
            'sizes' => '180x180',
            'href' => Url::to('@web/apple-touch-icon.png', true),
        ]);
        $view->registerLinkTag([
            'rel' => 'shortcut icon',
            'href' => Url::to('@web/favicon.ico', true),
        ]);
        $view->registerMetaTag([
            'name' => 'theme-color',
            'content' => self::THEME_COLOR,
        ]);
    }
}
