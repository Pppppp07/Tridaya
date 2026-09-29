@extends('rangka')
@section('judul', 'Preview berkas')
@section('isi')
@php use App\Support\Tampil; @endphp

<a class="btn" href="{{ url()->previous() }}" style="margin-bottom:16px">&larr; Kembali</a>

<div class="kartu" style="max-width:560px">
  <div class="mono" style="font-size:13px;word-break:break-all">{{ $b->nama_asli }}</div>
  <div class="lbl" style="margin-top:4px">
    {{ $b->jenisDokumen?->nama }} &middot; {{ Tampil::tgl($b->diunggah_pada) }}
  </div>

  <div style="margin:22px 0;padding:36px 30px;background:#fff;border:1px solid var(--rule);
              border-radius:3px;position:relative;overflow:hidden">
    <div style="position:absolute;inset:0;display:grid;place-items:center;pointer-events:none">
      <span style="transform:rotate(-22deg);font-family:var(--mono);font-size:15px;letter-spacing:.12em;
                   line-height:1.5;text-align:center;color:rgba(163,61,42,.2);
                   border:3px solid rgba(163,61,42,.2);border-radius:7px;padding:10px 16px">
        CONTOH<br>BUKAN DOKUMEN ASLI
      </span>
    </div>
    @for($i = 0; $i < 6; $i++)
      <div style="height:8px;border-radius:2px;background:#EFF1ED;margin-bottom:10px;
                  width:{{ [84, 100, 62, 100, 78, 44][$i] }}%"></div>
    @endfor
  </div>

  <div class="hint" style="line-height:1.6">
    Data contoh belum memiliki berkas asli di penyimpanan. Pada sistem resmi, berkas
    aslinya terbuka di sini lewat akses yang terotorisasi &mdash; bukan link langsung
    ke folder penyimpanan.
  </div>
</div>
@endsection
