<?php

namespace common\models;

use common\components\Domain\Scheduling\Service\TeleconsultaElegibilidadService;
use common\models\Scheduling\Turno;
use yii\db\ActiveRecord;

/**
 * Elegibilidad clínica de teleconsulta por código interno de triage.
 *
 * @property int $id
 * @property string $triage_codigo
 * @property string $elegibilidad excluido|presencial_preferido|permitido|sugerido
 * @property int $prioridad
 * @property string|null $notas
 */
class ReservaTriageTeleconsultaElegibilidad extends ActiveRecord
{
    /** @var array<string, string>|null codigo => elegibilidad */
    private static ?array $cachePorCodigo = null;

    public static function tableName(): string
    {
        return '{{%reserva_triage_teleconsulta_elegibilidad}}';
    }

    public function rules(): array
    {
        return [
            [['triage_codigo', 'elegibilidad'], 'required'],
            [['prioridad'], 'integer'],
            [['notas'], 'string'],
            [['triage_codigo'], 'string', 'max' => 64],
            [['elegibilidad'], 'string', 'max' => 32],
            [['elegibilidad'], 'in', 'range' => [
                TeleconsultaElegibilidadService::ELEG_EXCLUIDO,
                TeleconsultaElegibilidadService::ELEG_PRESENCIAL_PREFERIDO,
                TeleconsultaElegibilidadService::ELEG_PERMITIDO,
                TeleconsultaElegibilidadService::ELEG_SUGERIDO,
            ]],
            [['triage_codigo'], 'unique'],
        ];
    }

    public static function elegibilidadParaCodigo(string $codigo): ?string
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }
        self::warmCache();

        return self::$cachePorCodigo[$codigo] ?? null;
    }

    /**
     * Primer código (más específico primero) con regla en BD.
     *
     * @param list<string> $codigos
     */
    public static function elegibilidadParaCodigos(array $codigos): ?string
    {
        foreach ($codigos as $codigo) {
            if (!is_string($codigo)) {
                continue;
            }
            $eleg = self::elegibilidadParaCodigo($codigo);
            if ($eleg !== null && $eleg !== '') {
                return $eleg;
            }
        }

        return null;
    }

    /**
     * @param list<string> $codigos
     */
    public static function suggestTipoAtencionParaCodigos(array $codigos): ?string
    {
        $eleg = self::elegibilidadParaCodigos($codigos);
        if ($eleg === TeleconsultaElegibilidadService::ELEG_SUGERIDO) {
            return Turno::TIPO_ATENCION_TELECONSULTA;
        }

        return null;
    }

    public static function resetCache(): void
    {
        self::$cachePorCodigo = null;
    }

    private static function warmCache(): void
    {
        if (self::$cachePorCodigo !== null) {
            return;
        }
        self::$cachePorCodigo = [];
        try {
            /** @var self[] $rows */
            $rows = static::find()
                ->orderBy(['prioridad' => SORT_DESC, 'triage_codigo' => SORT_ASC])
                ->all();
            foreach ($rows as $row) {
                $code = trim((string) $row->triage_codigo);
                $eleg = trim((string) $row->elegibilidad);
                if ($code === '' || $eleg === '' || isset(self::$cachePorCodigo[$code])) {
                    continue;
                }
                self::$cachePorCodigo[$code] = $eleg;
            }
        } catch (\Throwable $e) {
            self::$cachePorCodigo = [];
        }
    }
}
