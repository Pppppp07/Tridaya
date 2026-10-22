<?php

namespace App\Enums;

/* Pilihan yang bisa berubah mengikuti peraturan disimpan sebagai data master. */
enum JenisReferensi: string
{
    case BENTUK_TL       = 'bentuk_tl';
    case KATEGORI_INTERN = 'kategori_intern';
    /* Sifat rekomendasi: administratif atau kerugian negara — menentukan wajib setor. */
    case SIFAT_REKOM     = 'sifat_rekom';
}
