<?php

namespace App\Notifications;

use App\Enums\HasilTelaah;
use App\Enums\PeranPengguna;
use App\Models\Notifikasi;
use App\Support\BedaLog;
use App\Support\Pemberitahuan;
use App\Support\Tampil;
use App\Support\Terlihat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Support\Str;

/**
 * Pemberitahuan lewat email ke penanggung jawab satuan kerja, dan ke petugas pusat yang
 * menyalakannya. Isinya disamarkan seperti di aplikasi; link-nya membuka pemberitahuan (hak lihat
 * diperiksa lagi).
 *
 * Lewat antrean `email`: halaman tidak menunggu server email, dan satu pekerja latar mengirimnya
 * berurutan dengan batas per menit (`email-keluar`), jadi lonjakan aksi tidak membanjiri server.
 */
class PemberitahuanEmail extends Notification implements ShouldQueue
{
    use Queueable;

    /** Server email yang sedang bermasalah dicoba lagi bertahap; error isi berhenti sesudah 3 kali. */
    public int $maxExceptions = 3;

    /** Warna nada pemberitahuan (Pemberitahuan::rupa) = warna keadaan di aplikasi. */
    private const NADA = [
        'merah' => ['#DC2626', '#FEF2F2'], 'kuning' => ['#B45309', '#FFFBEB'],
        'hijau' => ['#15803D', '#F0FDF4'], 'biru' => ['#1D4ED8', '#EFF6FF'],
    ];

    public function __construct(public Notifikasi $pemberitahuan)
    {
        $this->onQueue('email');
        $this->afterCommit();
    }

    /**
     * Batas laju hanya untuk pekerja antrean: tanpa antrean (sync) pekerjaan yang ditahan tidak
     * dikembalikan ke antrean, emailnya hilang.
     *
     * @return list<object>
     */
    public function middleware(object $penerima, string $channel): array
    {
        return ($this->connection ?? config('queue.default')) === 'sync' ? [] : [new RateLimited('email-keluar')];
    }

    /** Ditahan pembatas tidak dihitung gagal; menyerah sesudah 12 jam. */
    public function retryUntil(): \DateTimeInterface
    {
        return now()->addHours(12);
    }

    /** @return list<int> detik jeda sebelum mencoba lagi */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    /** @return list<string> */
    public function via(object $penerima): array
    {
        return ['mail'];
    }

