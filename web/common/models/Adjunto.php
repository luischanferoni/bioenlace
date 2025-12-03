<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "adjunto".
 *
 * @property int $id
 * @property string $nombre_archivo
 * @property int|null $size_archivo
 * @property string $path
 * @property string $parent_class
 * @property int $parent_id
 */
class Adjunto extends \yii\db\ActiveRecord
{
    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'adjunto';
    }

       /**
     * @var UploadedFile[]
     */
    public $array_archivos;

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['nombre_archivo', 'path', 'parent_class', 'parent_id'], 'required'],
            [['size_archivo', 'parent_id'], 'integer'],
            [['nombre_archivo', 'path', 'parent_class'], 'string', 'max' => 255],
            [['tipo'], 'string']
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'nombre_archivo' => 'Nombre Archivo',
            'size_archivo' => 'Size Archivo',
            'path' => 'Path',
            'parent_class' => 'Parent Class',
            'parent_id' => 'Parent ID',
        ];
    }


}
