<x-dm-kepala judul="Sumber link bukti" ket="Atur tempat penyimpanan yang boleh digunakan. Berlaku saat link disimpan dan dibuka." :info="[
  'Daftar layanan penyimpanan yang boleh digunakan untuk link bukti.',
  'Domain dan jalur menentukan alamat yang diterima. Sumber nonaktif tidak dapat digunakan untuk menyimpan atau membuka link bukti.',
  'Izin sumber hanya berlaku untuk alamat link; isi dokumen tetap diperiksa oleh pemeriksa.',
]" />
@if($errors->any())
  <div class="pesan bad dm-pesan" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<div class="dm-tabel" tabindex="0" role="region" aria-label="Daftar sumber link bukti">
  <table>
    <thead><tr><th>Sumber</th><th>Domain & jalur</th><th>Status</th><th>Aksi</th></tr></thead>
    <tbody>
    @foreach($sumberBukti as $s)
      <tr>
        <td><b>{{ $s->nama }}</b><div class="dm-sub">{{ $s->pola === 'repositori' ? 'Penyimpanan instansi' : 'Link satu file' }}</div></td>
        <td><span class="mono">{{ $s->host }}</span>@if($s->pola === 'repositori')<div class="dm-sub mono">{{ $s->prefix }}</div>@else<div class="dm-sub">Pola link satu file Google Drive/Docs mengikuti aturan bawaan aplikasi.</div>@endif</td>
        <td><span class="dm-keadaan {{ $s->aktif ? 'aktif' : '' }}">{{ $s->aktif ? 'Diizinkan' : 'Nonaktif' }}</span></td>
        <td>
          @php
            $ulang = (string) old('sumber_id') === (string) $s->id;
            $aktifIsian = $ulang ? (bool) old('aktif') : $s->aktif;
          @endphp
          <details class="bukti-sumber-edit" @if($ulang) open @endif><summary class="btn btn-s">Ubah</summary>
            <form method="post" action="{{ route('master.sumber.simpan', $s) }}" class="bukti-sumber-form">
              @csrf
              <input type="hidden" name="sumber_id" value="{{ $s->id }}">
              <label>Nama sumber<input type="text" name="nama" value="{{ $ulang ? old('nama') : $s->nama }}" maxlength="100" required></label>
              <label>Domain<input type="text" name="host" value="{{ $ulang ? old('host') : $s->host }}" maxlength="253" required><span class="dm-sub">Nama host, tanpa https:// atau jalur berkas.</span></label>
              @if($s->pola === 'repositori')<label>Jalur berkas<input type="text" name="prefix" value="{{ $ulang ? old('prefix') : $s->prefix }}" maxlength="200" required><span class="dm-sub">Awal jalur setelah domain, misalnya /dokumen/. Bukan lokasi folder di komputer.</span></label>
              @endif
              <label>Status<select name="aktif"><option value="1" @selected($aktifIsian)>Diizinkan</option><option value="0" @selected(! $aktifIsian)>Nonaktif</option></select></label>
              <button class="btn btn-pri btn-s" type="submit">Simpan</button>
            </form>
          </details>
        </td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>
<div class="dm-bagian bukti-sumber-catatan">
<details class="bukti-sumber-tambah" @if(old('sumber_id') === 'baru') open @endif>
  <summary class="btn btn-s"><x-ikon n="Plus" :s="14" /> Tambah sumber resmi</summary>
  <form method="post" action="{{ route('master.sumber.tambah') }}" class="bukti-sumber-form">
    @csrf
    <input type="hidden" name="sumber_id" value="baru">
    <label>Nama sumber<input type="text" name="nama" value="{{ old('sumber_id') === 'baru' ? old('nama') : '' }}" maxlength="100" placeholder="Arsip dokumen instansi" required></label>
    <label>Domain<input type="text" name="host" value="{{ old('sumber_id') === 'baru' ? old('host') : '' }}" maxlength="253" placeholder="arsip.instansi.go.id" required><span class="dm-sub">Nama host, tanpa https:// atau jalur berkas.</span></label>
    <label>Jalur berkas<input type="text" name="prefix" value="{{ old('sumber_id') === 'baru' ? old('prefix') : '' }}" maxlength="200" placeholder="/dokumen/" required><span class="dm-sub">Awal jalur setelah domain, misalnya /dokumen/. Bukan lokasi folder di komputer.</span></label>
    <input type="hidden" name="aktif" value="1">
    <button class="btn btn-pri btn-s" type="submit">Tambahkan</button>
  </form>
</details>
<p class="dm-sub">Link folder, halaman utama, dan pengalihan bebas ditolak. Domain yang diizinkan tetap memerlukan review isi dokumen. Perubahan aturan tidak menghapus arsip lama.</p>
<p class="dm-sub"><a href="{{ route('log', ['tab' => 'alert']) }}">Tinjau alert link</a> · <a href="{{ $keTab('riwayat') }}">Log perubahan aturan</a></p>
</div>
