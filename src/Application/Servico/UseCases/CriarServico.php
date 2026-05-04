<?php

namespace Application\Servico\UseCases;

use Domain\Catalogo\Entities\Servico;
use Domain\Catalogo\Repositories\ServicoRepository;

class CriarServico
{
    public function __construct(private ServicoRepository $repositorio) {}

    public function executar(string $nome, float $valor): Servico
    {
        $servico = new Servico(id: null, nome: $nome, valor: $valor);

        return $this->repositorio->save($servico);
    }
}
