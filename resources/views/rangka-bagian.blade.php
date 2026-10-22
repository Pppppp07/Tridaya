<title>@yield('judul') · {{ config('app.display_name') }}</title>
{{-- Kontrol akses, CSRF, validasi, dan header keamanan tetap melalui middleware biasa. --}}
@if(request()->routeIs('rekomendasi.index', 'laporan.index'))
  @php $nMenu = $perluKerjaMenu ?? \App\Support\Rangka::perluKerja(auth()->user()); @endphp
  <meta name="simtlhp-menu" content="{{ $nMenu }}" data-href="{{ route('rekomendasi.index') }}" data-angka="{{ \App\Support\Rangka::angka($nMenu) }}">
@endif
@include('bagian.pesan')
@yield('isi')
