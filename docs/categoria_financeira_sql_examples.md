# Categoria Financeira SQL Examples

```sql
CREATE TABLE categorias_financeiras (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nomeCategoria VARCHAR(255) NOT NULL,
    tipoLancamento ENUM('RECEITA', 'DESPESA') NOT NULL,
    grupoPrincipal VARCHAR(255) NOT NULL,
    valorPadrao DECIMAL(10, 2) NULL,
    servicoTipo VARCHAR(100) NOT NULL DEFAULT 'Nenhum',
    bloquearValorMinimo TINYINT(1) NOT NULL DEFAULT 0,
    ehProdutoOrcamento TINYINT(1) NOT NULL DEFAULT 0,
    ativo TINYINT(1) NOT NULL DEFAULT 1
);
```

```sql
ALTER TABLE categorias_financeiras
    ADD COLUMN omieProdutoId INT UNSIGNED NULL AFTER ativo;
```

```sql
INSERT INTO categorias_financeiras (
    nomeCategoria,
    tipoLancamento,
    grupoPrincipal,
    valorPadrao,
    servicoTipo,
    bloquearValorMinimo,
    ehProdutoOrcamento,
    ativo
) VALUES (
    'Standard Translation',
    'RECEITA',
    'Produtos e Serviços',
    150.00,
    'Outros',
    0,
    1,
    1
);
```

```sql
UPDATE categorias_financeiras
SET valorPadrao = 175.00
WHERE id = 1;
```

```sql
DELETE FROM categorias_financeiras
WHERE id = 1;
```
