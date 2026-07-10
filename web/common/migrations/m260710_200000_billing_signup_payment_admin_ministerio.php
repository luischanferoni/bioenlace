<?php

use common\components\Platform\Infra\Migration\MigrationEnumColumn;
use common\models\BillingPayment;
use common\models\BillingSignupRequest;
use yii\db\Migration;
use yii\db\Query;

/**
 * Onboarding comercial: pagos simulados, solicitudes ministerio, owner de cuenta, rol AdminMinisterio.
 */
class m260710_200000_billing_signup_payment_admin_ministerio extends Migration
{
    private const ROLE_TYPE = 1;

    private const ROUTE_TYPE = 3;

    private const ROLE_ADMIN_MINISTERIO = 'AdminMinisterio';

    /** @var list<string> */
    private const PUBLIC_ROUTES = [
        '/api/licencia/catalogo-ministerios',
        '/api/licencia/planes',
        '/api/licencia/registrar-efector',
        '/api/licencia/solicitar-ministerio',
    ];

    /** @var list<string> */
    private const ADMIN_EFECTOR_ROUTES = [
        '/api/licencia/mi-licencia',
        '/api/licencia/desvincular-pago-ministerio',
        '/api/licencia/asociar-pago-ministerio',
    ];

    public function safeUp()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $account = '{{%billing_account}}';
        $schema = $this->db->schema->getTableSchema($account, true);
        if ($schema !== null && !isset($schema->columns['owner_user_id'])) {
            $this->addColumn($account, 'owner_user_id', $this->integer()->null()->after('activo'));
            $this->createIndex('idx_billing_account_owner', $account, ['owner_user_id', 'deleted_at']);
        }

        $payment = '{{%billing_payment}}';
        if ($this->db->schema->getTableSchema($payment, true) === null) {
            $this->createTable($payment, [
                'id' => $this->primaryKey()->unsigned(),
                'id_billing_account' => $this->integer()->unsigned()->notNull(),
                'provider' => MigrationEnumColumn::mysqlEnum(
                    BillingPayment::providerValues(),
                    BillingPayment::PROVIDER_SIMULATED,
                    true,
                    'SIMULATED|MERCADOPAGO|STRIPE'
                ),
                'status' => MigrationEnumColumn::mysqlEnum(
                    BillingPayment::statusValues(),
                    BillingPayment::STATUS_PENDING,
                    true,
                    'PENDING|APPROVED|REJECTED|REFUNDED'
                ),
                'amount_usd' => $this->decimal(12, 2)->notNull()->defaultValue(0),
                'currency' => $this->string(3)->notNull()->defaultValue('USD'),
                'external_reference' => $this->string(64)->null(),
                'card_last4' => $this->string(4)->null(),
                'payload_json' => $this->text()->null(),
                'paid_at' => $this->dateTime()->null(),
                'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
                'updated_at' => $this->dateTime()->null(),
                'deleted_at' => $this->dateTime()->null(),
                'created_by' => $this->integer()->null(),
                'updated_by' => $this->integer()->null(),
                'deleted_by' => $this->integer()->null(),
            ]);
            $this->createIndex('idx_billing_payment_account', $payment, ['id_billing_account', 'deleted_at']);
            $this->createIndex('idx_billing_payment_status', $payment, ['status', 'deleted_at']);
        }

        $req = '{{%billing_signup_request}}';
        if ($this->db->schema->getTableSchema($req, true) === null) {
            $this->createTable($req, [
                'id' => $this->primaryKey()->unsigned(),
                'tipo' => MigrationEnumColumn::mysqlEnum(
                    BillingSignupRequest::tipoValues(),
                    BillingSignupRequest::TIPO_MINISTERIO,
                    true,
                    'MINISTERIO|EFECTOR'
                ),
                'status' => MigrationEnumColumn::mysqlEnum(
                    BillingSignupRequest::statusValues(),
                    BillingSignupRequest::STATUS_PENDING,
                    true,
                    'PENDING|APPROVED|REJECTED'
                ),
                'nombre_organizacion' => $this->string(255)->notNull(),
                'sector' => MigrationEnumColumn::mysqlEnum(
                    BillingSignupRequest::sectorValues(),
                    BillingSignupRequest::SECTOR_PRIVADO,
                    false,
                    'PUBLICO|PRIVADO'
                ),
                'id_billing_account_ministerio' => $this->integer()->unsigned()->null(),
                'contacto_nombre' => $this->string(120)->notNull(),
                'contacto_apellido' => $this->string(120)->notNull(),
                'contacto_email' => $this->string(255)->notNull(),
                'contacto_telefono' => $this->string(40)->null(),
                'contacto_documento' => $this->string(20)->null(),
                'notas' => $this->text()->null(),
                'id_user' => $this->integer()->null(),
                'id_billing_account' => $this->integer()->unsigned()->null(),
                'id_efector' => $this->integer()->null(),
                'reviewed_by' => $this->integer()->null(),
                'reviewed_at' => $this->dateTime()->null(),
                'created_at' => $this->dateTime()->notNull()->defaultExpression('CURRENT_TIMESTAMP'),
                'updated_at' => $this->dateTime()->null(),
                'deleted_at' => $this->dateTime()->null(),
                'created_by' => $this->integer()->null(),
                'updated_by' => $this->integer()->null(),
                'deleted_by' => $this->integer()->null(),
            ]);
            $this->createIndex('idx_billing_signup_tipo_status', $req, ['tipo', 'status', 'deleted_at']);
        }

