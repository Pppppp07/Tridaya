<?php

namespace App\Support\Historis;

use App\Enums\PosisiBerkas;

/**
 * Kolom sistem ↔ kolom file untuk review berdampingan. Tiap kolom sistem pada tiap baris diberi
 * keadaan: terbaca, diisi aturan sistem, kosong di file, tidak ada di file, tidak terbaca, atau
 * diubah saat review.
 */
class PetaKolom
{
    /**
     * Kolom file (judul huruf kecil) asal tiap kolom sistem. `cara`: bila bukan salinan langsung.
     * `turunan`: diturunkan dengan aturan dari kolom lain, jadi tidak disebut "terbaca". `wajib`:
     * kosongnya menahan impor (sama dengan aturan ReviewHistoris::periksa).
     */
    public const KOLOM = [
        'sumber' => ['dari' => ['no lhp'], 'cara' => 'Dari nomor laporan: memuat "LHP" = LHP (BPK), memuat "LHA" = LHA (Inspektorat).', 'jenis' => 'sumber', 'turunan' => true, 'wajib' => true],
        'nomor' => ['dari' => ['no lhp'], 'cara' => 'Nomor di depan tanggal pada kolom No LHP.', 'wajib' => true],
        'tgl_surat' => ['dari' => ['no lhp'], 'cara' => 'Tanggal di belakang nomor pada kolom No LHP.', 'jenis' => 'tanggal', 'wajib' => true],
        'tgl_terima' => ['dari' => ['no lhp'], 'cara' => 'File tidak punya tanggal diterima; disamakan dengan tanggal surat.', 'jenis' => 'tanggal', 'turunan' => true, 'wajib' => true],
        'kode' => ['dari' => ['reff idt'], 'wajib' => true],
        'ref_lhp' => ['dari' => ['reff lhp'], 'wajib' => true],
        'nomor_temuan' => ['dari' => ['reff lhp'], 'cara' => 'Awalan Ref LHP yang sama untuk satu judul temuan; satu rekomendasi saja: Ref LHP tanpa segmen terakhir.', 'turunan' => true, 'wajib' => true],
        'judul' => ['dari' => ['judul temuan besar'], 'wajib' => true],
        'uraian' => ['dari' => ['rekomendasi temuan besar'], 'wajib' => true],
        'satker_id' => ['dari' => ['kode satker'], 'cara' => 'Dicocokkan dengan kode satker di Data Master.', 'jenis' => 'satker', 'wajib' => true],
        'kategori_id' => ['dari' => ['jenis temuan'], 'cara' => 'Dicocokkan dengan Kategori temuan di Data Master.', 'jenis' => 'kategori'],
        'sifat_id' => ['dari' => ['sifat rekomendasi'], 'cara' => 'Dicocokkan dengan Sifat rekomendasi di Data Master.', 'jenis' => 'sifat', 'wajib' => true],
        /* Teks dari file (`bentuk_teks`) bukan baris tersendiri: isiannya ada di bawah pilihan daftar ini. */
        'bentuk_id' => ['dari' => ['bentuk tindak lanjut'], 'cara' => 'Dicocokkan dengan Bentuk tindak lanjut di Data Master.', 'jenis' => 'bentuk'],
        'paket' => ['dari' => ['keterangan paket'], 'jenis' => 'bebas'],
        'nilai' => ['dari' => ['nilai rekomendasi (idr)'], 'cara' => 'Rupiah bulat; sen dibulatkan.', 'jenis' => 'rupiah', 'wajib' => true],
        'nilai_itjen' => ['dari' => ['nilai memadai (idr)'], 'cara' => 'Rupiah bulat; sen dibulatkan.', 'jenis' => 'rupiah', 'wajib' => true],
        'nilai_bpk' => ['dari' => ['nilai ss (idr)'], 'cara' => 'Rupiah bulat; sen dibulatkan.', 'jenis' => 'rupiah', 'wajib' => true],
        'hasil' => ['dari' => ['status verifikasi'], 'jenis' => 'hasil'],
        'chv' => ['dari' => ['nomor chv'], 'cara' => 'Disimpan sebagai surat verifikasi Inspektorat (CHV) dan tampil di Progres.', 'jenis' => 'bebas'],
        'tgl_lhv' => ['dari' => ['tanggal surat pengantar (lhv)'], 'cara' => 'Tanggal LHV ke-n dipasangkan dengan nomor CHV ke-n.', 'jenis' => 'tanggal_lhv'],
        'uraian_verifikasi' => ['dari' => ['uraian verifikasi'], 'jenis' => 'bebas'],
        'hasil_uki' => ['dari' => [], 'jenis' => 'hasil'],
        'status_bpk' => ['dari' => ['status siptl'], 'jenis' => 'bpk'],
        'catatan_bpk' => ['dari' => ['hasil telaah bpk ri', 'keterangan new siptl'], 'cara' => 'Tampil sebagai catatan BPK di kartu SIPTL.', 'jenis' => 'bebas'],
        'posisi' => ['dari' => ['status/posisi dokumen tl'], 'cara' => 'Selesai dan Diproses SiPTL dianggap sudah selesai diperiksa Inspektorat.', 'jenis' => 'posisi', 'wajib' => true],
        'tanggal_acuan' => ['dari' => [], 'cara' => 'Tanggal file terakhir disimpan; bisa diubah lewat "Isi tanggal bersama".', 'jenis' => 'tanggal', 'wajib' => true],
        'siptl_tanggal' => ['dari' => [], 'jenis' => 'tanggal'],
        'renaksi' => ['dari' => ['due date renaksi'], 'jenis' => 'tanggal'],
        'klasifikasi_renaksi' => ['dari' => ['klasifikasi renaksi'], 'jenis' => 'bebas'],
        'rencana_aksi' => ['dari' => ['rencana aksi']],
        'catatan' => ['dari' => ['catatan belum memadai']],
    ];

