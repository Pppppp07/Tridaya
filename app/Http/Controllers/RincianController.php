<?php

namespace App\Http\Controllers;

use App\Enums\PeranPengguna;
use App\Enums\PosisiBerkas;
use App\Models\Rekomendasi;
use App\Support\DasborKeadaan;
use App\Support\RiwayatTindakLanjut;
use App\Support\Terlihat;
use Illuminate\Http\Request;

/**
 * Rincian rekomendasi: laporan dan temuannya, lalu tindak lanjut per bentuk berisi tabel satuan
 * kerja. Hak kerja dihitung di sini dan diperiksa lagi di pengendali aksinya.
 */
class RincianController extends Controller
{
    public function tampil(Request $req, Rekomendasi $rekomendasi)
    {
        $u = auth()->user();
        $terlihat = Terlihat::untuk();

        $rekomendasi->load([
            'temuan.laporan.lampiran', 'temuan.satkers', 'temuan.kategori', 'temuan.kategoriIntern',
            'temuan.rekomendasi.sasaran.satker',
            'tindakan.bentuk',
            'sasaran.satker', 'sasaran.tindakan.bentuk', 'sasaran.riwayatStatus', 'sasaran.draf.penyimpan',
            'tanggapan.pencatat', 'pemulihan.lampiran', 'tolakanBpk',
            'permintaanDokumen.item.lampiran',
            'pengembalian.sasaran', 'telaah.sasaran', 'telaah.lampiran',
            'keputusan.verifikasi.lampiran', 'keputusan.sasaran',
            'surat.sasaran', 'lampiran', 'riwayat', 'sifat',
        ]);

        /* Diperiksa di server, bukan disembunyikan di tampilan. */
        abort_unless($terlihat->bolehLihatRekomendasi($rekomendasi), 403,
            'Rekomendasi ini tidak ditujukan ke satuan kerja Anda.');

        $terlihat->pangkasRekomendasi($rekomendasi);

        $tem = $rekomendasi->temuan;
        $saudara = $tem->rekomendasi
            ->reject(fn ($r) => $r->id === $rekomendasi->id)
            ->filter(fn ($r) => $terlihat->bolehLihatRekomendasi($r))
            ->map(fn ($r) => $terlihat->pangkasRekomendasi($r->setRelation('temuan', $tem)))
            ->values();

        /* Kembali ke asal: halaman laporan, Dashboard (filter dan kartu yang sama), atau layar
           awal peran. */
        /* Baris rekomendasi ini ikut ditunjuk di halaman tujuan. */
        $kembali = match ($req->query('dari')) {
            'laporan' => route('laporan.show', [$tem->laporan, 'temuan' => $tem->id, 'rek' => $rekomendasi->id]),
            'ringkasan' => $this->kembaliKeRingkasan((string) $req->query('ring', '')),
            /* Dibuka dari Log aktivitas — kembali ke sana. */
            'log' => route('log'),
            default => $u->peranAktif() === PeranPengguna::PIMPINAN ? route('ringkasan')
                : route('rekomendasi.index', ['tuju' => $rekomendasi->id]),
        };

        return view('rekomendasi.show', [
            'r' => $rekomendasi,
            'tem' => $tem,
            'lap' => $tem->laporan,
            'saudara' => $saudara,
            'kembali' => $kembali,
            'riwayat' => RiwayatTindakLanjut::kartu($rekomendasi, $u->peranAktif(), $u->satkerAktifId()),
            'suratTerakhir' => [
                'uki' => session('surat_terakhir.'.PosisiBerkas::UKI->value),
                'inspektorat' => session('surat_terakhir.'.PosisiBerkas::INSPEKTORAT->value),
            ],
        ]);
    }

