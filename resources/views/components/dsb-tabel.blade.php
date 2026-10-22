@props(['kepala', 'baris', 'kaki' => null])

{{-- $kepala: [['nama', 'num' => bool], …]; $baris/$kaki: nilai per kolom. --}}
<div class="dsb-tw">
  <table class="dsb-tabel">
    <thead>
      <tr>@foreach($kepala as $kp)<th @if(! empty($kp['num'])) class="num" @endif>{{ $kp['nama'] }}</th>@endforeach</tr>
    </thead>
    <tbody>
      @foreach($baris as $b)
        <tr>@foreach($b as $i => $c)<td @if(! empty($kepala[$i]['num'])) class="num" @endif>{{ $c }}</td>@endforeach</tr>
      @endforeach
    </tbody>
    @if($kaki)
      <tfoot>
        <tr>@foreach($kaki as $i => $c)<td @if(! empty($kepala[$i]['num'])) class="num" @endif>{{ $c }}</td>@endforeach</tr>
      </tfoot>
    @endif
  </table>
</div>
