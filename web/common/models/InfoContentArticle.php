<?php

namespace common\models;

use Yii;
use yii\behaviors\BlameableBehavior;
use yii\behaviors\TimestampBehavior;
use yii\db\Expression;
use common\components\Platform\Core\Permission\IntentManifestIndex;

/**
 * Artículo informativo con alcance jerárquico (producto → provincia → efector).
 *
 * @property int $id
 * @property string $topic
 * @property string $title
 * @property string $body
 * @property string $scope producto|provincia|efector
 * @property int|null $id_provincia
 * @property int|null $id_efector
 * @property string|null $keywords
 * @property string|null $intent_ids intent_id separados por coma (CTA; solo producto)
 * @property bool $activo
 * @property int $priority
 * @property string $created_at
 * @property string $updated_at
 * @property int|null $created_by
 * @property int|null $updated_by
 *
 * @property-read Provincia|null $provincia
 * @property-read Efector|null $efector
 */
class InfoContentArticle extends \yii\db\ActiveRecord
{
    public const SCOPE_PRODUCTO = 'producto';
    public const SCOPE_PROVINCIA = 'provincia';
    public const SCOPE_EFECTOR = 'efector';

    public static function tableName(): string
    {
        return '{{%info_content_article}}';
    }

    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'value' => new Expression('NOW()'),
            ],
            [
                'class' => BlameableBehavior::class,
                'createdByAttribute' => 'created_by',
                'updatedByAttribute' => 'updated_by',
            ],
        ];
    }

    public function rules(): array
    {
        return [
            [['topic', 'title', 'body', 'scope'], 'required'],
            [['body'], 'string'],
            [['topic'], 'string', 'max' => 80],
            [['title'], 'string', 'max' => 255],
            [['keywords', 'intent_ids'], 'string', 'max' => 500],
            [['scope'], 'in', 'range' => [self::SCOPE_PRODUCTO, self::SCOPE_PROVINCIA, self::SCOPE_EFECTOR]],
            [['id_provincia', 'id_efector', 'priority'], 'integer'],
            [['activo'], 'boolean'],
            [['id_provincia'], 'required', 'when' => fn ($m) => $m->scope === self::SCOPE_PROVINCIA,
                'whenClient' => "function(a){return $('#infocontentarticle-scope').val()==='provincia';}"],
            [['id_efector'], 'required', 'when' => fn ($m) => $m->scope === self::SCOPE_EFECTOR,
                'whenClient' => "function(a){return $('#infocontentarticle-scope').val()==='efector';}"],
            [['id_provincia'], 'default', 'value' => null],
            [['id_efector'], 'default', 'value' => null],
            [['priority'], 'default', 'value' => 0],
            [['activo'], 'default', 'value' => true],
            [['intent_ids'], 'validateIntentIds'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'topic' => 'Tema',
            'title' => 'Título',
            'body' => 'Contenido',
            'scope' => 'Alcance',
            'id_provincia' => 'Provincia',
            'id_efector' => 'Centro de salud',
            'keywords' => 'Palabras clave',
            'intent_ids' => 'Intents CTA',
            'activo' => 'Activo',
            'priority' => 'Prioridad',
            'created_at' => 'Creado',
            'updated_at' => 'Actualizado',
        ];
    }

    public function beforeValidate(): bool
    {
        if (!parent::beforeValidate()) {
            return false;
        }

        // Overrides provincia/efector: solo body; CTA vive en producto.
        if ($this->scope !== self::SCOPE_PRODUCTO) {
            $this->intent_ids = null;
        }

        return true;
    }

    public function validateIntentIds(string $attribute): void
    {
        foreach ($this->getIntentIdList() as $intentId) {
            if (IntentManifestIndex::get($intentId) === null) {
                $this->addError($attribute, 'Intent desconocido en catálogo: ' . $intentId);
            }
        }
    }

    public function getProvincia(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Provincia::class, ['id_provincia' => 'id_provincia']);
    }

    public function getEfector(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Efector::class, ['id_efector' => 'id_efector']);
    }

    /**
     * @return string[]
     */
    public function getKeywordList(): array
    {
        return self::splitCsv((string) $this->keywords);
    }

    /**
     * @return list<string>
     */
    public function getIntentIdList(): array
    {
        return self::splitCsv((string) $this->intent_ids);
    }

    /**
     * @return list<string>
     */
    public static function splitCsv(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('trim', explode(',', $raw)))));
    }

    public static function scopeLabels(): array
    {
        return [
            self::SCOPE_PRODUCTO => 'Producto (global)',
            self::SCOPE_PROVINCIA => 'Provincia',
            self::SCOPE_EFECTOR => 'Centro de salud',
        ];
    }
}
