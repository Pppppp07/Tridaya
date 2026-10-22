<?php

use App\Http\Controllers\AkunController;
use App\Http\Controllers\AlertKeamananController;
use App\Http\Controllers\BerkasController;
use App\Http\Controllers\CariController;
use App\Http\Controllers\DataMasterController;
use App\Http\Controllers\DwaradayaController;
use App\Http\Controllers\HistorisController;
use App\Http\Controllers\LaporanBaruController;
use App\Http\Controllers\LaporanController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\MasukController;
use App\Http\Controllers\PemberitahuanController;
use App\Http\Controllers\PenggunaController;
use App\Http\Controllers\PeranAktifController;
use App\Http\Controllers\RekomendasiController;
use App\Http\Controllers\RingkasanController;
use App\Http\Controllers\SasaranController;
use App\Http\Controllers\SesiController;
use App\Http\Controllers\SiptlController;
use App\Http\Controllers\SsoController;
use App\Http\Controllers\SumberBuktiController;
use App\Http\Controllers\TanggapanController;
use App\Http\Controllers\UnitKerjaController;
use App\Http\Middleware\BatasDiam;
use App\Http\Middleware\PastikanAkunAktif;
use Illuminate\Support\Facades\Route;

Route::get('/masuk', [MasukController::class, 'form'])->name('masuk')->middleware('guest');
/* Login developer — tidak ada di produksi (MasukController::adaPengembang). */
Route::get('/masuk/pengembang', [MasukController::class, 'pengembang'])->name('masuk.pengembang')->middleware('guest');
/* Selain batas per email, satu alamat IP dibatasi (`masuk-ip`, SIMTLHP_MASUK_PER_MENIT_IP). */
Route::post('/masuk', [MasukController::class, 'masuk'])->middleware(['guest', 'throttle:masuk-ip']);
Route::post('/keluar', [MasukController::class, 'keluar'])->name('keluar');
/* Batas diam sesi (BatasDiam, simtlhp-sesi.js). */
Route::get('/sesi/berakhir', [SesiController::class, 'berakhir'])->name('sesi.berakhir');
Route::middleware(['auth', 'throttle:pengguna'])->group(function () {
    Route::get('/sesi', [SesiController::class, 'sisa'])->name('sesi.sisa');
    Route::post('/sesi', [SesiController::class, 'sisa'])->name('sesi.lanjut');
});

/* SSO (SIMTLHP_SSO); tanpa penyedia, rute ini 404. */
Route::middleware(['guest', 'throttle:masuk-ip'])->group(function () {
    Route::get('/masuk/sso', [SsoController::class, 'arahkan'])->name('sso.arahkan');
    Route::get('/masuk/sso/kembali', [SsoController::class, 'kembali'])->name('sso.kembali');
});

// SuperApp boleh membuka akun baru walau browser masih mempunyai sesi lama/berakhir.
// Identitas dan izin akun tujuan diperiksa ulang oleh pengendali; CSRF tetap aktif untuk POST.
Route::withoutMiddleware([
    BatasDiam::class,
    PastikanAkunAktif::class,
])->middleware('throttle:masuk-ip')->group(function () {
    Route::get('/sso-login', [DwaradayaController::class, 'masuk'])->name('sso.dwaradaya.masuk');
    Route::get('/masuk/dwaradaya/konfirmasi', [DwaradayaController::class, 'konfirmasi'])->name('sso.dwaradaya.konfirmasi');
    Route::post('/masuk/dwaradaya/konfirmasi', [DwaradayaController::class, 'lanjutkan'])->name('sso.dwaradaya.lanjutkan');
    Route::get('/masuk/dwaradaya/hasil', [DwaradayaController::class, 'hasil'])->name('sso.dwaradaya.hasil');
});

