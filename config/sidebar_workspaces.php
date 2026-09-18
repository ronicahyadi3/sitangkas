<?php

return [
    'group_meta' => [
        'pages' => [
            'label' => 'Pages',
            'icon' => 'fa-solid fa-layer-group',
        ],
        'ls' => [
            'label' => 'Pencairan Langsung',
            'icon' => 'fa-solid fa-money-bill-transfer',
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
        'ls.spp.index' => [
            'label' => 'Dokumen SPP',
            'group' => 'ls',
            'code' => 'SPP',
        ],
        'ls.spm.index' => [
            'label' => 'Dokumen SPM',
            'group' => 'ls',
            'code' => 'SPM',
        ],
        'ls.sp2d.index' => [
            'label' => 'Dokumen SP2D',
            'group' => 'ls',
            'code' => 'SP2D',
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

    'roles' => [
        'BUD' => [
            'workspace_routes' => [
                'ls.sp2d.index',
            ],
        ],
        'KUASA_BUD' => [
            'workspace_routes' => [
                'ls.sp2d.index',
            ],
        ],
        'VERIFIKATOR_BUD' => [
            'workspace_routes' => [
                'ls.spm.index',
                'ls.sp2d.index',
            ],
        ],
        'PA' => [
            'workspace_routes' => [
                'ls.spp.index',
                'ls.spm.index',
            ],
        ],
        'KPA' => [
            'workspace_routes' => [
                'ls.spp.index',
                'ls.spm.index',
            ],
        ],
        'PPK_SKPD' => [
            'workspace_routes' => [
                'ls.spp.index',
                'ls.spm.index',
            ],
        ],
        'PPTK' => [
            'workspace_routes' => [
                'ls.spp.index',
            ],
        ],
        'BP' => [
            'workspace_routes' => [
                'ls.spp.index',
            ],
        ],
        'BPP' => [
            'workspace_routes' => [
                'ls.spp.index',
            ],
        ],
        'AUDITOR' => [
            'workspace_routes' => [
                'ls.spp.index',
                'ls.spm.index',
                'ls.sp2d.index',
            ],
        ],
    ],
];
