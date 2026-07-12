<?php

namespace Application\OS\UseCases;

use Domain\Atendimento\Entities\OSOrcamento;
use Domain\Atendimento\Exceptions\TokenAprovacaoInvalido;
use Domain\Atendimento\Repositories\OSRepository;
use RuntimeException;

class RecusarOrcamento
{
    public function __construct(private OSRepository $repositorio) {}

    public function executar(int $osId, ?string $token): OSOrcamento
    {
        $os = $token !== null ? $this->repositorio->findByApprovalToken($token) : null;

        if (!$os || $os->getId() !== $osId) {
            throw new TokenAprovacaoInvalido();
        }

        $orcamento = $os->getOrcamento();
        if (!$orcamento || !$orcamento->isPendente()) {
            throw new RuntimeException('Somente orçamentos pendentes podem ser recusados.');
        }

        $orcamento->recusar();
        $this->repositorio->salvarOrcamento($orcamento);

        return $orcamento;
    }
}
