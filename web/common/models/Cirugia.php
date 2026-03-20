<?php

namespace common\models;

use Yii;
use yii\behaviors\AttributeBehavior;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $id_quirofano_sala
 * @property int $id_persona
 * @property int|null $id_seg_nivel_internacion
 * @property int|null $id_practica
 * @property string|null $procedimiento_descripcion
 * @property string|null $observaciones
 * @property string $estado
 * @property string $fecha_hora_inicio
 * @property string $fecha_hora_fin_estimada
 * @property string $created_at
 * @property string $updated_at
 * @property int|null $created_by
 * @property int|null $updated_by
 *
 * @property QuirofanoSala $sala
 * @property Persona $persona
 * @property SegNivelInternacion|null $internacion
 * @property Practica|null $practica
 */
class Cirugia extends ActiveRecord
{
    public const ESTADO_LISTA_ESPERA = 'LISTA_ESPERA';
    public const ESTADO_CONFIRMADA = 'CONFIRMADA';
    public const ESTADO_EN_CURSO = 'EN_CURSO';
    public const ESTADO_REALIZADA = 'REALIZADA';
    public const ESTADO_CANCELADA = 'CANCELADA';
    public const ESTADO_SUSPENDIDA = 'SUSPENDIDA';

    public const ESTADOS = [
        self::ESTADO_LISTA_ESPERA => 'Lista de espera',
        self::ESTADO_CONFIRMADA => 'Confirmada',
        self::ESTADO_EN_CURSO => 'En curso',
        self::ESTADO_REALIZADA => 'Realizada',
        self::ESTADO_CANCELADA => 'Cancelada',
        self::ESTADO_SUSPENDIDA => 'Suspendida',
    ];

    /** Estados que ocupan franja horaria en sala (solapamiento). */
    public const ESTADOS_OCUPAN_SALA = [
        self::ESTADO_LISTA_ESPERA,
        self::ESTADO_CONFIRMADA,
        self::ESTADO_EN_CURSO,
    ];

    /** Mensaje de negocio para solapamiento (la capa HTTP puede mapearlo a 409 u otro). */
    public const MENSAJE_SOLAPAMIENTO_SALA = 'La franja horaria se solapa con otra cirugía activa en la misma sala.';

    public static function tableName()
    {
        return 'cirugia';
    }

    public function behaviors()
    {
        return [
            'blames' => [
                'class' => AttributeBehavior::class,
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => ['created_by'],
                    ActiveRecord::EVENT_BEFORE_UPDATE => ['updated_by'],
                ],
                'value' => Yii::$app->has('user') && !Yii::$app->user->isGuest ? Yii::$app->user->id : null,
            ],
        ];
    }

    public function rules()
    {
        return [
            [['id_quirofano_sala', 'id_persona', 'fecha_hora_inicio', 'fecha_hora_fin_estimada'], 'required'],
            [['id_quirofano_sala', 'id_persona', 'id_seg_nivel_internacion', 'id_practica', 'created_by', 'updated_by'], 'integer'],
            [['procedimiento_descripcion', 'observaciones'], 'string'],
            [['fecha_hora_inicio', 'fecha_hora_fin_estimada'], 'safe'],
            [['estado'], 'string', 'max' => 24],
            [['estado'], 'in', 'range' => array_keys(self::ESTADOS)],
            [['id_quirofano_sala'], 'exist', 'skipOnError' => true, 'targetClass' => QuirofanoSala::class, 'targetAttribute' => ['id_quirofano_sala' => 'id']],
            [['id_persona'], 'exist', 'skipOnError' => true, 'targetClass' => Persona::class, 'targetAttribute' => ['id_persona' => 'id_persona']],
            [['id_seg_nivel_internacion'], 'exist', 'skipOnError' => true, 'targetClass' => SegNivelInternacion::class, 'targetAttribute' => ['id_seg_nivel_internacion' => 'id']],
            [['id_practica'], 'exist', 'skipOnError' => true, 'targetClass' => Practica::class, 'targetAttribute' => ['id_practica' => 'id_practica']],
            [['fecha_hora_fin_estimada'], 'validateVentanaTemporal'],
        ];
    }

    public function validateVentanaTemporal($attribute)
    {
        if (!$this->fecha_hora_inicio || !$this->fecha_hora_fin_estimada) {
            return;
        }
        $ini = strtotime($this->fecha_hora_inicio);
        $fin = strtotime($this->fecha_hora_fin_estimada);
        if ($ini === false || $fin === false || $fin <= $ini) {
            $this->addError($attribute, 'La fecha/hora de fin estimada debe ser posterior al inicio.');
        }
    }

    public function attributeLabels()
    {
        return [
            'id_quirofano_sala' => 'Sala',
            'id_persona' => 'Paciente',
            'id_seg_nivel_internacion' => 'Internación',
            'id_practica' => 'Práctica',
            'procedimiento_descripcion' => 'Procedimiento',
            'observaciones' => 'Observaciones',
            'estado' => 'Estado',
            'fecha_hora_inicio' => 'Inicio',
            'fecha_hora_fin_estimada' => 'Fin estimado',
        ];
    }

    public function getSala()
    {
        return $this->hasOne(QuirofanoSala::class, ['id' => 'id_quirofano_sala']);
    }

    public function getPersona()
    {
        return $this->hasOne(Persona::class, ['id_persona' => 'id_persona']);
    }

    public function getInternacion()
    {
        return $this->hasOne(SegNivelInternacion::class, ['id' => 'id_seg_nivel_internacion']);
    }

    public function getPractica()
    {
        return $this->hasOne(Practica::class, ['id_practica' => 'id_practica']);
    }

    public function getEstadoLabel()
    {
        return self::ESTADOS[$this->estado] ?? $this->estado;
    }

    /**
     * ¿Existe otra fila en la misma sala, con estado que ocupa franja, cuya ventana se solapa con [inicio, fin]?
     * Consulta de datos; sin excepciones HTTP (la capa web/API decide cómo responder).
     *
     * @param int $excludeCirugiaId excluir este id (p. ej. registro en actualización)
     */
    public static function existsSolapamientoEnSala(int $idSala, string $inicio, string $fin, ?int $excludeCirugiaId = null): bool
    {
        $q = static::find()
            ->where(['id_quirofano_sala' => $idSala])
            ->andWhere(['in', 'estado', self::ESTADOS_OCUPAN_SALA])
            ->andWhere(['and',
                ['<', 'fecha_hora_inicio', $fin],
                ['>', 'fecha_hora_fin_estimada', $inicio],
            ]);
        if ($excludeCirugiaId !== null) {
            $q->andWhere(['!=', 'id', $excludeCirugiaId]);
        }

        return $q->exists();
    }
}
