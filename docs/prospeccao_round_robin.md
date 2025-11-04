# Gestão de prospecções e distribuição de leads

Este documento descreve os pontos principais da lógica de cadastro, distribuição e agendamento implementados no CRM.

## Distribuição em fila (round-robin)

A fila de distribuição utiliza a tabela `lead_distribution_queue` para manter a ordem de atendimento dos vendedores ativos.

```sql
CREATE TABLE IF NOT EXISTS lead_distribution_queue (
    vendor_id INT NOT NULL PRIMARY KEY,
    position INT NOT NULL,
    last_assigned_at DATETIME NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_lead_distribution_queue_vendor FOREIGN KEY (vendor_id) REFERENCES users(id) ON DELETE CASCADE
);
```

Exemplos úteis:

```sql
-- Inserir ou atualizar a posição manualmente
INSERT INTO lead_distribution_queue (vendor_id, position, last_assigned_at)
VALUES (42, 3, NULL)
ON DUPLICATE KEY UPDATE position = VALUES(position), last_assigned_at = VALUES(last_assigned_at);

-- Consultar a ordem atual
SELECT vendor_id, position, last_assigned_at
FROM lead_distribution_queue
ORDER BY position ASC;

-- Remover um vendedor da fila
DELETE FROM lead_distribution_queue WHERE vendor_id = 42;
```

## Registro de distribuição

Sempre que um lead é delegado, a tabela `distribuicao_leads` registra o histórico:

```sql
INSERT INTO distribuicao_leads (prospeccaoId, sdrId, vendedorId, createdAt)
VALUES (:prospeccaoId, :operadorId, :vendedorId, NOW());
```

O campo `sdrId` armazena o usuário responsável pela distribuição (SDR ou gestor).

## Atualizações em prospecções

Para manter a consistência ao alterar o responsável, utilize uma atualização direta e registre a interação:

```sql
UPDATE prospeccoes
SET responsavel_id = :novoVendedor, data_ultima_atualizacao = NOW()
WHERE id = :prospeccaoId;
```

```sql
INSERT INTO interacoes (prospeccao_id, usuario_id, observacao, tipo)
VALUES (:prospeccaoId, :usuarioId, :descricao, 'nota');
```

## Agendamentos

Os agendamentos ficam centralizados na tabela `agendamentos`. Exemplo de criação manual:

```sql
INSERT INTO agendamentos (
    titulo, cliente_id, prospeccao_id, usuario_id,
    data_inicio, data_fim, local_link, observacoes, status
) VALUES (
    'Reunião com lead', :clienteId, :prospeccaoId, :usuarioId,
    '2024-07-01 10:00:00', '2024-07-01 10:30:00',
    'https://exemplo.com/reuniao', 'Apresentação inicial', 'Confirmado'
);
```

Após qualquer alteração relevante, registre a atividade correspondente em `interacoes` para manter o histórico completo.
