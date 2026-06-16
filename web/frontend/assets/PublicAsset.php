<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace frontend\assets;

use common\components\Platform\Ui\BioenlaceFavicon;
use yii\web\AssetBundle;
use yii\web\View;

class PublicAsset extends AssetBundle
{
    public $basePath = '@webroot';
    public $baseUrl = '@web';
    public $css = [
        'css/site.css',
    ];

    public $depends = [        
        'yii\web\YiiAsset',      
    ];

    public function registerAssetFiles($view): void
    {
        parent::registerAssetFiles($view);
        if ($view instanceof View) {
            BioenlaceFavicon::register($view);
        }
    }
}
