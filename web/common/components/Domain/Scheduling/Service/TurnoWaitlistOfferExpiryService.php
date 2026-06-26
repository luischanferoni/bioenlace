<?php

namespace common\components\Domain\Scheduling\Service;

use common\models\Scheduling\TurnoWaitlistEntry;
use common\models\Scheduling\TurnoWaitlistSlotOffer;
use Yii;

/**
 * Expira ofertas vencidas y dispara cascada al siguiente en FIFO.
 */
final class TurnoWaitlistOfferExpiryService
{
    public function processExpired(int $limit = 100): int
    {
        if (!(Yii::$app->params['autonomous_agent_waitlist_enabled'] ?? true)) {
            return 0;
        }

        $now = date('Y-m-d H:i:s');
        $q = TurnoWaitlistEntry::find()
            ->where(['estado' => TurnoWaitlistEntry::ESTADO_OFFERED])
            ->andWhere(['not', ['offer_expires_at' => null]])
            ->andWhere(['<', 'offer_expires_at', $now])
            ->orderBy(['offer_expires_at' => SORT_ASC])
            ->limit($limit);

        $agent = new TurnoWaitlistFillAgent();
        $n = 0;
        foreach ($q->each() as $entry) {
            if (!$entry instanceof TurnoWaitlistEntry) {
                continue;
            }
            $offerId = (int) ($entry->slot_offer_id ?? 0);
            $entry->estado = TurnoWaitlistEntry::ESTADO_EXPIRED;
            $entry->offer_token = null;
            $entry->updated_at = $now;
            $entry->save(false);

            if ($offerId > 0) {
                $offer = TurnoWaitlistSlotOffer::findOne($offerId);
                if ($offer !== null && $offer->estado === TurnoWaitlistSlotOffer::ESTADO_PENDING) {
                    $agent->offerToNextCandidate($offer);
                }
            }
            $n++;
        }

        return $n;
    }
}
