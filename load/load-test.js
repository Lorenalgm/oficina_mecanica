// Teste de carga (k6) para demonstrar o autoscaling (HPA) da aplicação.
//
// Uso:
//   BASE_URL=http://localhost:8080 k6 run load/load-test.js
//
// Enquanto roda, observe o HPA escalar em outro terminal:
//   kubectl -n oficina get hpa -w
//   kubectl -n oficina top pods
//
// Narrativa: conforme a carga cria "múltiplas ordens de serviço", a CPU
// sobe e o HPA aumenta o número de réplicas da aplicação.

import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE_URL = __ENV.BASE_URL || 'http://localhost:8080';

export const options = {
  stages: [
    { duration: '1m', target: 50 }, // ramp-up: carga sobe
    { duration: '3m', target: 50 }, // sustenta: HPA escala 2 -> N
    { duration: '1m', target: 0 },  // ramp-down: HPA reduz de volta
  ],
  thresholds: {
    http_req_failed: ['rate<0.15'],
  },
};

// --- Geradores para evitar colisão de dados únicos (documento/placa) em reruns ---

function genCPF() {
  const n = () => Math.floor(Math.random() * 9);
  const base = Array.from({ length: 9 }, n);
  const digito = (arr) => {
    let peso = arr.length + 1;
    const soma = arr.reduce((acc, v) => acc + v * peso--, 0);
    const resto = (soma * 10) % 11;
    return resto === 10 ? 0 : resto;
  };
  const d1 = digito(base);
  const d2 = digito([...base, d1]);
  return [...base, d1, d2].join('');
}

function genPlaca() {
  const L = () => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'[Math.floor(Math.random() * 26)];
  const D = () => Math.floor(Math.random() * 10);
  // Padrão Mercosul: LLL D L DD
  return `${L()}${L()}${L()}${D()}${L()}${D()}${D()}`;
}

function jsonHeaders(token) {
  const h = { 'Content-Type': 'application/json', Accept: 'application/json' };
  if (token) h.Authorization = `Bearer ${token}`;
  return { headers: h };
}

// setup() roda uma vez: autentica e cria um cliente + veículo para as ordens.
export function setup() {
  const login = http.post(
    `${BASE_URL}/api/login`,
    JSON.stringify({ email: 'admin@oficina.com', password: 'password' }),
    jsonHeaders(),
  );

  if (login.status !== 200) {
    return { token: null };
  }

  const token = login.json('token');

  const cliente = http.post(
    `${BASE_URL}/api/clientes`,
    JSON.stringify({ nome: 'Cliente Carga k6', documento: genCPF(), celular: '(11) 99999-9999' }),
    jsonHeaders(token),
  );
  const clienteId = cliente.status === 201 ? cliente.json('data.id') : null;

  let veiculoId = null;
  if (clienteId) {
    const veiculo = http.post(
      `${BASE_URL}/api/veiculos`,
      JSON.stringify({ cliente_id: clienteId, placa: genPlaca(), marca: 'Fiat', modelo: 'Uno', ano: 2020 }),
      jsonHeaders(token),
    );
    veiculoId = veiculo.status === 201 ? veiculo.json('data.id') : null;
  }

  return { token, clienteId, veiculoId };
}

export default function (data) {
  // Health check sempre (endpoint público).
  check(http.get(`${BASE_URL}/up`), { 'GET /up 200': (r) => r.status === 200 });

  if (data.token) {
    // Lista de OS: exercita banco + ordenação por prioridade.
    check(http.get(`${BASE_URL}/api/os`, jsonHeaders(data.token)), {
      'GET /api/os 200': (r) => r.status === 200,
    });

    // Cria OS: simula múltiplas ordens de serviço entrando no sistema.
    if (data.clienteId && data.veiculoId) {
      const os = http.post(
        `${BASE_URL}/api/os`,
        JSON.stringify({
          cliente_id: data.clienteId,
          veiculo_id: data.veiculoId,
          descricao_problema: 'Ordem gerada pelo teste de carga k6',
        }),
        jsonHeaders(data.token),
      );
      check(os, { 'POST /api/os 201': (r) => r.status === 201 });
    }
  }

  sleep(0.5);
}
