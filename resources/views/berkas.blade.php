@extends('rangka')
@section('judul', 'Preview berkas')
@section('isi')
@php use App\Support\Tampil; @endphp

<a class="btn" href="{{ url()->previous() }}" style="margin-bottom:16px">&larr; Kembali</a>

<div class="kartu" style="max-width:560px">
  <div class="mono" style="font-size:13px;word-break:break-all">{{ $b->nama_asli }}</div>
  <div class="lbl" style="margin-top:4px">
    {{ $b->jenis() }} &middot; {{ Tampil::tgl($b->diunggah_pada) }}
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

  @if($b->link)
    @if($linkInfo['boleh'] && ! $b->ditolak_pada)
      <p class="hint">{{ $linkInfo['alasan'] }}</p>
      <a class="btn btn-pri" href="{{ route('berkas.buka', $b) }}" target="_blank" rel="noopener noreferrer">Buka dokumen <x-ikon n="ExternalLink" :s="14" /></a>
    @else
      <div class="pesan bad" role="status">Perlu diperbarui. {{ $b->ditolak_pada ? 'Bukti ditolak: '.$b->alasan_penolakan : $linkInfo['alasan'] }}</div>
    @endif
    <p class="mono lbl" style="word-break:break-all">{{ $b->link }}</p>
  @else
    <p class="hint">Belum ada link dokumen asli. Arsip keterangannya tetap tersedia.</p>
  @endif
</div>
@endsection