    public function toMail(object $penerima): MailMessage
    {
        $k = $this->pemberitahuan->loadMissing('rekomendasi.temuan.laporan', 'satker', 'tindakan.bentuk', 'log');
        if ($k->sistem()) {
            return $this->perubahanSistem($penerima, $k);
        }
        $r = $k->rekomendasi;
        /* Hanya akun satuan kerja dan pimpinan yang melihat pemberitahuan disamarkan: nama Satker
           di luar cakupannya diganti. */
        $unit = $penerima->peran === PeranPengguna::SATKER
            && in_array('satker', $k->untuk_peran, true) && $k->satker->contains('id', $penerima->satker_id)
            ? collect([$penerima->satker])->filter()->values() : null;
        if (! $unit && $penerima->punyaPeran(PeranPengguna::PIMPINAN)
            && in_array('pimpinan', $k->untuk_peran, true)
            && ! collect($k->untuk_peran)->contains(fn ($p) => ! in_array($p, ['satker', 'pimpinan'], true) && $penerima->punyaPeran($p))) {
            $unit = $k->satker->whereIn('id', $penerima->satkerDipimpin())->values();
        }
        $unit = $unit?->isNotEmpty() ? $unit : null;
        $namaUnit = $unit?->map->namaPendek()->join(', ');
        $aksi = $unit ? Terlihat::teksUntuk($k->aksi, $unit) : $k->aksi;
        $judul = $unit ? Terlihat::teksUntuk($r->temuan?->judul, $unit) : $r->temuan?->judul;
        $lap = $r->temuan?->laporan;
        $catatan = ($unit
            ? 'Email ini dikirim untuk pemantauan tindak lanjut '.$namaUnit.'.'
            : 'Email ini dikirim karena Anda mengaktifkan pemberitahuan lewat email.')
            .' Atur di Profil & pengaturan → Pemberitahuan.';
        $rupa = Pemberitahuan::rupa($k);
        /* Kalimat pemberitahuan bersambung "—": bagian pertama jadi judul, sisanya baris keterangan. */
        $bagian = array_values(array_filter(array_map('trim', explode(' — ', rtrim((string) $aksi, '. ')))));
        /* Nama pengirim bawaan kerangka diganti nama aplikasi; yang sengaja diatur di .env dipakai. */
        $pengirim = (string) config('mail.from.name');

        return (new MailMessage)
            ->from((string) config('mail.from.address'), in_array($pengirim, ['', 'Laravel', 'Example', 'Tridaya', 'tridaya', 'Rekala'], true) ? (string) config('app.display_name') : $pengirim)
            ->subject('Tindak lanjut '.$r->kode.' — '.Str::limit((string) $aksi, 80))
            ->greeting('Yth. '.$penerima->name.',')
            ->line($k->label_pelaku.': '.$aksi.'.')
            ->line('Rekomendasi '.$r->kode.' — '.$judul)
            ->action('Buka di aplikasi', route('pemberitahuan.buka', $k))
            /* Penerima diberi tahu tempat mematikannya. */
            ->salutation($catatan)
            ->view(['html' => 'email.pemberitahuan', 'text' => 'email.pemberitahuan-teks'], [
                'aksi' => rtrim((string) $aksi, '. ').'.',
                'judulPesan' => $bagian[0] ?? (string) $aksi,
                'barisPesan' => array_map(fn ($s) => Str::ucfirst($s), array_slice($bagian, 1)),
                'pelaku' => $k->label_pelaku,
                'waktu' => Tampil::tglJam($k->waktu).' WIB',
                'jenis' => match (true) {
                    str_contains($bagian[0] ?? '', 'SIPTL') => 'SIPTL',
                    str_contains((string) $k->aksi, 'Rekomendasi baru') => 'Rekomendasi baru',
                    $rupa['nada'] === 'merah' => 'Perlu perhatian',
                    $rupa['nada'] === 'kuning' => 'Perlu dilengkapi',
                    $rupa['nada'] === 'hijau' => HasilTelaah::M->nama($r->jenis()),
                    default => 'Tindak lanjut',
                },
                'warna' => self::NADA[$rupa['nada']] ?? self::NADA['biru'],
                'rincian' => array_filter([
                    'Laporan' => $lap ? $lap->sumber->value.' '.$lap->nomor : null,
                    'Rekomendasi' => $r->kode,
                    'Temuan' => $judul,
                    'Bentuk tindak lanjut' => $k->tindakan?->bentuk?->nama,
                    'Satuan kerja' => $unit ? $namaUnit : ($k->satker->map->namaPendek()->join(', ') ?: null),
                ]),
            ]);
    }

    /** Perubahan Data Master oleh Admin DTI lain: isi perubahannya dari log, tombol ke Log admin. */
    private function perubahanSistem(object $penerima, Notifikasi $k): MailMessage
    {
        $log = $k->log;
        $pengirim = (string) config('mail.from.name');
        $catatan = 'Email ini dikirim karena Anda mengaktifkan pemberitahuan lewat email. Atur di Profil & pengaturan → Pemberitahuan.';
        $beda = array_map(fn ($b) => $b['nama'].': '.($b['ada_sebelum'] ? $b['sebelum'].' → ' : '').$b['sesudah'],
            BedaLog::butir($log?->rincian));

        return (new MailMessage)
            ->from((string) config('mail.from.address'), in_array($pengirim, ['', 'Laravel', 'Example', 'Tridaya', 'tridaya', 'Rekala'], true) ? (string) config('app.display_name') : $pengirim)
            ->subject('Perubahan sistem — '.Str::limit((string) $k->aksi, 80))
            ->greeting('Yth. '.$penerima->name.',')
            ->line($k->label_pelaku.': '.$k->aksi.'.')
            ->action('Lihat di Log admin', route('pemberitahuan.buka', $k))
            ->salutation($catatan)
            ->view(['html' => 'email.pemberitahuan', 'text' => 'email.pemberitahuan-teks'], [
                'aksi' => rtrim((string) $k->aksi, '. ').'.',
                'judulPesan' => rtrim((string) $k->aksi, '. '),
                'barisPesan' => array_slice($beda, 0, 6),
                'pelaku' => $k->label_pelaku,
                'waktu' => Tampil::tglJam($k->waktu).' WIB',
                'jenis' => 'Perubahan sistem',
                'warna' => self::NADA['biru'],
                'rincian' => array_filter([
                    'Bagian' => Pemberitahuan::bagianSistem($k),
                    'Oleh' => $log?->nama ? $log->nama.($log->peran ? ' ('.$log->sebutanPeran().')' : '') : $k->label_pelaku,
                ]),
            ]);
    }
}
