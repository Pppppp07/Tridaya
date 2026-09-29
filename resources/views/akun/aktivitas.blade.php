@php
  use App\Support\Aktivitas;
  use App\Support\Tampil;

  /* Tab Aktivitas saya — padanan `TabAktivitasAkun` prototipe: log aktivitas
     milik akun ini saja, tanpa masuk dan keluar (tab Keamanan). */
@endphp
<x-dm-kepala judul="Aktivitas akun" ket="Aktivitas Anda di aplikasi ini, terbaru di atas."
  :info="['Catatannya sama dengan Log aktivitas milik DTI: tidak bisa diubah atau dihapus siapa pun.',
    'Masuk dan keluar ada di tab Keamanan.']" />
{{-- Satu formulir GET: mengganti pilihan langsung mengirimnya (skrip, sama
     dengan Log aktivitas), tanpa skrip lewat tombol Terapkan. --}}
<form method="get" action="{{ route('akun') }}" class="lg-filter" data-log-filter>
  <input type="hidden" name="tab" value="aktivitas">
  <label class="lg-pilih"><span>Waktu</span>
    <select name="rentang">
      @foreach($rentang as $k => $v)<option value="{{ $k }}" @selected($f['rentang'] === (string) $k)>{{ $v }}</option>@endforeach
    </select>
  </label>
  <label class="lg-pilih"><span>Kelompok</span>
    <select name="kelompok">
      <option value="">Semua</option>
      @foreach(Aktivitas::KELOMPOK as $k => $v)<option value="{{ $k }}" @selected($f['kelompok'] === $k)>{{ $v }}</option>@endforeach
    </select>
  </label>
  <button type="submit" class="btn" data-tanpa-js>Terapkan</button>
</form>
<div class="dm-ringkas"><span><b>{{ $log->total() }}</b> aktivitas</span></div>
@if($log->count())
  <div class="dm-tabel">
    <table>
      <thead><tr><th>Waktu</th><th>Aktivitas</th><th>Objek</th></tr></thead>
      <tbody>
        @foreach($log as $x)
          @php $o = $subjek[$x->subjek_tipe.':'.$x->subjek_id] ?? null; @endphp
          <tr>
            <td class="dm-waktu" data-label="Waktu">{{ Tampil::waktuLog($x->waktu) }}</td>
            <td data-label="Aktivitas"><span class="dm-ubah">{{ $x->ringkasan }}</span>@include('bagian.beda-log', ['rincian' => $x->rincian])</td>
            <td data-label="Objek">
              @if($o && $o['url'])<a class="lg-objek" href="{{ $o['url'] }}">{{ $o['teks'] }}</a>
              @elseif($o)<span class="dm-sub">{{ $o['teks'] }}</span>
              @else<span class="dm-sub">—</span>@endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
@else
  <div class="kosong dm-kosong-kotak">Belum ada aktivitas pada pilihan ini.</div>
@endif
@if($log->lastPage() > 1)
  <nav class="lg-halaman" aria-label="Halaman aktivitas">
    @if($log->onFirstPage())
      <span class="btn btn-s" aria-disabled="true"><x-ikon n="ChevronLeft" :s="13" /> Sebelumnya</span>
    @else
      <a class="btn btn-s" href="{{ $log->previousPageUrl() }}"><x-ikon n="ChevronLeft" :s="13" /> Sebelumnya</a>
    @endif
    <span>Halaman {{ $log->currentPage() }} dari {{ $log->lastPage() }}</span>
    @if($log->hasMorePages())
      <a class="btn btn-s" href="{{ $log->nextPageUrl() }}">Berikutnya <x-ikon n="ChevronRight" :s="13" /></a>
    @else
      <span class="btn btn-s" aria-disabled="true">Berikutnya <x-ikon n="ChevronRight" :s="13" /></span>
    @endif
  </nav>
@endif
