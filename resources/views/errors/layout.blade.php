<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Erro' }} · Fazenda Macaybas</title>
    <link rel="icon" href="/favicon.ico">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f0fdf4 0%, #ecfeff 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: #1e293b;
        }
        .card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.08);
            padding: 48px 32px;
            max-width: 480px;
            width: 100%;
            text-align: center;
        }
        .icon {
            font-size: 64px;
            margin-bottom: 16px;
        }
        .codigo {
            font-size: 14px;
            font-weight: 600;
            color: #94a3b8;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            margin-bottom: 8px;
        }
        h1 {
            font-size: 28px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 12px;
            line-height: 1.2;
        }
        p {
            font-size: 15px;
            color: #475569;
            line-height: 1.6;
            margin-bottom: 24px;
        }
        .actions {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        @media (min-width: 480px) {
            .actions { flex-direction: row; justify-content: center; }
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 150ms;
            cursor: pointer;
            border: none;
        }
        .btn-primary {
            background: #15803d;
            color: white;
        }
        .btn-primary:hover { background: #166534; }
        .btn-outline {
            background: white;
            color: #475569;
            border: 1px solid #cbd5e1;
        }
        .btn-outline:hover { background: #f1f5f9; }
        .footer {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
            color: #94a3b8;
        }
        .footer a { color: #15803d; text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">{{ $icon ?? '❌' }}</div>
        <div class="codigo">Erro {{ $codigo ?? '' }}</div>
        <h1>{{ $titulo }}</h1>
        <p>{{ $mensagem }}</p>
        <div class="actions">
            @php
                // Auth check pode falhar se DB estiver fora (ex.: limite de conexões esgotado).
                // Try/catch garante que a página 500 SEMPRE renderize, mesmo sem banco.
                try { $logado = auth()->check(); } catch (\Throwable $e) { $logado = false; }
            @endphp
            @if ($logado)
                <a href="{{ url('/admin/inicio') }}" class="btn btn-primary">Voltar para o Início</a>
                <a href="{{ url()->previous() }}" class="btn btn-outline">Página anterior</a>
            @else
                <a href="{{ url('/login') }}" class="btn btn-primary">Entrar no sistema</a>
                <a href="{{ url('/') }}" class="btn btn-outline">Site público</a>
            @endif
        </div>
        <div class="footer">
            Se o problema persistir, entre em contato pelo suporte.<br>
            <a href="{{ url('/') }}" style="display:inline-block;padding:8px 12px;min-height:36px;line-height:1.4;border-radius:6px">Fazenda Macaybas</a>
        </div>
    </div>
</body>
</html>
