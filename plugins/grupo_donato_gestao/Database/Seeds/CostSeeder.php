<?php

declare(strict_types=1);

namespace grupo_donato_gestao\Database\Seeds;

use grupo_donato_gestao\Config\Constants;

/** Catálogo estrutural e migração não destrutiva da base antiga de despesas. */
final class CostSeeder
{
    private $db;
    private int $actor_id;

    /** code => [label, children code => label]. Tudo é global e reutilizável. */
    private const CATEGORIES = [
        "property" => ["Estrutura e imóvel", [
            "rent" => "Aluguel", "condominium" => "Condomínio", "property_tax" => "IPTU", "water" => "Água",
            "electricity" => "Energia elétrica", "gas" => "Gás", "internet" => "Internet", "telephony" => "Telefonia",
            "cleaning" => "Limpeza", "cleaning_products" => "Produtos de limpeza", "pest_control" => "Dedetização",
            "gardening" => "Jardinagem", "building_maintenance" => "Manutenção predial", "painting" => "Pintura",
            "electrical" => "Elétrica", "plumbing" => "Hidráulica", "renovation" => "Obras e reformas", "furniture" => "Móveis", "signage" => "Sinalização",
        ]],
        "courts" => ["Quadras", [
            "grass_maintenance" => "Manutenção do gramado", "synthetic_grass" => "Grama sintética", "rubber_sand" => "Borracha / areia",
            "nets" => "Redes", "goals" => "Traves", "lighting" => "Iluminação", "floodlights" => "Refletores", "balls" => "Bolas",
            "bibs" => "Coletes", "cones" => "Cones", "sports_materials" => "Materiais esportivos", "repairs" => "Reparos",
            "line_marking" => "Pintura / demarcação", "fence" => "Alambrado", "sports_equipment" => "Equipamentos esportivos",
        ]],
        "barbecues" => ["Churrasqueiras", [
            "cleaning" => "Limpeza", "gas" => "Gás", "charcoal" => "Carvão", "grills" => "Grelhas", "utensils" => "Utensílios",
            "maintenance" => "Manutenção", "tables" => "Mesas", "chairs" => "Cadeiras", "repairs" => "Reparos", "disposables" => "Descartáveis",
            "replacement" => "Reposição", "cleaning_products" => "Produtos de limpeza",
        ]],
        "parking" => ["Estacionamento", [
            "staff" => "Funcionários", "security" => "Segurança", "gates" => "Cancelas", "gate_maintenance" => "Manutenção de cancelas",
            "tickets" => "Tickets", "cards" => "Cartões", "signage" => "Sinalização", "lighting" => "Iluminação", "cameras" => "Câmeras",
            "parking_system" => "Sistema de estacionamento", "painting" => "Pintura", "floor_maintenance" => "Manutenção do piso",
        ]],
        "bar" => ["Bar", [
            "beverages" => "Bebidas", "food" => "Alimentos", "supplies" => "Insumos", "packaging" => "Embalagens", "disposables" => "Descartáveis",
            "gas" => "Gás", "ice" => "Gelo", "losses" => "Perdas", "breakage" => "Quebras", "expired_products" => "Produtos vencidos",
            "equipment" => "Equipamentos", "maintenance" => "Manutenção", "delivery_fees" => "Taxas de delivery", "card_fees" => "Taxas de cartão",
            "commissions" => "Comissões", "cleaning" => "Limpeza",
        ]],
        "academy" => ["GD Academy / Escola", [
            "teachers" => "Professores", "assistants" => "Auxiliares", "uniforms" => "Uniformes", "teaching_materials" => "Materiais didáticos",
            "sports_materials" => "Materiais esportivos", "workbooks" => "Apostilas", "medals" => "Medalhas", "certificates" => "Certificados",
            "events" => "Eventos", "food" => "Alimentação", "transport" => "Transporte", "equipment" => "Equipamentos", "marketing" => "Marketing",
        ]],
        "staff" => ["Pessoal / equipe", [
            "salaries" => "Salários", "owner_withdrawal" => "Pró-labore", "freelancers" => "Freelancers", "daily_rates" => "Diárias", "overtime" => "Horas extras",
            "commission" => "Comissão", "bonus" => "Bonificação", "fgts" => "FGTS", "inss" => "INSS", "vacation" => "Férias", "thirteenth_salary" => "13º salário",
            "transport_voucher" => "Vale-transporte", "meal_voucher" => "Vale-refeição", "uniforms" => "Uniformes", "training" => "Treinamentos",
            "medical_exams" => "Exames", "outsourced" => "Terceirizados", "accounting" => "Contabilidade", "legal" => "Advocacia", "consulting" => "Consultorias",
        ]],
        "marketing" => ["Marketing e vendas", [
            "meta_ads" => "Meta Ads", "google_ads" => "Google Ads", "agency" => "Agência", "social_media" => "Social Media", "designer" => "Designer",
            "photography" => "Fotografia", "filming" => "Filmagem", "influencers" => "Influenciadores", "sponsorships" => "Patrocínios",
            "printed_material" => "Impressos", "banners" => "Faixas", "flyers" => "Flyers", "gifts" => "Brindes", "promotions" => "Promoções",
            "promotional_events" => "Eventos promocionais", "sales_commission" => "Comissão comercial",
        ]],
        "technology" => ["Tecnologia", [
            "hosting" => "Hospedagem", "servers" => "Servidores", "domain" => "Domínio", "whatsapp_api" => "WhatsApp / API", "crm" => "CRM",
            "systems" => "Sistemas", "licenses" => "Licenças", "google_workspace" => "Google Workspace", "microsoft" => "Microsoft", "internet" => "Internet",
            "computers" => "Computadores", "printers" => "Impressoras", "tablets" => "Tablets", "cameras" => "Câmeras", "it_maintenance" => "Manutenção de TI",
        ]],
        "security" => ["Segurança", [
            "security_staff" => "Vigilância", "monitoring" => "Monitoramento", "alarms" => "Alarmes", "cameras" => "Câmeras", "access_control" => "Controle de acesso",
            "fire_extinguishers" => "Extintores", "extinguisher_recharge" => "Recarga de extintores", "avcb" => "AVCB", "inspections" => "Inspeções",
            "insurance" => "Seguro", "first_aid" => "Primeiros socorros", "emergency_equipment" => "Equipamentos de emergência",
        ]],
        "taxes" => ["Impostos e taxas", [
            "das" => "DAS", "iss" => "ISS", "property_tax" => "IPTU", "municipal_fees" => "Taxas municipais", "business_license" => "Alvará",
            "licenses" => "Licenças", "fire_department" => "Bombeiros", "health_surveillance" => "Vigilância sanitária", "government_fines" => "Multas governamentais", "other_taxes" => "Outros tributos",
        ]],
        "financial" => ["Financeiro", [
            "card_fee" => "Taxa de cartão", "pix_fee" => "Taxa PIX", "bank_fee" => "Tarifa bancária", "interest" => "Juros", "fines" => "Multas",
            "receivables_advance" => "Antecipação de recebíveis", "chargeback" => "Chargeback", "refund" => "Estorno", "gateway" => "Gateway", "iof" => "IOF",
            "loan_interest" => "Juros de empréstimos", "payment_terminal" => "Maquininha",
        ]],
        "events" => ["Eventos e campeonatos", [
            "refereeing" => "Arbitragem", "awards" => "Premiação", "trophies" => "Troféus", "medals" => "Medalhas", "security" => "Segurança",
            "ambulance" => "Ambulância", "event_staff" => "Equipe", "sound" => "Som", "lighting" => "Iluminação", "decoration" => "Decoração",
            "marketing" => "Marketing", "food" => "Alimentação", "materials" => "Materiais", "cleaning" => "Limpeza", "photography" => "Fotografia", "equipment_rental" => "Locação de equipamentos",
        ]],
        "uncategorized" => ["Não categorizado", []],
    ];

