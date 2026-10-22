<?php

namespace App\Support\Historis;

use DOMDocument;
use DOMXPath;
use PharData;
use RecursiveIteratorIterator;

/**
 * Pembaca lembar pemantauan Setba: XLSX (hanya nilai cache, tanpa menjalankan formula atau membuka
 * link) atau lembar yang sama disimpan sebagai CSV.
 */
class BacaExcel
{
    public const MAKS_BARIS = 500;

    /* Kolom yang tidak ikut menentukan "isi sama persis": nomor urut dan kolom bantu rumus. */
    public const KOLOM_BANTU = '/^(?:no|flag .+|rank .+|max rank .+|key .+)$/u';

    /* Tidak dipakai sistem, jadi dibuang sejak dibaca dan tidak pernah tersimpan di draft.
       Pejabat Terkait berisi nama orang. */
    public const KOLOM_TIDAK_DIPAKAI = ['Pejabat Terkait', 'Satker Lama', 'Tahun IHPS', 'Jenis LHP', 'Balai', 'Provinsi',
        'Status Verifikasi Olah Satker', 'Status Verifikasi Olah Unor', 'Nomor Permohonan Verifikasi'];

    private const BULAN = ['januari' => 1, 'jan' => 1, 'februari' => 2, 'feb' => 2, 'maret' => 3, 'mar' => 3,
        'april' => 4, 'apr' => 4, 'mei' => 5, 'juni' => 6, 'jun' => 6, 'juli' => 7, 'jul' => 7, 'agustus' => 8,
        'agu' => 8, 'agt' => 8, 'september' => 9, 'sep' => 9, 'oktober' => 10, 'okt' => 10, 'november' => 11,
        'nov' => 11, 'desember' => 12, 'des' => 12];

    private PharData $zip;

    private bool $tahun1904 = false;

    /* CSV menyimpan angka dan tanggal sebagai teks berformat ("87.295.888,19", "29/07/2021"). */
    private bool $csv = false;

    /* Tanggal terakhir file disimpan: bawaan "tanggal acuan data". CSV tidak menyimpannya. */
    private string $tanggalBerkas = '';

