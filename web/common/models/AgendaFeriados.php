<?php

namespace common\models;

use Yii;

/**
 * This is the model class for table "agenda_feriados".
 *
 * @property int $id
 * @property string $fecha
 * @property int $repite_todos_anios
 * @property string $created_at
 * @property string $updated_at
 * @property string|null $deleted_at
 * @property int $created_by
 * @property int|null $updated_by
 * @property int|null $deleted_by
 */
class AgendaFeriados extends \yii\db\ActiveRecord
{
    const TODO_EL_DIA = 'TODO_EL_DIA';
    const HASTA_MEDIODIA = 'HASTA_MEDIODIA';
    const DESDE_MEDIODDIA = 'DESDE_MEDIODDIA';
    const SI_REPITE_POR_ANIO = 'SI';
    const NO_REPITE_POR_ANIO = 'NO';

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return 'agenda_feriados';
    }

    public function behaviors()
    {
        return [
            'blames' => [
                'class' => 'yii\behaviors\AttributeBehavior',
                'attributes' => [
                    \yii\db\ActiveRecord::EVENT_BEFORE_INSERT => ['created_by'],
                    \yii\db\ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_by'],
                    \yii\db\ActiveRecord::EVENT_BEFORE_DELETE => ['deleted_by'],
                ],
                'value' => Yii::$app->user->id,
            ],
            'fechas' => [
                'class' => 'yii\behaviors\AttributeBehavior',
                'attributes' => [
                    \yii\db\ActiveRecord::EVENT_AFTER_VALIDATE => ['fecha'],
                ],
                'preserveNonEmptyValues' => true,
                'value' => function ($event) {
                    if ($this->owner->fecha != null) {
                        $fecha = date_create_from_format('d/m/Y', $this->owner->fecha);
                        $this->owner->fecha = date_format($fecha, 'Y-m-d');
                    }
                },
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['fecha', 'titulo', 'horario', 'repite_todos_anios'], 'required'],
            [['id', 'created_by', 'updated_by', 'deleted_by'], 'integer'],
            [['titulo', 'repite_todos_anios', 'horario'], 'string'],
            [['fecha', 'created_at', 'updated_at', 'deleted_at'], 'safe'],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'titulo' => 'Título',
            'fecha' => 'Fecha',
            'repite_todos_anios' => 'Repite Todos Años',
            'created_at' => 'Created At',
            'updated_at' => 'Updated At',
            'deleted_at' => 'Deleted At',
            'created_by' => 'Created By',
            'updated_by' => 'Updated By',
            'deleted_by' => 'Deleted By',
        ];
    }

    public static function getFeriados()
    {
        $haceTresMeses = date("Y-m-d", strtotime("-3 month"));
        $enTresMeses = date("Y-m-d", strtotime("+3 month"));
        $feriados = self::find()
            ->where(['between', 'fecha', $haceTresMeses, $enTresMeses])
            ->orWhere(['repite_todos_anios' => self::SI_REPITE_POR_ANIO])
            ->all();

        return $feriados;
    }

    public static function getFeriadosPorFecha($fecha)
    {

        $diaMes = date('m-d', strtotime($fecha));

        $feriado = self::find()
            ->where(['fecha' => $fecha])
            ->orWhere([
                'and',
                ['repite_todos_anios' => self::SI_REPITE_POR_ANIO, 'DATE_FORMAT(fecha, "%m-%d")' => $diaMes]
            ])
            ->one();

        return $feriado;
    }

    public static function esFeriado($fecha, $feriados)
    {

        $esFeriado = false;
        $diaMesFecha = date('m-d', strtotime($fecha));

        foreach ($feriados as $feriado) {

            $diaMesFeriado = date('m-d', strtotime($feriado->fecha));

            if ($fecha == $feriado->fecha) {

                $esFeriado = true;
            } elseif ($feriado->repite_todos_anios == self::SI_REPITE_POR_ANIO && $diaMesFecha == $diaMesFeriado) {

                $esFeriado = true;
            }
        }

        return $esFeriado;
    }
}
