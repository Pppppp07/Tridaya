@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
{{-- Nama aplikasi saja, tanpa logo dari situs luar. --}}
{!! $slot !!}
</a>
</td>
</tr>
