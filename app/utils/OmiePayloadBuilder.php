<?php

declare(strict_types=1);

require_once __DIR__ . '/PhoneUtils.php';

class OmiePayloadBuilder
{
    public static function buildPesquisarCidadesPayload(string $term, ?string $uf = null, int $page = 1, int $perPage = 50): array
    {
        $normalizedTerm = trim($term);
        if ($normalizedTerm === '') {
            throw new InvalidArgumentException('Informe pelo menos três caracteres para pesquisar cidades.');
        }

        $payload = [
            'pagina' => max(1, $page),
            'registros_por_pagina' => max(1, min($perPage, 100)),
            'filtrar_cidade_contendo' => $normalizedTerm,
        ];

        if ($uf !== null && $uf !== '') {
            $payload['filtrar_por_uf'] = strtoupper(substr($uf, 0, 2));
        }

        return $payload;
    }

    public static function buildIncluirClientePayload(array $cliente): array
    {
        $payload = self::buildClienteBody($cliente);
        if (!empty($cliente['codigo_cliente_integracao'])) {
            $payload['codigo_cliente_integracao'] = $cliente['codigo_cliente_integracao'];
        }

        return $payload;
    }

    public static function buildAlterarClientePayload(array $cliente): array
    {
        $payload = self::buildClienteBody($cliente);
        $omieId = $cliente['omie_id'] ?? $cliente['codigo_cliente_omie'] ?? null;
        $integrationCode = $cliente['codigo_cliente_integracao'] ?? null;

        if (!empty($omieId)) {
            $payload['codigo_cliente_omie'] = (int) $omieId;
        } elseif (!empty($integrationCode)) {
            $payload['codigo_cliente_integracao'] = $integrationCode;
        }

        return $payload;
    }

    private static function buildClienteBody(array $cliente): array
    {
        $telefone = $cliente['telefone'] ?? '';
        $telefoneParts = ['ddd' => null, 'phone' => null];
        $dddInformado = $cliente['telefone_ddd'] ?? null;
        $numeroInformado = $cliente['telefone_numero'] ?? null;

        if ($dddInformado !== null && $numeroInformado !== null) {
            $telefoneParts = [
                'ddd' => normalizeDDD((string) $dddInformado),
                'phone' => normalizePhone((string) $numeroInformado),
            ];
        } elseif ($telefone !== '') {
            $telefoneParts = extractPhoneParts($telefone);
        }

        $cep = stripNonDigits($cliente['cep'] ?? '');

        $payload = [
            'razao_social' => $cliente['nome_cliente'] ?? '',
            'nome_fantasia' => $cliente['nome_responsavel'] ?? ($cliente['nome_cliente'] ?? ''),
            'cnpj_cpf' => stripNonDigits($cliente['cpf_cnpj'] ?? ''),
            'email' => $cliente['email'] ?? null,
            'telefone1_ddd' => $telefoneParts['ddd'],
            'telefone1_numero' => $telefoneParts['phone'],
            'endereco' => $cliente['endereco'] ?? null,
            'endereco_numero' => $cliente['numero'] ?? 'SN',
            'bairro' => $cliente['bairro'] ?? null,
            'cidade' => $cliente['cidade'] ?? null,
            'estado' => isset($cliente['estado']) ? strtoupper($cliente['estado']) : null,
            'cep' => $cep !== '' ? $cep : null,
        ];

        return array_filter($payload, static function ($value) {
            if ($value === null) {
                return false;
            }

            if (is_string($value)) {
                return trim($value) !== '';
            }

            return true;
        });
    }
}