/* `throttle:pengguna`: batas per user (AppServiceProvider), menjaga server terbatas tetap ringan. */
Route::middleware(['auth', 'throttle:pengguna'])->group(function () {
    Route::post('/akun/peran-aktif', PeranAktifController::class)->name('akun.peran-aktif');
    /* Layar awal mengikuti peran. */
    Route::get('/', [RekomendasiController::class, 'beranda'])->name('beranda');

    Route::get('/rekomendasi', [RekomendasiController::class, 'index'])->name('rekomendasi.index');
    Route::get('/rekomendasi/{rekomendasi}', [RekomendasiController::class, 'show'])->name('rekomendasi.show');
    Route::get('/rekomendasi/{rekomendasi}/terbaru', [RekomendasiController::class, 'terbaru'])->name('rekomendasi.terbaru');

    /* Gerak berkas bekerja pada sasaran: satu satuan kerja pada satu bentuk tindak lanjut. */
    Route::post('/sasaran/{sasaran}/tanggapan', [TanggapanController::class, 'simpan'])->name('tanggapan.simpan');
    Route::post('/sasaran/{sasaran}/teruskan', [SasaranController::class, 'teruskan'])->name('sasaran.teruskan');
    Route::post('/sasaran/{sasaran}/putus', [SasaranController::class, 'putus'])->name('sasaran.putus');
    Route::post('/sasaran/{sasaran}/kirim-ulang', [SasaranController::class, 'kirimUlang'])->name('sasaran.kirimUlang');

    /* Urusan SIPTL milik Setba, per satuan kerja. */
    Route::post('/sasaran/{sasaran}/siptl/unggah', [SiptlController::class, 'unggah'])->name('siptl.unggah');
    Route::post('/sasaran/{sasaran}/siptl/status', [SiptlController::class, 'status'])->name('siptl.status');
    Route::post('/rekomendasi/{rekomendasi}/ulang-bpk', [SiptlController::class, 'ulangBpk'])->name('siptl.ulangBpk');

    /* Sebelum {laporan}, supaya 'baru' tidak terbaca sebagai nomor laporan. */
    Route::get('/laporan/baru', [LaporanBaruController::class, 'form'])->name('laporan.baru');
    Route::post('/laporan/baru', [LaporanBaruController::class, 'simpan'])->name('laporan.baru.simpan');
    Route::post('/laporan/baru/buang', [LaporanBaruController::class, 'buang'])->name('laporan.baru.buang');

    Route::get('/laporan/historis', [HistorisController::class, 'index'])->name('laporan.historis.index');
    Route::post('/laporan/historis', [HistorisController::class, 'upload'])->name('laporan.historis.upload')->middleware('throttle:10,1');
    Route::get('/laporan/historis/{draf}', [HistorisController::class, 'review'])->name('laporan.historis.review')->whereNumber('draf');
    Route::get('/laporan/historis/{draf}/sumber', [HistorisController::class, 'sumber'])->name('laporan.historis.sumber')->whereNumber('draf')->middleware('throttle:unduh');
    Route::get('/laporan/historis/{draf}/baris/{baris}', [HistorisController::class, 'baris'])->name('laporan.historis.baris')->whereNumber(['draf', 'baris']);
    Route::post('/laporan/historis/{draf}/baris/{baris}', [HistorisController::class, 'simpan'])->name('laporan.historis.simpan')->whereNumber(['draf', 'baris']);
    Route::post('/laporan/historis/{draf}/impor', [HistorisController::class, 'impor'])->name('laporan.historis.impor')->whereNumber('draf');
    Route::post('/laporan/historis/{draf}/tanggal', [HistorisController::class, 'massal'])->name('laporan.historis.massal')->whereNumber('draf');
    Route::post('/laporan/historis/{draf}/periksa', [HistorisController::class, 'periksaSemua'])->name('laporan.historis.periksa')->whereNumber('draf');

    Route::get('/laporan', [LaporanController::class, 'index'])->name('laporan.index');
    Route::get('/laporan/{laporan}', [LaporanController::class, 'show'])->name('laporan.show');

    Route::get('/pemberitahuan', [PemberitahuanController::class, 'index'])->name('pemberitahuan');
    Route::post('/pemberitahuan/semua', [PemberitahuanController::class, 'tandaiSemua'])->name('pemberitahuan.semua');
    /* `ringkas` sebelum `{notifikasi}`. */
    Route::post('/pemberitahuan/panel', [PemberitahuanController::class, 'panel'])->name('pemberitahuan.panel');
    Route::get('/pemberitahuan/ringkas', [PemberitahuanController::class, 'ringkas'])->name('pemberitahuan.ringkas');
    Route::post('/pemberitahuan/{notifikasi}/tandai', [PemberitahuanController::class, 'tandai'])->name('pemberitahuan.tandai');
    Route::get('/pemberitahuan/{notifikasi}', [PemberitahuanController::class, 'buka'])->name('pemberitahuan.buka');

    Route::get('/ringkasan', RingkasanController::class)->name('ringkasan');

    /* Pencarian di batang atas: rekomendasi dan laporan yang boleh dilihat. */
    Route::get('/cari', CariController::class)->name('cari');

    Route::get('/data-master', [DataMasterController::class, 'index'])->name('master');
    /* Rute tetap (unduh, massal, banyak) sebelum rute berparameter. */
    Route::get('/data-master/riwayat/unduh', [DataMasterController::class, 'unduhRiwayat'])->name('master.riwayat.unduh')
        ->middleware('throttle:unduh');
    Route::get('/data-master/unit/unduh', [DataMasterController::class, 'unduhUnit'])->name('master.unit.unduh')
        ->middleware('throttle:unduh');
    Route::get('/data-master/pengguna/unduh', [DataMasterController::class, 'unduhPengguna'])->name('master.pengguna.unduh')
        ->middleware('throttle:unduh');
    Route::post('/data-master/pengguna/massal', [PenggunaController::class, 'massal'])->name('master.pengguna.massal');
    Route::post('/data-master/pengguna/banyak', [PenggunaController::class, 'banyak'])->name('master.pengguna.banyak');
    Route::get('/data-master/daftar/{daftar}/unduh', [DataMasterController::class, 'unduhDaftar'])->name('master.daftar.unduh')
        ->whereIn('daftar', DataMasterController::DAFTAR)->middleware('throttle:unduh');
    Route::post('/data-master/daftar/{daftar}/banyak', [DataMasterController::class, 'banyak'])->name('master.banyak')
        ->whereIn('daftar', DataMasterController::DAFTAR);
    Route::post('/data-master/daftar/{daftar}/{id}/urut', [DataMasterController::class, 'urut'])->name('master.urut')
        ->whereIn('daftar', DataMasterController::DAFTAR)->whereNumber('id');
    Route::post('/data-master/daftar/{daftar}/{id}/gabung', [DataMasterController::class, 'gabung'])->name('master.gabung')
        ->whereIn('daftar', DataMasterController::DAFTAR)->whereNumber('id');
    Route::post('/data-master/kategori', [DataMasterController::class, 'tambah'])->name('master.tambah');
    Route::post('/data-master/kategori/{referensi}', [DataMasterController::class, 'simpan'])->name('master.simpan');
    Route::post('/data-master/kategori/{referensi}/saklar', [DataMasterController::class, 'saklar'])->name('master.saklar');
    Route::post('/data-master/temuan', [DataMasterController::class, 'tambahTemuan'])->name('master.temuan.tambah');
    Route::post('/data-master/temuan/{kategori}', [DataMasterController::class, 'simpanTemuan'])->name('master.temuan.simpan');
    Route::post('/data-master/temuan/{kategori}/saklar', [DataMasterController::class, 'saklarTemuan'])->name('master.temuan.saklar');
    Route::post('/data-master/sifat', [DataMasterController::class, 'tambahSifat'])->name('master.sifat.tambah');
    Route::post('/data-master/sifat/{referensi}', [DataMasterController::class, 'simpanSifat'])->name('master.sifat.simpan');
    Route::post('/data-master/sifat/{referensi}/saklar', [DataMasterController::class, 'saklarSifat'])->name('master.sifat.saklar');
    Route::post('/data-master/bentuk', [DataMasterController::class, 'tambahBentuk'])->name('master.bentuk.tambah');
    Route::post('/data-master/bentuk/{referensi}', [DataMasterController::class, 'simpanBentuk'])->name('master.bentuk.simpan');
    Route::post('/data-master/bentuk/{referensi}/saklar', [DataMasterController::class, 'saklarBentuk'])->name('master.bentuk.saklar');
    /* Unit kerja; penanggung jawab dipilih dari IRM. */
    Route::post('/data-master/unit', [UnitKerjaController::class, 'tambah'])->name('master.unit.tambah');
    Route::post('/data-master/unit/{satker}', [UnitKerjaController::class, 'simpan'])->name('master.unit.simpan');
    Route::post('/data-master/unit/{satker}/saklar', [UnitKerjaController::class, 'saklar'])->name('master.unit.saklar');
    Route::post('/data-master/unit/{satker}/penanggung-jawab', [UnitKerjaController::class, 'penanggungJawab'])->name('master.unit.pj');
    Route::get('/data-master/unit/{satker}/irm', [UnitKerjaController::class, 'cariIrm'])->name('master.unit.irm');
    /* Pengguna & hak akses (petugas pusat, dari direktori pegawai). */
    Route::get('/data-master/pengguna/direktori', [PenggunaController::class, 'cari'])->name('master.pengguna.cari');
    Route::post('/data-master/pengguna', [PenggunaController::class, 'tambah'])->name('master.pengguna.tambah');
    Route::post('/data-master/pengguna/{pengguna}', [PenggunaController::class, 'simpan'])->name('master.pengguna.simpan');
    Route::post('/data-master/pengguna/{pengguna}/saklar', [PenggunaController::class, 'saklar'])->name('master.pengguna.saklar');
    Route::post('/data-master/pengguna/{pengguna}/sesi', [PenggunaController::class, 'sesi'])->name('master.pengguna.sesi');

    /* Log aktivitas — DTI dan Admin. */
    Route::get('/log-aktivitas', [LogController::class, 'index'])->name('log');
    Route::get('/log-aktivitas/unduh', [LogController::class, 'unduh'])->name('log.unduh')->middleware('throttle:unduh');
    Route::post('/log-aktivitas/alert/{alert}/tinjau', [AlertKeamananController::class, 'tinjau'])->name('log.alert.tinjau');

    Route::post('/data-master/sumber-bukti', [SumberBuktiController::class, 'simpan'])->name('master.sumber.tambah');
    Route::post('/data-master/sumber-bukti/{sumber}', [SumberBuktiController::class, 'simpan'])->name('master.sumber.simpan');
    Route::get('/berkas/link', [BerkasController::class, 'tautan'])->name('berkas.link')->middleware('signed');
    Route::get('/berkas/{lampiran}/buka', [BerkasController::class, 'buka'])->name('berkas.buka');
    Route::get('/berkas/{lampiran}', [BerkasController::class, 'show'])->name('berkas.show');

    /* Profil & pengaturan akun sendiri. Password dikelola eHRM PU. */
    Route::get('/akun', [AkunController::class, 'index'])->name('akun');
    Route::post('/akun/sesi', [AkunController::class, 'sesi'])->name('akun.sesi');
    Route::post('/akun/setelan', [AkunController::class, 'setelan'])->name('akun.setelan');
    Route::post('/akun/tampilan', [AkunController::class, 'tampilan'])->name('akun.tampilan');
});
