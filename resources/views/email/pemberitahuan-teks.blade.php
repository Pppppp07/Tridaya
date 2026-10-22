{{-- Versi teks biasa untuk aplikasi email tanpa HTML; tidak di-escape karena bukan HTML. --}}
{!! $greeting !!}

[{!! $jenis !!}] {!! $judulPesan !!}
@foreach($barisPesan as $baris)
- {!! $baris !!}
@endforeach
Dari {!! $pelaku !!} · {!! $waktu !!}

@foreach($rincian as $label => $isi)
{!! $label !!}: {!! $isi !!}
@endforeach

{!! $actionText !!}: {!! $actionUrl !!}

{!! $salutation !!}

--
{{ config('app.display_name') }} · Pemantauan Tindak Lanjut BPSDM
Email ini dikirim otomatis, mohon tidak dibalas.
