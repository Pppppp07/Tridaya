@php
  $tunai = ($st['jenis'] ?? 'setor') !== 'perbaikan';
  $adaBukti = ! empty(trim($st['berkas'] ?? '')) || ! empty($st['link']);
@endphp
{{-- Satu baris pemulihan — kotak tipis di dalam isian Pemulihan nilai (25 Sep).
     Nomor urutnya, keadaan lengkapnya, dan sebutan buktinya (bukti setor atau
     berita acara) ditulis ulang skrip menurut cara pemulihannya. --}}
<div class="brs" data-setor>
  <div class="kepala">
    <b data-judul-setor>Pemulihan</b>
    <span class="fb-catatan salah" data-setor-belum hidden>belum lengkap</span>
    <span style="flex:1"></span>
    <button type="button" class="fb-x" aria-label="Hapus baris pemulihan ini" data-hapus-setor><x-ikon n="X" :s="14" /></button>
  </div>
  {{-- Baris pertama: apa yang dilakukan, berapa, dan kapan. --}}
  <div class="trio lebar1">
    <label class="fld">
      <span class="lbl">Cara pemulihan</span>
      <select name="setoran[{{ $n }}][jenis]" data-f="jenis">
        <option value="setor" @selected($tunai)>Setoran ke kas negara</option>
        <option value="perbaikan" @selected(! $tunai)>Perbaikan fisik atau pengembalian barang</option>
      </select>
    </label>
    <label class="fld">
      <span class="lbl">Nilai yang dipulihkan</span>
      <input type="text" class="mono" inputmode="numeric" name="setoran[{{ $n }}][nilai]" value="{{ $st['nilai'] ?? '' }}" placeholder="0" data-f="nilai">
      <span class="hint" data-hint-nilai>&nbsp;</span>
    </label>
    <label class="fld">
      <span class="lbl" data-label-tanggal>{{ $tunai ? 'Tanggal setor' : 'Tanggal perbaikan selesai' }}</span>
      <input type="date" name="setoran[{{ $n }}][tanggal]" value="{{ $st['tanggal'] ?? '' }}" data-f="tanggal">
    </label>
  </div>
  {{-- Baris kedua: nomor-nomor rujukannya, bertiga sama lebar. --}}
  <div class="trio" data-hanya-tunai @if(! $tunai) hidden @endif>
    <label class="fld">
      <span class="lbl">Nomor SSBP</span>
      <input type="text" class="mono" name="setoran[{{ $n }}][ssbp]" value="{{ $st['ssbp'] ?? '' }}" placeholder="SSBP/2026/00/00000" data-f="ssbp">
    </label>
    <label class="fld">
      <span class="lbl">NTPN</span>
      <input type="text" class="mono" name="setoran[{{ $n }}][ntpn]" value="{{ $st['ntpn'] ?? '' }}" placeholder="16 karakter" maxlength="16" data-f="ntpn">
      <span class="hint" data-hint-ntpn>16 karakter</span>
    </label>
    <label class="fld">
      <span class="lbl">Nota Konfirmasi KPPN</span>
      <input type="text" class="mono" name="setoran[{{ $n }}][notaKppn]" value="{{ $st['notaKppn'] ?? '' }}" placeholder="NK-000/KPPN-000/2026" data-f="notaKppn">
    </label>
  </div>
  <label class="fld" style="max-width:300px" data-hanya-perbaikan @if($tunai) hidden @endif>
    <span class="lbl">Nomor berita acara</span>
    <input type="text" class="mono" name="setoran[{{ $n }}][noBa]" value="{{ $st['noBa'] ?? '' }}" placeholder="BA-000/PPK/2026" data-f="noBa">
  </label>
  {{-- Bukti melekat pada baris ini, bukan pada rekomendasi. --}}
  <div class="fld" style="margin-bottom:0">
    <span class="lbl" data-label-bukti>{{ $tunai ? 'Bukti setor (SSBP)' : 'Berita acara perbaikan' }}</span>
    <div class="fb-dok-brs" data-isi-bukti-setor @if(! $adaBukti) hidden @endif>
      <span style="flex:1;min-width:0">
        <span class="fb-dua">
          <input type="text" name="setoran[{{ $n }}][berkas]" value="{{ trim($st['berkas'] ?? '') }}" data-f="berkas" data-judul-bukti
            aria-label="{{ $tunai ? 'Judul bukti setor' : 'Judul berita acara' }}"
            placeholder="Judul, mis. {{ $tunai ? 'Bukti setor kas negara (SSBP)' : 'Berita acara perbaikan' }}">
          <input type="text" class="mono" name="setoran[{{ $n }}][link]" value="{{ $st['link'] ?? '' }}" data-f="link" data-link-bukti
            aria-label="{{ $tunai ? 'Link bukti setor' : 'Link berita acara' }}" placeholder="Link, mis. https://…">
        </span>
      </span>
      <button type="button" class="fb-x" aria-label="Hapus link bukti" data-hapus-bukti-setor><x-ikon n="X" :s="14" /></button>
    </div>
    <button type="button" class="fb-link" data-tambah-bukti-setor @if($adaBukti) hidden @endif>
      <x-ikon n="ExternalLink" :s="13" /> Tambah link bukti
    </button>
  </div>
</div>