    /* Kolom file yang tidak disimpan sendiri, beserta alasannya. Kolom yang tidak dipakai sama
       sekali sudah dibuang saat file dibaca (BacaExcel::KOLOM_TIDAK_DIPAKAI). */
    public const DIABAIKAN = [
        'tahun lhp' => 'Dihitung dari tanggal LHP.',
        'unit organisasi' => 'Selalu BPSDM.',
        'satker baru' => 'Nama satker; pencocokan memakai Kode Satker.',
        'nilai tptd (idr)' => 'BPSDM tidak memakai status TD; isi di atas nol menahan impor.',
        'sisa nilai (idr)' => 'Dihitung aplikasi; hanya dipakai mencocokkan sisa nilai SIPTL.',
        'sisa nilai belum memadai (idr)' => 'Dihitung aplikasi; hanya dipakai mencocokkan sisa nilai Inspektorat.',
        'status rekomendasi unor' => 'Turunan hasil Inspektorat; dihitung aplikasi.',
        'status tindak lanjut satker' => 'Turunan hasil Inspektorat; dihitung aplikasi.',
        'nilai tl per 18 des 2023' => 'Nilai pada satu tanggal lampau; tidak disimpan.',
    ];

    public const KEADAAN = [
        'terbaca' => ['nama' => 'Terbaca', 'nada' => 'ok'],
        'aturan' => ['nama' => 'Diisi aturan sistem', 'nada' => 'info'],
        'diubah' => ['nama' => 'Diubah saat review', 'nada' => 'info'],
        'kosong' => ['nama' => 'Kosong di file', 'nada' => ''],
        'tidak_ada' => ['nama' => 'Tidak ada di file', 'nada' => ''],
        'tidak_terbaca' => ['nama' => 'Tidak terbaca', 'nada' => 'warn'],
    ];

    private const TIDAK_COCOK = [
        'satker' => 'Kode satker ini tidak ada di Data Master.',
        'kategori' => 'Tidak sama dengan daftar Kategori temuan di Data Master.',
        'sifat' => 'Tidak sama dengan daftar Sifat rekomendasi di Data Master.',
        'bentuk' => 'Tidak sama dengan daftar Bentuk tindak lanjut di Data Master; teksnya disimpan apa adanya.',
    ];