    public function __construct(int $actor_id = 0)
    {
        $this->db = db_connect();
        $this->actor_id = $actor_id;
    }

    public function run(): void
    {
        if (!$this->db->tableExists($this->db->prefixTable("gd_expense_categories"))) {
            return;
        }
        $parents = [];
        foreach (self::CATEGORIES as $code => [$name, $children]) {
            $parents[$code] = $this->ensure_category($code, $name, null, 0);
            foreach ($children as $childCode => $childName) {
                $this->ensure_category($code . "." . $childCode, $childName, $parents[$code], 1);
            }
        }
        $this->migrate_legacy_expenses($parents["uncategorized"] ?? null);
    }

    private function ensure_category(string $code, string $name, ?int $parent_id, int $sort_order): int
    {
        $table = $this->db->prefixTable("gd_expense_categories");
        $row = $this->db->table($table)->where("unit_id IS NULL", null, false)->where("code", $code)->where("deleted", 0)->get(1)->getRow();
        if ($row) {
            return (int) $row->id;
        }
        $now = gmdate("Y-m-d H:i:s");
        $this->db->table($table)->insert([
            "unit_id" => null, "parent_id" => $parent_id, "code" => $code, "name" => $name,
            "status" => Constants::STATUS_ACTIVE, "is_system" => 1, "sort_order" => $sort_order,
            "created_at" => $now, "updated_at" => $now, "created_by" => $this->actor_id ?: null,
            "updated_by" => $this->actor_id ?: null, "deleted" => 0,
        ]);
        return (int) $this->db->insertID();
    }

