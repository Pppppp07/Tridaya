<span class="hint bukti-link-hint">
  Link satu file dari {{ implode(', ', array_column(\App\Support\KebijakanBukti::sumber(), 'nama')) ?: 'sumber yang perlu diaktifkan Admin DTI' }}.
  <x-info teks="Link harus membuka satu dokumen bukti dari sumber yang diizinkan. Pastikan pemeriksa dapat mengaksesnya. Alamat yang diterima belum berarti isi dokumen sudah disetujui." />
</span>
