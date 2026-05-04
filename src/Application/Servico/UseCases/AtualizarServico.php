<?php

namespace Application\Servico\UseCases;

use Domain\Catalogo\Entities\Servico;
use Domain\Catalogo\Repositories\ServicoRepository;

class AtualizarServico
{
    public function __construct(private ServicoRepository $repositorio) {}

    public function executar(int $id, string $nome, float $valor): Servico
    {
        $servico = $this->repositorio->findById($id);

        if (!$servico) {
            throw new \RuntimeException("Serviço #{$id} não encontrado.");
        }

        $servico->atualizar($nome, $valor);

        return $this->repositorio->save($servico);
    }
}
