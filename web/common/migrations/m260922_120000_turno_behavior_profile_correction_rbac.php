<?php

use yii\db\Migration;
use yii\db\Query;

/**
 * RBAC de corrección del perfil factual de turnos (paciente + staff).
 */
class m260922_120000_turno_behavior_profile_correction_rbac extends Migration
{
    private const REQUEST_OWN = '/api/turnos-perfil/solicitar-correccion-propia-como-paciente';
    private const REQUEST_REP = '/api/turnos-perfil/solicitar-correccion-representada-como-paciente';
    private const LIST_STAFF = '/api/turnos-perfil/listar-correcciones-efector-para-staff';
    private const RESOLVE_STAFF = '/api/turnos-perfil/resolver-correccion-para-staff';
    private const HISTORIAL_OWN = '/api/turnos-perfil/historial-propio-como-paciente';
    private const AGGREGATE = '/api/turnos-perfil/agregado-efector-para-staff';

    public function safeUp()
    {
        $items = '{{%auth_item}}';
        $children = '{{%auth_item_child}}';
        if ($this->db->schema->getTableSchema($items, true) === null
            || $this->db->schema->getTableSchema($children, true) === null) {
            return;
        }

        $this->ensureRoute($items, self::REQUEST_OWN, 'Solicitar corrección de evento del propio perfil de turnos');
        $this->ensureRoute($items, self::REQUEST_REP, 'Solicitar corrección de evento del perfil representado');
        $this->ensureRoute($items, self::LIST_STAFF, 'Listar solicitudes de corrección de perfil de turnos');
        $this->ensureRoute($items, self::RESOLVE_STAFF, 'Resolver solicitud de corrección de perfil de turnos');

        $this->inheritParents($children, self::HISTORIAL_OWN, [
            self::REQUEST_OWN,
            self::REQUEST_REP,
        ]);
        $this->inheritParents($children, self::AGGREGATE, [
            self::LIST_STAFF,
            self::RESOLVE_STAFF,
        ]);
    }

    public function safeDown()
    {
        if ($this->db->schema->getTableSchema('{{%auth_item}}', true) === null
            || $this->db->schema->getTableSchema('{{%auth_item_child}}', true) === null) {
            return;
        }
        foreach ([
            self::REQUEST_OWN,
            self::REQUEST_REP,
            self::LIST_STAFF,
            self::RESOLVE_STAFF,
        ] as $route) {
            $this->delete('{{%auth_item_child}}', ['child' => $route]);
            $this->delete('{{%auth_item}}', ['name' => $route]);
        }
    }

    private function ensureRoute(string $items, string $route, string $description): void
    {
        if ((new Query())->from($items)->where(['name' => $route])->exists($this->db)) {
            return;
        }
        $now = time();
        $this->insert($items, [
            'name' => $route,
            'type' => 3,
            'description' => $description,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param list<string> $newChildren
     */
    private function inheritParents(string $children, string $fromChild, array $newChildren): void
    {
        $parents = (new Query())->select('parent')->from($children)
            ->where(['child' => $fromChild])->column($this->db);
        foreach ($parents as $parent) {
            foreach ($newChildren as $child) {
                if (!(new Query())->from($children)->where([
                    'parent' => $parent,
                    'child' => $child,
                ])->exists($this->db)) {
                    $this->insert($children, ['parent' => $parent, 'child' => $child]);
                }
            }
        }
    }
}