    private static function judul(string $k): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $k)));
    }

    /** Keadaan tiap kolom sistem pada satu baris, beserta isi kolom file asalnya. */
    public static function bandingkan(array $row, array $pilihan): array
    {
        $file = [];
        foreach ($row['asli'] as $k => $v) {
            $file[self::judul((string) $k)] = ['judul' => (string) $k, 'isi' => trim((string) $v)];
        }
        $hasil = [];
        foreach (self::KOLOM as $kolom => $peta) {
            $nilai = trim((string) ($row['data'][$kolom] ?? ''));
            $asal = array_values(array_filter(array_map(fn ($d) => $file[$d] ?? null, $peta['dari'])));
            $adaIsi = (bool) array_filter($asal, fn ($s) => $s['isi'] !== '');
            $alasan = self::alasan($kolom, $peta['jenis'] ?? 'teks', $nilai, $pilihan);
            $otomatis = $row['otomatis'][$kolom] ?? null;
            /* Catatan aturan saat upload, mis. tanggal LHP yang diambil dari teks lain. */
            $aturan = $row['aturan'][$kolom] ?? '';
            $keadaan = match (true) {
                $otomatis !== null && trim((string) $otomatis) !== $nilai => 'diubah',
                $aturan !== '' && $nilai !== '' => 'aturan',
                ! $asal => $nilai !== '' ? 'aturan' : 'tidak_ada',
                ! $adaIsi => $nilai !== '' ? 'aturan' : 'kosong',
                $alasan !== '' => 'tidak_terbaca',
                ! empty($peta['turunan']) => 'aturan',
                default => 'terbaca',
            };
            $hasil[$kolom] = ['label' => ReviewHistoris::LABEL[$kolom], 'asal' => $asal, 'nilai' => $nilai,
                'keadaan' => $keadaan, 'alasan' => $keadaan === 'tidak_terbaca' ? $alasan : '',
                'cara' => $keadaan === 'aturan' && $aturan !== '' ? $aturan : ($peta['cara'] ?? ''), 'wajib' => ! empty($peta['wajib'])];
        }

        return $hasil;
    }

    /** Kolom bermasalah pada satu baris: tidak terbaca, atau kolom wajib yang kosong. */
    public static function masalah(array $banding): array
    {
        return array_filter($banding, fn ($x) => $x['keadaan'] === 'tidak_terbaca'
            || ($x['wajib'] && in_array($x['keadaan'], ['kosong', 'tidak_ada'], true)));
    }

    /** Kolom file pada satu baris yang tidak dipakai kolom sistem mana pun (tanpa kolom bantu rumus). */
    public static function kolomLain(array $row): array
    {
        $dipakai = array_merge(...array_column(self::KOLOM, 'dari'));
        $lain = [];
        foreach ($row['asli'] as $k => $v) {
            $kk = self::judul((string) $k);
            if (! in_array($kk, $dipakai, true) && ! preg_match(BacaExcel::KOLOM_BANTU, $kk)) {
                $lain[] = ['judul' => (string) $k, 'isi' => trim((string) $v), 'ket' => self::DIABAIKAN[$kk] ?? ''];
            }
        }

        return $lain;
    }

    /**
     * Bahan diskusi pemetaan: jumlah baris per keadaan untuk tiap kolom sistem, dan kolom file yang
     * belum dipakai sistem beserta berapa baris yang terisi.
     */
    public static function ringkas(array $banding, array $rows): array
    {
        $judulAsli = [];
        foreach ($rows as $row) {
            foreach (array_keys($row['asli']) as $k) {
                $judulAsli[self::judul((string) $k)] ??= (string) $k;
            }
        }
        $kolom = [];
        foreach (self::KOLOM as $k => $peta) {
            $kolom[$k] = ['label' => ReviewHistoris::LABEL[$k], 'cara' => $peta['cara'] ?? '', 'wajib' => ! empty($peta['wajib']),
                'dari' => array_values(array_filter(array_map(fn ($d) => $judulAsli[$d] ?? null, $peta['dari']))),
                'jumlah' => array_fill_keys(array_keys(self::KEADAAN), 0)];
        }
        foreach ($banding as $b) {
            foreach ($b as $k => $x) {
                $kolom[$k]['jumlah'][$x['keadaan']]++;
            }
        }
        $lain = [];
        foreach ($rows as $row) {
            foreach (self::kolomLain($row) as $x) {
                $lain[$x['judul']] ??= ['judul' => $x['judul'], 'terisi' => 0, 'ket' => $x['ket']];
                $lain[$x['judul']]['terisi'] += $x['isi'] !== '' ? 1 : 0;
            }
        }

        return ['kolom' => $kolom, 'lain' => array_values($lain)];
    }

    /** Mengapa isian sistem belum sah; '' bila sudah. */
    private static function alasan(string $kolom, string $jenis, string $nilai, array $pilihan): string
    {
        $tanggalSah = fn ($v) => preg_match('/^(\d{4})-(\d{2})-(\d{2})$/D', $v, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);

        return match ($jenis) {
            'tanggal' => $nilai === '' ? (in_array($kolom, ['tgl_surat', 'tgl_terima'], true) ? 'Tidak ada tanggal di kolom No LHP.' : 'Tanggal tidak terbaca.')
                : ($tanggalSah($nilai) ? '' : 'Format tanggal tidak dikenal.'),
            'rupiah' => preg_match('/^\d{1,15}$/D', $nilai) ? '' : 'Bukan angka rupiah.',
            'hasil' => in_array($nilai, ['M', 'BM'], true) ? '' : 'Isian tidak dikenal.',
            'bpk' => in_array($nilai, ['SS', 'BS', 'BT'], true) ? '' : 'Isian tidak dikenal.',
            'posisi' => in_array($nilai, array_column(PosisiBerkas::cases(), 'value'), true) ? '' : 'Isian tidak dikenal.',
            'sumber' => in_array($nilai, ['LHP', 'LHA'], true) ? '' : 'Isian tidak dikenal.',
            'satker', 'kategori', 'sifat', 'bentuk' => $nilai !== '' && $pilihan[$jenis]->contains('id', (int) $nilai) ? '' : self::TIDAK_COCOK[$jenis],
            'tanggal_lhv' => $nilai === '' || BacaExcel::daftarTanggal($nilai) ? '' : 'Tanggal tidak terbaca.',
            /* Teks pelengkap: boleh kosong, isinya disimpan apa adanya. */
            'bebas' => '',
            default => $nilai !== '' ? '' : 'Kosong.',
        };
    }
}
