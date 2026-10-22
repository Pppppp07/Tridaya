{{-- Jendela pemilih penanggung jawab dari IRM, digambar server (`?pj=`); skrip mencarikan
     sambil mengetik. --}}
@php
  $u = $pilih['unit'];
  $tutupPj = route('master', ['tab' => 'unit', 'kartu' => 'unit-'.$u->id]);
@endphp
<div class="tirai" data-tirai-pj data-tutup="{{ $tutupPj }}">
  <div class="lembar pilihpj" role="dialog" aria-modal="true" aria-label="Penanggung jawab {{ $u->namaPendek() }}">
    <div class="kep">
      <span class="ic-kotak biru"><x-ikon n="Users" :s="17" /></span>
      <span class="judul">
        <b>Penanggung jawab {{ $u->namaPendek() }}</b>
        <span>Cari di IRM dengan nama atau NIP. Nama, NIP, dan email terisi otomatis.</span>
      </span>
      <a class="tutup" href="{{ $tutupPj }}" aria-label="Tutup"><x-ikon n="X" :s="16" /></a>
    </div>
    <div class="bdn">
      @if(session('gagal'))
        <div class="pesan bad" style="margin-top:0"><x-ikon n="AlertTriangle" :s="16" /><span>{{ session('gagal') }}</span></div>
      @endif
      <form method="get" action="{{ route('master') }}" class="cari-irm" data-cari-irm
        data-sumber="{{ route('master.unit.irm', $u) }}">
        <x-ikon n="Search" :s="15" />
        <input type="hidden" name="pj" value="{{ $u->id }}">
        <input type="text" name="q" value="{{ $pilih['q'] }}" placeholder="Ketik nama atau NIP"
          aria-label="Cari pegawai di IRM" autocomplete="off" autofocus>
      </form>
      <div data-hasil-irm>@include('data-master.hasil-irm', $pilih)</div>
    </div>
  </div>
</div>
