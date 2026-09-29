{{-- Keadaan kosong tiap tab — padanan `PemberitahuanKosong`. Skrip memilih yang tampil. --}}
<div class="kb-kosong" data-kosong-pemberitahuan="belum" @if(($tab ?? '') !== 'belum') hidden @endif>
  <x-ikon n="CheckCircle2" :s="22" />
  <b>Semua sudah dibaca</b>
  <span>Pemberitahuan yang datang sesudah ini muncul di sini.</span>
</div>
<div class="kb-kosong" data-kosong-pemberitahuan="semua" @if(($tab ?? '') !== 'semua') hidden @endif>
  <x-ikon n="Bell" :s="22" />
  <b>Belum ada pemberitahuan</b>
  <span>Pemberitahuan tentang berkas Anda akan muncul di sini.</span>
</div>
