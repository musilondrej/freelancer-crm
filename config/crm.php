<?php

return [
    'fx' => [
        /**
         * Reference rates against EUR (1 EUR = rate in target currency).
         */
        'rates' => [
            'EUR' => 1.0,
            'USD' => 1.09,
            'CZK' => 25.30,
        ],
        'symbols' => [
            'CZK' => 'Kč',
            'EUR' => '€',
            'USD' => '$',
        ],
    ],
    'time_tracking' => [
        'rounding' => [
            'enabled' => true,
            'mode' => 'ceil',
            'interval_minutes' => 15,
            'minimum_minutes' => 1,
        ],
    ],
];
