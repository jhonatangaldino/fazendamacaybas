<x-mail::layout>
{{ $slot }}

@isset($subcopy)
<x-slot:subcopy>
{{ $subcopy }}
</x-slot:subcopy>
@endisset

© {{ date('Y') }} {{ config('app.name') }}. Todos os direitos reservados.
</x-mail::layout>
