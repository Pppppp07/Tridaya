@php
  $hasilJejak = \App\Support\TampilanLog::hasil($x);
  $asalJejak = \App\Support\TampilanLog::asal($x);
  $namaJejak = \App\Support\TampilanLog::nama($x) ?: 'Sistem';
  $labelJejak = $x->peran ? $x->sebutanPeran() : $asalJejak;
@endphp
<tr>
  <td class="dm-waktu" data-label="Waktu">{{ \App\Support\Tampil::waktuLog($x->waktu) }}</td>
  <td data-label="Oleh">
    <span class="dm-orang"><b>{{ $namaJejak }}</b>
      @if($labelJejak !== $namaJejak)<span>{{ $labelJejak }}</span>@endif
    </span>
  </td>
  <td data-label="Aktivitas"><span class="dm-ubah">{{ \App\Support\TampilanLog::ringkasan($x) }}</span></td>
  <td data-label="Hasil"><span class="lg-resultat {{ $hasilJejak['kelas'] }}">{{ $hasilJejak['nama'] }}</span></td>
  <td class="dm-log-detail" data-label="Detail"><a class="dm-log-tautan" href="{{ route('log', ['kejadian' => $x->id, 'rinci' => $x->id]) }}" aria-label="Detail log #{{ $x->id }}">Detail <x-ikon n="ArrowRight" :s="12" /></a></td>
</tr>
