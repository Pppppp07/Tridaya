<?php

namespace App\Support\Historis;

use App\Enums\PosisiBerkas;
use App\Models\KategoriTemuan;
use App\Models\Laporan;
use App\Models\Referensi;
use App\Models\Rekomendasi;
use App\Models\Satker;
use App\Support\Aktivitas;
use Illuminate\Support\Facades\Validator;

class ReviewHistoris
{
    private ?array $daftarPilihan = null;

    public const LABEL = [
        'sumber' => 'Sumber laporan', 'nomor' => 'Nomor laporan', 'tgl_surat' => 'Tanggal surat',
        'tgl_terima' => 'Tanggal diterima', 'kode' => 'Ref IDT', 'ref_lhp' => 'Ref LHP',
        'nomor_temuan' => 'Nomor temuan pada laporan', 'judul' => 'Judul temuan', 'uraian' => 'Uraian rekomendasi', 'satker_id' => 'Satuan kerja',
        'kategori_id' => 'Kategori temuan', 'sifat_id' => 'Sifat rekomendasi', 'bentuk_id' => 'Bentuk tindak lanjut',
        'nilai' => 'Nilai penugasan Satker', 'nilai_itjen' => 'Nilai diakui Inspektorat',
        'nilai_bpk' => 'Nilai diakui BPK', 'hasil' => 'Hasil Inspektorat', 'hasil_uki' => 'Hasil UKI',
        'status_bpk' => 'Status SIPTL', 'posisi' => 'Posisi berkas', 'tanggal_acuan' => 'Tanggal acuan data',
        'siptl_tanggal' => 'Tanggal unggah SIPTL', 'renaksi' => 'Tenggat rencana aksi',
        'catatan' => 'Catatan', 'rencana_aksi' => 'Rencana aksi',
        'bentuk_teks' => 'Teks bentuk dari file', 'paket' => 'Keterangan paket',
        'klasifikasi_renaksi' => 'Klasifikasi rencana aksi', 'catatan_bpk' => 'Catatan BPK', 'chv' => 'Nomor CHV',
        'tgl_lhv' => 'Tanggal LHV', 'uraian_verifikasi' => 'Uraian verifikasi',
    ];

    public function pilihan(): array
    {
        return $this->daftarPilihan ??= [
            'satker' => Satker::where('aktif', true)->orderBy('nama')->get(),
            'kategori' => KategoriTemuan::where('aktif', true)->orderBy('nama')->get(),
            'sifat' => Referensi::where('jenis', 'sifat_rekom')->where('aktif', true)->orderBy('nama')->get(),
            'bentuk' => Referensi::where('jenis', 'bentuk_tl')->where('aktif', true)->orderBy('nama')->get(),
        ];
    }

