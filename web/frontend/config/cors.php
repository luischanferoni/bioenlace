<?php

return [
    'class' => 'yii\filters\Cors',
    'cors' => [
        'Origin' => ['http://localhost:3000', 'http://127.0.0.1:3000'],
        'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'],
        'Access-Control-Request-Headers' => ['*'],
        'Access-Control-Allow-Credentials' => true,
        'Access-Control-Max-Age' => 86400,
        'Access-Control-Expose-Headers' => ['X-Pagination-Current-Page', 'X-Pagination-Page-Count', 'X-Pagination-Total-Count', 'X-Pagination-Per-Page'],
    ],
];
