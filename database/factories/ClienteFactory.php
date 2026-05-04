<?php

namespace Database\Factories;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClienteFactory extends Factory
{
    protected $model = Cliente::class;

    public function definition(): array
    {
        $cpf = $this->gerarCpf();

        return [
            'nome' => $this->faker->name(),
            'documento' => $cpf,
            'celular' => $this->faker->numerify('(##) #####-####'),
            'email' => $this->faker->unique()->safeEmail(),
        ];
    }

    private function gerarCpf(): string
    {
        $n = [];
        for ($i = 0; $i < 9; $i++) {
            $n[] = rand(0, 9);
        }

        $soma = 0;
        for ($i = 0; $i < 9; $i++) {
            $soma += $n[$i] * (10 - $i);
        }
        $r = $soma % 11;
        $n[] = $r < 2 ? 0 : 11 - $r;

        $soma = 0;
        for ($i = 0; $i < 10; $i++) {
            $soma += $n[$i] * (11 - $i);
        }
        $r = $soma % 11;
        $n[] = $r < 2 ? 0 : 11 - $r;

        return implode('', $n);
    }
}