    public function awal(array $isian): array
    {
        /* Baris yang isinya sama persis (selain nomor urut dan kolom bantu) diimpor sekali; nomor
           baris kembarnya dicatat supaya tetap terlihat di review. */
        $sidik = [];
        $baris = [];
        foreach ($isian['baris'] as $row) {
            $isi = array_filter(BacaExcel::kunci($row['asli']), fn ($k) => ! preg_match(BacaExcel::KOLOM_BANTU, $k), ARRAY_FILTER_USE_KEY);
            $kunci = $row['sheet'].'|'.md5(json_encode($isi, JSON_UNESCAPED_UNICODE));
            if (isset($sidik[$kunci])) {
                $baris[$sidik[$kunci]]['kembar'][] = $row['baris'];

                continue;
            }
            $sidik[$kunci] = count($baris);
            $baris[] = $row + ['kembar' => []];
        }
        $isian['baris'] = $baris;

        /* Nomor temuan = awalan Ref LHP yang sama pada semua rekomendasi satu judul temuan di laporan
           yang sama (A.2.10.a dan A.2.10.b.1 → A.2.10). Satu rekomendasi saja: bawaan BacaExcel. */
        $temuan = [];
        foreach ($isian['baris'] as $i => $row) {
            $temuan[mb_strtolower($row['data']['nomor']).'|'.mb_strtolower($row['data']['judul'])][] = $i;
        }
        foreach ($temuan as $anggota) {
            $ref = array_values(array_unique(array_filter(array_map(
                fn ($i) => rtrim(trim($isian['baris'][$i]['data']['ref_lhp']), '.) '), $anggota))));
            if (count($ref) < 2) {
                continue;
            }
            $awalan = explode('.', array_shift($ref));
            foreach ($ref as $r) {
                $bagian = explode('.', $r);
                $n = 0;
                while (isset($awalan[$n], $bagian[$n]) && $awalan[$n] === $bagian[$n]) {
                    $n++;
                }
                $awalan = array_slice($awalan, 0, $n);
            }
            if ($awalan) {
                foreach ($anggota as $i) {
                    $isian['baris'][$i]['data']['nomor_temuan'] = implode('.', $awalan);
                }
            }
        }

        /* Tanggal yang tidak tertulis di kolom No LHP kadang ada di teks lain pada file yang sama:
           "LHPDTT BPK RI No.9/LHP/XVII/03/2024 tanggal 7 Maret 2024". Tidak ada = tetap kosong. */
        $tanpaTanggal = array_unique(array_map(fn ($r) => $r['data']['nomor'],
            array_filter($isian['baris'], fn ($r) => $r['data']['tgl_surat'] === '' && $r['data']['nomor'] !== '')));
        if ($tanpaTanggal) {
            $teks = implode("\n", array_map(fn ($r) => implode("\n", [$r['data']['uraian_verifikasi'] ?? '',
                $r['data']['catatan_bpk'] ?? '', $r['data']['catatan'] ?? '']), $isian['baris']));
            foreach ($tanpaTanggal as $nomor) {
                $pola = '/(?:No\.?|Nomor)\s*'.preg_quote($nomor, '/').'\s*,?\s*(?:tanggal|tgl\.?)\s+(\d{1,2}\s+\pL+\.?\s+\d{4})/iu';
                if (! preg_match($pola, $teks, $m) || ($tgl = BacaExcel::tanggalIndonesia($m[1])) === '') {
                    continue;
                }
                foreach ($isian['baris'] as &$row) {
                    if ($row['data']['nomor'] === $nomor && $row['data']['tgl_surat'] === '') {
                        $row['data']['tgl_surat'] = $row['data']['tgl_terima'] = $tgl;
                        $row['aturan']['tgl_surat'] = $row['aturan']['tgl_terima']
                            = 'Kolom No LHP tidak bertanggal; tanggal diambil dari teks di file: "'.trim($m[0]).'".';
                    }
                }
                unset($row);
            }
        }

        $p = $this->pilihan();
        /* "Surat teguran/sanksi" dan "Surat teguran atau sanksi" dianggap sama. */
        $baku = fn ($s) => trim(preg_replace('/\s+/u', ' ', str_replace('/', ' atau ', mb_strtolower((string) $s))));
        $cocok = fn ($daftar, $nama) => $daftar->first(fn ($m) => $baku($m->nama) === $baku($nama))?->id ?? '';
        foreach ($isian['baris'] as &$row) {
            $a = BacaExcel::kunci($row['asli']);
            $d = &$row['data'];
            $d['satker_id'] = (string) ($p['satker']->first(fn ($s) => $d['kode_satker'] !== '' && (string) $s->kode_satker === $d['kode_satker'])?->id ?? '');
            // Kategori dicocokkan dengan daftar milik sumber laporannya (LHP atau LHA).
            $d['kategori_id'] = (string) $cocok($p['kategori']->filter(fn ($k) => $k->sumber->value === $d['sumber']), $a['jenis temuan'] ?? '');
            $d['sifat_id'] = (string) $cocok($p['sifat'], $a['sifat rekomendasi'] ?? '');
            $d['bentuk_id'] = (string) $cocok($p['bentuk'], $a['bentuk tindak lanjut'] ?? '');
            unset($d);
            // Isian otomatis disimpan terpisah: review menandai kolom yang kemudian diubah tangan.
            $row['otomatis'] = $row['data'];
        }
        unset($row);

        return $isian;
    }

