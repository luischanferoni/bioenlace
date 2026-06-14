<?php

namespace common\components\Platform\Core\Permission;

use common\models\User;
use yii\bootstrap5\Nav;
use yii\helpers\Html;

/**
 * Enlaces visibles solo si {@see User::canRoute()} lo permite.
 */
class BioenlaceGhostHtml extends Html
{
    /**
     * @param string|array|null $url
     * @param array<string, mixed> $options
     */
    public static function a($text, $url = null, $options = [])
    {
        if (in_array($url, [null, '', '#'], true)) {
            return parent::a($text, $url, $options);
        }

        return User::canRoute($url) ? parent::a($text, $url, $options) : '';
    }
}
