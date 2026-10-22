{{-- Pilihan filter yang tidak menyisakan apa pun. Tanpa filter, yang kosong tab datanya: menghapus
     filter tidak menolong. --}}
<div class="dsb-kosong" style="min-height:0">
  @if(\App\Support\Dasbor::adaFilter($f) || $k['data'] === 'semua')
    Tidak ada tindak lanjut pada pilihan ini.
    <button type="submit" name="ubah" value="kosongkan" class="btn btn-s" style="margin-top:10px">
      <x-ikon n="RotateCcw" :s="13" /> Hapus semua filter
    </button>
  @else
    Belum ada tindak lanjut {{ $f['jenis'] }} di {{ mb_strtolower(\App\Support\Dasbor::DATA[$k['data']]['nama']) }}.
    <button type="submit" name="ubah" value="data:semua" class="btn btn-s" style="margin-top:10px">
      <x-ikon n="Layers" :s="13" /> Lihat semua data
    </button>
  @endif
</div>
