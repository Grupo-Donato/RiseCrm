<?php
/**
 * Barra de abas do Financeiro (Resumo / Pagamentos / Gerar cobranças).
 * Espera: $active (chave da aba ativa). Permite navegar sem depender do menu lateral.
 */
$active = isset($active) ? $active : "";
echo view("grupo_donato_gestao\\Views\\components\\tabs_nav", ["active" => $active, "items" => [
    ["key" => "overview", "url" => "grupo_donato/finance", "label" => app_lang("gd_finance_summary"), "icon" => "pie-chart"],
    ["key" => "payments", "url" => "grupo_donato/finance/payments", "label" => app_lang("gd_finance_payments"), "icon" => "check-circle"],
    ["key" => "generate", "url" => "grupo_donato/finance/generate", "label" => app_lang("gd_finance_generate"), "icon" => "refresh-cw"],
    ["key" => "costs", "url" => "grupo_donato/finance/costs", "label" => app_lang("gd_costs"), "icon" => "arrow-down-circle"],
]]);
