@php
  /* Email pemberitahuan. Gaya ditulis di tiap unsur dan tata letaknya tabel: banyak aplikasi
     email membuang <style> dan tidak mengenal flex/grid. Logo disematkan, bukan dimuat dari luar. */
  $huruf = "font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;";
  $logo = isset($message) ? $message->embed(resource_path('images/email/infax-simbol.png')) : null;
  [$nada, $nadaBg] = $warna;
@endphp
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>{{ $subject }}</title>
{{-- Layar ponsel: jarak dalam kartu dikurangi. Aplikasi yang membuang <style> tetap memakai gaya di unsur. --}}
<style>
@media (max-width:620px) {
  .isi-kartu { padding:22px 20px 24px !important; }
  .label-rincian { width:40% !important; }
  .judul-pesan { font-size:17px !important; }
}
</style>
</head>
<body style="margin:0;padding:0;background:#F3F5F8;{{ $huruf }}">
{{-- Cuplikan di daftar kotak masuk. --}}
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:#F3F5F8;">{{ $pelaku }}: {{ $aksi }}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F3F5F8;">
<tr><td align="center" style="padding:28px 12px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;">

  {{-- Kepala: logo dan nama aplikasi. --}}
  <tr><td style="padding:0 4px 16px;">
    <table role="presentation" aria-label="{{ config('app.display_name') }}" cellpadding="0" cellspacing="0" border="0"><tr>
      @if($logo)
        <td style="padding-right:10px;vertical-align:middle;"><img src="{{ $logo }}" width="36" height="36" alt="" style="display:block;border:0;border-radius:8px;"></td>
      @endif
      <td style="vertical-align:middle;{{ $huruf }}">
        <div style="font-size:19px;line-height:22px;font-weight:700;color:#155B59;letter-spacing:-.2px;">@if(config('app.display_name') === 'infaX')infa<span style="color:#C96F51;">X</span>@else{{ config('app.display_name') }}@endif</div>
        <div style="font-size:12px;line-height:16px;color:#6B7280;">Pemantauan Tindak Lanjut BPSDM</div>
      </td>
    </tr></table>
  </td></tr>

  {{-- Kartu: garis warna keadaan, pesan, rincian, tombol. --}}
  <tr><td style="background:#FFFFFF;border:1px solid #E5E7EB;border-radius:12px;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
      <tr><td style="height:4px;line-height:4px;font-size:0;background:{{ $nada }};border-radius:12px 12px 0 0;">&nbsp;</td></tr>
      <tr><td class="isi-kartu" style="padding:26px 32px 30px;{{ $huruf }}">

        <span style="display:inline-block;padding:3px 10px;border-radius:999px;background:{{ $nadaBg }};color:{{ $nada }};font-size:12px;line-height:18px;font-weight:600;">{{ $jenis }}</span>

        <p style="margin:18px 0 8px;font-size:15px;line-height:22px;color:#374151;">{{ $greeting }}</p>
        <p class="judul-pesan" style="margin:0 0 4px;font-size:18px;line-height:26px;font-weight:600;color:#111827;">{{ $judulPesan }}</p>
        @if($barisPesan)
          {{-- Keterangan per baris, bergaris tepi warna keadaan. --}}
          <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:10px 0 2px;"><tr>
            <td style="width:3px;background:{{ $nada }};border-radius:2px;font-size:0;line-height:0;">&nbsp;</td>
            <td style="padding:2px 0 2px 12px;{{ $huruf }}">
              @foreach($barisPesan as $baris)
                <p style="margin:0 0 {{ $loop->last ? '0' : '6px' }};font-size:14px;line-height:21px;color:#374151;">{{ $baris }}</p>
              @endforeach
            </td>
          </tr></table>
        @endif
        <p style="margin:12px 0 22px;font-size:13px;line-height:20px;color:#6B7280;">Dari {{ $pelaku }} &middot; {{ $waktu }}</p>

        @if($rincian)
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F9FAFB;border:1px solid #E5E7EB;border-radius:8px;">
            @foreach($rincian as $label => $isi)
              <tr>
                <td class="label-rincian" style="padding:10px 14px;{{ $loop->first ? '' : 'border-top:1px solid #EEF0F3;' }}width:36%;vertical-align:top;font-size:13px;line-height:19px;color:#6B7280;{{ $huruf }}">{{ $label }}</td>
                <td style="padding:10px 14px 10px 0;{{ $loop->first ? '' : 'border-top:1px solid #EEF0F3;' }}vertical-align:top;font-size:13px;line-height:19px;color:#111827;font-weight:500;{{ $huruf }}">{{ $isi }}</td>
              </tr>
            @endforeach
          </table>
        @endif

        {{-- Tombol = warna tombol utama di aplikasi. --}}
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:26px 0 0;"><tr>
          <td style="background:#2563EB;border-radius:8px;">
            <a href="{{ $actionUrl }}" target="_blank" style="display:inline-block;padding:12px 24px;font-size:14px;line-height:20px;font-weight:600;color:#FFFFFF;text-decoration:none;border-radius:8px;{{ $huruf }}">{{ $actionText }} &rarr;</a>
          </td>
        </tr></table>

        <p style="margin:24px 0 0;padding-top:18px;border-top:1px solid #EEF0F3;font-size:12px;line-height:19px;color:#6B7280;">{{ $salutation }}</p>
      </td></tr>
    </table>
  </td></tr>

  {{-- Link cadangan dan kaki. --}}
  <tr><td style="padding:18px 8px 0;font-size:12px;line-height:18px;color:#6B7280;{{ $huruf }}">
    Jika tombol "{{ $actionText }}" tidak bisa diklik, salin alamat berikut ke peramban:<br>
    <a href="{{ $actionUrl }}" style="color:#1D4ED8;word-break:break-all;">{{ $displayableActionUrl }}</a>
  </td></tr>
  <tr><td align="center" style="padding:22px 8px 4px;font-size:12px;line-height:18px;color:#9CA3AF;{{ $huruf }}">
    {{ config('app.display_name') }} &middot; Pemantauan Tindak Lanjut BPSDM<br>Email ini dikirim otomatis, mohon tidak dibalas.
  </td></tr>

</table>
</td></tr>
</table>
</body>
</html>
