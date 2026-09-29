<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nama di basis data mengikuti istilah tampilan (29 Sep 2026). Kata Hizkia:
 * "untuk penamaan kolom di database atau lainnya juga coba sesuaikan ulang
 * penamaannya" — lanjutan penggantian istilah 28 Sep (surel → email,
 * tautan → link, kabar → pemberitahuan/notifikasi, sembulan → pop-up).
 *
 * Kolom:
 *   - lampirans.tautan, surats.tautan  → link
 *   - users.kabar_dilihat_pada          → notifikasi_dilihat_pada (sejalan
 *     dengan tabel notifikasis, notifikasi_bacas, notifikasi_satker)
 *
 * Isi JSON yang tersimpan ikut, supaya kode baru membaca data lama:
 *   - users.pengaturan: sembul → popup, surel → email, gerak → animasi;
 *     halaman pertama "kabar" → "pemberitahuan"
 *   - draf_tanggapans.bukti/setoran dan draf_laporans.isian: tautan → link
 *   - log_aktivitas: kode aksi akun.sandi → akun.password,
 *     master.pengguna.tautkan → master.pengguna.hubungkan; kunci rincian
 *     surel/sembul/gerak → email/popup/animasi; cara masuk "sandi" → "password".
 *     Kalimat ringkasan yang sudah tercatat TIDAK diubah — itu catatan resmi.
 *
 * Migrasi lama (tambah_tautan_lampiran, tambah_kabar_dilihat, …) sengaja
 * tidak disentuh: namanya sudah tercatat di tabel migrations.
 */
return new class extends Migration
{
    private const KUNCI = ['sembul' => 'popup', 'surel' => 'email', 'gerak' => 'animasi'];

    private const AKSI = ['akun.sandi' => 'akun.password', 'master.pengguna.tautkan' => 'master.pengguna.hubungkan'];

    public function up(): void
    {
        Schema::table('lampirans', fn (Blueprint $t) => $t->renameColumn('tautan', 'link'));
        Schema::table('surats', fn (Blueprint $t) => $t->renameColumn('tautan', 'link'));
        Schema::table('users', fn (Blueprint $t) => $t->renameColumn('kabar_dilihat_pada', 'notifikasi_dilihat_pada'));

        $this->isi(self::KUNCI, ['tautan' => 'link'], self::AKSI, ['sandi' => 'password'], ['kabar' => 'pemberitahuan']);
    }

    public function down(): void
    {
        $this->isi(array_flip(self::KUNCI), ['link' => 'tautan'], array_flip(self::AKSI), ['password' => 'sandi'], ['pemberitahuan' => 'kabar']);

        Schema::table('users', fn (Blueprint $t) => $t->renameColumn('notifikasi_dilihat_pada', 'kabar_dilihat_pada'));
        Schema::table('surats', fn (Blueprint $t) => $t->renameColumn('link', 'tautan'));
        Schema::table('lampirans', fn (Blueprint $t) => $t->renameColumn('link', 'tautan'));
    }

    /** Ganti kunci dan nilai JSON yang tersimpan, satu arah. */
    private function isi(array $kunci, array $link, array $aksi, array $cara, array $beranda): void
    {
        $this->json('users', 'pengaturan', function (array $x) use ($kunci, $beranda) {
            $x = $this->kunci($x, $kunci);
            if (isset($x['beranda'], $beranda[$x['beranda']])) {
                $x['beranda'] = $beranda[$x['beranda']];
            }

            return $x;
        });
        foreach (['bukti', 'setoran'] as $kolom) {
            $this->json('draf_tanggapans', $kolom, fn (array $x) => $this->kunci($x, $link));
        }
        $this->json('draf_laporans', 'isian', fn (array $x) => $this->kunci($x, $link));

        foreach ($aksi as $lama => $baru) {
            DB::table('log_aktivitas')->where('aksi', $lama)->update(['aksi' => $baru]);
        }
        $this->json('log_aktivitas', 'rincian', function (array $x) use ($kunci, $cara) {
            $x = $this->kunci($x, $kunci);
            if (isset($x['cara']) && is_string($x['cara']) && isset($cara[$x['cara']])) {
                $x['cara'] = $cara[$x['cara']];
            }

            return $x;
        });
    }

    /** Baca-ubah-tulis satu kolom JSON; baris yang tidak berubah tidak ditulis. */
    private function json(string $tabel, string $kolom, callable $ubah): void
    {
        DB::table($tabel)->whereNotNull($kolom)->orderBy('id')->select(['id', $kolom])
            ->chunkById(200, function ($baris) use ($tabel, $kolom, $ubah) {
                foreach ($baris as $b) {
                    $x = json_decode((string) $b->{$kolom}, true);
                    if (! is_array($x)) {
                        continue;
                    }
                    $y = $ubah($x);
                    if ($y !== $x) {
                        DB::table($tabel)->where('id', $b->id)->update([$kolom => json_encode($y)]);
                    }
                }
            });
    }

    /** Ganti nama kunci di seluruh kedalaman larik. */
    private function kunci(array $x, array $peta): array
    {
        $hasil = [];
        foreach ($x as $k => $v) {
            $hasil[is_string($k) && isset($peta[$k]) ? $peta[$k] : $k] = is_array($v) ? $this->kunci($v, $peta) : $v;
        }

        return $hasil;
    }
};
