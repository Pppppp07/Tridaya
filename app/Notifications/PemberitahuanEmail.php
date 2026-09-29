<?php

namespace App\Notifications;

use App\Models\Notifikasi;
use App\Support\Terlihat;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Pemberitahuan untuk satuan kerja, dikirim juga ke email penanggung jawabnya — dan,
 * sejak 28 Sep, ke petugas pusat yang menyalakannya di Profil → Pemberitahuan.
 *
 * Kata Bang Kamal: "Notif email lah! Lu kalau kayak begini nih maaf, lu hanya
 * model konsep manual pindah ke elektronik." Pemberitahuan yang cuma menunggu dibuka di
 * aplikasi tidak menjangkau orang yang sedang tidak membukanya.
 *
 * Isinya dibatasi sama seperti di aplikasi: kalimatnya disamarkan dari nama
 * satuan kerja lain, dan link-nya membuka pemberitahuan itu sendiri — yang kembali
 * memeriksa hak lihatnya.
 */
class PemberitahuanEmail extends Notification
{
    public function __construct(public Notifikasi $pemberitahuan) {}

    /** @return list<string> */
    public function via(object $penerima): array
    {
        return ['mail'];
    }

    public function toMail(object $penerima): MailMessage
    {
        $k = $this->pemberitahuan->loadMissing('rekomendasi.temuan');
        $r = $k->rekomendasi;
        /* Hanya akun satuan kerja yang melihat pemberitahuannya disamarkan; petugas
           pusat pun bisa tercatat di sebuah unit (Setba di Sekretariat BPSDM). */
        $unit = $penerima->peran === \App\Enums\PeranPengguna::SATKER ? $penerima->satker : null;
        $aksi = $unit ? Terlihat::teksUntuk($k->aksi, $unit) : $k->aksi;

        return (new MailMessage)
            ->subject('Tindak lanjut '.$r->kode.' — '.Str::limit((string) $aksi, 80))
            ->greeting('Yth. '.$penerima->name.',')
            ->line($k->label_pelaku.': '.$aksi.'.')
            ->line('Rekomendasi '.$r->kode.' — '.$r->temuan?->judul)
            ->action('Buka di aplikasi', route('pemberitahuan.buka', $k))
            /* Sejak 28 Sep petugas pusat juga bisa menerimanya, bila ia sendiri
               menyalakannya; keduanya diberi tahu tempat mematikannya. */
            ->salutation(($penerima->peran === \App\Enums\PeranPengguna::SATKER
                ? 'Email ini dikirim karena Anda penanggung jawab tindak lanjut '.($unit?->namaPendek() ?? 'unit kerja Anda').'.'
                : 'Email ini dikirim karena Anda mengaktifkan pemberitahuan lewat email.')
                .' Atur di Profil & pengaturan → Pemberitahuan.');
    }
}
