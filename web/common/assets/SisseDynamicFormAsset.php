<?php


namespace common\assets;

use yii\web\AssetBundle;

class SisseDynamicFormAsset extends AssetBundle
{
    public $sourcePath = "@common/web";
    public $js = ["js/sisse_dynamicform.js"];

    public $depends = ["wbraganca\dynamicform\DynamicFormAsset"];
}