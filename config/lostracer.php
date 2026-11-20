<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Domain Email Resmi Kampus (PRV-06)
    |--------------------------------------------------------------------------
    | Daftar domain email institusi yang diizinkan untuk login & registrasi.
    */
    'allowed_email_domains' => array_values(array_filter(array_map(
        static fn (string $domain): string => strtolower(trim($domain)),
        explode(',', (string) env('ALLOWED_EMAIL_DOMAINS', 'student.uph.edu,uph.edu'))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Daftar Gedung Kampus UPH
    |--------------------------------------------------------------------------
    */
    'buildings' => [
        'Building A',
        'Building B',
        'Building C',
        'Building D',
        'Building F',
        'Perpustakaan UPH',
        'Food Junction',
        'Lainnya',
    ],

    /*
    |--------------------------------------------------------------------------
    | Titik Penitipan Awal (Pos Keamanan & Lobby) - CUS-01, CUS-02
    |--------------------------------------------------------------------------
    | Lokasi resmi tempat penemu menyerahkan barang fisik sebelum melapor.
    */
    'deposit_locations' => [
        'Pos Keamanan Utama (Main Gate)',
        'Pos Keamanan Gedung A',
        'Pos Keamanan Gedung B',
        'Pos Keamanan Gedung C / D',
        'Lobby Gedung A',
        'Lobby Gedung B',
        'Lobby Gedung C',
        'Lobby Gedung D / F',
    ],

    /*
    |--------------------------------------------------------------------------
    | Daftar Lengkap Titik Penyimpanan (Custody Points) - CUS-04, CUS-05
    |--------------------------------------------------------------------------
    | Mencakup titik penitipan awal dan rak gudang Lost & Found pusat.
    */
    'custody_locations' => [
        'Pos Keamanan Utama (Main Gate)',
        'Pos Keamanan Gedung A',
        'Pos Keamanan Gedung B',
        'Pos Keamanan Gedung C / D',
        'Lobby Gedung A',
        'Lobby Gedung B',
        'Lobby Gedung C',
        'Lobby Gedung D / F',
        'Gudang Lost & Found — Rak Priority/Recent (Bulan 1-3)',
        'Gudang Lost & Found — Rak Extended Shelf (Bulan 4-6)',
        'Gudang Lost & Found — Lemari Terkunci (Barang Bernilai / Dokumen)',
    ],

    'extended_shelf_location' => 'Gudang Lost & Found — Rak Extended Shelf (Bulan 4-6)',

    /*
    |--------------------------------------------------------------------------
    | Kebijakan Masa Simpan & Batas Klaim (CUS-05, CLM-07, CLM-09, FR-13)
    |--------------------------------------------------------------------------
    */
    'retention' => [
        'shelf_move_months' => 3,
        'expiration_months' => 6,
        'pickup_window_days' => 7,
        'max_claim_attempts' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Konstanta Aksi Audit Trail (HND-06, Section 6 Blueprint v2.1)
    |--------------------------------------------------------------------------
    */
    'audit_actions' => [
        'ITEM_MOVED',
        'ITEM_EDITED',
        'CLAIM_APPROVED',
        'CLAIM_REJECTED',
        'HANDOVER_COMPLETED',
        'POST_DELETED',
        'USER_BLOCKED',
        'USER_UNBLOCKED',
        'ITEM_EXPIRED',
        'ITEM_DISPOSED',
    ],
];
