# Ajustes aplicados à tabela `omie_produtos`

Este documento descreve os ajustes estruturais implementados na migração `20241015121500_update_omie_produtos_constraints.php`.

## Limpeza de duplicidades

Antes de criar índices únicos, a migração remove registros duplicados mantendo sempre o mais recente por coluna monitorada (`codigo_produto`, `codigo_integracao` e `local_produto_id`). Caso seja necessário executar manualmente, utilize:

```sql
DELETE FROM omie_produtos
WHERE id IN (
    SELECT id FROM (
        SELECT id,
               ROW_NUMBER() OVER (PARTITION BY codigo_produto ORDER BY updated_at DESC, id DESC) AS row_num
        FROM omie_produtos
        WHERE codigo_produto IS NOT NULL
    ) AS ranked
    WHERE ranked.row_num > 1
);

DELETE FROM omie_produtos
WHERE id IN (
    SELECT id FROM (
        SELECT id,
               ROW_NUMBER() OVER (PARTITION BY codigo_integracao ORDER BY updated_at DESC, id DESC) AS row_num
        FROM omie_produtos
        WHERE codigo_integracao IS NOT NULL
    ) AS ranked
    WHERE ranked.row_num > 1
);

DELETE FROM omie_produtos
WHERE id IN (
    SELECT id FROM (
        SELECT id,
               ROW_NUMBER() OVER (PARTITION BY local_produto_id ORDER BY updated_at DESC, id DESC) AS row_num
        FROM omie_produtos
        WHERE local_produto_id IS NOT NULL
    ) AS ranked
    WHERE ranked.row_num > 1
);
```

## Ajustes de esquema

1. Garantir chave primária numérica crescente:
   ```sql
   ALTER TABLE omie_produtos
     MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT,
     ADD PRIMARY KEY (id);
   ```
2. Padronizar tipos de colunas utilizadas pela integração:
   ```sql
   ALTER TABLE omie_produtos
     MODIFY codigo_produto VARCHAR(50) NULL,
     MODIFY codigo_integracao VARCHAR(60) NULL;
   ```
3. Criar índices únicos para evitar duplicidades futuras:
   ```sql
   ALTER TABLE omie_produtos
     ADD UNIQUE INDEX idx_omie_produtos_codigo (codigo_produto),
     ADD UNIQUE INDEX idx_omie_produtos_integracao (codigo_integracao),
     ADD UNIQUE INDEX idx_omie_produtos_local (local_produto_id);
   ```

Execute os comandos somente se a migração automatizada não estiver disponível no ambiente desejado.
