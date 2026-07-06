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
        $version = self::assetVersion();
        $view->registerLinkTag([
            'rel' => 'icon',
            'type' => 'image/png',
            'sizes' => '32x32',
            'href' => Url::to('@web/favicon-32x32.png', true) . $version,
        ]);
        $view->registerLinkTag([
            'rel' => 'icon',
            'type' => 'image/png',
            'sizes' => '16x16',
            'href' => Url::to('@web/favicon-16x16.png', true) . $version,
        ]);
        $view->registerLinkTag([
            'rel' => 'icon',
            'type' => 'image/svg+xml',
            'href' => Url::to('@web/favicon.svg', true) . $version,
        ]);
        $view->registerLinkTag([
            'rel' => 'apple-touch-icon',
            'sizes' => '180x180',
            'href' => Url::to('@web/apple-touch-icon.png', true) . $version,
        ]);
        $view->registerLinkTag([
            'rel' => 'shortcut icon',
            'href' => Url::to('@web/favicon.ico', true) . $version,
        ]);
        $view->registerMetaTag([
            'name' => 'theme-color',
            'content' => self::THEME_COLOR,
        ]);
    }

    private static function assetVersion(): string
    {
        $candidates = ['favicon.ico', 'favicon-32x32.png', 'favicon.svg'];
        $latest = 0;
        foreach ($candidates as $name) {
            $path = \Yii::getAlias('@webroot/' . $name, false);
            if (is_string($path) && is_file($path)) {
                $latest = max($latest, (int) filemtime($path));
            }
        }

        return $latest > 0 ? ('?v=' . $latest) : '';
    }
}
