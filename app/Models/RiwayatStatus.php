<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu perubahan penilaian pada satu baris. `aksi_id` menyatukan butir satu putusan; urutan =
 * urutan pencatatan (`id`).
 */
class RiwayatStatus extends Model
{
    protected $table = 'riwayat_statuses';

    public const UKI = 'UKI';
    public const ITJEN = 'Itjen';
    public const SIPTL = 'SIPTL';

    /* `oleh` = sebutan peran; `oleh_id` = akun pencatat (nama orangnya di riwayat). */
    protected $fillable = ['sasaran_id', 'sumber', 'dari', 'ke', 'oleh', 'oleh_id', 'tanggal', 'catatan', 'aksi_id', 'jenis_aksi'];

    protected $casts = ['tanggal' => 'date'];

    public function sasaran() { return $this->belongsTo(Sasaran::class); }
    public function pelaku()  { return $this->belongsTo(User::class, 'oleh_id'); }
}
