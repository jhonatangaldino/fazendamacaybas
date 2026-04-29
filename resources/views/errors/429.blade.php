@include('errors.layout', [
    'codigo' => '429',
    'icon' => '⏱️',
    'titulo' => 'Muitas tentativas',
    'mensagem' => 'Você fez muitas requisições em pouco tempo. Aguarde alguns segundos e tente de novo. Se continuar acontecendo, recarregue a página.',
])
