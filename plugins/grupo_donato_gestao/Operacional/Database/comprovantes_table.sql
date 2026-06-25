-- Tabela para armazenar comprovantes de pagamento do Grupo Donato
-- Esta tabela armazena todos os dados do comprovante conforme a imagem fornecida

CREATE TABLE IF NOT EXISTS `rise_grupo_donato_comprovantes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `numero_comprovante` varchar(50) DEFAULT NULL COMMENT 'Nº do comprovante',
  `data_emissao` date DEFAULT NULL COMMENT 'Data de emissão do comprovante',
  `responsavel_id` int(11) NOT NULL COMMENT 'ID do responsável (FK)',
  `responsavel_nome` varchar(255) DEFAULT NULL COMMENT 'Nome do responsável',
  `responsavel_cpf` varchar(20) DEFAULT NULL COMMENT 'CPF do responsável formatado',
  `aluno_id` int(11) NOT NULL COMMENT 'ID do aluno (FK)',
  `aluno_nome` varchar(255) DEFAULT NULL COMMENT 'Nome do aluno',
  `aluno_nome_adicional` varchar(255) DEFAULT NULL COMMENT 'Nome do segundo aluno (se houver)',
  `mensalidade_numero` tinyint(4) DEFAULT NULL COMMENT '1=1º Mensalidade, 2=2º, etc. (1-6)',
  `valor` decimal(10,2) NOT NULL COMMENT 'Valor em R$',
  `forma_pagamento` enum('BOLETO','CRÉDITO','DÉBITO','PIX') DEFAULT NULL COMMENT 'Forma de pagamento',
  `conferido_por` varchar(255) DEFAULT NULL COMMENT 'Nome de quem conferiu',
  `data_conferencia` date DEFAULT NULL COMMENT 'Data da conferência',
  `cobranca_id` int(11) DEFAULT NULL COMMENT 'ID da cobrança relacionada (FK)',
  `arquivo_path` varchar(500) DEFAULT NULL COMMENT 'Caminho do arquivo PDF gerado',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_responsavel` (`responsavel_id`),
  KEY `idx_aluno` (`aluno_id`),
  KEY `idx_cobranca` (`cobranca_id`),
  KEY `idx_numero` (`numero_comprovante`),
  KEY `idx_data_emissao` (`data_emissao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
