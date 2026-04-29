@include('errors.layout', [
    'codigo' => '405',
    'icon' => '🚧',
    'titulo' => 'Tela não disponível direto',
    'mensagem' => 'Esta ação só funciona quando aberta de dentro do sistema (botão "+ Novo" ou modal). Volte para a tela do módulo correspondente e tente pelo botão.',
])
