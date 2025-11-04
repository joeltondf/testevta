# Automação por Coluna no Kanban de Leads

Este guia descreve como vincular cada coluna do Kanban a campanhas de automação que enviam e-mails e mensagens Digisac quando um lead é movido. Os exemplos abaixo utilizam os campos padrão da tabela `automacao_campanhas` e os placeholders reconhecidos pelo serviço de automação.

## Placeholders disponíveis

Os templates de e-mail aceitam as chaves abaixo:

| Placeholder | Descrição |
|-------------|-----------|
| `{{clientName}}` | Nome do cliente associado ao lead. |
| `{{clientContact}}` | Responsável do cliente. |
| `{{clientPhone}}` | Telefone do cliente. |
| `{{clientEmail}}` | E-mail principal do cliente. |
| `{{leadName}}` | Nome do lead/prospecto. |
| `{{leadValue}}` | Valor proposto formatado. |
| `{{stageName}}` | Nome da coluna do Kanban. |
| `{{campaignName}}` | Nome da campanha de automação. |

## Exemplo de estrutura da tabela (ambiente de testes)

```sql
CREATE TABLE IF NOT EXISTS `automacao_campanhas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome_campanha` VARCHAR(120) NOT NULL,
  `crm_gatilhos` JSON NOT NULL,
  `digisac_conexao_id` VARCHAR(60) NULL,
  `digisac_template_id` VARCHAR(60) NULL,
  `mapeamento_parametros` JSON NOT NULL DEFAULT JSON_OBJECT(),
  `digisac_user_id` VARCHAR(60) NULL,
  `email_assunto` VARCHAR(255) NULL,
  `email_cabecalho` TEXT NULL,
  `email_corpo` MEDIUMTEXT NULL,
  `intervalo_reenvio_dias` INT NOT NULL DEFAULT 0,
  `ativo` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Templates por coluna

```sql
INSERT INTO `automacao_campanhas` (
  `nome_campanha`, `crm_gatilhos`, `digisac_conexao_id`, `digisac_template_id`,
  `mapeamento_parametros`, `digisac_user_id`, `email_assunto`,
  `email_cabecalho`, `email_corpo`, `intervalo_reenvio_dias`, `ativo`
) VALUES
('Contato inicial', JSON_ARRAY('Contato ativo'), 'conexao_principal', 'tpl_contato', JSON_OBJECT(), 'bot',
 'Chegamos até você, {{clientName}}!',
 '<p>Olá {{clientContact}},</p>',
 '<p>Recebemos o lead {{leadName}} no estágio {{stageName}} e vamos acompanhá-lo de perto.</p>',
 0, 1),
('Primeiro follow-up', JSON_ARRAY('Primeiro contato'), 'conexao_principal', 'tpl_followup', JSON_OBJECT(), 'bot',
 'Seguimos com {{leadName}}',
 '<p>Oi {{clientContact}},</p>',
 '<p>Estamos preparando os materiais do lead {{leadName}}. Em breve entraremos em contato.</p>',
 0, 1),
('Proposta enviada', JSON_ARRAY('Proposta enviada'), 'conexao_principal', 'tpl_proposta', JSON_OBJECT(), 'bot',
 'Proposta pronta para {{clientName}}',
 '<p>Olá {{clientContact}},</p>',
 '<p>A proposta para {{leadName}} foi enviada com o valor de R$ {{leadValue}}.</p>',
 0, 1),
('Fechamento', JSON_ARRAY('Fechamento'), 'conexao_principal', 'tpl_fechamento', JSON_OBJECT(), 'bot',
 'Encerramento do lead {{leadName}}',
 '<p>Olá {{clientContact}},</p>',
 '<p>O lead {{leadName}} entrou em {{stageName}}. Entraremos em contato para os próximos passos.</p>',
 0, 1);
```

## Configurando as colunas do Kanban

O Kanban usa a chave `kanban_columns` da tabela `configuracoes`. A interface administrativa permite editar a ordem e os nomes, mas é possível manipular diretamente via SQL quando necessário:

```sql
-- Define as colunas na ordem desejada
INSERT INTO configuracoes (chave, valor)
VALUES ('kanban_columns', JSON_ARRAY('Contato ativo', 'Primeiro contato', 'Segundo contato', 'Reunião agendada', 'Proposta enviada', 'Fechamento', 'Pausar'))
ON DUPLICATE KEY UPDATE valor = VALUES(valor);

-- Restaura as colunas padrão do sistema
DELETE FROM configuracoes WHERE chave = 'kanban_columns';
```

> Após alterar os nomes das colunas, revise as campanhas para garantir que os gatilhos (`crm_gatilhos`) usem exatamente os mesmos textos.

## Atualizações rápidas

```sql
-- ALTER TABLE: adicionar um gatilho adicional a uma campanha existente
UPDATE `automacao_campanhas`
   SET `crm_gatilhos` = JSON_ARRAY('Segundo contato', 'Terceiro contato')
 WHERE `nome_campanha` = 'Primeiro follow-up';

-- INSERT: criar nova campanha para o estágio "Reunião agendada"
INSERT INTO `automacao_campanhas` (
  `nome_campanha`, `crm_gatilhos`, `email_assunto`, `email_cabecalho`, `email_corpo`, `intervalo_reenvio_dias`, `ativo`
) VALUES (
  'Reunião agendada',
  JSON_ARRAY('Reunião agendada'),
  'Reunião confirmada para {{clientName}}',
  '<p>Olá {{clientContact}},</p>',
  '<p>Confirmamos a reunião referente ao lead {{leadName}}. Verifique seus e-mails para detalhes adicionais.</p>',
  0,
  1
);

-- UPDATE: desativar uma campanha temporariamente
UPDATE `automacao_campanhas`
   SET `ativo` = 0
 WHERE `nome_campanha` = 'Contato inicial';

-- DELETE: remover campanha criada por engano
DELETE FROM `automacao_campanhas`
 WHERE `nome_campanha` = 'Fechamento';
```

> **Importante:** execute os comandos em um ambiente controlado e realize backup antes de alterações definitivas.