    /** `$jenis`: 'xlsx' atau 'csv'. */
    public function baca(string $path, string $jenis = 'xlsx'): array
    {
        $this->csv = $jenis === 'csv';
        $this->tanggalBerkas = '';
        $hasil = [];
        $cocok = false;
        $buang = array_map(fn ($k) => self::judulKecil($k), self::KOLOM_TIDAK_DIPAKAI);
        foreach ($this->csv ? $this->lembarCsv($path) : $this->lembarXlsx($path) as $nama => $daftar) {
            $kolom = null;
            foreach ($daftar as [$nomor, $sel]) {
                if ($kolom === null && $nomor <= 30) {
                    $kandidat = array_map(fn ($s) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $s))), $sel);
                    if (in_array('no lhp', $kandidat, true) && in_array('reff idt', $kandidat, true)
                        && in_array('rekomendasi temuan besar', $kandidat, true)) {
                        $kolom = $sel;
                        $bernama = array_filter($kandidat, fn ($s) => $s !== '');
                        if (count(array_unique($bernama)) !== count($bernama)) {
                            throw new ExcelTidakValid('Ada judul kolom yang sama. Bedakan judul kolom sebelum upload.');
                        }
                        $cocok = true;
                    }

                    continue;
                }
                if ($kolom === null) {
                    continue;
                }
                $asli = [];
                foreach ($kolom as $h => $judul) {
                    if ($judul !== '' && ! in_array(self::judulKecil($judul), $buang, true)) {
                        $asli[$judul] = $sel[$h] ?? '';
                    }
                }
                $kecil = self::kunci($asli);
                // Sisa tabel sering hanya berisi formula bantu. Tidak dijadikan penugasan kosong.
                if (trim(($kecil['no lhp'] ?? '').($kecil['judul temuan besar'] ?? '').($kecil['rekomendasi temuan besar'] ?? '')) === '') {
                    continue;
                }
                if (count($hasil) >= self::MAKS_BARIS) {
                    throw new ExcelTidakValid('Maksimal 500 baris data per upload. Pisahkan file terlebih dahulu.');
                }
                $data = $this->petakan($kecil);
                $aturan = $data['_aturan'];
                unset($data['_aturan']);
                $hasil[] = ['sheet' => (string) $nama, 'baris' => $nomor, 'asli' => $asli,
                    'data' => $data, 'aturan' => $aturan, 'diperiksa' => false, 'ikut' => true];
            }
        }
        if (! $cocok || ! $hasil) {
            throw new ExcelTidakValid('Data tidak terbaca. Gunakan kolom No LHP, Reff IDT, dan Rekomendasi Temuan Besar seperti lembar pemantauan SETBA.');
        }

        return ['baris' => $hasil, 'hasil' => []];
    }

    /** Baris tiap sheet: nama sheet → [[nomor baris, [huruf kolom → isi]], …]. */
    private function lembarXlsx(string $path): array
    {
        if (filesize($path) > 2 * 1024 * 1024 || file_get_contents($path, false, null, 0, 4) !== "PK\x03\x04") {
            throw new ExcelTidakValid('Gunakan file Excel .xlsx, maksimal 2 MB.');
        }
        $this->zip = new PharData($path);
        $ukuran = 0;
        $n = 0;
        foreach (new RecursiveIteratorIterator($this->zip) as $file) {
            $ukuran += $file->getSize();
            if (++$n > 256 || $file->getSize() > 4 * 1024 * 1024 || $ukuran > 16 * 1024 * 1024) {
                throw new ExcelTidakValid('Isi Excel terlalu besar. Pisahkan data ke beberapa file.');
            }
            if (preg_match('~(?:vbaProject|externalLinks|embeddings)/?~i', $file->getPathname())) {
                throw new ExcelTidakValid('Gunakan XLSX tanpa macro, objek tertanam, atau referensi workbook eksternal.');
            }
        }
        $workbook = $this->xml('xl/workbook.xml');
        $this->tahun1904 = (bool) $workbook->query('//*[local-name()="workbookPr"][@date1904="1" or @date1904="true"]')->length;
        $hubungan = [];
        foreach ($this->xml('xl/_rels/workbook.xml.rels')->query('//*[local-name()="Relationship"]') as $r) {
            if ($r->getAttribute('TargetMode') === 'External') {
                throw new ExcelTidakValid('Workbook menggunakan sumber eksternal. Simpan salinan berupa nilai terlebih dahulu.');
            }
            $hubungan[$r->getAttribute('Id')] = $r->getAttribute('Target');
        }
        if (isset($this->zip['docProps/core.xml'])) {
            $disimpan = $this->xml('docProps/core.xml')->query('//*[local-name()="modified"]')->item(0)?->textContent;
            try {
                $this->tanggalBerkas = $disimpan ? \Illuminate\Support\Carbon::parse($disimpan)->setTimezone(config('app.timezone'))->toDateString() : '';
            } catch (\Throwable) {
                $this->tanggalBerkas = '';
            }
        }
        $teks = [];
        if (isset($this->zip['xl/sharedStrings.xml'])) {
            foreach ($this->xml('xl/sharedStrings.xml')->query('//*[local-name()="si"]') as $si) {
                $teks[] = $this->teks($si);
            }
        }
        $lembar = [];
        foreach ($workbook->query('//*[local-name()="sheet"]') as $sheet) {
            $id = $sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
            $target = $hubungan[$id] ?? '';
            $part = str_starts_with($target, '/xl/') ? ltrim($target, '/') : 'xl/'.$target;
            if (! preg_match('~^xl/worksheets/[a-zA-Z0-9_-]+\.xml$~D', $part)) {
                throw new ExcelTidakValid('Struktur sheet tidak didukung. Simpan kembali sebagai XLSX biasa.');
            }
            $daftar = [];
            foreach ($this->xml($part)->query('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
                $sel = [];
                foreach ($row->childNodes as $cell) {
                    if ($cell->nodeName !== 'c') {
                        continue;
                    }
                    $huruf = preg_replace('/\d/', '', $cell->getAttribute('r'));
                    $val = '';
                    foreach ($cell->childNodes as $child) {
                        if ($child->nodeName === 'v') {
                            $val = $child->textContent;
                        }
                        if ($child->nodeName === 'is') {
                            $val = $this->teks($child);
                        }
                    }
                    if ($cell->getAttribute('t') === 's') {
                        $val = $teks[(int) $val] ?? '';
                    }
                    if (mb_strlen($val) > 20000) {
                        throw new ExcelTidakValid('Ada isi sel terlalu panjang. Maksimal 20.000 karakter per sel.');
                    }
                    $sel[$huruf] = trim($val);
                }
                $daftar[] = [(int) $row->getAttribute('r'), $sel];
            }
            $lembar[$sheet->getAttribute('name')] = $daftar;
        }

        return $lembar;
    }

    /** CSV satu lembar; pemisahnya titik koma, koma, atau tab, mana yang paling sering di baris awal. */
    private function lembarCsv(string $path): array
    {
        if (filesize($path) > 2 * 1024 * 1024) {
            throw new ExcelTidakValid('Gunakan file CSV maksimal 2 MB.');
        }
        $isi = (string) file_get_contents($path);
        if (str_contains($isi, "\0")) {
            throw new ExcelTidakValid('File CSV tidak bisa dibaca. Simpan kembali sebagai CSV biasa.');
        }
        $isi = preg_replace('/^\xEF\xBB\xBF/', '', $isi);
        // CSV dari Excel Windows sering berkode ANSI, bukan UTF-8.
        if (! mb_check_encoding($isi, 'UTF-8')) {
            $isi = mb_convert_encoding($isi, 'UTF-8', 'Windows-1252');
        }
        $awal = implode("\n", array_slice(preg_split('/\R/', $isi, 31), 0, 30));
        $pemisah = ';';
        $terbanyak = -1;
        foreach ([';', ',', "\t"] as $p) {
            if (substr_count($awal, $p) > $terbanyak) {
                $terbanyak = substr_count($awal, $p);
                $pemisah = $p;
            }
        }
        $f = fopen('php://temp', 'r+');
        fwrite($f, $isi);
        rewind($f);
        $daftar = [];
        $nomor = 0;
        try {
            while (($r = fgetcsv($f, null, $pemisah, '"', '')) !== false) {
                $nomor++;
                if (count($r) > 300) {
                    throw new ExcelTidakValid('Kolom CSV terlalu banyak. Simpan lembar pemantauannya saja.');
                }
                $sel = [];
                foreach ($r as $i => $v) {
                    $v = trim((string) $v);
                    if (mb_strlen($v) > 20000) {
                        throw new ExcelTidakValid('Ada isi sel terlalu panjang. Maksimal 20.000 karakter per sel.');
                    }
                    $sel[$i] = $v;
                }
                $daftar[] = [$nomor, $sel];
            }
        } finally {
            fclose($f);
        }

        return ['CSV' => $daftar];
    }

    private function xml(string $part): DOMXPath
    {
        if (! isset($this->zip[$part])) {
            throw new ExcelTidakValid('Struktur file Excel tidak lengkap.');
        }
        $xml = $this->zip[$part]->getContent();
        if (preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
            throw new ExcelTidakValid('XML dengan deklarasi eksternal tidak diperbolehkan.');
        }
        $sebelum = libxml_use_internal_errors(true);
        try {
            $doc = new DOMDocument;
            if (! $doc->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT) || $doc->doctype) {
                throw new ExcelTidakValid('Isi XML pada Excel tidak valid.');
            }

            return new DOMXPath($doc);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($sebelum);
        }
    }

    /** Judul asli dipertahankan, tetapi pemetaan mengabaikan kapital, spasi, dan pindah baris. */
    public static function kunci(array $asli): array
    {
        $out = [];
        foreach ($asli as $k => $v) {
            $out[self::judulKecil((string) $k)] = $v;
        }

        return $out;
    }

    public static function judulKecil(string $judul): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $judul)));
    }

    /** "1/5/2/16/2025/238 1/5/2/16/2026/025" → daftar nomor CHV, urut seperti di sel. */
    public static function daftarChv(string $teks): array
    {
        return preg_split('/[\s;,]+/u', trim($teks), -1, PREG_SPLIT_NO_EMPTY);
    }

    /** "10 November 2025 19 Februari 2026" → ['2025-11-10', '2026-02-19']; yang bukan tanggal dilewati. */
    public static function daftarTanggal(string $teks): array
    {
        preg_match_all('/\d{1,2}\s+\pL+\.?\s+\d{4}/u', $teks, $m);

        return array_values(array_filter(array_map(fn ($t) => self::tanggalIndonesia($t), $m[0])));
    }

    /** Tanggal CHV yang tertulis di teks, mis. "CHV Nomor 1/5/2/16/2025/238 Tanggal 10 November 2025". */
    public static function tanggalChv(array $teks): array
    {
        $peta = [];
        foreach ($teks as $t) {
            preg_match_all('/CHV\s+Nomor\s+([0-9][0-9\/.\-]*[0-9])\s+Tanggal\s+(\d{1,2}\s+\pL+\.?\s+\d{4})/iu', (string) $t, $m, PREG_SET_ORDER);
            foreach ($m as $x) {
                $peta[$x[1]] ??= self::tanggalIndonesia($x[2]);
            }
        }

        return array_filter($peta);
    }

    /**
     * Surat verifikasi satu baris, urut seperti di sel: nomor CHV ke-n berpasangan dengan tanggal
     * LHV ke-n. Tanggal CHV dari `tanggalChv()` bila ada; kalau tidak, tanggal LHV-nya.
     *
     * @return list<array{nomor: string, lhv: string, tanggal: string}>
     */
    public static function suratChv(string $chv, string $tglLhv, array $tglChv): array
    {
        $lhv = self::daftarTanggal($tglLhv);
        $out = [];
        foreach (self::daftarChv($chv) as $i => $nomor) {
            $out[] = ['nomor' => $nomor, 'lhv' => $lhv[$i] ?? '', 'tanggal' => $tglChv[$nomor] ?? ($lhv[$i] ?? '')];
        }

        return $out;
    }

    private function teks(\DOMNode $node): string
    {
        $xpath = new DOMXPath($node->ownerDocument);
        $out = '';
        foreach ($xpath->query('.//*[local-name()="t"]', $node) as $t) {
            $out .= $t->textContent;
        }

        return $out;
    }

    /** Tanggal sel → [Y-m-d, keterangan sesudah tanggal]; yang tidak dikenal tetap teks aslinya. */
    private function tanggal(string $nilai): array
    {
        if (in_array(mb_strtoupper($nilai), ['', '-', '—', 'N/A'], true)) {
            return ['', ''];
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $nilai)) {
            return [$nilai, ''];
        }
        // Nomor seri tanggal hanya ada di XLSX; di CSV angka lepas seperti "2021" bukan tanggal.
        if (! $this->csv && is_numeric($nilai) && (float) $nilai >= 1 && (float) $nilai < 100000) {
            $dasar = new \DateTimeImmutable($this->tahun1904 ? '1904-01-01' : '1899-12-30');

            return [$dasar->modify('+'.(int) $nilai.' days')->format('Y-m-d'), ''];
        }
        // Hari lebih dulu, seperti penulisan di Indonesia: 29/07/2021, 29-07-2021.
        if (preg_match('/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4})$/D', $nilai, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return [sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]), ''];
        }
        /* "29 Juli 2021 (Konfirmasi ke Satker)": tanggalnya dipakai, keterangannya dibawa. */
        if (preg_match('/^(\d{1,2}\s+\pL+\.?\s+\d{4})\s*(.*)$/u', $nilai, $m) && ($iso = self::tanggalIndonesia($m[1])) !== '') {
            return [$iso, trim($m[2], " \t()")];
        }

        return [$nilai, '']; // Yang tidak dikenal tetap terlihat sebagai isian yang perlu diperbaiki.
    }

    /** "5 September 2005" → 2005-09-05; '' bila bukan tanggal yang sah. */
    public static function tanggalIndonesia(string $teks): string
    {
        if (! preg_match('/^(\d{1,2})\s+(\pL+)\.?\s+(\d{4})$/u', trim($teks), $m)) {
            return '';
        }
        $bulan = self::BULAN[mb_strtolower($m[2])] ?? null;

        return $bulan && checkdate($bulan, (int) $m[1], (int) $m[3]) ? sprintf('%04d-%02d-%02d', $m[3], $bulan, $m[1]) : '';
    }

    /**
     * Kolom No LHP memuat nomor dan tanggal surat, kadang dengan kata "tanggal" di antaranya:
     * "11/XII/09/2005 5 September 2005", "12.b/LHP/XVII/05/2025 tanggal 19 Mei 2025".
     */
    public static function pisahNomorLhp(string $teks): array
    {
        $teks = trim(preg_replace('/\s+/u', ' ', $teks));
        if (preg_match('/^(.+?)\s+(?:(?:tanggal|tgl\.?)\s+)?(\d{1,2}\s+\pL+\.?\s+\d{4})$/iu', $teks, $m) && ($tgl = self::tanggalIndonesia($m[2])) !== '') {
            return [trim($m[1]), $tgl];
        }

        return [$teks, ''];
    }

    /** Ref LHP "58.B.8.b." → nomor temuan "58.B.8": segmen terakhir adalah nomor rekomendasinya. */
    public static function nomorTemuan(string $refLhp): string
    {
        $ref = rtrim(trim($refLhp), '.) ');
        $titik = strrpos($ref, '.');

        return $titik === false ? $ref : substr($ref, 0, $titik);
    }

    /** Pilihan "Status/Posisi Dokumen TL" di Excel → posisi berkas sistem. */
    public static function posisi(string $teks): string
    {
        $p = mb_strtolower(preg_replace('/\s+/u', ' ', trim($teks)));

        return match (true) {
            $p === '' => '',
            /* "Diproses SiPTL": sudah lewat Inspektorat, tinggal penilaian BPK. */
            str_contains($p, 'selesai') || str_contains($p, 'tuntas') || str_contains($p, 'siptl') => 'tuntas',
            str_contains($p, 'itjen') || str_contains($p, 'inspektorat') => 'inspektorat',
            str_contains($p, 'uki') => 'uki',
            str_contains($p, 'satker') || str_contains($p, 'setunor') || str_contains($p, 'satuan kerja') => 'satker',
            default => '',
        };
    }

    /**
     * Nilai sel → rupiah bulat (sen dibulatkan); teks lain dibiarkan untuk diperiksa. CSV boleh
     * berformat ribuan ("87.295.888,19" atau "87,295,888.19").
     */
    public static function rupiah(string $teks, bool $csv = false): string
    {
        $t = str_replace(' ', '', preg_replace('/^Rp\.?/iu', '', trim($teks)));
        if ($csv) {
            if (preg_match('/^\d{1,3}(?:\.\d{3})+(?:,\d+)?$/D', $t)) {
                $t = str_replace(['.', ','], ['', '.'], $t);
            } elseif (preg_match('/^\d{1,3}(?:,\d{3})+(?:\.\d+)?$/D', $t)) {
                $t = str_replace(',', '', $t);
            } elseif (preg_match('/^\d+,\d+$/D', $t)) {
                $t = str_replace(',', '.', $t);
            }
        }

        return preg_match('/^\d+(?:\.\d+)?$/D', $t) ? (string) (int) round((float) $t) : trim($teks);
    }

    private function petakan(array $a): array
    {
        $ambil = fn ($k) => (string) ($a[$k] ?? '');
        $hasil = fn ($s) => match (mb_strtoupper(trim($s))) {
            'MEMADAI', 'M' => 'M', 'BELUM MEMADAI', 'BM' => 'BM', '' => '', default => $s,
        };
        $bpk = match (mb_strtoupper($ambil('status siptl'))) {
            'SS', 'SUDAH SESUAI', 'SESUAI' => 'SS', 'BS', 'BELUM SESUAI' => 'BS',
            'BT', 'BELUM DITINDAKLANJUTI' => 'BT', '' => '', default => $ambil('status siptl'),
        };
        /* Tanggal surat dari kolom No LHP; file tidak mencatat tanggal diterima, jadi disamakan. */
        [$nomor, $tglLhp] = self::pisahNomorLhp($ambil('no lhp'));
        [$renaksi, $ketRenaksi] = $this->tanggal($ambil('due date renaksi'));
        $rencana = $ambil('rencana aksi');
        if ($ketRenaksi !== '') {
            $rencana = trim($rencana."\n".'Tenggat rencana aksi: '.$ketRenaksi);
        }
        $rupiah = fn ($k) => self::rupiah($ambil($k), $this->csv);
        $satuSpasi = fn ($k) => trim(preg_replace('/\s+/u', ' ', $ambil($k)));
        $aturan = [];

        // "2016.22.B.101.14.2)": kurung tutup tanpa pembuka adalah salah ketik.
        $kode = trim($ambil('reff idt'));
        if (substr_count($kode, ')') > substr_count($kode, '(')) {
            $kode = rtrim($kode, ') ');
            $aturan['kode'] = 'Tanda ")" di akhir Ref IDT pada file dibuang.';
        }

        /* Hasil Inspektorat dari kolom Status Verifikasi (Memadai/Belum Memadai). Posisi yang
           bertentangan dengan hasil mengikuti hasilnya: Memadai sudah selesai diperiksa, Belum
           memadai kembali ke satuan kerja. */
        $hasilItjen = $hasil($ambil('status verifikasi'));
        $teksPosisi = $satuSpasi('status/posisi dokumen tl');
        $posisi = self::posisi($teksPosisi);
        if ($hasilItjen === 'M' && $posisi !== '' && $posisi !== 'tuntas') {
            $posisi = 'tuntas';
            $aturan['posisi'] = 'Posisi di file "'.$teksPosisi.'" bertentangan dengan hasil Memadai; posisi mengikuti hasil.';
        } elseif ($hasilItjen === 'BM' && $posisi === 'tuntas') {
            $posisi = 'satker';
            $aturan['posisi'] = 'Posisi di file "'.$teksPosisi.'" bertentangan dengan hasil Belum memadai; posisi mengikuti hasil.';
        }

        /* Sumber dari nomor laporan: "…/LHA/…" Inspektorat, "…/LHP/…" BPK. Nomor lama tanpa
           keduanya dianggap LHP, karena lembar ini memantau rekomendasi BPK. */
        $sumber = preg_match('/\bLHA\b/i', $nomor) ? 'LHA' : 'LHP';
        if (! preg_match('/\bLH[AP]\b/i', $nomor)) {
            $aturan['sumber'] = 'Nomor laporan tidak memuat LHP atau LHA; dianggap LHP karena lembar ini memantau rekomendasi BPK.';
        }

        return [
            'sumber' => $sumber, 'nomor' => $nomor, 'tgl_surat' => $tglLhp, 'tgl_terima' => $tglLhp,
            'kode' => $kode, 'ref_lhp' => $ambil('reff lhp'), 'nomor_temuan' => self::nomorTemuan($ambil('reff lhp')),
            'judul' => $ambil('judul temuan besar'),
            'uraian' => $ambil('rekomendasi temuan besar'), 'kode_satker' => $ambil('kode satker'),
            'satker_id' => '', 'kategori_id' => '', 'sifat_id' => '', 'bentuk_id' => '',
            'bentuk_teks' => $satuSpasi('bentuk tindak lanjut'), 'paket' => $satuSpasi('keterangan paket'),
            'nilai' => $rupiah('nilai rekomendasi (idr)'), 'nilai_itjen' => $rupiah('nilai memadai (idr)'),
            'nilai_bpk' => $rupiah('nilai ss (idr)'), 'hasil' => $hasilItjen,
            'chv' => $satuSpasi('nomor chv'), 'tgl_lhv' => $satuSpasi('tanggal surat pengantar (lhv)'),
            'uraian_verifikasi' => trim($ambil('uraian verifikasi')),
            'hasil_uki' => '', 'status_bpk' => $bpk,
            'catatan_bpk' => implode("\n", array_filter([trim($ambil('hasil telaah bpk ri')), trim($ambil('keterangan new siptl'))])),
            'posisi' => $posisi,
            'tanggal_acuan' => $this->tanggalBerkas, 'siptl_tanggal' => '',
            'renaksi' => $renaksi, 'klasifikasi_renaksi' => $satuSpasi('klasifikasi renaksi'),
            'catatan' => $ambil('catatan belum memadai'),
            'rencana_aksi' => $rencana,
            '_aturan' => $aturan,
        ];
    }
}
