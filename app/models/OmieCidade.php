<?php

declare(strict_types=1);

class OmieCidade
{
    public string $codigo;
    public string $nome;
    public string $uf;
    public ?int $codigoIbge;
    public ?int $codigoSiafi;

    public function __construct(string $codigo, string $nome, string $uf, ?int $codigoIbge, ?int $codigoSiafi)
    {
        $this->codigo = $codigo;
        $this->nome = $nome;
        $this->uf = $uf;
        $this->codigoIbge = $codigoIbge;
        $this->codigoSiafi = $codigoSiafi;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['cCod'] ?? ''),
            (string) ($data['cNome'] ?? ''),
            (string) ($data['cUF'] ?? ''),
            isset($data['nCodIBGE']) ? (int) $data['nCodIBGE'] : null,
            isset($data['nCodSIAFI']) ? (int) $data['nCodSIAFI'] : null
        );
    }

    public function toArray(): array
    {
        return [
            'cCod' => $this->codigo,
            'cNome' => $this->nome,
            'cUF' => $this->uf,
            'nCodIBGE' => $this->codigoIbge,
            'nCodSIAFI' => $this->codigoSiafi,
        ];
    }
}

