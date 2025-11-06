# Database Migrations — Lead Conversion Wizard

As instruções abaixo atualizam o banco de dados para suportar o fluxo de conversão de leads em serviços pendentes.

## 0. Ajustes na tabela `prospeccoes`

```sql
ALTER TABLE `prospeccoes`
  ADD COLUMN `leadCategory` VARCHAR(50) NOT NULL DEFAULT 'Entrada' AFTER `status`;

UPDATE `prospeccoes` p
LEFT JOIN `clientes` c ON c.`id` = p.`cliente_id`
   SET p.`leadCategory` = COALESCE(c.`categoria`, 'Entrada')
 WHERE p.`leadCategory` = 'Entrada';
```


## 1. Ajustes na tabela `clientes`

```sql
ALTER TABLE `clientes`
  ADD COLUMN `telefone_ddi` VARCHAR(4) NULL DEFAULT NULL AFTER `telefone`,
  ADD COLUMN `telefone_ddd` VARCHAR(4) NULL DEFAULT NULL AFTER `telefone_ddi`,
  ADD COLUMN `telefone_numero` VARCHAR(20) NULL DEFAULT NULL AFTER `telefone_ddd`,
  ADD COLUMN `prazo_acordado_dias` INT UNSIGNED NULL DEFAULT NULL AFTER `tipo_assessoria`,
  ADD COLUMN `complemento` VARCHAR(60) NULL DEFAULT NULL AFTER `numero`,
  ADD COLUMN `cidade_validation_source` ENUM('api', 'database') NULL DEFAULT 'api' AFTER `estado`,
  ADD COLUMN `data_conversao` DATETIME NULL DEFAULT NULL AFTER `cidade_validation_source`,
  ADD COLUMN `usuario_conversao_id` INT UNSIGNED NULL DEFAULT NULL AFTER `data_conversao`,
  ADD COLUMN `crmOwnerId` INT UNSIGNED NULL DEFAULT NULL AFTER `usuario_conversao_id`,
  ADD COLUMN `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `data_cadastro`,
  ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL AFTER `updated_at`;

CREATE INDEX `idx_clientes_tipo_pessoa` ON `clientes` (`tipo_pessoa`);
CREATE INDEX `idx_clientes_tipo_assessoria` ON `clientes` (`tipo_assessoria`);
CREATE INDEX `idx_clientes_is_prospect` ON `clientes` (`is_prospect`);
CREATE INDEX `idx_clientes_crm_owner` ON `clientes` (`crmOwnerId`);
CREATE INDEX `idx_clientes_data_conversao` ON `clientes` (`data_conversao`);
```

## 2. Ajustes na tabela `cliente_servicos_mensalistas`

```sql
ALTER TABLE `cliente_servicos_mensalistas`
  ADD COLUMN `servico_tipo` VARCHAR(50) NULL DEFAULT NULL AFTER `valor_padrao`,
  ADD COLUMN `ativo` TINYINT(1) NOT NULL DEFAULT 1 AFTER `servico_tipo`,
  ADD COLUMN `data_inicio` DATE NULL DEFAULT NULL AFTER `ativo`,
  ADD COLUMN `data_fim` DATE NULL DEFAULT NULL AFTER `data_inicio`,
  ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `data_fim`,
  ADD COLUMN `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

CREATE INDEX `idx_csm_ativo` ON `cliente_servicos_mensalistas` (`ativo`);
```

## 3. Ajustes na tabela `processos`

```sql
ALTER TABLE `processos`
  ADD COLUMN `comprovante_pagamento_1` VARCHAR(255) NULL DEFAULT NULL AFTER `data_pagamento_2`,
  ADD COLUMN `comprovante_pagamento_2` VARCHAR(255) NULL DEFAULT NULL AFTER `comprovante_pagamento_1`,
  MODIFY COLUMN `orcamento_forma_pagamento` ENUM('À vista', 'Parcelado', 'Mensal') NULL DEFAULT NULL;

CREATE INDEX `idx_processos_status` ON `processos` (`status_processo`);
CREATE INDEX `idx_processos_data_inicio_traducao` ON `processos` (`data_inicio_traducao`);
```

## 4. Atualização do `status_processo`

```sql
ALTER TABLE `processos`
  MODIFY COLUMN `status_processo`
    ENUM('Orçamento Pendente','Orçamento','Serviço Pendente','Serviço em Andamento','Concluído','Cancelado')
    DEFAULT 'Orçamento';
```

## 5. Migração dos valores legados de status

Execute os comandos abaixo para alinhar registros antigos aos novos rótulos padronizados:

