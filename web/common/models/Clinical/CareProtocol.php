<?php

namespace common\models\Clinical;

use yii\db\ActiveRecord;

/**
 * Catálogo de protocolos de cuidado (PlanDefinition-lite) — Nación / Provincia.
 */
class CareProtocol extends ActiveRecord
{
    public const SCOPE_NATION = 'NATION';

    public const SCOPE_PROVINCE = 'PROVINCE';

    public const MATCH_NONE = 'none';

    public const MATCH_ACTIVE = 'active';

    public const MATCH_CHRONIC = 'chronic';

    public const MATCH_ACTIVE_OR_CHRONIC = 'active_or_chronic';

    public static function tableName(): string
    {
        return '{{%care_protocol}}';
    }

    public function rules(): array
    {
        return [
            [['protocol_key', 'title', 'scope_type', 'condition_match', 'actions_json', 'created_at'], 'required'],
            [['enabled'], 'boolean'],
            [['orden', 'id_provincia', 'age_min', 'age_max', 'created_by', 'updated_by'], 'integer'],
            [['protocol_key'], 'string', 'max' => 64],
            [['title', 'hub_label'], 'string', 'max' => 255],
            [['scope_type'], 'in', 'range' => [self::SCOPE_NATION, self::SCOPE_PROVINCE]],
            [['condition_match'], 'in', 'range' => [
                self::MATCH_NONE,
                self::MATCH_ACTIVE,
                self::MATCH_CHRONIC,
                self::MATCH_ACTIVE_OR_CHRONIC,
            ]],
            [['sex_json', 'condition_codes_json', 'actions_json'], 'string'],
            [['created_at', 'updated_at'], 'safe'],
            [['protocol_key'], 'unique'],
        ];
    }

    /**
     * @return list<string>
     */
    public function sexList(): array
    {
        return $this->decodeStringList($this->sex_json);
    }

    /**
     * @return list<string>
     */
    public function conditionCodesList(): array
    {
        $codes = [];
        foreach ($this->decodeStringList($this->condition_codes_json) as $c) {
            $s = strtoupper(trim($c));
            if ($s !== '') {
                $codes[] = $s;
            }
        }

        return $codes;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function actionsList(): array
    {
        $raw = json_decode((string) $this->actions_json, true);
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $action) {
            if (!is_array($action)) {
                continue;
            }
            $code = trim((string) ($action['code'] ?? ''));
            if ($code === '') {
                continue;
            }
            $draft = [];
            foreach ($action['draft'] ?? [] as $k => $v) {
                $draft[trim((string) $k)] = trim((string) $v);
            }
            $out[] = [
                'code' => $code,
                'label' => trim((string) ($action['label'] ?? $code)),
                'description' => trim((string) ($action['description'] ?? '')),
                'outcome' => trim((string) ($action['outcome'] ?? 'captura_mensaje')) ?: 'captura_mensaje',
                'draft' => $draft,
            ];
        }

        return $out;
    }

    /**
     * Shape consumido por matcher / hub (compatible con el shape previo del YAML).
     *
     * @return array<string, mixed>
     */
    public function toCatalogArray(): array
    {
        $title = trim((string) $this->title);
        $hubLabel = trim((string) ($this->hub_label ?? ''));

        return [
            'id' => (string) $this->protocol_key,
            'db_id' => (int) $this->id,
            'title' => $title,
            'hub_label' => $hubLabel !== '' ? $hubLabel : $title,
            'fhir_kind' => 'PlanDefinition',
            'enabled' => (bool) $this->enabled,
            'orden' => (int) $this->orden,
            'scope_type' => (string) $this->scope_type,
            'id_provincia' => $this->id_provincia !== null ? (int) $this->id_provincia : null,
            'condition_match' => (string) $this->condition_match,
            'applies' => [
                'condition_codes' => $this->conditionCodesList(),
                'age_years' => [
                    'min' => $this->age_min !== null ? (int) $this->age_min : null,
                    'max' => $this->age_max !== null ? (int) $this->age_max : null,
                ],
                'sex' => $this->sexList(),
            ],
            'actions' => $this->actionsList(),
        ];
    }

    /**
     * @return list<string>
     */
    private function decodeStringList(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }
        $raw = json_decode($json, true);
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $v) {
            $s = trim((string) $v);
            if ($s !== '') {
                $out[] = $s;
            }
        }

        return $out;
    }
}
