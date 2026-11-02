<?php

namespace common\models\Organization;

use yii\db\ActiveRecord;

/**
 * Immutable historical USD/ARS conversion rate used by commercial quotes.
 *
 * @property int $id
 * @property string $currency_base
 * @property string $currency_quote
 * @property float $rate
 * @property string $effective_date
 * @property string $source
 * @property string|null $created_at
 */
final class BillingExchangeRate extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'billing_exchange_rate';
    }

    public function rules(): array
    {
        return [
            [['currency_base', 'currency_quote', 'rate', 'effective_date', 'source'], 'required'],
            [['rate'], 'number', 'min' => 0.0001],
            [['effective_date', 'created_at'], 'safe'],
            [['currency_base', 'currency_quote'], 'string', 'length' => 3],
            [['source'], 'string', 'max' => 64],
            [['currency_base', 'currency_quote', 'effective_date'], 'unique',
                'targetAttribute' => ['currency_base', 'currency_quote', 'effective_date']],
        ];
    }
}
