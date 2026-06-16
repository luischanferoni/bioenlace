<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * Queja operativa enviada por un paciente desde la app.
 *
 * @property int $id
 * @property int $id_persona
 * @property string $categoria
 * @property string $descripcion
 * @property string $created_at
 * @property-read Persona|null $persona
 */
class QuejaPaciente extends ActiveRecord
{
    public const CATEGORIA_APP = 'APP';

    public const CATEGORIA_TURNOS = 'TURNOS';

    public const CATEGORIA_ATENCION = 'ATENCION';

    public const CATEGORIA_FACTURACION_COBERTURA = 'FACTURACION_COBERTURA';

    public const CATEGORIA_OTRO = 'OTRO';

    /** @var array<string, string> */
    private const CATEGORIA_LABELS = [
        self::CATEGORIA_APP => 'Aplicación',
        self::CATEGORIA_TURNOS => 'Turnos',
        self::CATEGORIA_ATENCION => 'Atención recibida',
        self::CATEGORIA_FACTURACION_COBERTURA => 'Facturación / cobertura',
        self::CATEGORIA_OTRO => 'Otro',
    ];

    public static function tableName()
    {
        return '{{%queja_paciente}}';
    }

    /**
     * @return list<string>
     */
    public static function categoriaValues(): array
    {
        return [
            self::CATEGORIA_APP,
            self::CATEGORIA_TURNOS,
            self::CATEGORIA_ATENCION,
            self::CATEGORIA_FACTURACION_COBERTURA,
            self::CATEGORIA_OTRO,
        ];
    }

    /**
     * @return array<string, string> value => label
     */
    public static function categoriaLabels(): array
    {
        return self::CATEGORIA_LABELS;
    }

    public static function categoriaLabel(string $categoria): string
    {
        return self::CATEGORIA_LABELS[$categoria] ?? $categoria;
    }

    public function rules()
    {
        return [
            [['id_persona', 'categoria', 'descripcion'], 'required'],
            [['id_persona'], 'integer'],
            [['descripcion'], 'string', 'min' => 20, 'max' => 2000],
            [['categoria'], 'in', 'range' => self::categoriaValues()],
        ];
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'id_persona' => 'Persona',
            'categoria' => 'Categoría',
            'descripcion' => 'Descripción',
            'created_at' => 'Fecha',
        ];
    }

    public function getPersona()
    {
        return $this->hasOne(Persona::class, ['id_persona' => 'id_persona']);
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function crearDesdeInput(int $idPersona, array $input): self
    {
        $row = new static();
        $row->id_persona = $idPersona;
        $row->categoria = strtoupper(trim((string) ($input['categoria'] ?? '')));
        $row->descripcion = trim((string) ($input['descripcion'] ?? ''));
        if (!$row->validate()) {
            $first = reset($row->firstErrors);
            throw new \InvalidArgumentException(is_string($first) ? $first : 'Datos inválidos.');
        }
        $row->save(false);

        return $row;
    }
}
