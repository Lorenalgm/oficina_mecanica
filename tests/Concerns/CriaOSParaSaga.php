<?php

namespace Tests\Concerns;

use App\Models\Cliente;
use App\Models\Insumo;
use App\Models\OS;
use App\Models\OSServico;
use App\Models\OSServicoInsumo;
use App\Models\Servico;
use App\Models\Status;
use App\Models\Veiculo;
use Database\Seeders\StatusSeeder;

trait CriaOSParaSaga
{
    protected Cliente $cliente;
    protected Veiculo $veiculo;
    protected Servico $servico;
    protected Insumo $insumo;

    protected function prepararCatalogo(int $estoque = 10): void
    {
        $this->seed(StatusSeeder::class);
        $this->cliente = Cliente::factory()->create(['nome' => 'Ana Souza', 'email' => 'ana@exemplo.com']);
        $this->veiculo = Veiculo::factory()->create([
            'cliente_id' => $this->cliente->id,
            'placa' => 'ABC1D23',
            'marca' => 'Fiat',
            'modelo' => 'Uno',
        ]);
        $this->servico = Servico::factory()->create(['nome' => 'Troca de óleo', 'valor' => 100.00]);
        $this->insumo = Insumo::factory()->create(['nome' => 'Óleo 5W30', 'valor' => 49.90, 'quantidade_estoque' => $estoque]);
    }

    protected function criarOSComServico(int $quantidadeInsumo = 2, string $filial = 'MATRIZ'): OS
    {
        $os = OS::create([
            'cliente_id' => $this->cliente->id,
            'veiculo_id' => $this->veiculo->id,
            'status_atual_id' => Status::where('nome', 'Recebida')->value('id'),
            'descricao_problema' => 'Barulho no motor',
            'filial' => $filial,
        ]);
        $osServico = OSServico::create(['os_id' => $os->id, 'servico_id' => $this->servico->id]);
        OSServicoInsumo::create([
            'os_servico_id' => $osServico->id,
            'insumo_id' => $this->insumo->id,
            'quantidade' => $quantidadeInsumo,
        ]);

        return $os;
    }

    protected function statusDaOS(int $osId): string
    {
        return OS::with('statusAtual')->find($osId)->statusAtual->nome;
    }
}
