@component('mail::message')
{{-- Saudação --}}
@if (! empty($greeting))
# {{ $greeting }}
@else
@if ($level === 'error')
# Ops!
@else
# Olá!
@endif
@endif

{{-- Linhas de introdução --}}
@foreach ($introLines as $line)
{{ $line }}

@endforeach

{{-- Botão de ação --}}
@isset($actionText)
<?php
    $color = match ($level) {
        'success', 'error' => $level,
        default => 'primary',
    };
?>
@component('mail::button', ['url' => $actionUrl, 'color' => $color])
{{ $actionText }}
@endcomponent
@endisset

{{-- Linhas finais --}}
@foreach ($outroLines as $line)
{{ $line }}

@endforeach

{{-- Assinatura --}}
@if (! empty($salutation))
{!! $salutation !!}
@else
Atenciosamente,<br>
{{ config('app.name') }}
@endif

{{-- Subcopy --}}
@isset($actionText)
@slot('subcopy')
Se você estiver com problemas para clicar no botão "{{ $actionText }}", copie e cole a URL abaixo
no seu navegador: <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
@endslot
@endisset
@endcomponent
