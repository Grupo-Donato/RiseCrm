<?php

defined('PLUGINPATH') or exit('No direct script access allowed');

/*
Module: Grupo Donato — Operacional (Bombeiros)
Description: Painel operacional multiunidade para alunos, pagamentos, presença e captação.
Version: 1.3.0
Requires at least: 2.8
*/

app_hooks()->add_filter('app_filter_staff_left_menu', function ($sidebar_menu) {
    foreach (bombeiros_left_menu_native_items() as $key => $item) {
        $sidebar_menu[$key] = $item;
    }

    return $sidebar_menu;
});

app_hooks()->add_filter('app_filter_app_csrf_exclude_uris', function ($uris) {
    $uris[] = "matricula-online.*+";
    $uris[] = "grupo_donato/operacional/salvar_matricula_publica.*+";

    return $uris;
});

if (function_exists("service")) {
    require __DIR__ . "/Config/Routes.php";
}

if (!function_exists("bombeiros_install_or_update")) {
    /*
     * Fonte única das turmas/horários do Grupo Donato. Todas as telas
     * (cadastro de aluno, matrícula pública, chamada e filtro de pagamentos)
     * consomem estas opções para não haver divergência entre os valores
     * gravados no aluno e os valores oferecidos nos filtros/chamada.
     *
     * O VALOR (chave) é o texto completo gravado na coluna `turma`; o rótulo
     * exibido é curto porque o próprio optgroup já indica o dia da semana.
     */
    function bombeiros_turmas_grouped($incluir_placeholder = true, $placeholder = "-")
    {
        $grupos = [
            "Segunda e Quarta" => [
                "Seg/Qua Manhã 08:30-10:00" => "Manhã 08:30-10:00",
                "Seg/Qua Tarde 14:15-15:45" => "Tarde 14:15-15:45",
                "Seg/Qua Noite 19:30-21:00" => "Noite 19:30-21:00",
            ],
            "Terça e Quinta" => [
                "Ter/Qui Manhã 09:15-10:30" => "Manhã 09:15-10:30",
                "Ter/Qui Tarde 14:15-15:45" => "Tarde 14:15-15:45",
                "Ter/Qui Tarde 15:45-17:00" => "Tarde 15:45-17:00",
                "Ter/Qui Noite 18:30-19:45" => "Noite 18:30-19:45",
            ],
            "Sábado" => [
                "Sábado 08:00-09:30" => "08:00-09:30",
                "Sábado 09:30-11:00" => "09:30-11:00",
                "Sábado 11:00-12:15" => "11:00-12:15",
            ],
        ];

        if ($incluir_placeholder) {
            return ["" => $placeholder] + $grupos;
        }

        return $grupos;
    }

    // Lista achatada [valor => valor] com todas as turmas válidas (sem grupos).
    function bombeiros_turmas_values()
    {
        $flat = [];
        foreach (bombeiros_turmas_grouped(false) as $opcoes) {
            foreach ($opcoes as $valor => $rotulo) {
                $flat[$valor] = $valor;
            }
        }

        return $flat;
    }

    function bombeiros_left_menu_sections()
    {
        return [
            "dashboard" => ["name" => "Dashboard", "class" => "bar-chart-2"],
            "alunos" => ["name" => "Ativos", "class" => "users"],
            "responsaveis" => ["name" => "Responsáveis", "class" => "user"],
            "presenca" => ["name" => "Presença", "class" => "check-square"],
            "pagamentos" => ["name" => "Pagamentos", "class" => "credit-card"],
            "financeiro" => ["name" => "Inadimplência", "class" => "trending-up"],
            "custos" => ["name" => "Custos", "class" => "dollar-sign"],
            "materiais" => ["name" => "Materiais", "class" => "package"],
            "leads" => ["name" => "Captação", "class" => "radio"],
            "mensagens" => ["name" => "Mensagens", "class" => "message-square"],
            "unidades" => ["name" => "Unidades", "class" => "map"]
        ];
    }

    function bombeiros_left_menu_native_name($key, $item)
    {
        return $item["name"];
    }

    function bombeiros_left_menu_native_items()
    {
        $items = [];
        $position = 3;
        $active_key = "";

        if (function_exists("uri_string") && strpos(uri_string(), "grupo_donato/operacional") === 0) {
            $tab = "";
            if (function_exists("service")) {
                $request = service("request");
                $tab = $request ? $request->getGet("gd_tab") : "";
            }
            $active_key = "grupo_donato_" . ($tab ?: "dashboard");
        }

        foreach (bombeiros_left_menu_sections() as $key => $item) {
            $menu_key = "grupo_donato_" . $key;
            $items[$menu_key] = [
                "name" => bombeiros_left_menu_native_name($key, $item),
                "url" => get_uri("grupo_donato/operacional?gd_tab=" . $key),
                "is_custom_menu_item" => true,
                "class" => $item["class"],
                "position" => $position++
            ];

            if ($active_key === $menu_key) {
                $items[$menu_key]["is_active_menu"] = 1;
            }
        }

        return $items;
    }

    function bombeiros_sync_left_menu_settings($db, $dbprefix)
    {
        $settings_table = $dbprefix . "settings";
        if (!$db->tableExists($settings_table)) {
            return;
        }

        $native_items = [];
        $native_names = [];
        $prefixed_native_names = [];
        $legacy_submenu_names = [];
        $rentals_items = [
            ["name" => "Locações"],
            ["name" => "rental_agenda", "is_sub_menu" => "1"],
            ["name" => "rental_bookings", "is_sub_menu" => "1"],
            ["name" => "rental_series", "is_sub_menu" => "1"],
            ["name" => "rental_single", "is_sub_menu" => "1"],
            ["name" => "rental_monthly", "is_sub_menu" => "1"],
            ["name" => "rental_finance", "is_sub_menu" => "1"],
            ["name" => "rental_charges", "is_sub_menu" => "1"],
        ];
        $rentals_names = array_map(function ($item) {
            return $item["name"];
        }, $rentals_items);
        $legacy_rental_names = [
            "locacoes",
            "Locações",
            "Cobrança",
            "agenda",
            "court_monthly",
            "cobranca",
        ];
        foreach (bombeiros_left_menu_sections() as $key => $item) {
            $native_name = bombeiros_left_menu_native_name($key, $item);
            $native_items[] = ["name" => $native_name];
            $native_names[] = $native_name;
            // COMPAT: detecta e remove entradas de menu persistidas com o nome da
            // marca anterior ("SIAMESA <seção>") em bancos migrados de instalações
            // legadas. Mantido apenas para limpeza; nenhuma entrada nova usa esse nome.
            $prefixed_native_names[] = "SIAMESA " . $item["name"];
            $legacy_submenu_names[] = $item["name"];
        }

        // COMPAT: nomes-raiz herdados do menu antigo (marca anterior e namespace antigo),
        // detectados para que sejam removidos de bancos migrados de instalações legadas.
        $legacy_root_names = ["Grupo Donato — Operacional", "SIAMESA Operacional", "grupo_donato_gestao\Operacional"];
        $rows = $db->query("SELECT setting_name, setting_value FROM `" . $settings_table . "`
            WHERE deleted=0 AND (setting_name='default_left_menu' OR setting_name LIKE 'user\\_%\\_left_menu')")->getResult();

        foreach ($rows as $row) {
            $items = @unserialize($row->setting_value);
            if (!is_array($items) || !count($items)) {
                continue;
            }

            $changed = false;
            $inserted_native_items = false;
            $inserted_rentals_items = false;
            $rebuilt = [];
            foreach ($items as $item) {
                $name = $item["name"] ?? "";

                if (in_array($name, $rentals_names, true) || in_array($name, $legacy_rental_names, true)) {
                    $changed = true;
                    continue;
                }

                if (in_array($name, $native_names, true) || in_array($name, $prefixed_native_names, true)) {
                    $changed = true;
                    continue;
                }

                if (!empty($item["is_sub_menu"]) && in_array($name, $legacy_submenu_names, true)) {
                    $changed = true;
                    continue;
                }

                if (in_array($name, $legacy_root_names, true)) {
                    foreach ($native_items as $native_item) {
                        $rebuilt[] = $native_item;
                    }
                    $inserted_native_items = true;
                    $changed = true;
                    continue;
                }

                $rebuilt[] = $item;
            }

            if (!$inserted_rentals_items) {
                array_splice($rebuilt, 0, 0, $rentals_items);
                $inserted_rentals_items = true;
                $changed = true;
            }

            if (!$inserted_native_items && $row->setting_name === "default_left_menu") {
                $native_insert_at = $inserted_rentals_items ? min(count($rentals_items) + 4, count($rebuilt)) : min(4, count($rebuilt));
                array_splice($rebuilt, $native_insert_at, 0, $native_items);
                $changed = true;
            }

            if ($changed) {
                $db->query("UPDATE `" . $settings_table . "` SET setting_value=" . $db->escape(serialize($rebuilt)) . "
                    WHERE setting_name=" . $db->escape($row->setting_name));
            }
        }
    }

    function bombeiros_slugify($value)
    {
        $value = trim((string) $value);
        if (function_exists("iconv")) {
            $converted = @iconv("UTF-8", "ASCII//TRANSLIT", $value);
            if ($converted !== false) {
                $value = $converted;
            }
        }

        $value = strtolower($value);
        $value = preg_replace("/[^a-z0-9]+/", "_", $value);
        $value = trim($value, "_");

        return $value ?: "unidade";
    }

    function bombeiros_ensure_enum_values($db, $table, $column, $required_values, $default_value)
    {
        $column_info = $db->query("SHOW COLUMNS FROM `" . $table . "` LIKE " . $db->escape($column))->getRow();
        if (!$column_info || strpos((string) $column_info->Type, "enum(") !== 0) {
            return;
        }

        preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", (string) $column_info->Type, $matches);
        $values = array_map(function ($value) {
            return str_replace(["\\'", "\\\\"], ["'", "\\"], $value);
        }, $matches[1] ?? []);

        $changed = false;
        foreach ($required_values as $required_value) {
            if (!in_array($required_value, $values, true)) {
                $values[] = $required_value;
                $changed = true;
            }
        }

        if (!$changed) {
            return;
        }

        $enum_values = array_map(function ($value) use ($db) {
            return $db->escape($value);
        }, $values);

        $db->query("ALTER TABLE `" . $table . "` MODIFY `" . $column . "` enum(" . implode(",", $enum_values) . ") DEFAULT " . $db->escape($default_value));
    }

    /**
     * MIGRAÇÃO DE COMPATIBILIDADE: renomeia as tabelas da marca anterior
     * (<prefix>siamesa_*) para o padrão Grupo Donato (<prefix>grupo_donato_*).
     *
     * Segura e idempotente:
     *  - só renomeia quando a tabela antiga existe e a nova ainda não existe;
     *  - RENAME TABLE preserva dados, índices, chaves estrangeiras e gatilhos;
     *  - nunca executa DROP nem recria tabelas; pode rodar várias vezes.
     */
    function bombeiros_rename_legacy_tables($db, $dbprefix)
    {
        // sufixos próprios do módulo (não tocar em tabelas do núcleo do Rise)
        $suffixes = [
            "unidades",
            "responsaveis",
            "alunos",
            "cobrancas",
            "custos_unidade",
            "presenca",
            "comprovantes",
            "person_unit_access",
            "leads_palestra",
        ];

        foreach ($suffixes as $suffix) {
            $old = $dbprefix . "siamesa_" . $suffix;
            $new = $dbprefix . "grupo_donato_" . $suffix;

            try {
                $old_exists = $db->tableExists($old);
                $new_exists = $db->tableExists($new);

                if ($old_exists && !$new_exists) {
                    $db->query("RENAME TABLE `" . $old . "` TO `" . $new . "`");
                    log_message("notice", "Grupo Donato: tabela renomeada " . $old . " -> " . $new . " (dados preservados).");
                } elseif ($old_exists && $new_exists) {
                    // Ambas existem: cenário inesperado. Não mexe nos dados; apenas registra.
                    log_message("warning", "Grupo Donato: tabelas legada e nova coexistem (" . $old . " / " . $new . "); rename ignorado para preservar dados.");
                }
            } catch (\Throwable $e) {
                log_message("error", "Grupo Donato: falha ao renomear " . $old . ": " . $e->getMessage());
            }
        }
    }

    function bombeiros_install_or_update()
    {
        if (!function_exists("db_connect")) {
            return;
        }

        try {
            $db = db_connect();
            $dbprefix = $db->getPrefix();

            // MIGRAÇÃO DE COMPATIBILIDADE (marca anterior -> Grupo Donato):
            // renomeia as tabelas legadas "<prefix>siamesa_*" para "<prefix>grupo_donato_*".
            // RENAME TABLE preserva 100% dos dados, índices, chaves estrangeiras e
            // relacionamentos. Idempotente: só renomeia quando a tabela antiga existe
            // e a nova ainda não existe; é seguro rodar quantas vezes for necessário.
            bombeiros_rename_legacy_tables($db, $dbprefix);

            bombeiros_sync_left_menu_settings($db, $dbprefix);

            $ensure_column = function ($table, $column, $definition) use ($db) {
                $exists = $db->query("SHOW COLUMNS FROM `" . $table . "` LIKE " . $db->escape($column))->getRow();
                if (!$exists) {
                    $db->query("ALTER TABLE `" . $table . "` ADD `" . $column . "` " . $definition);
                }
            };

            $ensure_index = function ($table, $index, $definition) use ($db) {
                $exists = $db->query("SHOW INDEX FROM `" . $table . "` WHERE Key_name=" . $db->escape($index))->getRow();
                if (!$exists) {
                    $db->query("ALTER TABLE `" . $table . "` ADD " . $definition);
                }
            };

            $table_name = $dbprefix . "grupo_donato_unidades";
            if (!$db->tableExists($table_name)) {
                $db->query("CREATE TABLE IF NOT EXISTS `" . $table_name . "` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `slug` varchar(120) DEFAULT NULL,
                    `is_default` tinyint(1) DEFAULT 0,
                    `nome_unidade` varchar(255) NOT NULL,
                    `cidade` varchar(255) NOT NULL,
                    `endereco` varchar(500) DEFAULT NULL,
                    `status` enum('Ativo','Inativo') DEFAULT 'Ativo',
                    `deleted` tinyint(1) DEFAULT 0,
                    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_slug` (`slug`),
                    KEY `idx_default` (`is_default`),
                    KEY `idx_status` (`status`),
                    KEY `idx_cidade` (`cidade`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            }
            $ensure_column($table_name, "slug", "varchar(120) DEFAULT NULL AFTER `id`");
            $ensure_column($table_name, "is_default", "tinyint(1) DEFAULT 0 AFTER `slug`");
            $ensure_index($table_name, "idx_slug", "KEY `idx_slug` (`slug`)");
            $ensure_index($table_name, "idx_default", "KEY `idx_default` (`is_default`)");

            $unidades = $db->query("SELECT id, nome_unidade, cidade, slug FROM `" . $table_name . "` WHERE deleted=0")->getResult();
            foreach ($unidades as $unidade) {
                if (!$unidade->slug) {
                    $slug_base = $unidade->cidade ?: $unidade->nome_unidade;
                    $db->query("UPDATE `" . $table_name . "` SET slug=" . $db->escape(bombeiros_slugify($slug_base)) . " WHERE id=" . (int) $unidade->id);
                }
            }

            // Garante UMA unidade padrão ativa, sem marretar nenhuma unidade
            // específica: não recria, não reativa e não força um slug fixo
            // (ex.: "sao_bernardo_do_campo"). Assim o usuário pode excluir
            // qualquer unidade pelo painel e a exclusão é definitiva.
            $default_unit = $db->query("SELECT id FROM `" . $table_name . "` WHERE is_default=1 AND deleted=0 AND status='Ativo' ORDER BY id ASC LIMIT 1")->getRow();
            if ($default_unit) {
                // Mantém apenas uma unidade marcada como padrão.
                $db->query("UPDATE `" . $table_name . "` SET is_default=0 WHERE id!=" . (int) $default_unit->id);
            } else {
                // Nenhuma padrão ativa: promove a primeira unidade ativa existente.
                $first_active = $db->query("SELECT id FROM `" . $table_name . "` WHERE deleted=0 AND status='Ativo' ORDER BY id ASC LIMIT 1")->getRow();
                if ($first_active) {
                    $db->query("UPDATE `" . $table_name . "` SET is_default=0");
                    $db->query("UPDATE `" . $table_name . "` SET is_default=1 WHERE id=" . (int) $first_active->id);
                }
                // Sem nenhuma unidade ativa: não cria nada automaticamente.
            }

            $table_name = $dbprefix . "grupo_donato_responsaveis";
            if (!$db->tableExists($table_name)) {
                $db->query("CREATE TABLE IF NOT EXISTS `" . $table_name . "` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `nome` varchar(255) NOT NULL,
                    `nascimento` date DEFAULT NULL,
                    `rg` varchar(50) DEFAULT NULL,
                    `cpf` varchar(20) DEFAULT NULL,
                    `whats` varchar(20) DEFAULT NULL,
                    `celular` varchar(20) DEFAULT NULL,
                    `email` varchar(255) DEFAULT NULL,
                    `endereco` varchar(500) DEFAULT NULL,
                    `numero` varchar(20) DEFAULT NULL,
                    `complemento` varchar(255) DEFAULT NULL,
                    `bairro` varchar(255) DEFAULT NULL,
                    `cep` varchar(20) DEFAULT NULL,
                    `cidade` varchar(255) DEFAULT NULL,
                    `recado` varchar(20) DEFAULT NULL,
                    `status` enum('Ativo','Inativo') DEFAULT 'Ativo',
                    `deleted` tinyint(1) DEFAULT 0,
                    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_cpf` (`cpf`),
                    KEY `idx_whats` (`whats`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            }
            $ensure_column($table_name, "nascimento", "date DEFAULT NULL AFTER `nome`");
            $ensure_column($table_name, "rg", "varchar(50) DEFAULT NULL AFTER `nascimento`");
            $ensure_column($table_name, "numero", "varchar(20) DEFAULT NULL AFTER `endereco`");
            $ensure_column($table_name, "complemento", "varchar(255) DEFAULT NULL AFTER `numero`");
            $ensure_column($table_name, "bairro", "varchar(255) DEFAULT NULL AFTER `complemento`");
            $ensure_column($table_name, "cep", "varchar(20) DEFAULT NULL AFTER `bairro`");
            $ensure_column($table_name, "cidade", "varchar(255) DEFAULT NULL AFTER `cep`");
            $ensure_column($table_name, "recado", "varchar(20) DEFAULT NULL AFTER `cidade`");
            $ensure_column($table_name, "status", "enum('Ativo','Inativo') DEFAULT 'Ativo' AFTER `recado`");

            $table_name = $dbprefix . "grupo_donato_alunos";
            if (!$db->tableExists($table_name)) {
                $db->query("CREATE TABLE IF NOT EXISTS `" . $table_name . "` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `matricula` varchar(50) DEFAULT NULL,
                    `unidade_id` int(11) NOT NULL,
                    `responsavel_id` int(11) NOT NULL,
                    `nome_aluno` varchar(255) NOT NULL,
                    `nascimento_aluno` date DEFAULT NULL,
                    `rg_aluno` varchar(50) DEFAULT NULL,
                    `cpf_aluno` varchar(20) DEFAULT NULL,
                    `turma` varchar(50) DEFAULT NULL,
                    `curso_nome` varchar(255) DEFAULT NULL,
                    `num_parcelas` int(11) DEFAULT 12,
                    `quer_camisa` tinyint(1) DEFAULT 0,
                    `tamanho_camisa` varchar(10) DEFAULT NULL,
                    `tamanho_camiseta` varchar(10) DEFAULT NULL,
                    `valor_mensalidade` decimal(10,2) DEFAULT 237.00,
                    `valor_inscricao` decimal(10,2) DEFAULT 100.00,
                    `data_inscricao` date DEFAULT NULL,
                    `valor_mensal` decimal(10,2) DEFAULT 237.00,
                    `data_primeira_parcela` date DEFAULT NULL,
                    `data_matricula` date DEFAULT NULL,
                    `data_inicio` date DEFAULT NULL,
                    `matricula_efetuada` tinyint(1) DEFAULT 0,
                    `uniforme_efetuado` tinyint(1) DEFAULT 0,
                    `material_efetuado` tinyint(1) DEFAULT 0,
                    `melhor_horario_ligacao` varchar(20) DEFAULT NULL,
                    `cidade_assinatura` varchar(255) DEFAULT NULL,
                    `estado_assinatura` varchar(2) DEFAULT NULL,
                    `dia_assinatura` varchar(20) DEFAULT NULL,
                    `mes_assinatura` varchar(50) DEFAULT NULL,
                    `ano_assinatura` varchar(4) DEFAULT NULL,
                    `assinatura_contratada` varchar(255) DEFAULT NULL,
                    `assinatura_contratante` varchar(255) DEFAULT NULL,
                    `li_ciente` tinyint(1) DEFAULT 0,
                    `origem_matricula` varchar(50) DEFAULT 'manual',
                    `status` enum('Ativo','Cancelado','Inativo','Pendente','Inadimplente','Concluido') DEFAULT 'Ativo',
                    `telefone_1` varchar(20) DEFAULT NULL,
                    `telefone_2` varchar(20) DEFAULT NULL,
                    `camiseta` varchar(50) DEFAULT NULL,
                    `material_01` varchar(50) DEFAULT NULL,
                    `material_02` varchar(50) DEFAULT NULL,
                    `horario` varchar(50) DEFAULT NULL,
                    `pelotao` varchar(100) DEFAULT NULL,
                    `data_cancelamento` date DEFAULT NULL,
                    `motivo_cancelamento` varchar(255) DEFAULT NULL,
                    `observacao_cancelamento` text DEFAULT NULL,
                    `deleted` tinyint(1) DEFAULT 0,
                    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_unidade` (`unidade_id`),
                    KEY `idx_responsavel` (`responsavel_id`),
                    KEY `idx_matricula` (`matricula`),
                    KEY `idx_status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            }
            $ensure_column($table_name, "matricula", "varchar(50) DEFAULT NULL AFTER `id`");
            $ensure_column($table_name, "curso_nome", "varchar(255) DEFAULT NULL AFTER `turma`");
            $ensure_column($table_name, "num_parcelas", "int(11) DEFAULT 12 AFTER `curso_nome`");
            $ensure_column($table_name, "valor_inscricao", "decimal(10,2) DEFAULT 100.00 AFTER `valor_mensalidade`");
            $ensure_column($table_name, "data_inscricao", "date DEFAULT NULL AFTER `valor_inscricao`");
            $ensure_column($table_name, "valor_mensal", "decimal(10,2) DEFAULT 237.00 AFTER `data_inscricao`");
            $ensure_column($table_name, "data_primeira_parcela", "date DEFAULT NULL AFTER `valor_mensal`");
            $ensure_column($table_name, "matricula_efetuada", "tinyint(1) DEFAULT 0 AFTER `data_inicio`");
            $ensure_column($table_name, "uniforme_efetuado", "tinyint(1) DEFAULT 0 AFTER `matricula_efetuada`");
            $ensure_column($table_name, "material_efetuado", "tinyint(1) DEFAULT 0 AFTER `uniforme_efetuado`");
            $ensure_column($table_name, "melhor_horario_ligacao", "varchar(20) DEFAULT NULL AFTER `material_efetuado`");
            $ensure_column($table_name, "cidade_assinatura", "varchar(255) DEFAULT NULL AFTER `melhor_horario_ligacao`");
            $ensure_column($table_name, "estado_assinatura", "varchar(2) DEFAULT NULL AFTER `cidade_assinatura`");
            $ensure_column($table_name, "dia_assinatura", "varchar(20) DEFAULT NULL AFTER `estado_assinatura`");
            $ensure_column($table_name, "mes_assinatura", "varchar(50) DEFAULT NULL AFTER `dia_assinatura`");
            $ensure_column($table_name, "ano_assinatura", "varchar(4) DEFAULT NULL AFTER `mes_assinatura`");
            $ensure_column($table_name, "assinatura_contratada", "varchar(255) DEFAULT NULL AFTER `ano_assinatura`");
            $ensure_column($table_name, "assinatura_contratante", "varchar(255) DEFAULT NULL AFTER `assinatura_contratada`");
            $ensure_column($table_name, "li_ciente", "tinyint(1) DEFAULT 0 AFTER `assinatura_contratante`");
            $ensure_column($table_name, "origem_matricula", "varchar(50) DEFAULT 'manual' AFTER `li_ciente`");
            $ensure_column($table_name, "telefone_1", "varchar(20) DEFAULT NULL AFTER `status`");
            $ensure_column($table_name, "telefone_2", "varchar(20) DEFAULT NULL AFTER `telefone_1`");
            $ensure_column($table_name, "camiseta", "varchar(50) DEFAULT NULL AFTER `telefone_2`");
            $ensure_column($table_name, "material_01", "varchar(50) DEFAULT NULL AFTER `camiseta`");
            $ensure_column($table_name, "material_02", "varchar(50) DEFAULT NULL AFTER `material_01`");
            $ensure_column($table_name, "horario", "varchar(50) DEFAULT NULL AFTER `material_02`");
            $ensure_column($table_name, "pelotao", "varchar(100) DEFAULT NULL AFTER `horario`");
            $ensure_column($table_name, "data_cancelamento", "date DEFAULT NULL AFTER `pelotao`");
            $ensure_column($table_name, "motivo_cancelamento", "varchar(255) DEFAULT NULL AFTER `data_cancelamento`");
            $ensure_column($table_name, "observacao_cancelamento", "text DEFAULT NULL AFTER `motivo_cancelamento`");
            $ensure_column($table_name, "cancelado_por", "int(11) DEFAULT NULL AFTER `observacao_cancelamento`");
            $ensure_column($table_name, "camiseta_status", "varchar(50) DEFAULT NULL AFTER `camiseta`");
            $ensure_column($table_name, "camiseta_data", "date DEFAULT NULL AFTER `camiseta_status`");
            $ensure_column($table_name, "material_01_status", "varchar(50) DEFAULT NULL AFTER `material_01`");
            $ensure_column($table_name, "material_01_data", "date DEFAULT NULL AFTER `material_01_status`");
            $ensure_column($table_name, "material_02_status", "varchar(50) DEFAULT NULL AFTER `material_02`");
            $ensure_column($table_name, "material_02_data", "date DEFAULT NULL AFTER `material_02_status`");
            $ensure_column($table_name, "materiais_observacao", "text DEFAULT NULL AFTER `material_02_data`");
            $ensure_column($table_name, "exame_medico", "varchar(255) DEFAULT NULL AFTER `materiais_observacao`");
            $ensure_column($table_name, "exame_medico_nome", "varchar(255) DEFAULT NULL AFTER `exame_medico`");
            $ensure_column($table_name, "exame_medico_mime", "varchar(120) DEFAULT NULL AFTER `exame_medico_nome`");
            $ensure_column($table_name, "exame_medico_tamanho", "int(11) DEFAULT NULL AFTER `exame_medico_mime`");
            $ensure_column($table_name, "exame_medico_enviado_em", "datetime DEFAULT NULL AFTER `exame_medico_tamanho`");
            $ensure_index($table_name, "idx_matricula", "KEY `idx_matricula` (`matricula`)");
            bombeiros_ensure_enum_values($db, $table_name, "status", ["Ativo", "Cancelado", "Inativo", "Pendente", "Inadimplente", "Concluido"], "Ativo");

            $table_name = $dbprefix . "grupo_donato_cobrancas";
            if (!$db->tableExists($table_name)) {
                $db->query("CREATE TABLE IF NOT EXISTS `" . $table_name . "` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `aluno_id` int(11) NOT NULL,
                    `responsavel_id` int(11) DEFAULT NULL,
                    `unit_id` int(11) DEFAULT NULL,
                    `vencimento` date NOT NULL,
                    `valor` decimal(10,2) NOT NULL,
                    `competencia` varchar(20) DEFAULT NULL,
                    `mes_referencia` tinyint(2) DEFAULT NULL,
                    `ano_referencia` smallint(4) DEFAULT NULL,
                    `descricao` varchar(255) DEFAULT NULL,
                    `status` enum('Pendente','Pago','Cancelado','Isento','Sem registro','Vencido') DEFAULT 'Pendente',
                    `tipo` varchar(50) DEFAULT 'Mensalidade',
                    `data_pagamento` datetime DEFAULT NULL,
                    `forma_pagamento` varchar(50) DEFAULT NULL,
                    `observacao` text DEFAULT NULL,
                    `origem_importacao` varchar(100) DEFAULT NULL,
                    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_aluno` (`aluno_id`),
                    KEY `idx_unit` (`unit_id`),
                    KEY `idx_competencia` (`competencia`),
                    KEY `idx_vencimento` (`vencimento`),
                    KEY `idx_status` (`status`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            }
            $ensure_column($table_name, "responsavel_id", "int(11) DEFAULT NULL AFTER `aluno_id`");
            $ensure_column($table_name, "unit_id", "int(11) DEFAULT NULL AFTER `responsavel_id`");
            $ensure_column($table_name, "mes_referencia", "tinyint(2) DEFAULT NULL AFTER `competencia`");
            $ensure_column($table_name, "ano_referencia", "smallint(4) DEFAULT NULL AFTER `mes_referencia`");
            $ensure_column($table_name, "descricao", "varchar(255) DEFAULT NULL AFTER `ano_referencia`");
            $ensure_column($table_name, "forma_pagamento", "varchar(50) DEFAULT NULL AFTER `data_pagamento`");
            $ensure_column($table_name, "observacao", "text DEFAULT NULL AFTER `forma_pagamento`");
            $ensure_column($table_name, "origem_importacao", "varchar(100) DEFAULT NULL AFTER `observacao`");
            $ensure_index($table_name, "idx_unit", "KEY `idx_unit` (`unit_id`)");
            $ensure_index($table_name, "idx_competencia", "KEY `idx_competencia` (`competencia`)");
            $status_column = $db->query("SHOW COLUMNS FROM `" . $table_name . "` LIKE 'status'")->getRow();
            if ($status_column && strpos((string) $status_column->Type, "Vencido") === false) {
                $db->query("ALTER TABLE `" . $table_name . "` MODIFY `status` enum('Pendente','Pago','Cancelado','Isento','Sem registro','Vencido') DEFAULT 'Pendente'");
            }

            $table_name = $dbprefix . "grupo_donato_custos_unidade";
            if (!$db->tableExists($table_name)) {
                $db->query("CREATE TABLE IF NOT EXISTS `" . $table_name . "` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `unit_id` int(11) NOT NULL,
                    `descricao` varchar(255) NOT NULL,
                    `categoria` varchar(100) DEFAULT 'Operacional',
                    `valor` decimal(12,2) NOT NULL DEFAULT 0.00,
                    `data_custo` date NOT NULL,
                    `mes_referencia` tinyint(2) DEFAULT NULL,
                    `ano_referencia` smallint(4) DEFAULT NULL,
                    `status` enum('Previsto','Pago','Cancelado') DEFAULT 'Pago',
                    `forma_pagamento` varchar(100) DEFAULT NULL,
                    `observacao` text DEFAULT NULL,
                    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    `deleted` tinyint(1) DEFAULT 0,
                    PRIMARY KEY (`id`),
                    KEY `idx_unit` (`unit_id`),
                    KEY `idx_categoria` (`categoria`),
                    KEY `idx_status` (`status`),
                    KEY `idx_data_custo` (`data_custo`),
                    KEY `idx_referencia` (`mes_referencia`, `ano_referencia`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            }
            $ensure_column($table_name, "unit_id", "int(11) NOT NULL AFTER `id`");
            $ensure_column($table_name, "descricao", "varchar(255) NOT NULL AFTER `unit_id`");
            $ensure_column($table_name, "categoria", "varchar(100) DEFAULT 'Operacional' AFTER `descricao`");
            $ensure_column($table_name, "valor", "decimal(12,2) NOT NULL DEFAULT 0.00 AFTER `categoria`");
            $ensure_column($table_name, "data_custo", "date NOT NULL AFTER `valor`");
            $ensure_column($table_name, "mes_referencia", "tinyint(2) DEFAULT NULL AFTER `data_custo`");
            $ensure_column($table_name, "ano_referencia", "smallint(4) DEFAULT NULL AFTER `mes_referencia`");
            $ensure_column($table_name, "status", "enum('Previsto','Pago','Cancelado') DEFAULT 'Pago' AFTER `ano_referencia`");
            $ensure_column($table_name, "forma_pagamento", "varchar(100) DEFAULT NULL AFTER `status`");
            $ensure_column($table_name, "observacao", "text DEFAULT NULL AFTER `forma_pagamento`");
            $ensure_column($table_name, "created_at", "datetime DEFAULT CURRENT_TIMESTAMP AFTER `observacao`");
            $ensure_column($table_name, "updated_at", "datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`");
            $ensure_column($table_name, "deleted", "tinyint(1) DEFAULT 0 AFTER `updated_at`");
            $ensure_index($table_name, "idx_unit", "KEY `idx_unit` (`unit_id`)");
            $ensure_index($table_name, "idx_categoria", "KEY `idx_categoria` (`categoria`)");
            $ensure_index($table_name, "idx_status", "KEY `idx_status` (`status`)");
            $ensure_index($table_name, "idx_data_custo", "KEY `idx_data_custo` (`data_custo`)");
            $ensure_index($table_name, "idx_referencia", "KEY `idx_referencia` (`mes_referencia`, `ano_referencia`)");
            bombeiros_ensure_enum_values($db, $table_name, "status", ["Previsto", "Pago", "Cancelado"], "Pago");

            $table_name = $dbprefix . "grupo_donato_presenca";
            if (!$db->tableExists($table_name)) {
                $db->query("CREATE TABLE IF NOT EXISTS `" . $table_name . "` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `aluno_id` int(11) NOT NULL,
                    `data_aula` date NOT NULL,
                    `status` tinyint(1) DEFAULT 0,
                    `status_tipo` enum('presente','falta','feriado','aula_cancelada','sem_registro') DEFAULT 'sem_registro',
                    `turma` varchar(50) DEFAULT NULL,
                    `observacao` text DEFAULT NULL,
                    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_aluno` (`aluno_id`),
                    KEY `idx_data` (`data_aula`),
                    UNIQUE KEY `unique_aluno_data` (`aluno_id`, `data_aula`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            }
            $ensure_column($table_name, "status_tipo", "enum('presente','falta','feriado','aula_cancelada','sem_registro') DEFAULT 'sem_registro' AFTER `status`");
            $ensure_column($table_name, "turma", "varchar(50) DEFAULT NULL AFTER `status_tipo`");
            $ensure_column($table_name, "observacao", "text DEFAULT NULL AFTER `turma`");
            $presenca_status_column = $db->query("SHOW COLUMNS FROM `" . $table_name . "` LIKE 'status_tipo'")->getRow();
            if ($presenca_status_column && strpos((string) $presenca_status_column->Type, "sem_registro") === false) {
                $db->query("ALTER TABLE `" . $table_name . "` MODIFY `status_tipo` enum('presente','falta','feriado','aula_cancelada','sem_registro') DEFAULT 'sem_registro'");
            }

            $table_name = $dbprefix . "grupo_donato_comprovantes";
            if (!$db->tableExists($table_name)) {
                $db->query("CREATE TABLE IF NOT EXISTS `" . $table_name . "` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `numero_comprovante` varchar(50) DEFAULT NULL,
                    `data_emissao` date DEFAULT NULL,
                    `responsavel_id` int(11) NOT NULL,
                    `responsavel_nome` varchar(255) DEFAULT NULL,
                    `responsavel_cpf` varchar(20) DEFAULT NULL,
                    `aluno_id` int(11) NOT NULL,
                    `aluno_nome` varchar(255) DEFAULT NULL,
                    `aluno_nome_adicional` varchar(255) DEFAULT NULL,
                    `mensalidade_numero` tinyint(4) DEFAULT NULL,
                    `valor` decimal(10,2) NOT NULL,
                    `forma_pagamento` enum('BOLETO','CRÉDITO','DÉBITO','PIX') DEFAULT NULL,
                    `conferido_por` varchar(255) DEFAULT NULL,
                    `data_conferencia` date DEFAULT NULL,
                    `cobranca_id` int(11) DEFAULT NULL,
                    `arquivo_path` varchar(500) DEFAULT NULL,
                    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    `deleted` tinyint(1) DEFAULT 0,
                    PRIMARY KEY (`id`),
                    KEY `idx_responsavel` (`responsavel_id`),
                    KEY `idx_aluno` (`aluno_id`),
                    KEY `idx_cobranca` (`cobranca_id`),
                    KEY `idx_numero` (`numero_comprovante`),
                    KEY `idx_data_emissao` (`data_emissao`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            }

            $table_name = $dbprefix . "grupo_donato_person_unit_access";
            if (!$db->tableExists($table_name)) {
                $db->query("CREATE TABLE IF NOT EXISTS `" . $table_name . "` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `user_id` int(11) NOT NULL,
                    `unit_id` int(11) NOT NULL,
                    `role` enum('owner','director','manager','staff','viewer') DEFAULT 'viewer',
                    `can_view_finance` tinyint(1) DEFAULT 0,
                    `can_manage_finance` tinyint(1) DEFAULT 0,
                    `can_view_students` tinyint(1) DEFAULT 1,
                    `can_manage_students` tinyint(1) DEFAULT 0,
                    `can_view_leads` tinyint(1) DEFAULT 0,
                    `can_manage_leads` tinyint(1) DEFAULT 0,
                    `can_view_templates` tinyint(1) DEFAULT 0,
                    `can_manage_templates` tinyint(1) DEFAULT 0,
                    `can_view_messages` tinyint(1) DEFAULT 0,
                    `can_manage_messages` tinyint(1) DEFAULT 0,
                    `can_import_data` tinyint(1) DEFAULT 0,
                    `can_export_reports` tinyint(1) DEFAULT 0,
                    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    `deleted` tinyint(1) DEFAULT 0,
                    PRIMARY KEY (`id`),
                    KEY `idx_user_unit` (`user_id`, `unit_id`),
                    KEY `idx_unit` (`unit_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            }

            $table_name = $dbprefix . "grupo_donato_leads_palestra";
            if (!$db->tableExists($table_name)) {
                $db->query("CREATE TABLE IF NOT EXISTS `" . $table_name . "` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `unit_id` int(11) NOT NULL,
                    `responsavel_nome` varchar(255) DEFAULT NULL,
                    `aluno_nome` varchar(255) DEFAULT NULL,
                    `telefone` varchar(40) DEFAULT NULL,
                    `telefone_normalizado` varchar(20) DEFAULT NULL,
                    `status` enum('compareceu_palestra','matriculado','nao_matriculado','em_negociacao','perdido','sem_status') DEFAULT 'sem_status',
                    `compareceu_palestra` tinyint(1) DEFAULT 1,
                    `aluno_id` int(11) DEFAULT NULL,
                    `responsavel_id` int(11) DEFAULT NULL,
                    `origem` varchar(100) DEFAULT NULL,
                    `observacao` text DEFAULT NULL,
                    `data_evento` date DEFAULT NULL,
                    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    `deleted` tinyint(1) DEFAULT 0,
                    PRIMARY KEY (`id`),
                    KEY `idx_unit` (`unit_id`),
                    KEY `idx_status` (`status`),
                    KEY `idx_telefone` (`telefone_normalizado`),
                    KEY `idx_aluno` (`aluno_id`),
                    KEY `idx_responsavel` (`responsavel_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
            }
        } catch (\Exception $e) {
            log_message("error", "Erro ao instalar/atualizar plugin Bombeiros: " . $e->getMessage());
        }
    }
}

// Módulo operacional embutido no Grupo Donato: o ciclo de vida é o do plugin hospedeiro.
// `bombeiros_install_or_update()` é invocada pelo gd_install() do Grupo Donato
// (ver index.php) — não registramos hooks sob um nome de plugin inexistente.
