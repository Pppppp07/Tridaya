{{-- Isi panel di bawah lonceng — padanan `PanelPemberitahuan` (26 Sep). Diambil
     skrip saat lonceng ditekan (PemberitahuanController@panel); tanpa skrip lonceng
     tetap link ke halaman Pemberitahuan. Memuat 40 pemberitahuan terbaru untuk tab
     "Semua" ditambah yang belum dibaca untuk tab "Belum dibaca". --}}
<div class="kb-kepala">
  <b>Pemberitahuan</b>
  <x-info :teks="\App\Support\Pemberitahuan::KET" />
  <span class="sela"></span>
  <button type="button" class="kb-semua" data-pemberitahuan-semua data-url="{{ route('pemberitahuan.semua') }}" @if(! $belum) hidden @endif>
    <x-ikon n="CheckCheck" :s="15" /> Tandai semua dibaca
  </button>
</div>
@include('bagian.pemberitahuan-tab', ['tab' => $tab, 'belum' => $belum, 'semua' => $semua, 'idIsi' => 'kb-isi'])
<div class="kb-isi" id="kb-isi" role="tabpanel" data-isi-pemberitahuan data-tab="{{ $tab }}">
  @include('bagian.pemberitahuan-daftar', ['daftar' => $daftar, 'lengkap' => false, 'semuaId' => $semuaId, 'tab' => $tab])
  @include('bagian.pemberitahuan-kosong', ['tab' => $kosong ? $tab : ''])
</div>
<div class="kb-kaki">
  <a class="link" href="{{ route('pemberitahuan') }}">Buka semua pemberitahuan <x-ikon n="ArrowRight" :s="14" /></a>
</div>
