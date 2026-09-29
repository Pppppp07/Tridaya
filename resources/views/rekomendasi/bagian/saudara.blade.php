@php $u = auth()->user(); @endphp
{{-- Rekomendasi satu temuan saling berkaitan, dan pembacanya kerap perlu
     menyeberang ke sebelah. --}}
<span style="display:grid">
  @foreach($saudara as $s)
    <a class="linkrek" href="{{ route('rekomendasi.show', $s) }}">
      <span class="isi">
        {{ $s->uraian }}
        <span class="ket">
          <x-cap :s="$s->status" :jenis="$jenis" :rek="$s"
            :satker="$u->peran === \App\Enums\PeranPengguna::SATKER ? $u->satker_id : null" />
          <span class="lbl" style="margin:0">{{ $s->sebutSatker($u->peran, $u->satker_id) }} · {{ $s->posisiRek()->label() }}</span>
        </span>
      </span>
      <x-ikon n="ChevronRight" :s="14" />
    </a>
  @endforeach
</span>
