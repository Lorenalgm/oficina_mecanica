<?php

namespace Domain\Identidade\Entities;

use Domain\Shared\ValueObjects\Documento;

class Cliente
{
    public function __construct(
        private readonly ?int $id,
        private string $nome,
        private Documento $documento,
        private ?string $celular,
        private ?string $email,
    ) {}

    public function getId(): ?int { return $this->id; }
    public function getNome(): string { return $this->nome; }
    public function getDocumento(): Documento { return $this->documento; }
    public function getCelular(): ?string { return $this->celular; }
    public function getEmail(): ?string { return $this->email; }

    public function atualizar(string $nome, ?string $celular, ?string $email): void
    {
        $this->nome = $nome;
        $this->celular = $celular;
        $this->email = $email;
    }
}
