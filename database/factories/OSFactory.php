<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\OS;
use App\Models\Status;
use App\Models\Veiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

class OSFactory extends Factory
{
    protected $model = OS::class;

    public function definition(): array
    {
        $cliente = Cliente::factory()->create();

        return [
            'veiculo_id' => Veiculo::factory()->create(['cliente_id' => $cliente->id])->id,
            'cliente_id' => $cliente->id,
            'status_atual_id' => Status::where('nome', 'Recebida')->first()?->id ?? 1,
            'descricao_problema' => $this->faker->sentence(),
        ];
    }
}
