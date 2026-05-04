<?php

namespace Application\OS\UseCases;

use App\Models\OS as OSModel;

class ConsultarOSPublica
{
    public function executar(string $documento, string $placa): ?array
    {
        $documentoNormalizado = preg_replace('/\D/', '', $documento);
        $placaNormalizada = strtoupper(trim($placa));

        $os = OSModel::with(['statusAtual', 'cliente', 'veiculo'])
            ->whereHas('cliente', fn ($q) => $q->where('documento', $documentoNormalizado))
            ->whereHas('veiculo', fn ($q) => $q->where('placa', $placaNormalizada))
            ->latest()
            ->first();

        if (!$os) {
            return null;
        }

        return [
            'status_atual' => $os->statusAtual->nome,
            'descricao_problema' => $os->descricao_problema,
        ];
    }
}
