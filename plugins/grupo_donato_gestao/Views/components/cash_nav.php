<?php
/**
 * Barra de abas de Caixa e despesas (Movimentações de caixa / Despesas).
 * Espera: $active (chave da aba ativa).
 */
$active = isset($active) ? $active : "";
echo view("grupo_donato_gestao\\Views\\components\\tabs_nav", ["active" => $active, "items" => [
    ["key" => "cash", "url" => "grupo_donato/finance/cash", "label" => app_lang("gd_finance_cash"), "icon" => "book"],
    ["key" => "costs", "url" => "grupo_donato/finance/costs", "label" => app_lang("gd_costs"), "icon" => "arrow-down-circle"],
]]);
