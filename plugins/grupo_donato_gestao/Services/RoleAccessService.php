<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Services;

final class RoleAccessService
{
    public const OPERATIONAL_ANY_SECTION = "__any__";

    private const FULL_ROLE_KEYS = [
        "diretor" => true,
        "director" => true,
        "gestor" => true,
        "manager" => true,
        "secretaria" => true,
        "secretario" => true,
        "secretary" => true,
    ];

    private const PROFESSOR_ROLE_KEYS = [
        "professor" => true,
        "professora" => true,
        "teacher" => true,
    ];

    private const OPERATIONAL_FULL_SECTIONS = [
        "dashboard",
        "alunos",
        "cancelados",
        "concluidos",
        "responsaveis",
        "presenca",
        "pagamentos",
        "financeiro",
        "custos",
        "materiais",
        "leads",
        "mensagens",
        "unidades",
        "importar",
        "comprovantes",
    ];

    private const OPERATIONAL_PROFESSOR_SECTIONS = [
        "alunos",
        "responsaveis",
        "presenca",
    ];

    private const OPERATIONAL_ROUTE_SECTIONS = [
        "trocar_unidade" => self::OPERATIONAL_ANY_SECTION,
        "lista_responsaveis" => "responsaveis",
        "lista_pagamentos" => "pagamentos",
        "financeiro_resumo" => "financeiro",
        "custos" => "custos",
        "unidades" => "unidades",
        "leads_palestra" => "leads",
        "templates_mensagem" => "mensagens",
        "mensagens" => "mensagens",
        "historico_mensagens" => "mensagens",
        "alunos_list_data" => "alunos",
        "responsaveis_list_data" => "responsaveis",
        "unidades_list_data" => "unidades",
        "pagamentos_list_data" => "pagamentos",
        "pagamentos_mensais_resumo" => "pagamentos",
        "inadimplencia_list_data" => "financeiro",
        "cancelados_list_data" => "cancelados",
        "concluidos_list_data" => "concluidos",
        "materiais_list_data" => "materiais",
        "leads_palestra_list_data" => "leads",
        "custos_list_data" => "custos",
        "aluno_modal_form" => "alunos",
        "responsavel_modal_form" => "responsaveis",
        "unidade_modal_form" => "unidades",
        "lead_palestra_modal_form" => "leads",
        "custo_modal_form" => "custos",
        "importar_modal_form" => "importar",
        "comprovante_modal_form" => "comprovantes",
        "baixa_pagamento_modal_form" => "pagamentos",
        "save_aluno" => "alunos",
        "save_responsavel" => "responsaveis",
        "save_unidade" => "unidades",
        "delete_aluno" => "alunos",
        "delete_responsavel" => "responsaveis",
        "delete_unidade" => "unidades",
        "save_custo" => "custos",
        "delete_custo" => "custos",
        "save_lead_palestra" => "leads",
        "delete_lead_palestra" => "leads",
        "converter_lead_em_aluno" => "leads",
        "reativar_aluno" => "cancelados",
        "atualizar_material" => "materiais",
        "lista_chamada" => "presenca",
        "salvar_presenca" => "presenca",
        "baixar_pagamento" => "pagamentos",
        "marcar_pagamento_pendente" => "pagamentos",
        "gerar_mensalidades_periodo" => "pagamentos",
        "criar_cobranca_mensal_aluno" => "pagamentos",
        "toggle_pagamento_mensal" => "pagamentos",
        "importar_csv" => "importar",
        "importar_preview" => "importar",
        "confirmar_importacao" => "importar",
        "importacao_relatorio" => "importar",
        "buscar_dados_comprovante" => "comprovantes",
        "gerar_comprovante" => "comprovantes",
        "baixar_comprovante" => "comprovantes",
        "baixar_comprovante_pdf" => "comprovantes",
        "visualizar_comprovante" => "comprovantes",
        "baixar_exame_medico" => "alunos",
    ];

    /** @var array<int, string> */
    private static array $title_cache = [];

    public static function title_key(?object $user): string
    {
        if (!$user) {
            return "";
        }

        foreach (["job_title", "role_title"] as $property) {
            $key = self::normalize($user->$property ?? "");
            if ($key !== "") {
                return $key;
            }
        }

        $user_id = (int) ($user->id ?? 0);
        if (!$user_id) {
            return "";
        }

        if (!array_key_exists($user_id, self::$title_cache)) {
            self::$title_cache[$user_id] = self::load_title_key($user_id);
        }

        return self::$title_cache[$user_id];
    }

    public static function has_full_plugin_access(?object $user): bool
    {
        if (!$user || ($user->user_type ?? "") !== "staff") {
            return false;
        }

        return !empty($user->is_admin) || self::is_full_access_role($user);
    }

    public static function is_full_access_role(?object $user): bool
    {
        return isset(self::FULL_ROLE_KEYS[self::title_key($user)]);
    }

    public static function is_professor(?object $user): bool
    {
        return isset(self::PROFESSOR_ROLE_KEYS[self::title_key($user)]);
    }

    /** @return array<string> */
    public static function allowed_operational_sections(?object $user): array
    {
        if (!$user || ($user->user_type ?? "") !== "staff") {
            return [];
        }

        if (!empty($user->is_admin) || self::is_full_access_role($user)) {
            return self::OPERATIONAL_FULL_SECTIONS;
        }

        if (self::is_professor($user)) {
            return self::OPERATIONAL_PROFESSOR_SECTIONS;
        }

        return [];
    }

    public static function can_access_operational_section(?object $user, string $section): bool
    {
        return in_array(self::normalize($section), self::allowed_operational_sections($user), true);
    }

    public static function default_operational_section(?object $user): string
    {
        $sections = self::allowed_operational_sections($user);
        return $sections[0] ?? "";
    }

    public static function operational_route_section(string $method): ?string
    {
        $method = strtolower(trim($method));
        return self::OPERATIONAL_ROUTE_SECTIONS[$method] ?? null;
    }

    private static function load_title_key(int $user_id): string
    {
        if (!function_exists("db_connect")) {
            return "";
        }

        try {
            $db = db_connect();
            $users_table = $db->prefixTable("users");
            $roles_table = $db->prefixTable("roles");
            $row = $db->table($users_table . " AS users")
                ->select("users.job_title, roles.title AS role_title")
                ->join($roles_table . " AS roles", "roles.id = users.role_id AND roles.deleted = 0", "left")
                ->where("users.id", $user_id)
                ->where("users.deleted", 0)
                ->get()
                ->getRow();

            foreach (["job_title", "role_title"] as $property) {
                $key = self::normalize($row->$property ?? "");
                if ($key !== "") {
                    return $key;
                }
            }
        } catch (\Throwable $e) {
            log_message("error", "GD access: falha ao carregar cargo do usuario " . $user_id . ": " . $e->getMessage());
        }

        return "";
    }

    private static function normalize($value): string
    {
        $value = trim((string) $value);
        if ($value === "") {
            return "";
        }

        if (function_exists("iconv")) {
            $converted = @iconv("UTF-8", "ASCII//TRANSLIT", $value);
            if ($converted !== false) {
                $value = $converted;
            }
        }

        $value = strtolower($value);
        $value = preg_replace("/[^a-z0-9]+/", "_", $value);
        return trim((string) $value, "_");
    }
}
