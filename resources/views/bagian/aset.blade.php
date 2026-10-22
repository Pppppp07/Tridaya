{{-- Build memakai nama berkas berdasarkan isi. Aset sumber tetap tersedia untuk pemasangan tanpa build. --}}
@if(is_file(public_path('build/manifest.json')) || is_file(public_path('hot')))
  @vite(['resources/css/app.css', 'resources/js/app.js'])
@else
  <link rel="preload" href="{{ asset('fonts/manrope-variable.ttf') }}" as="font" type="font/ttf" crossorigin>
  <link rel="stylesheet" href="{{ asset('css/simtlhp.css') }}?v={{ filemtime(public_path('css/simtlhp.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/simtlhp-tambahan.css') }}?v={{ filemtime(public_path('css/simtlhp-tambahan.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/simtlhp-tema.css') }}?v={{ filemtime(public_path('css/simtlhp-tema.css')) }}">
  <link rel="stylesheet" href="{{ asset('css/simtlhp-historis.css') }}?v={{ filemtime(public_path('css/simtlhp-historis.css')) }}">
  <script src="{{ asset('js/simtlhp.js') }}?v={{ filemtime(public_path('js/simtlhp.js')) }}" defer></script>
  <script src="{{ asset('js/simtlhp-sesi.js') }}?v={{ filemtime(public_path('js/simtlhp-sesi.js')) }}" defer></script>
@endif
