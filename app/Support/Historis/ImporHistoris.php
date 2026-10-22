<?php

namespace App\Support\Historis;

use App\Models\DrafHistoris;
use App\Models\KeputusanVerifikasi;
use App\Models\Laporan;
use App\Models\Rekomendasi;
use App\Models\Sasaran;
use App\Models\Satker;
use App\Models\Temuan;
use App\Models\Tindakan;
use App\Models\Verifikasi;
use App\Support\Aktivitas;
use App\Support\Jejak;
use App\Support\Tampil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImporHistoris
{
    /** Satu transaksi untuk seluruh pilihan. Tidak ada import sebagian bila pemeriksaan gagal. */
    public function jalankan(DrafHistoris $draf, int $versi): DrafHistoris
    {
        return DB::transaction(function () use ($draf, $versi) {
            DB::table('kunci_impor_historis')->where('id', 1)->lockForUpdate()->first();
            $draf = DrafHistoris::whereKey($draf->id)->lockForUpdate()->firstOrFail();
            if ($draf->diimpor_pada) {
                return $draf;
            } // Konfirmasi ulang/klik ganda tidak menulis lagi.
            if ($draf->versi !== $versi) {
                throw ValidationException::withMessages(['review' => 'Draft sudah berubah di tab lain. Muat ulang sebelum mengonfirmasi.']);
            }
            $rows = app(ReviewHistoris::class)->periksa($draf->isian);
            $pilihan = collect($rows)->filter(fn ($r) => $r['ikut']);
            if ($pilihan->isEmpty() || $pilihan->contains(fn ($r) => ! $r['siap'])) {
                throw ValidationException::withMessages(['review' => 'Masih ada data yang belum lengkap, belum diperiksa, atau bertabrakan dengan database. Periksa kembali ringkasan review.']);
            }
            $laps = [];
            $tems = [];
            $reks = [];
            $tindakans = [];
            $hasil = [];
            $putusan = [];
            $tglChv = BacaExcel::tanggalChv(array_map(fn ($r) => $r['data']['uraian_verifikasi'] ?? '', $rows));
            $kini = Aktivitas::waktuKini();
            foreach ($rows as $i => $row) {
                if (! $row['ikut']) {
                    continue;
                }
                $d = $row['data'];
                $keyLap = mb_strtolower($d['nomor']);
                $lap = $laps[$keyLap] ??= Laporan::where('nomor', $d['nomor'])->first() ?? Laporan::create([
                    'sumber' => $d['sumber'], 'nomor' => $d['nomor'], 'tgl_surat' => $d['tgl_surat'],
                    'tgl_terima' => $d['tgl_terima'], 'dicatat_pada' => $kini->toDateString(), 'dicatat_oleh' => auth()->id(),
                ]);
                $keyTem = $lap->id.'|'.$d['nomor_temuan'];
                if (! isset($tems[$keyTem])) {
                    $tem = Temuan::where('laporan_id', $lap->id)->where('nomor_pada_surat', $d['nomor_temuan'])->first();
                    if ($tem && ($tem->judul !== $d['judul'] || (string) $tem->kategori_temuan_id !== (string) $d['kategori_id'])) {
                        throw ValidationException::withMessages(['review' => 'Nomor temuan sudah ada dengan judul/kategori berbeda. Periksa kembali data sebelum impor.']);
                    }
                    $tems[$keyTem] = $tem ?? Temuan::create([
                        'laporan_id' => $lap->id, 'kode' => 'H-'.Str::ulid(), 'nomor_pada_surat' => $d['nomor_temuan'],
                        'judul' => $d['judul'], 'kategori_temuan_id' => $d['kategori_id'] ?: null, 'nilai' => 0,
                    ]);
                }
                $tem = $tems[$keyTem];
                $tem->satkers()->syncWithoutDetaching([(int) $d['satker_id']]);
                $keyRek = mb_strtolower($d['kode']);
                if (! isset($reks[$keyRek])) {
                    $reks[$keyRek] = Rekomendasi::create([
                        'temuan_id' => $tem->id, 'kode' => $d['kode'], 'ref_lhp' => $d['ref_lhp'],
                        'nomor_urut' => (int) $tem->rekomendasi()->max('nomor_urut') + 1,
                        'uraian' => $d['uraian'], 'sifat_id' => $d['sifat_id'], 'nilai_pulih' => 0,
                        'status' => 'BT', 'rencana_angsur' => 0, 'kunci_angsur' => false,
                    ]);
                    Jejak::riwayat($reks[$keyRek], 'Setba · impor historis',
                        'Data historis diimpor dari draft #'.$draf->id.'. Tanggal acuan data '.$d['tanggal_acuan'].'; tahapan sebelumnya belum direkonstruksi.', $kini);
                }
                $rek = $reks[$keyRek];
                /* Tindakan yang sama dipakai bersama satker lain; baris lain dari satker yang sama
                   mendapat tindakan sendiri (satu sasaran per tindakan dan satker). */
                /* Teks bentuk hanya disimpan bila tidak ada di daftar; paket membedakan tindak lanjut. */
                $bentukTeks = $d['bentuk_id'] === '' ? ($d['bentuk_teks'] ?? '') : '';
                $keyTk = implode('|', [$rek->id, $d['bentuk_id'], $bentukTeks, $d['paket'] ?? '', $d['renaksi'],
                    $d['klasifikasi_renaksi'] ?? '', $d['rencana_aksi']]);
                $tk = null;
                foreach ($tindakans[$keyTk] ?? [] as $j => $calon) {
                    if (! in_array((int) $d['satker_id'], $calon['satker'], true)) {
                        $tk = $calon['tk'];
                        $tindakans[$keyTk][$j]['satker'][] = (int) $d['satker_id'];
                        break;
                    }
                }
                if (! $tk) {
                    $tk = Tindakan::create([
                        'rekomendasi_id' => $rek->id, 'bentuk_id' => $d['bentuk_id'] ?: null, 'bentuk_teks' => $bentukTeks ?: null,
                        'keterangan_paket' => ($d['paket'] ?? '') ?: null,
                        'urutan' => (int) $rek->tindakan()->max('urutan') + 1,
                        'tgl_renaksi' => $d['renaksi'] ?: null, 'klasifikasi_renaksi' => ($d['klasifikasi_renaksi'] ?? '') ?: null,
                        'catatan' => $d['rencana_aksi'] ?: null,
                    ]);
                    $tindakans[$keyTk][] = ['tk' => $tk, 'satker' => [(int) $d['satker_id']]];
                }
                /* CHV terakhir yang bertanggal dan berhasil jadi surat verifikasi (tab Progres); CHV
                   sebelumnya disebut di catatannya. Tanpa tanggal atau hasil, nomornya masuk uraian. */
                $uraian = trim($d['uraian_verifikasi'] ?? '');
                $surat = BacaExcel::suratChv($d['chv'] ?? '', $d['tgl_lhv'] ?? '', $tglChv);
                $akhir = $surat ? end($surat) : null;
                if ($akhir && $akhir['tanggal'] !== '' && $d['hasil'] !== '') {
                    $lalu = array_slice($surat, 0, -1);
                    $putusan[$akhir['nomor'].'|'.$rek->id] ??= ['surat' => $akhir, 'rek' => $rek, 'baris' => []];
                    $putusan[$akhir['nomor'].'|'.$rek->id]['baris'][] = ['satker_id' => (int) $d['satker_id'], 'hasil' => $d['hasil'],
                        'catatan' => $lalu ? 'Sebelumnya juga diverifikasi dengan CHV '.implode(', ', array_map(
                            fn ($c) => $c['nomor'].($c['lhv'] ? ' (LHV '.Tampil::tgl($c['lhv']).')' : ''), $lalu)).'.' : ''];
                } elseif ($surat) {
                    $uraian = trim($uraian."\n".'Nomor CHV: '.implode(', ', array_column($surat, 'nomor'))
                        .(($d['tgl_lhv'] ?? '') !== '' ? '; tanggal LHV: '.$d['tgl_lhv'] : '').'.');
                }
                $s = new Sasaran;
                $s->forceFill([
                    'draf_historis_id' => $draf->id, 'tanggal_acuan_historis' => $d['tanggal_acuan'],
                    'created_at' => $kini, 'updated_at' => $kini,
                    'tindakan_id' => $tk->id, 'satker_id' => $d['satker_id'], 'nilai' => (int) $d['nilai'],
                    'posisi' => $d['posisi'], 'hasil' => $d['hasil'] ?: null, 'hasil_uki' => $d['hasil_uki'] ?: null,
                    'status_bpk' => $d['status_bpk'] ?: null, 'siptl_tanggal' => $d['siptl_tanggal'] ?: null,
                    'siptl_historis' => $d['status_bpk'] !== '', 'nilai_diakui_itjen' => (int) $d['nilai_itjen'],
                    'nilai_diakui_bpk' => (int) $d['nilai_bpk'], 'catatan' => $d['catatan'] ?: null,
                    'catatan_bpk' => ($d['catatan_bpk'] ?? '') ?: null, 'uraian_verifikasi' => $uraian ?: null,
                    'tgl_pantau' => $d['tanggal_acuan'],
                ])->save();
                $hasil[] = ['sheet' => $row['sheet'], 'baris' => $row['baris'], 'laporan_id' => $lap->id, 'rekomendasi_id' => $rek->id, 'sasaran_id' => $s->id];
            }
            /* Satu CHV memutus seluruh rekomendasi: memadai hanya bila semua barisnya memadai. */
            $nama = Satker::whereIn('id', array_merge([0], ...array_map(fn ($p) => array_column($p['baris'], 'satker_id'), array_values($putusan))))
                ->get()->keyBy('id');
            foreach ($putusan as $p) {
                /* Tanpa pencatat: Progres menyebut penerbitnya (Inspektorat), bukan akun yang mengimpor. */
                $v = Verifikasi::firstOrCreate(['jenis' => Verifikasi::CHV, 'nomor_surat' => $p['surat']['nomor']], [
                    'tgl_surat' => $p['surat']['tanggal'], 'pejabat' => 'Inspektorat',
                    'tgl_lhv' => $p['surat']['lhv'] ?: null, 'dicatat_oleh' => null,
                ]);
                $berisi = collect($p['baris'])->filter(fn ($b) => $b['catatan'] !== '');
                KeputusanVerifikasi::updateOrCreate(['verifikasi_id' => $v->id, 'rekomendasi_id' => $p['rek']->id, 'sasaran_id' => null], [
                    'hasil' => collect($p['baris'])->every(fn ($b) => $b['hasil'] === 'M') ? 'M' : 'BM',
                    'catatan' => $berisi->map(fn ($b) => ($nama[$b['satker_id']]?->namaPendek() ?? 'Satker').': '.$b['catatan'])->join(' · ')
                        ?: 'Dicatat dari data historis.',
                    'catatan_umum' => 'Dicatat dari data historis.',
                    'catatan_satker' => $berisi->map(fn ($b) => ['satker_id' => $b['satker_id'], 'catatan' => $b['catatan']])->values()->all(),
                ]);
            }
            foreach ($reks as $rek) {
                $rek->update(['nilai_pulih' => (int) $rek->sasaran()->sum('sasarans.nilai'),
                    'tenggat_jawab' => $rek->tindakan()->whereNotNull('tgl_renaksi')->min('tgl_renaksi')]);
                Jejak::rangkumStatus($rek);
            }
            $isian = $draf->isian;
            $isian['hasil'] = $hasil;
            $draf->update(['isian' => $isian, 'diimpor_pada' => $kini, 'versi' => $draf->versi + 1]);
            Aktivitas::catat('laporan.historis.impor', 'Mengimpor '.count($hasil).' penugasan historis setelah review', [
                'kelompok' => 'laporan', 'subjek' => $draf,
                'rincian' => ['sha256' => $draf->sha256, 'penugasan' => count($hasil), 'rekomendasi' => count($reks), 'laporan' => count($laps),
                    'surat_chv' => count($putusan)],
            ]);

            return $draf;
        }, 3);
    }
}