        $this->ensureAdminMinisterioRole();
        $this->ensureApiRoutes();
        $this->seedSampleMinisterios();
    }

    public function safeDown()
    {
        if (!in_array($this->db->driverName, ['mysql', 'mysqli'], true)) {
            return;
        }

        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        $assignment = $this->db->schema->getRawTableName('{{%auth_assignment}}');

        foreach (array_merge(self::PUBLIC_ROUTES, self::ADMIN_EFECTOR_ROUTES) as $route) {
            if ($this->db->schema->getTableSchema($childTable, true) !== null) {
                $this->db->createCommand()->delete($childTable, ['child' => $route])->execute();
            }
            if ($this->db->schema->getTableSchema($authItem, true) !== null) {
                $this->db->createCommand()->delete($authItem, ['name' => $route])->execute();
            }
        }

        if ($this->db->schema->getTableSchema($assignment, true) !== null) {
            $this->db->createCommand()->delete($assignment, ['item_name' => self::ROLE_ADMIN_MINISTERIO])->execute();
        }
        if ($this->db->schema->getTableSchema($authItem, true) !== null) {
            $this->db->createCommand()->delete($authItem, ['name' => self::ROLE_ADMIN_MINISTERIO])->execute();
        }

        if ($this->db->schema->getTableSchema('{{%billing_signup_request}}', true) !== null) {
            $this->dropTable('{{%billing_signup_request}}');
        }
        if ($this->db->schema->getTableSchema('{{%billing_payment}}', true) !== null) {
            $this->dropTable('{{%billing_payment}}');
        }

        $account = '{{%billing_account}}';
        $schema = $this->db->schema->getTableSchema($account, true);
        if ($schema !== null && isset($schema->columns['owner_user_id'])) {
            $this->dropIndex('idx_billing_account_owner', $account);
            $this->dropColumn($account, 'owner_user_id');
        }
    }

    private function ensureAdminMinisterioRole(): void
    {
        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            return;
        }
        $now = time();
        if (!(new Query())->from($authItem)->where(['name' => self::ROLE_ADMIN_MINISTERIO])->exists($this->db)) {
            $this->db->createCommand()->insert($authItem, [
                'name' => self::ROLE_ADMIN_MINISTERIO,
                'type' => self::ROLE_TYPE,
                'description' => 'Administrador de cuenta ministerio / red pública',
                'rule_name' => null,
                'data' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->execute();
        }
    }

    private function ensureApiRoutes(): void
    {
        $authItem = $this->db->schema->getRawTableName('{{%auth_item}}');
        $childTable = $this->db->schema->getRawTableName('{{%auth_item_child}}');
        if ($this->db->schema->getTableSchema($authItem, true) === null) {
            return;
        }

        $now = time();
        foreach (array_merge(self::PUBLIC_ROUTES, self::ADMIN_EFECTOR_ROUTES) as $route) {
            if (!(new Query())->from($authItem)->where(['name' => $route])->exists($this->db)) {
                $this->db->createCommand()->insert($authItem, [
                    'name' => $route,
                    'type' => self::ROUTE_TYPE,
                    'description' => 'API licencia onboarding: ' . $route,
                    'rule_name' => null,
                    'data' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->execute();
            }
        }

        if ($this->db->schema->getTableSchema($childTable, true) === null) {
            return;
        }

        foreach (self::ADMIN_EFECTOR_ROUTES as $route) {
            if ((new Query())->from($childTable)->where([
                'parent' => 'AdminEfector',
                'child' => $route,
            ])->exists($this->db)) {
                continue;
            }
            if (!(new Query())->from($authItem)->where(['name' => 'AdminEfector'])->exists($this->db)) {
                continue;
            }
            $this->db->createCommand()->insert($childTable, [
                'parent' => 'AdminEfector',
                'child' => $route,
            ])->execute();
        }
    }

    private function seedSampleMinisterios(): void
    {
        $account = '{{%billing_account}}';
        if ($this->db->schema->getTableSchema($account, true) === null) {
            return;
        }

        $samples = [
            'Ministerio de Salud — Nación (demo)',
            'Ministerio de Salud — Santiago del Estero (demo)',
            'Ministerio de Salud — Santa Fe (demo)',
        ];
        foreach ($samples as $nombre) {
            $exists = (new Query())
                ->from($account)
                ->where(['nombre' => $nombre, 'tipo' => 'MINISTERIO', 'deleted_at' => null])
                ->exists($this->db);
            if ($exists) {
                continue;
            }
            $this->db->createCommand()->insert($account, [
                'nombre' => $nombre,
                'tipo' => 'MINISTERIO',
                'notas' => 'Catálogo demo onboarding institucional',
                'activo' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ])->execute();
        }
    }
}
