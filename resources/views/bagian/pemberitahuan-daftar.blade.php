@php
  use App\Support\Pemberitahuan;

  /* Pemberitahuan per hari, terbaru di atas. `$tab`: "belum" atau "semua" (panel: `$semuaId`, 40
     terbaru). */
  $u = auth()->user();
  $tab ??= 'semua';
  $tampilkah = fn ($k) => $tab === 'belum' ? ! Pemberitahuan::sudahDibaca($k, $u)
    : (! isset($semuaId) || in_array($k->id, $semuaId, true));
@endphp
@foreach(Pemberitahuan::kelompokHari($daftar) as $g)
  @php $ada = $g['isi']->contains($tampilkah); @endphp
  <section class="kb-hari" aria-label="{{ $g['label'] }}" @if(! $ada) hidden @endif>
    <h4 class="kb-hari-kep">{{ $g['label'] }}</h4>
    <ul class="kb-daftar">
      @foreach($g['isi'] as $k)
        @include('bagian.pemberitahuan-butir', ['k' => $k, 'lengkap' => $lengkap ?? false,
          'semua' => ! isset($semuaId) || in_array($k->id, $semuaId, true), 'sembunyi' => ! $tampilkah($k)])
      @endforeach
    </ul>
  </section>
@endforeach