    /**
     * Pekerjaan peran ini pada satu baris; yang pertama jadi label tombol "Kerjakan". Null = tidak
     * ada.
     */
    public static function aksiBaris(Rekomendasi $r, $x, PeranPengguna $peran, int|array|null $satkerId): ?array
    {
        $pos = $x->pos();
        $jenis = $r->jenis();
        return match (true) {
            $peran === PeranPengguna::SATKER => $pos === PosisiBerkas::SATKER && $x->satker_id === $satkerId
                ? ['label' => 'Isi tindak lanjut', 'ket' => 'Isi tanggapan, lampirkan link bukti, dan kirim berkas penugasan ini ke Setba.'] : null,
            $peran === PeranPengguna::UKI => $pos === PosisiBerkas::UKI
                ? ['label' => 'Telaah berkas', 'ket' => 'Catat hasil telaah UKI untuk penugasan ini, beserta catatan dan surat hasil validasinya.'] : null,
            $peran === PeranPengguna::INSPEKTORAT => $pos === PosisiBerkas::INSPEKTORAT
                ? ['label' => 'Catat hasil verifikasi', 'ket' => 'Catat putusan Inspektorat untuk penugasan ini, beserta catatan dan surat hasil verifikasinya (CHV).'] : null,
            $peran !== PeranPengguna::SETBA => null,
            $pos === PosisiBerkas::SETBA_TINJAU => ['label' => 'Teruskan ke UKI',
                'ket' => 'Kirim berkas penugasan ini ke UKI untuk ditelaah, dengan nomor dan tanggal surat pengantar.'],
            $pos === PosisiBerkas::SETBA_KEMBALI => ['label' => 'Kirim ulang ke satuan kerja',
                'ket' => 'Kembalikan berkas penugasan ini ke Satker untuk memperbaiki tanggapan sesuai catatan '.($x->kembali_dari ?: 'pemeriksa').'.'],
            $pos === PosisiBerkas::SETBA_TERUSKAN => ['label' => 'Teruskan ke Inspektorat',
                'ket' => 'Kirim berkas penugasan ini beserta hasil validasi UKI ke Inspektorat untuk diverifikasi.'],
            $pos === PosisiBerkas::INSPEKTORAT => ['label' => 'Catat hasil verifikasi Inspektorat',
                'ket' => 'Salin putusan dan catatan pada CHV untuk penugasan ini. Setba mencatat hasilnya; penilainya tetap Inspektorat.'],
            /* Urusan SIPTL per baris. Putusan BPK dicatat sekali tiap unggahan. */
            $pos === PosisiBerkas::TUNTAS && $x->perluUnggah($jenis) => ['label' => 'Catat unggahan ke SIPTL',
                'ket' => 'Unggah berkasnya di aplikasi SIPTL, lalu catat tanggal unggahnya di sini.'],
            $pos === PosisiBerkas::TUNTAS && $x->perluCek($jenis) => ['label' => 'Catat hasil pemantauan BPK',
                'ket' => 'Cek statusnya di SIPTL. Putusan BPK dicatat sekali untuk unggahan ini.'],
            $pos === PosisiBerkas::TUNTAS && $x->perluKirimUlang($jenis) => ['label' => 'Kirim ulang ke satuan kerja',
                'ket' => 'Ditolak BPK. Kirim ulang ke satuan kerja untuk diperbaiki, lalu unggah lagi.'],
            default => null,
        };
    }

    /** Keadaan urusan SIPTL dalam satu kalimat, dihitung dari barisnya. Null = tidak digambar. */
    public static function keadaanSiptl(Rekomendasi $r, PeranPengguna $peran, int|array|null $satkerId): ?array
    {
        $jenis = $r->jenis();
        if (! $jenis->melewatiSiptl()) {
            return null;
        }
        $baris = $r->barisTerlihat($peran, $satkerId);
        if ($baris->isEmpty()) {
            return null;
        }
        $st = ($peran->terbatasSatker()
            ? Rekomendasi::rangkumBpk($baris->map->statusSiptl())
            : $r->statusRek())?->value;
        if ($st === 'SS') {
            return ['nada' => 'hijau', 'kalimat' => ($peran->terbatasSatker()
                ? 'Seluruh tindak lanjut Anda sudah dinyatakan Sudah Sesuai oleh BPK. '
                : 'Seluruh satuan kerja sudah dinyatakan Sudah Sesuai oleh BPK. ').'Tidak ada lagi yang perlu dikerjakan di sini.'];
        }

        $naik = $baris->filter(fn ($x) => $x->perluUnggah($jenis))->count();
        $ditolak = $baris->filter(fn ($x) => $x->perluKirimUlang($jenis))->count();
        /* `perluCek` hanya memuat baris yang masih BT, jadi yang ditolak tidak terhitung. */
        $ditunggu = $baris->filter(fn ($x) => $x->perluCek($jenis))->count();
        $dikerjakanUlang = $baris->filter(fn ($x) => ! $x->tuntas()
            && $r->tolakanBpk->contains(fn ($t) => ! $t->sasaran_id || $t->sasaran_id === $x->id))->count();

        $bagian = [];
        if ($naik) {
            $bagian[] = "{$naik} tindak lanjut siap diunggah ke SIPTL";
        }
        if ($ditolak) {
            $bagian[] = "{$ditolak} ditolak BPK, perlu dikirim ulang";
        }
        if ($ditunggu > 0) {
            $bagian[] = "{$ditunggu} sudah diunggah, menunggu penilaian BPK";
        }
        if ($dikerjakanUlang) {
            $bagian[] = "{$dikerjakanUlang} sedang ditindaklanjuti ulang";
        }
        $sesuai = $baris->filter(fn ($x) => $x->sudahSiptl() && $x->status_bpk?->value === 'SS')->count();
        if ($sesuai) {
            $bagian[] = "{$sesuai} sudah sesuai menurut BPK";
        }
        if (! $bagian) {
            return null;
        }
        $belumSampai = $baris->reject(fn ($x) => $x->tuntas())->count() - $dikerjakanUlang;
        if ($belumSampai > 0) {
            $bagian[] = "{$belumSampai} belum selesai diperiksa";
        }

        $kalimat = count($bagian) === 1
            ? $bagian[0].'.'
            : implode(', ', array_slice($bagian, 0, -1)).', dan '.end($bagian).'.';

        return ['nada' => $ditolak ? 'jingga' : ($naik ? '' : 'kuning'), 'kalimat' => mb_strtoupper(mb_substr($kalimat, 0, 1)).mb_substr($kalimat, 1)];
    }

    /** Alamat Ringkasan dari keadaan yang dibawa `ring` (isi alamat Ringkasan). */
    private function kembaliKeRingkasan(string $ring): string
    {
        parse_str($ring, $q);

        return DasborKeadaan::alamat(DasborKeadaan::dari(Request::create('/', 'GET', is_array($q) ? $q : [])));
    }
}
