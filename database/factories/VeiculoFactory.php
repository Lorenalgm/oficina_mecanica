<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\Veiculo;
use Illuminate\Database\Eloquent\Factories\Factory;

class VeiculoFactory extends Factory
{
    protected $model = Veiculo::class;

    public function definition(): array
    {
        $letras = strtoupper($this->faker->lexify('???'));
        $numeros = $this->faker->numerify('####');

        return [
            'placa' => "{$letras}-{$numeros}",
            'marca' => $this->faker->randomElement(['Toyota', 'Honda', 'Ford', 'Chevrolet', 'Volkswagen']),
            'modelo' => $this->faker->randomElement(['Corolla', 'Civic', 'Fiesta', 'Onix', 'Gol']),
            'ano' => $this->faker->numberBetween(2000, 2024),
            'cliente_id' => Cliente::factory(),
        ];
    }
}
