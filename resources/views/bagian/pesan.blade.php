@if(session('pesan') || session('gagal') || $errors->any())
  <div class="body pesanbingkai">
    @if(session('pesan'))
      <div class="pesan ok"><x-ikon n="Check" :s="16" /><span>{{ session('pesan') }}</span></div>
    @endif
    @if(session('gagal') || $errors->any())
      <div class="pesan bad"><x-ikon n="AlertTriangle" :s="16" /><span>{{ session('gagal') ?? $errors->first() }}</span></div>
    @endif
  </div>
@endif