    private function migrate_legacy_expenses(?int $uncategorized_id): void
    {
        $expense_table = $this->db->prefixTable("gd_expenses");
        $payment_table = $this->db->prefixTable("gd_expense_payments");
        $cash_table = $this->db->prefixTable("gd_cash_movements");
        if (!$this->db->tableExists($expense_table) || !$this->db->tableExists($payment_table)) {
            return;
        }
        $rows = $this->db->table($expense_table)->where("deleted", 0)->get()->getResult();
        foreach ($rows as $expense) {
            $id = (int) $expense->id;
            $updates = [];
            if (!$expense->category_id && $uncategorized_id) $updates["category_id"] = $uncategorized_id;
            if (!$expense->issue_date) $updates["issue_date"] = $expense->expense_date;
            if (!$expense->reference_month) $updates["reference_month"] = substr((string) $expense->expense_date, 0, 7);
            if ((string) $expense->gross_amount === "0.00") $updates["gross_amount"] = $expense->amount;
            if ((string) $expense->final_amount === "0.00") $updates["final_amount"] = $expense->amount;

            if ((string) $expense->status === "paid") {
                $updates["paid_amount"] = $expense->amount;
                $updates["balance_amount"] = "0.00";
                $legacy = $this->db->table($payment_table)->where("unit_id", (int) $expense->unit_id)->where("legacy_expense_id", $id)->where("deleted", 0)->get(1)->getRow();
                if (!$legacy) {
                    $movement = null;
                    if ($this->db->tableExists($cash_table)) {
                        $movement = $this->db->table($cash_table)->where("unit_id", (int) $expense->unit_id)->where("source_type", "expense")->where("source_id", $id)->where("movement_type", "out")->get(1)->getRow();
                    }
                    $now = gmdate("Y-m-d H:i:s");
                    $this->db->table($payment_table)->insert([
                        "unit_id" => (int) $expense->unit_id, "expense_id" => $id, "payment_number" => "LEGACY-" . $id,
                        "financial_account_id" => $expense->financial_account_id ?: null, "payment_date" => $expense->paid_date ?: $expense->expense_date,
                        "amount" => $expense->amount, "payment_method" => $expense->payment_method ?: "other",
                        "status" => "legacy_migrated", "legacy_expense_id" => $id, "legacy_cash_movement_id" => $movement->id ?? null,
                        "cash_movement_id" => $movement->id ?? null, "notes" => "Pagamento migrado da despesa legada.",
                        "created_at" => $now, "updated_at" => $now, "created_by" => $this->actor_id ?: null,
                        "updated_by" => $this->actor_id ?: null, "deleted" => 0,
                    ]);
                }
            } elseif ((string) $expense->status !== "cancelled") {
                $updates["paid_amount"] = "0.00";
                $updates["balance_amount"] = $expense->final_amount ?: $expense->amount;
            }
            if ($updates) {
                $updates["updated_at"] = gmdate("Y-m-d H:i:s");
                $this->db->table($expense_table)->where("id", $id)->where("unit_id", (int) $expense->unit_id)->update($updates);
            }
        }
    }
}
