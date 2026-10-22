@extends(\App\Support\Halaman::bagian() ? 'rangka-bagian' : 'rangka')
@section('judul', 'Data historis')
@section('isi')
<div class="body hrv">
  <div class="hrv-head"><div><a href="{{ route('laporan.index') }}" class="hrv-back">← Daftar laporan</a><h2>Upload & review data historis</h2><p>Periksa laporan lama sebelum digabungkan ke data sistem.</p></div></div>
  <ol class="hrv-steps" aria-label="Tahap impor"><li aria-current="step">1 · Upload file</li><li>2 · Review data</li><li>3 · Konfirmasi & impor</li></ol>
  <section class="hrv-card">
    <h3>Upload lembar pemantauan SETBA</h3>
    <p class="hint">Format .xlsx atau .csv · maksimal 2 MB dan 500 baris data per file. Gunakan judul kolom seperti lembar pemantauan SETBA: No LHP, Reff IDT, dan Rekomendasi Temuan Besar. Sesudah upload, sistem menampilkan isi file berdampingan dengan yang diisinya.</p>
    <form method="post" action="{{ route('laporan.historis.upload') }}" enctype="multipart/form-data" class="hrv-upload">
      @csrf
      <label class="fld"><span>File Excel atau CSV</span><input type="file" name="excel" accept=".xlsx,.csv" required></label>
      <button type="submit" class="btn btn-p"><x-ikon n="Upload" :s="16" /> Upload & review</button>
    </form>
    <p class="hint">Upload membuat draft pribadi. Angka Dashboard berubah setelah Anda mengonfirmasi impor.</p>
  </section>
  <section class="hrv-card">
    <div class="hrv-head"><h3>Upload Anda</h3><span class="hint">Draft tersimpan dan bisa dilanjutkan nanti.</span></div>
    <div class="tw"><table><thead><tr><th>File</th><th>Diupload</th><th class="num">Baris data</th><th>Status</th><th></th></tr></thead><tbody>
      @forelse($drafts as $d)
        <tr><td>{{ $d->nama_berkas }}</td><td>{{ \App\Support\Tampil::tgl($d->created_at) }}</td><td class="num">{{ $d->jumlah_baris }}</td><td><span class="hrv-badge {{ $d->diimpor_pada ? 'ok' : 'warn' }}">{{ $d->diimpor_pada ? 'Sudah diimpor' : 'Dalam review' }}</span></td><td><a class="btn btn-s" href="{{ route('laporan.historis.review', $d) }}">{{ $d->diimpor_pada ? 'Lihat hasil' : 'Lanjut review' }} →</a></td></tr>
      @empty
        <tr><td colspan="5" class="hrv-empty">Belum ada upload data historis.</td></tr>
      @endforelse
    </tbody></table></div>
    {{ $drafts->links() }}
  </section>
</div>
@endsection
