{{-- Hiasan tepi halaman masuk (SVG sebaris). Diam secara bawaan; satu gerak pendek hanya di
     perangkat yang sesuai. --}}
<div class="msk-latar" aria-hidden="true">
  @foreach(['b1', 'b2'] as $bidang)
    <svg class="msk-bidang {{ $bidang }}" viewBox="0 0 96 96" focusable="false">
      <path d="M28 18H24C17.373 18 12 23.373 12 30V66C12 72.627 17.373 78 24 78H28C32 78 35 76 37 72L59 24C61 20 64 18 68 18H72C78.627 18 84 23.373 84 30V66C84 72.627 78.627 78 72 78H68C64 78 61 76 59 72L37 24C35 20 32 18 28 18Z" fill="none" stroke="currentColor" stroke-width="3" stroke-linejoin="round"/>
    </svg>
  @endforeach
</div>
