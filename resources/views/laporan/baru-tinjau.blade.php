@php
  use App\Enums\SumberLaporan;
  use App\Support\Tampil;

  /* Langkah 2 — tinjau. Yang dibaca di sini persis yang akan tersimpan: tiap
     tindak lanjut, siapa yang memikulnya, dan catatannya. */
  $s = $d['surat'];
  $sumber = SumberLaporan::from($s['sumber']);
  $administratif = $sifat->firstWhere('nama', 'Administratif')?->id;
  $nama = fn ($id) => $satker->firstWhere('id', (int) $id)?->namaPendek() ?? '—';
  $semuaSatker = collect($d['temuan'])->flatMap(fn ($t) => $t['satker'])->map(fn ($id) => (int) $id)->unique()->values();
  /* Satuan kerja yang muncul di lebih dari satu temuan disebut jumlahnya —
     padanan `DaftarSatker` prototipe. */
  $nTemuan = fn ($id) => collect($d['temuan'])->filter(fn ($t) => in_array($id, array_map('intval', $t['satker']), true))->count();
@endphp

<div class="card" style="margin-bottom:14px">
  <div style="display:flex;gap:9px;align-items:center;margin-bottom:12px">
    <x-sumber :j="$s['sumber']" />
    <span class="mono" style="font-size:12px">{{ $s['nomor'] }}</span>
  </div>
  <div class="duo">
    <x-meta label="Sumber">{{ $sumber->nama() }} — {{ $sumber->penerbit() }}</x-meta>
    <x-meta label="Tanggal surat">{{ Tampil::tgl($s['tgl_surat']) }}</x-meta>
    <x-meta label="Diterima">{{ Tampil::tgl($s['tgl_terima']) }}</x-meta>
    {{-- Ditumpuk sebagai keping, bukan disambung koma (Bang Kamal: "jangan
         dikoma-koma, kagak bagus"). --}}
    <x-meta label="Satuan kerja terperiksa">
      @if($semuaSatker->isEmpty())
        —
      @else
        <span class="kepingsatker">
          @foreach($semuaSatker as $id)
            <span class="keping">{{ $nama($id) }}@if($nTemuan($id) > 1)<i>{{ $nTemuan($id) }} temuan</i>@endif</span>
          @endforeach
        </span>
      @endif
    </x-meta>
  </div>
</div>

@foreach($d['temuan'] as $i => $t)
  <div class="temblok">
    <div class="kep">
      <div style="display:flex;gap:10px;align-items:baseline;flex-wrap:wrap;margin-bottom:4px">
        <span class="mono" style="font-size:15px;font-weight:700;color:var(--brass)">{{ $i + 1 }}</span>
        <span class="lbl" style="color:var(--brass)">
          butir {{ $t['nomor'] ?: '—' }} ·
          {{ $kategori->firstWhere('id', (int) $t['kategori'])?->nama ?: 'kategori belum dipilih' }}
        </span>
        <div style="flex:1"></div>
        <span class="lbl" style="color:{{ $form->temOk($t) ? 'var(--stamp)' : 'var(--verm)' }}">
          {{ $form->temOk($t) ? 'lengkap' : 'belum lengkap' }}
        </span>
      </div>
      <div style="font-size:14px;font-weight:600;color:var(--brass)">{{ $t['judul'] ?: 'tanpa judul' }}</div>
      <div class="lbl" style="margin-top:4px;color:var(--brass);opacity:.85">
        kategori internal {{ $intern->firstWhere('id', (int) $t['intern'])?->nama ?: '—' }} ·
        nilai temuan {{ Tampil::rupiah($form->nilaiTem($t)) }} ·
        {{ count($t['rekom']) }} rekomendasi
      </div>
    </div>

    <div class="isi">
      <div class="unsur">
        <div class="lbl" style="margin-bottom:4px">Sebab</div>
        <div style="font-size:13px">{{ $t['sebab'] ?: '—' }}</div>
      </div>
      <div class="unsur">
        <div class="lbl" style="margin-bottom:4px">Akibat</div>
        <div style="font-size:13px">{{ $t['akibat'] ?: '—' }}</div>
      </div>

      <div class="lbl" style="margin:18px 0 9px;display:flex;align-items:center;gap:8px">
        <x-ikon n="CircleDot" :s="12" /> Rekomendasi · tiap baris jadi satu penugasan tersendiri
      </div>

      @foreach($t['rekom'] as $j => $r)
        @php
          $nilai = $form->nilaiRek($r);
          $tgl = collect($r['tindakan'])->pluck('tgl_renaksi')->filter()->sort()->values();
          $tgt = collect($r['tindakan'])->pluck('target')->filter()->sort()->values();
        @endphp
        <div class="rekbaris" style="cursor:default">
          <div style="display:flex;gap:9px;align-items:center;flex-wrap:wrap;margin-bottom:6px">
            {{-- Nomornya Ref LHP, sama dengan yang tersimpan. --}}
            <span class="mono" style="font-size:11.5px;font-weight:700">
              {{ trim($r['ref_lhp']) ?: ($t['nomor'] ?: $i + 1).'.'.$form->huruf($j) }}
            </span>
            <span class="lbl">{{ $sifat->firstWhere('id', (int) $r['sifat'])?->nama ?: '—' }}</span>
            <div style="flex:1"></div>
            <span class="lbl" style="color:{{ $form->rekOk($r) ? 'var(--stamp)' : 'var(--verm)' }}">
              {{ $form->rekOk($r) ? 'lengkap' : 'belum lengkap' }}
            </span>
          </div>

          <div style="font-size:13px;margin-bottom:9px">
            {{ $r['uraian'] ?: '' }}
            @if(! $r['uraian'])<span style="color:var(--verm)">uraian belum diisi</span>@endif
          </div>

          <div class="trio" style="border-top:1px solid var(--rule-2);padding-top:9px">
            <x-meta label="Penugasan">
              @if($form->barisRek($r))
                {{ $form->barisRek($r) }} penugasan · {{ count($r['tindakan']) }} tindak lanjut
              @else
                <span style="color:var(--verm)">belum ada</span>
              @endif
            </x-meta>
            <x-meta label="Rencana aksi"><span class="mono">{{ Tampil::tgl($tgl->first()) }}</span></x-meta>
            <x-meta label="Target penyelesaian">
              <span class="mono">{{ $tgt->last() ? Tampil::tgl($tgt->last()) : 'tidak ditetapkan' }}</span>
            </x-meta>
            <x-meta label="Nilai yang dipulihkan">
              <span class="mono">{{ $nilai ? Tampil::rupiah($nilai) : '—' }}</span>
            </x-meta>
          </div>

          {{-- SELURUH tindak lanjutnya, bukan cuma yang pertama: tindak lanjut
               kedua, siapa yang memikulnya, dan catatannya harus terbaca
               sebelum laporannya terkirim, bukan sesudah. --}}
          @foreach($r['tindakan'] as $k => $tk)
            @php $dok = collect($tk['dokumen'])->filter(fn ($x) => trim($x) !== ''); @endphp
            <div style="border-top:1px solid var(--rule-2);padding-top:9px;margin-top:2px">
              <div class="lbl" style="margin-bottom:4px">
                Tindak lanjut {{ $k + 1 }} · {{ $tk['bentuk'] ?: 'bentuk belum diisi' }}
              </div>
              <div style="font-size:12.5px">
                @if(count($tk['satker']))
                  {{ collect($tk['satker'])->map(fn ($x) => $nama($x['satker'])
                    .((int) $x['nilai'] > 0 ? ' ('.Tampil::rupiah((int) $x['nilai']).')' : ''))->join(' · ') }}
                @else
                  <span style="color:var(--verm)">satuan kerja belum dipilih</span>
                @endif
              </div>
              <div class="lbl" style="margin-top:3px">
                rencana aksi {{ $tk['tgl_renaksi'] ? Tampil::tgl($tk['tgl_renaksi']) : 'belum diisi' }}
                · {{ $dok->count() ? $dok->count().' dokumen diminta' : 'tanpa dokumen diminta' }}
              </div>
              @if(trim($tk['catatan']))
                <div style="font-size:12.5px;margin-top:4px">{{ $tk['catatan'] }}</div>
              @endif
            </div>
          @endforeach
        </div>
      @endforeach
    </div>
  </div>
@endforeach
