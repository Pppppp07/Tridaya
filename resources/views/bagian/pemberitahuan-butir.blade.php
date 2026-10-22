@php
  use App\Support\Pemberitahuan;

  /* Satu pemberitahuan: barisnya satu link (menandai dibaca lalu mengantar ke bagiannya); tombol
     di ujung menandai dibaca/belum tanpa pindah halaman. */
  $u = auth()->user();
  $r = $k->rekomendasi;
  $dibaca = Pemberitahuan::sudahDibaca($k, $u);
  $rupa = Pemberitahuan::rupa($k);
  $tujuan = Pemberitahuan::BAGIAN[$k->blok] ?? null;
  $bentuk = $k->tindakan?->bentuk?->nama;
  $sebut = Pemberitahuan::sebutSatker($k, $u);
  $lengkap ??= false;
  $semua ??= true;
@endphp
<li class="kb-butir{{ $dibaca ? '' : ' belum' }}" data-pemberitahuan="{{ $k->id }}" data-jenis="{{ Pemberitahuan::jenis($k) }}" @if($semua) data-semua @endif @if($sembunyi ?? false) hidden @endif>
  <a class="kb-buka" href="{{ route('pemberitahuan.buka', $k) }}"
    aria-label="{{ $dibaca ? '' : 'Belum dibaca. ' }}{{ $k->label_pelaku }} — {{ $k->aksi }}. {{ $k->sistem() ? 'Perubahan sistem' : ($r?->kode ?? $k->rekomendasi_id) }}, {{ $sebut }}.">
    <span class="kb-ikon {{ $rupa['nada'] }}" aria-hidden="true"><x-ikon :n="$rupa['ikon']" :s="$lengkap ? 17 : 15" /></span>
    <span class="kb-teks">
      <span class="kb-apa"><b>{{ $k->label_pelaku }}</b> — {{ $k->aksi }}</span>
      <span class="kb-meta">
        @if($k->sistem())
          <span>Perubahan sistem · {{ $sebut }}</span>
        @else
          <x-sumber :j="$r?->temuan->laporan->sumber ?? 'LHP'" />
          <span class="mono">{{ $r?->kode ?? $k->rekomendasi_id }}</span>
          <span>· {{ $sebut }}{{ $bentuk ? ' · '.$bentuk : '' }}</span>
        @endif
      </span>
      @if($lengkap && $k->sistem())
        <span class="kb-meta"><span class="kb-tujuan">Buka di Log admin <x-ikon n="ChevronRight" :s="12" /></span></span>
      @elseif($lengkap)
        <span class="kb-meta">
          <span>{{ $r?->temuan->judul ?? '—' }}</span>
          @if($tujuan)<span class="kb-tujuan">· Buka {{ $tujuan }} <x-ikon n="ChevronRight" :s="12" /></span>@endif
        </span>
      @endif
    </span>
    <span class="kb-sisi">
      <span class="kb-jam">{{ $k->waktu->format('H.i') }}</span>
      @if(in_array($k->id, $baruTadi ?? [], true))<span class="kb-lencana">Baru</span>@endif
    </span>
  </a>
  <form method="post" action="{{ route('pemberitahuan.tandai', $k) }}" class="kb-tandai-f" data-tandai-pemberitahuan>
    @csrf
    <input type="hidden" name="baca" value="{{ $dibaca ? 0 : 1 }}">
    <button type="submit" class="kb-tandai" title="{{ $dibaca ? 'Tandai belum dibaca' : 'Tandai dibaca' }}"
      aria-label="{{ $dibaca ? 'Tandai belum dibaca' : 'Tandai dibaca' }}">
      <x-ikon :n="$dibaca ? 'CircleDot' : 'Check'" :s="15" />
    </button>
  </form>
</li>