```sql
UPDATE `processos`
   SET `status_processo` = 'Concluído'
 WHERE `status_processo` IN ('Finalizado','Finalizada','Concluido','Concluida');

UPDATE `processos`
   SET `status_processo` = 'Serviço em Andamento'
 WHERE `status_processo` IN ('Em andamento','Em Andamento','Serviço em Andamento','Serviço em andamento');

UPDATE `processos`
   SET `status_processo` = 'Serviço Pendente'
 WHERE `status_processo` IN ('Pendente','Serviço pendente','Serviço Pendente','Aprovado');

UPDATE `processos`
   SET `status_processo` = 'Cancelado'
 WHERE `status_processo` IN ('Arquivado','Arquivada','Recusado','Recusada');
```

## Exemplos de uso

A tabela abaixo demonstra comandos básicos utilizando os novos campos.

```sql
-- CREATE TABLE: exemplo para testes isolados
CREATE TABLE IF NOT EXISTS `clientes_test` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome_cliente` VARCHAR(255) NOT NULL,
  `prazo_acordado_dias` INT UNSIGNED NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;

-- INSERT: novo cliente convertido
INSERT INTO `clientes` (
  `nome_cliente`, `tipo_pessoa`, `tipo_assessoria`, `prazo_acordado_dias`,
  `cpf_cnpj`, `email`, `telefone`, `telefone_ddi`, `telefone_ddd`, `telefone_numero`, `cidade`, `estado`,
  `cidade_validation_source`, `is_prospect`, `data_conversao`
) VALUES (
  'Cliente Exemplo Ltda', 'Jurídica', 'Mensalista', 10,
  '12345678000100', 'contato@exemplo.com', '5511999999999', '55', '11', '999999999', 'São Paulo', 'SP',
  'api', 0, NOW()
);

-- UPDATE: registro de comprovante no processo
UPDATE `processos`
   SET `orcamento_forma_pagamento` = 'Parcelado',
       `orcamento_valor_entrada` = 1500.00,
       `comprovante_pagamento_1` = 'uploads/comprovantes/comprovante_1.pdf'
 WHERE `id` = 42;

-- UPDATE: ajuste da categoria da prospecção conforme evolução do lead
UPDATE `prospeccoes`
   SET `leadCategory` = 'Em Negociação'
 WHERE `id` = 25;

-- DELETE: desativação lógica de serviço mensalista
UPDATE `cliente_servicos_mensalistas`
   SET `ativo` = 0, `data_fim` = CURDATE()
 WHERE `cliente_id` = 42 AND `produto_orcamento_id` = 7;

-- ALTER TABLE: remoção da tabela auxiliar de testes
ALTER TABLE `clientes_test`
  ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- DELETE definitivo (apenas para ambiente de testes)
DELETE FROM `clientes_test` WHERE `id` = 1;

-- UPDATE: define o DDI padrão para registros legados
UPDATE `clientes`
   SET `telefone_ddi` = '55'
 WHERE (`telefone_ddi` IS NULL OR `telefone_ddi` = '')
   AND `telefone` IS NOT NULL
   AND `telefone` <> '';
```

> **Observação:** execute os scripts em ordem para preservar a integridade referencial. Sempre realize backup antes de aplicar as alterações em produção.

## 6. Definir o vendedor padrão da empresa

Os comandos abaixo criam (caso necessário) o usuário e o vendedor corporativos, salvam o identificador na configuração `default_vendedor_id` e atualizam registros sem vendedor associado. Execute cada bloco sequencialmente no phpMyAdmin.

```sql
-- 6.1 — garante que o usuário corporativo exista
INSERT INTO users (nome_completo, email, senha, perfil, ativo)
SELECT 'Empresa', 'empresa@empresa.com', '$2y$12$xTpY2gtJrsY/vTw0XnNlgeuYEHrBBbgt0POK9BlTSy2B2a7IAiYSG', 'vendedor', 1
WHERE NOT EXISTS (
    SELECT 1 FROM users WHERE email = 'empresa@empresa.com'
);

SET @companyUserId := (
    SELECT id FROM users WHERE email = 'empresa@empresa.com' LIMIT 1
);

-- 6.2 — cria o vendedor corporativo se ainda não existir
INSERT INTO vendedores (user_id, percentual_comissao, data_contratacao, ativo)
SELECT @companyUserId, 0, CURDATE(), 1
WHERE NOT EXISTS (
    SELECT 1 FROM vendedores WHERE user_id = @companyUserId
);

SET @defaultVendorId := (
    SELECT id FROM vendedores WHERE user_id = @companyUserId LIMIT 1
);

