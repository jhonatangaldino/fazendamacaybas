@include('errors.layout', [
    'codigo' => '500',
    'icon' => '⚠️',
    'titulo' => 'Algo deu errado no servidor',
    'mensagem' => 'Tivemos um problema ao processar sua solicitação. Tente novamente em alguns segundos. Se persistir, entre em contato com o suporte.',
])