    /** Semua query dibuat per kelompok, bukan satu query untuk setiap baris Excel. */
    public function periksa(array $isian): array
    {
        $p = $this->pilihan();
        $rows = $isian['baris'];
        foreach ($rows as &$row) {
            foreach (['renaksi', 'siptl_tanggal'] as $k) {
                if (in_array(mb_strtoupper($row['data'][$k]), ['-', '—', 'N/A'], true)) {
                    $row['data'][$k] = '';
                }
            }
        }
        unset($row);
        $rek = Rekomendasi::whereIn('kode', array_column(array_column($rows, 'data'), 'kode'))->get()->keyBy(fn ($r) => mb_strtolower($r->kode));
        $lap = Laporan::whereIn('nomor', array_column(array_column($rows, 'data'), 'nomor'))->get()->groupBy(fn ($l) => mb_strtolower($l->nomor));
        $identitas = [];
        $kelompok = [];
        $lapKelompok = [];
        foreach ($rows as $i => $row) {
            if (! $row['ikut']) {
                continue;
            }
            $d = $row['data'];
            $identitas[mb_strtolower($d['kode']).'|'.$d['satker_id'].'|'.$d['bentuk_id']][] = $i;
            $kelompok[mb_strtolower($d['kode'])][] = $i;
            $lapKelompok[mb_strtolower($d['nomor'])][] = $i;
        }
        $tglChv = BacaExcel::tanggalChv(array_map(fn ($r) => $r['data']['uraian_verifikasi'] ?? '', $rows));
        foreach ($rows as $i => &$row) {
            $d = $row['data'];
            $tanggalKini = Aktivitas::waktuKini()->toDateString();
            $rules = [
                'sumber' => 'required|in:LHP,LHA', 'nomor' => 'required|string|max:255',
                'tgl_surat' => 'required|date_format:Y-m-d|before_or_equal:'.$tanggalKini,
                /* Tanggal pembanding yang masih kosong cukup dilaporkan "perlu diisi" sendiri. */
                'tgl_terima' => 'required|date_format:Y-m-d'.($d['tgl_surat'] !== '' ? '|after_or_equal:tgl_surat' : '').'|before_or_equal:'.$tanggalKini,
                'kode' => ['required', 'string', 'max:40', 'regex:/^[\pL\pN._\/-]+$/u'],
                'ref_lhp' => 'required|string|max:40', 'nomor_temuan' => 'required|string|max:30', 'judul' => 'required|string|max:500',
                'uraian' => 'required|string|max:20000', 'satker_id' => 'required',
                /* Bentuk yang tidak ada di daftar tetap diimpor bersama teks aslinya. */
                'bentuk_id' => 'nullable', 'sifat_id' => 'required', 'kategori_id' => 'nullable',
                'hasil' => 'nullable|in:M,BM', 'hasil_uki' => 'nullable|in:M,BM', 'status_bpk' => 'nullable|in:BT,BS,SS',
                'posisi' => 'required|in:'.implode(',', array_column(PosisiBerkas::cases(), 'value')),
                'tanggal_acuan' => 'required|date_format:Y-m-d'.($d['tgl_terima'] !== '' ? '|after_or_equal:tgl_terima' : '').'|before_or_equal:'.$tanggalKini,
                'siptl_tanggal' => 'nullable|date_format:Y-m-d'.($d['tgl_surat'] !== '' ? '|after_or_equal:tgl_surat' : '').'|before_or_equal:tanggal_acuan',
                'renaksi' => 'nullable|date_format:Y-m-d', 'catatan' => 'nullable|string|max:20000',
                'rencana_aksi' => 'nullable|string|max:20000',
                'bentuk_teks' => 'nullable|string|max:500', 'paket' => 'nullable|string|max:500',
                'klasifikasi_renaksi' => 'nullable|string|max:20', 'catatan_bpk' => 'nullable|string|max:20000',
                'chv' => 'nullable|string|max:500', 'tgl_lhv' => 'nullable|string|max:500', 'uraian_verifikasi' => 'nullable|string|max:20000',
            ];
            foreach (['nilai', 'nilai_itjen', 'nilai_bpk'] as $k) {
                $rules[$k] = ['required', 'regex:/^\d{1,15}(?:\.0+)?$/D'];
            }
            $v = Validator::make($d, $rules, [
                'required' => ':attribute perlu diisi.', 'date_format' => ':attribute harus berupa tanggal yang valid.',
                'before_or_equal' => ':attribute melewati tanggal batas.', 'after_or_equal' => ':attribute mendahului tanggal sebelumnya.',
                'max' => ':attribute terlalu panjang (maksimal :max karakter).', 'in' => ':attribute belum sesuai pilihan sistem.',
                'kode.regex' => ':attribute hanya boleh berisi huruf, angka, titik, garis miring, garis bawah, atau tanda hubung.',
                'regex' => ':attribute harus rupiah bulat tanpa pemisah ribuan.',
            ], self::LABEL);
            $errors = $v->errors()->all();
            $errorsKolom = $v->errors()->toArray();
            foreach (['satker_id' => 'satker', 'sifat_id' => 'sifat', 'bentuk_id' => 'bentuk', 'kategori_id' => 'kategori'] as $k => $jenis) {
                if ($d[$k] !== '' && ! $p[$jenis]->contains('id', $d[$k])) {
                    $errors[] = $errorsKolom[$k][] = self::LABEL[$k].' sudah tidak aktif atau tidak terdaftar.';
                }
            }
            if ($d['kategori_id'] !== '' && ($kat = $p['kategori']->firstWhere('id', $d['kategori_id'])) && $kat->sumber->value !== $d['sumber']) {
                $errors[] = 'Kategori temuan tidak sesuai sumber laporan.';
            }
            $n = (int) $d['nilai'];
            $itjen = (int) $d['nilai_itjen'];
            $bpk = (int) $d['nilai_bpk'];
            if ($itjen > $n || $bpk > $n) {
                $errors[] = 'Nilai diakui tidak boleh melebihi nilai penugasan Satker.';
            }
            if ($d['hasil'] === 'M' && $itjen !== $n) {
                $errors[] = 'Hasil Memadai harus sesuai nilai penugasan yang sudah diakui seluruhnya.';
            }
            if ($d['hasil'] === '' && $itjen > 0) {
                $errors[] = 'Nilai diakui Inspektorat memerlukan hasil verifikasi.';
            }
            if ($d['status_bpk'] === 'SS' && $bpk !== $n) {
                $errors[] = 'Status SS harus sesuai nilai penugasan yang sudah diakui seluruhnya.';
            }
            if ($d['status_bpk'] === '' && $bpk > 0) {
                $errors[] = 'Nilai diakui BPK memerlukan status SIPTL.';
            }
            if ($d['hasil'] === 'M' && $d['posisi'] !== 'tuntas') {
                $errors[] = 'Hasil Memadai memerlukan posisi Selesai diperiksa.';
            }
            if ($d['posisi'] === 'tuntas' && $d['hasil'] !== 'M') {
                $errors[] = 'Posisi Selesai diperiksa memerlukan hasil Inspektorat Memadai.';
            }
            if ($d['sumber'] === 'LHA' && ($d['status_bpk'] !== '' || $d['siptl_tanggal'] !== '' || $bpk > 0)) {
                $errors[] = 'LHA tidak menggunakan SIPTL/BPK.';
            }
            if ($d['siptl_tanggal'] !== '' && $d['status_bpk'] === '') {
                $errors[] = 'Tanggal unggah SIPTL memerlukan status SIPTL.';
            }
            $duplikat = $rek->get(mb_strtolower($d['kode']));
            if ($duplikat) {
                $errors[] = 'Ref IDT sudah ada di database. Keluarkan baris ini dari impor; data yang sudah ada tidak ditimpa.';
            }
            $lapCocok = $lap->get(mb_strtolower($d['nomor']), collect());
            if ($lapCocok->count() > 1) {
                $errors[] = 'Nomor laporan cocok dengan beberapa laporan. Periksa identitasnya terlebih dahulu.';
            }
            if (($l = $lapCocok->first()) && ($l->sumber->value !== $d['sumber'] || $l->tgl_surat->format('Y-m-d') !== $d['tgl_surat'] || $l->tgl_terima->format('Y-m-d') !== $d['tgl_terima'])) {
                $errors[] = 'Sumber atau tanggal laporan berbeda dari laporan dengan nomor yang sama di database.';
            }
            $id = mb_strtolower($d['kode']).'|'.$d['satker_id'].'|'.$d['bentuk_id'];
            $berulang = $row['ikut'] && count($identitas[$id] ?? []) > 1;
            if ($row['ikut']) {
                foreach ($kelompok[mb_strtolower($d['kode'])] ?? [] as $j) {
                    foreach (['nomor', 'sumber', 'tgl_surat', 'tgl_terima', 'ref_lhp', 'nomor_temuan', 'judul', 'uraian', 'sifat_id', 'kategori_id'] as $k) {
                        if ($rows[$j]['data'][$k] !== $d[$k]) {
                            $errors[] = 'Identitas atau uraian untuk Ref IDT yang sama tidak konsisten antarbaris.';
                            break 2;
                        }
                    }
                }
                foreach ($lapKelompok[mb_strtolower($d['nomor'])] ?? [] as $j) {
                    foreach (['sumber', 'tgl_surat', 'tgl_terima'] as $k) {
                        if ($rows[$j]['data'][$k] !== $d[$k]) {
                            $errors[] = 'Sumber atau tanggal untuk nomor laporan yang sama tidak konsisten antarbaris.';
                            break 2;
                        }
                    }
                    if ($rows[$j]['data']['nomor_temuan'] === $d['nomor_temuan'] && ($rows[$j]['data']['judul'] !== $d['judul'] || $rows[$j]['data']['kategori_id'] !== $d['kategori_id'])) {
                        $errors[] = 'Judul atau kategori untuk nomor temuan yang sama tidak konsisten.';
                    }
                }
            }
            $warnings = [];
            if ($row['kembar'] ?? []) {
                $warnings[] = 'Juga mewakili baris '.implode(', ', $row['kembar']).' yang isinya sama persis, jadi diimpor sekali.';
            }
            /* Yang sama persis sudah digabung saat upload; yang tersisa berbeda isinya. */
            if ($berulang) {
                $warnings[] = 'Ref IDT dan Satker ini juga ada di baris lain dengan isi berbeda. Masing-masing diimpor sebagai tindak lanjut terpisah.';
            }
            if ($d['status_bpk'] !== '' && $d['siptl_tanggal'] === '') {
                $warnings[] = 'Status SIPTL akan dicatat sebagai snapshot historis; tanggal unggah belum diketahui.';
            }
            if ($row['ikut'] && count($kelompok[mb_strtolower($d['kode'])] ?? []) > 1) {
                $warnings[] = 'Ref IDT dibagi ke beberapa penugasan. Pastikan nilai pada setiap baris adalah bagian Satker tersebut, bukan total rekomendasi yang diulang.';
            }
            /* Isian yang diubah aturan sistem saat upload; tidak berlaku lagi bila sudah dikoreksi. */
            foreach ($row['aturan'] ?? [] as $k => $teks) {
                if ($k !== 'tgl_terima' && ($row['otomatis'][$k] ?? null) === ($d[$k] ?? null)) {
                    $warnings[] = self::LABEL[$k].': '.$teks;
                }
            }
            if (($d['bentuk_id'] ?? '') === '') {
                $warnings[] = ($d['bentuk_teks'] ?? '') === ''
                    ? 'Bentuk tindak lanjut kosong di file. Bentuknya bisa dipilih nanti di aplikasi.'
                    : 'Bentuk tindak lanjut "'.$d['bentuk_teks'].'" tidak ada di daftar Data Master; teksnya disimpan apa adanya.';
            }
            $surat = BacaExcel::suratChv($d['chv'] ?? '', $d['tgl_lhv'] ?? '', $tglChv);
            if ($surat && (end($surat)['tanggal'] === '' || $d['hasil'] === '')) {
                $warnings[] = 'Nomor CHV tanpa tanggal atau tanpa hasil Inspektorat hanya dicatat di uraian verifikasi.';
            } elseif ($surat && ($d['tgl_lhv'] ?? '') !== '' && count(BacaExcel::daftarTanggal($d['tgl_lhv'])) !== count($surat)) {
                $warnings[] = 'Jumlah nomor CHV dan tanggal LHV berbeda; tanggalnya dipasangkan berurutan.';
            }
            $a = BacaExcel::kunci($row['asli']);
            if (isset($a['nilai tptd (idr)']) && $a['nilai tptd (idr)'] !== '' && (is_numeric($a['nilai tptd (idr)']) ? (float) $a['nilai tptd (idr)'] > 0 : true)) {
                $errors[] = 'Nilai TPTD pada sumber belum didukung alur saat ini. Keluarkan baris ini dari impor sampai pemetaannya ditentukan.';
            }
            foreach (['sisa nilai (idr)' => $n - $bpk, 'sisa nilai belum memadai (idr)' => $n - $itjen] as $k => $sisa) {
                if (isset($a[$k]) && preg_match('/^\d+(?:\.0+)?$/D', $a[$k]) && (int) $a[$k] !== $sisa) {
                    $warnings[] = 'Sisa nilai pada Excel berbeda dari nilai hasil review. Pastikan koreksinya benar.';
                }
            }
            $row['errors'] = array_values(array_unique($errors));
            $row['errors_kolom'] = $errorsKolom;
            $row['warnings'] = array_values(array_unique($warnings));
            $row['duplikat_id'] = $duplikat?->id;
            $unit = $p['satker']->firstWhere('id', $d['satker_id']);
            $row['satker_nama'] = $unit?->namaPendek();
            $row['satker_kode'] = $unit?->kode_satker;
            $row['siap'] = $row['ikut'] && ! $row['errors'] && $row['diperiksa'];
        }

        return $rows;
    }
}