-- 6.3 — registra o vendedor padrão nas configurações
INSERT INTO configuracoes (chave, valor)
VALUES ('default_vendedor_id', @defaultVendorId)
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

-- 6.4 — aplica o vendedor padrão a registros sem vendedor
UPDATE processos
   SET vendedor_id = @defaultVendorId
 WHERE vendedor_id IS NULL;

-- Execute as duas instruções abaixo apenas se as tabelas estiverem presentes no seu banco.
UPDATE servicos
   SET vendedor_id = @defaultVendorId
 WHERE vendedor_id IS NULL;

UPDATE orcamentos
   SET vendedor_id = @defaultVendorId
 WHERE vendedor_id IS NULL;
```

## 7. Habilitar o fluxo de SDR

Execute as instruções abaixo para incluir o perfil `sdr`, registrar o proprietário de cada lead e ativar o histórico de distribuição.

```sql
-- 7.1 — adiciona o novo perfil ao ENUM de usuários
ALTER TABLE `users`
  MODIFY COLUMN `perfil`
    ENUM('master','admin','gerencia','supervisor','financeiro','vendedor','colaborador','cliente','sdr') NOT NULL;

-- 7.2 — armazena o SDR responsável por cada prospecção
ALTER TABLE `prospeccoes`
  ADD COLUMN `sdrId` INT UNSIGNED NULL DEFAULT NULL AFTER `responsavel_id`;

-- 7.3 — registra respostas de qualificação
CREATE TABLE IF NOT EXISTS `prospeccao_qualificacoes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `prospeccaoId` INT UNSIGNED NOT NULL,
  `sdrId` INT UNSIGNED NOT NULL,
  `fitIcp` VARCHAR(40) NOT NULL,
  `budget` VARCHAR(40) NOT NULL,
  `authority` VARCHAR(40) NOT NULL,
  `timing` VARCHAR(40) NOT NULL,
  `decision` ENUM('qualificado','descartado') NOT NULL,
  `notes` TEXT NULL,
  `createdAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_prospeccao_qualificacoes_prospeccao` (`prospeccaoId`),
  CONSTRAINT `fk_qualificacoes_prospeccoes` FOREIGN KEY (`prospeccaoId`) REFERENCES `prospeccoes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_qualificacoes_users` FOREIGN KEY (`sdrId`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7.4 — histórico de distribuição automática
CREATE TABLE IF NOT EXISTS `distribuicao_leads` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `prospeccaoId` INT UNSIGNED NOT NULL,
  `sdrId` INT UNSIGNED NULL,
  `vendedorId` INT UNSIGNED NOT NULL,
  `createdAt` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_distribuicao_leads_prospeccao` (`prospeccaoId`),
  CONSTRAINT `fk_distribuicao_leads_prospeccoes` FOREIGN KEY (`prospeccaoId`) REFERENCES `prospeccoes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7.4.1 — fila de distribuição rotativa
CREATE TABLE IF NOT EXISTS `lead_distribution_queue` (
  `vendor_id` INT UNSIGNED NOT NULL PRIMARY KEY,
  `position` INT NOT NULL,
  `last_assigned_at` DATETIME NULL,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_lead_distribution_queue_vendor`
    FOREIGN KEY (`vendor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7.5 — exemplos para cadastro de SDRs e leads de teste
INSERT INTO `users` (nome_completo, email, senha, perfil, ativo)
VALUES ('SDR Teste', 'sdr@empresa.com', '$2y$10$abcdefghijklmnopqrstuv', 'sdr', 1);

SET @novoSdrId := LAST_INSERT_ID();

INSERT INTO `prospeccoes` (
  cliente_id, nome_prospecto, data_prospeccao,
  responsavel_id, sdrId, feedback_inicial,
  valor_proposto, status, leadCategory
) VALUES (
  1, 'Lead Demonstrativo', NOW(),
  NULL, @novoSdrId, 'Contato inicial via inbound',
  0, 'Novo', 'Entrada'
);

-- 7.6 — atualiza leads existentes vinculando o SDR atual quando aplicável
UPDATE `prospeccoes`
   SET `sdrId` = `responsavel_id`
 WHERE `sdrId` IS NULL
   AND `responsavel_id` IN (SELECT id FROM users WHERE perfil = 'sdr');

-- 7.7 — remove a responsabilidade do vendedor para leads ainda não distribuídos
UPDATE `prospeccoes`
   SET `responsavel_id` = NULL
 WHERE `status` IN ('Novo','Em Contato')
   AND `responsavel_id` IN (SELECT id FROM users WHERE perfil = 'sdr');
```
