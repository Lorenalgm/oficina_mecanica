<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $assunto }}</title>
</head>
<body>
    <p>Olá{{ $clienteNome ? ', ' . $clienteNome : '' }},</p>

    <p>{{ $chamada }} Valor: <strong>{{ $valor }}</strong>.</p>

    <p><a href="{{ $link }}">{{ $acao }}</a></p>

    <p>Atenciosamente,<br>Equipe da Oficina</p>
</body>
</html>
