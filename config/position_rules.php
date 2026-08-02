<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SITANGKAS Position Rules
    |--------------------------------------------------------------------------
    |
    | These rules are the single source of truth for Admin Super acting context.
    | New code must resolve master data by stable `kode` values. Legacy numeric
    | IDs are kept only as a migration bridge while old flows are ported.
    |
    */

    'admin_super_jabatan_codes' => [
        'ADMIN_SUPER',
    ],

    'manual_context' => [
        'route' => 'login.post',
        'store_route' => 'login.post.store',
        'excluded_jabatan_codes' => [
            'ADMIN_SUPER',
        ],
        'requires_instansi' => true,
        'requires_unit_kerja' => true,
        'session_keys' => [
            'jabatan_id' => 'acting_jabatan_id',
            'jabatan_name' => 'acting_jabatan_name',
            'instansi_id' => 'acting_instansi_id',
            'unit_kerja_id' => 'acting_unit_kerja_id',
            'pptk_user_position_id' => 'acting_pptk_user_position_id',
            'bud_user_position_id' => 'acting_bud_user_position_id',
            'selected_at' => 'acting_selected_at',
        ],
    ],

    'special_user_by_jabatan_code' => [
        'BUD' => 'bud',
        'KUASA_BUD' => 'bud',
        'PPTK' => 'pptk',
    ],

    'fixed_bkad_jabatan_codes' => [
        'BUD',
        'KUASA_BUD',
        'VERIFIKATOR_BUD',
        'BANK',
        'PIMPINAN',
        'AUDITOR',
    ],

    'instansi' => [
        'skpd_code' => 'SKPD',

        'kecamatan_codes' => [
            'KEC_LOWOKWARU',
            'KEC_KLOJEN',
            'KEC_BLIMBING',
            'KEC_SUKUN',
            'KEC_KEDUNGKANDANG',
        ],

        'selectable_codes' => [
            'SKPD',
            'DIKBUD',
            'DINKES',
            'SETDA',
            'KEC_LOWOKWARU',
            'KEC_KLOJEN',
            'KEC_BLIMBING',
            'KEC_SUKUN',
            'KEC_KEDUNGKANDANG',
        ],
    ],

    'unit_kerja' => [
        'bkad_code' => 'SKPD_BKAD',

        'kecamatan_root_codes' => [
            'KEC_LOWOKWARU',
            'KEC_KLOJEN',
            'KEC_BLIMBING',
            'KEC_SUKUN',
            'KEC_KEDUNGKANDANG',
        ],
    ],

    'jabatan_to_instansi_codes' => [
        'BUD' => ['SKPD'],
        'KUASA_BUD' => ['SKPD'],
        'VERIFIKATOR_BUD' => ['SKPD'],
        'PA' => ['SKPD'],
        'KPA' => [
            'DIKBUD',
            'DINKES',
            'SETDA',
            'KEC_LOWOKWARU',
            'KEC_KLOJEN',
            'KEC_BLIMBING',
            'KEC_SUKUN',
            'KEC_KEDUNGKANDANG',
        ],
        'PPK_SKPD' => ['SKPD'],
        'PPTK' => [
            'SKPD',
            'DIKBUD',
            'DINKES',
            'SETDA',
            'KEC_LOWOKWARU',
            'KEC_KLOJEN',
            'KEC_BLIMBING',
            'KEC_SUKUN',
            'KEC_KEDUNGKANDANG',
        ],
        'BP' => ['SKPD'],
        'BPP' => [
            'DIKBUD',
            'DINKES',
            'SETDA',
            'KEC_LOWOKWARU',
            'KEC_KLOJEN',
            'KEC_BLIMBING',
            'KEC_SUKUN',
            'KEC_KEDUNGKANDANG',
        ],
        'BANK' => ['SKPD'],
        'PIMPINAN' => ['SKPD'],
        'AUDITOR' => ['SKPD'],
    ],

    'unit_scope_policy_by_jabatan_code' => [
        'BUD' => 'fixed_bkad',
        'KUASA_BUD' => 'fixed_bkad',
        'VERIFIKATOR_BUD' => 'fixed_bkad',
        'PA' => 'skpd_plus_kecamatan_roots',
        'KPA' => 'kelurahan_only_when_kecamatan',
        'PPK_SKPD' => 'skpd_plus_kecamatan_roots',
        'PPTK' => 'pptk_mixed_scope',
        'BP' => 'skpd_plus_kecamatan_roots',
        'BPP' => 'kelurahan_only_when_kecamatan',
        'BANK' => 'fixed_bkad',
        'PIMPINAN' => 'fixed_bkad',
        'AUDITOR' => 'fixed_bkad',
    ],

    'unit_scope_policies' => [
        'fixed_bkad' => [
            'fixed_instansi_code' => 'SKPD',
            'fixed_unit_kerja_code' => 'SKPD_BKAD',
        ],
        'skpd_plus_kecamatan_roots' => [
            'instansi_code' => 'SKPD',
            'include_skpd_units' => true,
            'include_kecamatan_root_units' => true,
            'exclude_kecamatan_root_units_when_kecamatan_selected' => false,
        ],
        'kelurahan_only_when_kecamatan' => [
            'include_default_instansi_units' => true,
            'exclude_kecamatan_root_units_when_kecamatan_selected' => true,
        ],
        'pptk_mixed_scope' => [
            'include_skpd_units_when_skpd_selected' => true,
            'include_kecamatan_root_units_when_skpd_selected' => true,
            'exclude_kecamatan_root_units_when_kecamatan_selected' => true,
        ],
    ],

    'legacy' => [
        'jabatan_id_to_code' => [
            1 => 'ADMIN_SUPER',
            2 => 'BUD',
            3 => 'KUASA_BUD',
            4 => 'VERIFIKATOR_BUD',
            5 => 'PA',
            6 => 'KPA',
            7 => 'PPK_SKPD',
            8 => 'PPTK',
            9 => 'BP',
            10 => 'BPP',
            11 => 'BANK',
            12 => 'PIMPINAN',
            13 => 'AUDITOR',
        ],

        'instansi_key_to_code' => [
            'SKPD' => 'SKPD',
            'DIKBUD' => 'DIKBUD',
            'DINKES' => 'DINKES',
            'SETDA' => 'SETDA',
            'LOWOKWARU' => 'KEC_LOWOKWARU',
            'KLOJEN' => 'KEC_KLOJEN',
            'BLIMBING' => 'KEC_BLIMBING',
            'SUKUN' => 'KEC_SUKUN',
            'KEDUNGKANDANG' => 'KEC_KEDUNGKANDANG',
        ],

        'admin_super_names' => [
            'ADMIN SUPER',
            'SUPER ADMIN',
        ],

        'fixed_bkad_role_ids' => [
            2,
            3,
            4,
            11,
            12,
            13,
        ],

        'jabatan_name_to_code' => [
            'ADMIN SUPER' => 'ADMIN_SUPER',
            'SUPER ADMIN' => 'ADMIN_SUPER',
            'BUD' => 'BUD',
            'KUASA BUD' => 'KUASA_BUD',
            'VERIFIKATOR BUD' => 'VERIFIKATOR_BUD',
            'PA' => 'PA',
            'KPA' => 'KPA',
            'PPK-SKPD' => 'PPK_SKPD',
            'PPTK' => 'PPTK',
            'BP' => 'BP',
            'BPP' => 'BPP',
            'BANK' => 'BANK',
            'PIMPINAN' => 'PIMPINAN',
            'AUDITOR' => 'AUDITOR',
        ],

        'instansi_name_to_code' => [
            'SKPD' => 'SKPD',
            'DIKBUD' => 'DIKBUD',
            'DINKES' => 'DINKES',
            'SETDA' => 'SETDA',
            'KECAMATAN LOWOKWARU' => 'KEC_LOWOKWARU',
            'KECAMATAN KLOJEN' => 'KEC_KLOJEN',
            'KECAMATAN BLIMBING' => 'KEC_BLIMBING',
            'KECAMATAN SUKUN' => 'KEC_SUKUN',
            'KECAMATAN KEDUNGKANDANG' => 'KEC_KEDUNGKANDANG',
        ],

        'unit_kerja_name_to_code' => [
            'BKAD' => 'SKPD_BKAD',
            'KECAMATAN LOWOKWARU' => 'KEC_LOWOKWARU',
            'KECAMATAN KLOJEN' => 'KEC_KLOJEN',
            'KECAMATAN BLIMBING' => 'KEC_BLIMBING',
            'KECAMATAN SUKUN' => 'KEC_SUKUN',
            'KECAMATAN KEDUNGKANDANG' => 'KEC_KEDUNGKANDANG',
        ],

        'special_user_by_role_id' => [
            2 => 'bud',
            3 => 'bud',
            8 => 'pptk',
        ],

        'unit_scope_policy_by_role_id' => [
            2 => 'fixed_bkad',
            3 => 'fixed_bkad',
            4 => 'fixed_bkad',
            5 => 'skpd_plus_kecamatan_roots',
            6 => 'kelurahan_only_when_kecamatan',
            7 => 'skpd_plus_kecamatan_roots',
            8 => 'pptk_mixed_scope',
            9 => 'skpd_plus_kecamatan_roots',
            10 => 'kelurahan_only_when_kecamatan',
            11 => 'fixed_bkad',
            12 => 'fixed_bkad',
            13 => 'fixed_bkad',
        ],
    ],
];
