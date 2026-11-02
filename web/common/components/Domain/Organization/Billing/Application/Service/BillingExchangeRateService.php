<?php

namespace common\components\Domain\Organization\Billing\Application\Service;

use common\models\Organization\BillingExchangeRate;
use Yii;
use yii\base\InvalidConfigException;
use yii\db\IntegrityException;

final class BillingExchangeRateService
{
    /**
     * @return array{rate: float, effective_date: string, source: string}
     */
    public function current(): array
    {
        $effectiveDate = date('Y-m-d');
        $record = BillingExchangeRate::find()
            ->where([
                'currency_base' => 'USD',
                'currency_quote' => 'ARS',
            ])
            ->andWhere(['<=', 'effective_date', $effectiveDate])
            ->orderBy(['effective_date' => SORT_DESC, 'id' => SORT_DESC])
            ->one();

        if ($record === null) {
            throw new InvalidConfigException('No hay una cotización USD/ARS vigente registrada.');
        }

        $ageDays = (int) floor((strtotime($effectiveDate) - strtotime($record->effective_date)) / 86400);
        if ($ageDays < 0 || $ageDays > 100) {
            throw new InvalidConfigException('La cotización USD/ARS está vencida; debe revisarse trimestralmente.');
        }

        return [
            'rate' => (float) $record->rate,
            'effective_date' => (string) $record->effective_date,
            'source' => (string) $record->source,
        ];
    }

    public function record(float $rate, string $effectiveDate, string $source = 'MEP vendedor'): BillingExchangeRate
    {
        if ($rate <= 0 || !is_finite($rate)) {
            throw new \InvalidArgumentException('La cotización debe ser mayor que cero.');
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $effectiveDate);
        if ($date === false || $date->format('Y-m-d') !== $effectiveDate) {
            throw new \InvalidArgumentException('La fecha debe tener el formato YYYY-MM-DD.');
        }
        if ($date > new \DateTimeImmutable('today')) {
            throw new \InvalidArgumentException('La fecha de vigencia no puede estar en el futuro.');
        }

        $source = trim($source);
        if ($source === '' || mb_strlen($source) > 64) {
            throw new \InvalidArgumentException('Indicá una fuente de cotización de hasta 64 caracteres.');
        }

        $record = new BillingExchangeRate();
        $record->currency_base = 'USD';
        $record->currency_quote = 'ARS';
        $record->rate = round($rate, 4);
        $record->effective_date = $effectiveDate;
        $record->source = $source;

        try {
            if (!$record->save()) {
                throw new \InvalidArgumentException('No se pudo guardar la cotización: ' . json_encode($record->getErrors()));
            }
        } catch (IntegrityException $e) {
            Yii::error($e, 'billing.exchange-rate');
            throw new \InvalidArgumentException('Ya existe una cotización para esa fecha.', 0, $e);
        }

        return $record;
    }
}
