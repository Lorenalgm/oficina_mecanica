<?php

namespace Database\Factories;

use App\Models\Insumo;
use Illuminate\Database\Eloquent\Factories\Factory;

class InsumoFactory extends Factory
{
    protected $model = Insumo::class;

    public function definition(): array
    {
        return [
            'nome' => $this->faker->randomElement([
                'Filtro de óleo',
                'Filtro de ar',
                'Vela de ignição',
                'Pastilha de freio',
                'Óleo motor',
                'Fluido de freio',
            ]),
            'valor' => $this->faker->randomFloat(2, 10, 200),
            'quantidade_estoque' => $this->faker->numberBetween(10, 100),
        ];
    }
}
