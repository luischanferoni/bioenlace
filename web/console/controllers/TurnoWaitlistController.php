<?php

namespace console\controllers;

use common\components\Domain\Scheduling\Service\TurnoWaitlistOfferExpiryService;
use yii\console\Controller;

/**
 * Lista de espera: expira ofertas y ofrece al siguiente en FIFO.
 */
class TurnoWaitlistController extends Controller
{
    public function actionExpireOffers($limit = 100): int
    {
        $n = (new TurnoWaitlistOfferExpiryService())->processExpired((int) $limit);
        $this->stdout("Ofertas waitlist expiradas procesadas: {$n}\n");

        return 0;
    }
}
