# Database Usage Examples

Os exemplos abaixo demonstram comandos SQL comuns utilizados no banco de dados.

```sql
CREATE TABLE customers (
    customerId INT AUTO_INCREMENT PRIMARY KEY,
    customerName VARCHAR(255) NOT NULL,
    customerEmail VARCHAR(255) UNIQUE NOT NULL,
    createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

```sql
ALTER TABLE customers
ADD COLUMN statusFlag TINYINT(1) DEFAULT 1;
```

```sql
INSERT INTO customers (customerName, customerEmail)
VALUES ('Maria Oliveira', 'maria.oliveira@example.com');
```

```sql
UPDATE customers
SET statusFlag = 0
WHERE customerEmail = 'maria.oliveira@example.com';
```

```sql
DELETE FROM customers
WHERE statusFlag = 0;
```

```sql
DELETE FROM process_notifications
WHERE processId = 42
  AND alertStatus IN ('Approved', 'Rejected');
```

```sql
ALTER TABLE notificacoes
ADD COLUMN tipo_alerta VARCHAR(60) NOT NULL DEFAULT 'processo_generico';
```

```sql
UPDATE notificacoes
SET resolvido = 1,
    lida = 1
WHERE tipo_alerta = 'processo_pendente_servico'
  AND referencia_id = 42;
```

```sql
DELETE FROM notificacoes
WHERE resolvido = 1
  AND data_criacao < DATE_SUB(NOW(), INTERVAL 45 DAY);
```
