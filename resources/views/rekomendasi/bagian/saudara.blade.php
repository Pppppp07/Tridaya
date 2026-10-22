@php $u = auth()->user(); @endphp
<span style="display:grid">
  @foreach($saudara as $s)
    <a class="linkrek" href="{{ route('rekomendasi.show', $s) }}">
      <span class="isi">
        {{ $s->uraian }}
        <span class="ket">
          <x-cap :jenis="$jenis" :rek="$s"
            :satker="$u->satkerAktifId()" />
          <span class="lbl" style="margin:0">{{ $s->sebutSatker($u->peranAktif(), $u->satkerAktifId()) }} · {{ $s->posisiRek()->label() }}</span>
        </span>
      </span>
      <x-ikon n="ChevronRight" :s="14" />
    </a>
  @endforeach
</span>
