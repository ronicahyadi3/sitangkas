<?php

return [
    'group_meta' => [
        'pages' => [
            'label' => 'Pages',
            'icon' => 'fa-solid fa-layer-group',
        ],
        'administration' => [
            'label' => 'Administrasi',
            'icon' => 'fa-solid fa-screwdriver-wrench',
        ],
    ],

    'route_meta' => [
        'positions.index' => [
            'label' => 'Ganti Posisi Kerja',
            'group' => 'pages',
            'code' => 'POS',
        ],
        'users.index' => [
            'label' => 'Management Users',
            'group' => 'administration',
            'code' => 'USR',
        ],
        'users.audit-trail' => [
            'label' => 'Audit Trail',
            'group' => 'administration',
            'code' => 'AUD',
        ],
    ],
];
