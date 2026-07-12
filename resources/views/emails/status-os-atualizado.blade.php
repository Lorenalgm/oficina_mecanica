<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Atualização da OS #{{ $osId }}</title>
</head>
<body>
    <p>Olá{{ $clienteNome ? ', ' . $clienteNome : '' }},</p>

    <p>O status da sua Ordem de Serviço <strong>#{{ $osId }}</strong> foi atualizado para:
        <strong>{{ $statusNome }}</strong>.</p>

    <p>Qualquer dúvida, entre em contato com a oficina.</p>

    <p>Atenciosamente,<br>Equipe da Oficina</p>
</body>
</html>
