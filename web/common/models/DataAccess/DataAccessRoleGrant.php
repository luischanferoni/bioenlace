<?php

namespace common\models\DataAccess;

use Yii;
use yii\db\ActiveRecord;
use common\components\Core\DataAccess\AttributeGroupCatalog;
use common\components\Core\DataAccess\Grant\DatabaseRoleGrantSource;
use common\components\Core\DataAccess\QueryOperation;
use common\components\Core\DataAccess\ScopeCheckerRegistry;

/**
 * Grant de acceso a grupo de atributos por rol (fuente única de permisos DataAccess).
 *
 * @property int $id
 * @property string $role_name
 * @property string $entity_group_key
 * @property string $operations_csv
 * @property string|null $scope_checker
 * @property int $active
 * @property string|null $notas
 *
 * @property list<string> $operationsSelected
 */
class DataAccessRoleGrant extends ActiveRecord
{
    /** @var list<string> */
    public $operationsSelected = [];

    public static function tableName(): string
    {
        return 'data_access_role_grant';
    }

    public function rules(): array
    {
        return [
            [['role_name', 'entity_group_key'], 'required'],
            [['role_name'], 'string', 'max' => 64],
            [['entity_group_key'], 'string', 'max' => 128],
            [['scope_checker'], 'string', 'max' => 64],
            [['operations_csv'], 'string', 'max' => 255],
            [['notas'], 'string'],
            [['active'], 'integer'],
            [['active'], 'default', 'value' => 1],
            [['operationsSelected'], 'safe'],
            [['entity_group_key'], 'validateEntityGroupKey'],
            [['role_name'], 'validateRoleName'],
            [['operationsSelected'], 'validateOperationsSelected'],
            [['scope_checker'], 'validateScopeChecker'],
            [
                ['role_name', 'entity_group_key'],
                'unique',
                'targetAttribute' => ['role_name', 'entity_group_key'],
                'message' => 'Ya existe un grant para este rol y grupo.',
            ],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'role_name' => 'Rol',
            'entity_group_key' => 'Grupo de atributos',
            'operations_csv' => 'Operaciones',
            'operationsSelected' => 'Operaciones',
            'scope_checker' => 'Scope checker',
            'active' => 'Activo',
            'notas' => 'Notas',
        ];
    }

    public function afterFind(): void
    {
        parent::afterFind();
        $this->operationsSelected = self::parseOperationsCsv((string) $this->operations_csv);
    }

    public function beforeValidate(): bool
    {
        if (is_array($this->operationsSelected) && $this->operationsSelected !== []) {
            $ops = self::normalizeOperations($this->operationsSelected);
            $this->operations_csv = implode(',', $ops);
        }

        $checker = trim((string) $this->scope_checker);
        $this->scope_checker = $checker === '' ? null : $checker;

        return parent::beforeValidate();
    }

    public function afterSave($insert, $changedAttributes): void
    {
        parent::afterSave($insert, $changedAttributes);
        DatabaseRoleGrantSource::clearCache();
    }

    public function afterDelete(): void
    {
        parent::afterDelete();
        DatabaseRoleGrantSource::clearCache();
    }

    public function validateEntityGroupKey(string $attribute): void
    {
        $key = trim((string) $this->$attribute);
        if ($key === '') {
            return;
        }
        $catalog = new AttributeGroupCatalog();
        if (!$catalog->entityGroupExists($key)) {
            $this->addError($attribute, 'Grupo no registrado en data-access-config.');
        }
    }

    public function validateRoleName(string $attribute): void
    {
        $role = trim((string) $this->$attribute);
        if ($role === '') {
            return;
        }
        if (!isset(self::roleNameOptions()[$role])) {
            $this->addError($attribute, 'El rol no existe en webvimark.');
        }
    }

    /**
     * Roles referenciados en grants que ya no existen en authManager.
     *
     * @return list<string>
     */
    public static function findOrphanRoleNames(): array
    {
        $known = array_keys(self::roleNameOptions());
        $used = self::find()->select('role_name')->distinct()->column();
        $orphans = [];
        foreach ($used as $role) {
            $role = (string) $role;
            if ($role !== '' && !in_array($role, $known, true)) {
                $orphans[] = $role;
            }
        }
        sort($orphans);

        return $orphans;
    }

    /**
     * @return array<string, int> rol huérfano => cantidad de grants
     */
    public static function orphanRoleGrantCounts(): array
    {
        $out = [];
        foreach (self::findOrphanRoleNames() as $role) {
            $out[$role] = (int) self::find()->where(['role_name' => $role])->count();
        }

        return $out;
    }

    public function validateOperationsSelected(string $attribute): void
    {
        $ops = is_array($this->operationsSelected) ? $this->operationsSelected : [];
        $ops = self::normalizeOperations($ops);
        if ($ops === []) {
            $this->addError($attribute, 'Seleccione al menos una operación.');

            return;
        }
        foreach ($ops as $op) {
            if (!QueryOperation::isValid($op)) {
                $this->addError($attribute, 'Operación inválida: ' . $op);

                return;
            }
        }
    }

    public function validateScopeChecker(string $attribute): void
    {
        $checker = trim((string) $this->$attribute);
        if ($checker === '') {
            return;
        }
        if (!in_array($checker, ScopeCheckerRegistry::knownIds(), true)) {
            $this->addError($attribute, 'Scope checker desconocido.');
        }
    }

    /**
     * @return array{operations: list<string>, scope_checker?: string|null}|null
     */
    public function toGrantShape(): ?array
    {
        $ops = self::parseOperationsCsv((string) $this->operations_csv);
        if ($ops === []) {
            return null;
        }
        $checker = trim((string) $this->scope_checker);

        $out = ['operations' => $ops];
        if ($checker !== '') {
            $out['scope_checker'] = $checker;
        }

        return $out;
    }

    /** @return array<string, string> */
    public static function roleNameOptions(): array
    {
        $auth = Yii::$app->authManager;
        if ($auth === null) {
            return [];
        }
        $out = [];
        foreach ($auth->getRoles() as $name => $role) {
            $out[(string) $name] = (string) $name;
        }
        ksort($out);

        return $out;
    }

    /** @return array<string, string> */
    public static function operationOptions(): array
    {
        $out = [];
        foreach (QueryOperation::all() as $op) {
            $out[$op] = $op;
        }

        return $out;
    }

    /**
     * @param list<string>|array<int, string> $operations
     * @return list<string>
     */
    public static function normalizeOperations(array $operations): array
    {
        $ops = array_values(array_unique(array_filter(array_map(static function ($op) {
            return mb_strtolower(trim((string) $op), 'UTF-8');
        }, $operations))));
        sort($ops);

        return $ops;
    }

    /**
     * @return list<string>
     */
    public static function parseOperationsCsv(string $csv): array
    {
        if ($csv === '') {
            return [];
        }

        return self::normalizeOperations(explode(',', $csv));
    }
}
