<?php

namespace Database\Factories;

use App\Models\Servico;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServicoFactory extends Factory
{
    protected $model = Servico::class;

    public function definition(): array
    {
        return [
            'nome' => $this->faker->randomElement([
                'Troca de óleo',
                'Alinhamento',
                'Balanceamento',
                'Revisão geral',
                'Troca de filtro',
                'Freios',
            ]),
            'valor' => $this->faker->randomFloat(2, 50, 500),
        ];
    }
}
