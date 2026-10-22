<!doctype html>
<html lang="id" data-tema="terang">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="referrer" content="no-referrer">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('judul', 'Masuk') · {{ config('app.display_name') }}</title>
  @include('bagian.ikon-aplikasi')
  @include('bagian.aset')
</head>
{{-- Tidak memuat data/sidebar akun lama yang mungkin sudah dicabut izinnya. --}}
<body>
  @yield('isi')
</body>
</html>
