<?php

namespace common\models\Clinical\Input;

use common\components\Domain\Clinical\Capture\Domain\RowContract\OftalmologiaRecetaRowContract;
use yii\base\Model;

/**
 * Receta de lentes en captura: una fila con ambos ojos.
 * Completitud: {@see OftalmologiaRecetaRowContract}.
 */
final class OftalmologiaRecetaInput extends Model
{
    /** @var string|null */
    public $odEsfera;

    /** @var string|null */
    public $odCilindro;

    /** @var string|null */
    public $odEje;

    /** @var string|null */
    public $odAdicion;

    /** @var string|null */
    public $oiEsfera;

    /** @var string|null */
    public $oiCilindro;

    /** @var string|null */
    public $oiEje;

    /** @var string|null */
    public $oiAdicion;

    /**
     * Mapa atributo del modelo → clave que devuelve la IA.
     *
     * @return array<string, string>
     */
    public static function fieldMap(): array
    {
        return [
            'odEsfera' => OftalmologiaRecetaRowContract::FIELD_OD_ESFERA,
            'odCilindro' => OftalmologiaRecetaRowContract::FIELD_OD_CILINDRO,
            'odEje' => OftalmologiaRecetaRowContract::FIELD_OD_EJE,
            'odAdicion' => OftalmologiaRecetaRowContract::FIELD_OD_ADICION,
            'oiEsfera' => OftalmologiaRecetaRowContract::FIELD_OI_ESFERA,
            'oiCilindro' => OftalmologiaRecetaRowContract::FIELD_OI_CILINDRO,
            'oiEje' => OftalmologiaRecetaRowContract::FIELD_OI_EJE,
            'oiAdicion' => OftalmologiaRecetaRowContract::FIELD_OI_ADICION,
        ];
    }

    /**
     * @return list<string>
     */
    public static function promptFieldNames(): array
    {
        return OftalmologiaRecetaRowContract::PROMPT_FIELDS;
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function fromExtractedRow($row): self
    {
        $model = new self();
        foreach (self::fieldMap() as $attribute => $promptField) {
            $value = OftalmologiaRecetaRowContract::value($row, $promptField);
            $model->{$attribute} = $value !== '' ? $value : null;
        }

        return $model;
    }

    public function rules(): array
    {
        return [
            [
                [
                    'odEsfera', 'odCilindro', 'odEje', 'odAdicion',
                    'oiEsfera', 'oiCilindro', 'oiEje', 'oiAdicion',
                ],
                'string',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function missingFieldsForCompleteness(): array
    {
        $missing = [];
        foreach (self::fieldMap() as $attribute => $promptField) {
            if (trim((string) ($this->{$attribute} ?? '')) === '') {
                $missing[] = $promptField;
            }
        }

        return count($missing) === count(self::fieldMap()) ? $missing : [];
    }

    public function rowLabel(): string
    {
        foreach (self::fieldMap() as $attribute => $promptField) {
            $value = trim((string) ($this->{$attribute} ?? ''));
            if ($value !== '') {
                return $promptField . ' ' . $value;
            }
        }

        return 'receta';
    }
}
