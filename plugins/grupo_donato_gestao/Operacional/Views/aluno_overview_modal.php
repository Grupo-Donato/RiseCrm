<?php
$student = $model_info ?? (object) [];
$finance = is_array($finance_overview ?? null) ? $finance_overview : [];
$finance_summary = is_array($finance["summary"] ?? null) ? $finance["summary"] : [];
$finance_rows = is_array($finance["rows"] ?? null) ? $finance["rows"] : [];
$finance_groups = is_array($finance["groups"] ?? null) ? $finance["groups"] : [];
$history_events = is_array($student_sport_history["events"] ?? null) ? $student_sport_history["events"] : [];
$can_edit = !empty($can_edit);
$can_manage_finance = !empty($can_manage_finance);

$money = static fn($value): string => "R$ " . number_format((float) $value, 2, ",", ".");
$value = static fn($value, string $fallback = "-"): string => trim((string) $value) !== "" ? trim((string) $value) : $fallback;
$date = static function ($date): string {
    $date = substr(trim((string) $date), 0, 10);
    if ($date === "") return "-";
    $parsed = \DateTimeImmutable::createFromFormat("!Y-m-d", $date);
    $errors = \DateTimeImmutable::getLastErrors();
    if (!$parsed || ($errors !== false && ($errors["warning_count"] || $errors["error_count"]))) return $date;
    return $parsed->format("d/m/Y");
};
$detail = static function (string $label, $content): string {
    return "<div class='gd-overview-detail'><span>" . esc($label) . "</span><strong>" . esc(trim((string) $content) !== "" ? (string) $content : "-") . "</strong></div>";
};
$status_labels = [
    "paid" => "Pago", "open" => "Em aberto", "partial" => "Parcial", "overdue" => "Vencido",
    "cancelled" => "Cancelado", "exempt" => "Isento", "awaiting_charge" => "Aguardando cobrança",
];
$status_class = static fn(string $status): string => "gd-overview-status--" . preg_replace("/[^a-z0-9_-]/", "", strtolower($status));
$status_label = static fn(string $status): string => $status_labels[$status] ?? ($status !== "" ? ucfirst($status) : "Sem registro");
$category_labels = [
    "mensalidade" => "Mensalidade", "campeonato" => "Campeonato", "uniforme" => "Uniforme",
    "caneleira" => "Caneleira", "meiao" => "Meião", "passeio" => "Passeio", "matricula" => "Matrícula", "outros" => "Outros",
];
$category_label = static fn(string $category): string => $category_labels[$category] ?? "Outros";

$material = static function ($status, $legacy): array {
    $raw = trim((string) ($status ?: $legacy));
    $normalized = mb_strtolower($raw);
    if ($raw === "") return ["Não informado", "gd-overview-material--empty"];
    if (in_array($normalized, ["entregue", "efetuado", "sim", "pago", "ok"], true)) return ["Entregue", "gd-overview-material--done"];
    if (in_array($normalized, ["pendente", "a ser pago", "aguardando", "sem registro", "não", "nao", "não entregue", "nao_entregue"], true)) return ["Pendente", "gd-overview-material--pending"];
    return [$raw, "gd-overview-material--info"];
};
$material_items = [
    ["label" => "Uniforme", "status" => $student->camiseta_status ?? "", "legacy" => $student->camiseta ?? "", "icon" => "shopping-bag"],
    ["label" => "Caneleira", "status" => $student->material_01_status ?? "", "legacy" => $student->material_01 ?? "", "icon" => "shield"],
    ["label" => "Meião", "status" => $student->material_02_status ?? "", "legacy" => $student->material_02 ?? "", "icon" => "layers"],
];

$student_photo_default_url = get_avatar();
$student_photo_has_current = !empty($student->id) && !empty($student->photo_path);
$student_photo_url = $student_photo_has_current
    ? get_uri("grupo_donato/operacional/foto_aluno/" . (int) $student->id) . "?v=" . rawurlencode(pathinfo((string) $student->photo_path, PATHINFO_FILENAME))
    : $student_photo_default_url;
$status_student = (string) ($student->status ?? "");
$status_student_label = ["Ativo" => "Ativo", "Cancelado" => "Cancelado", "Concluido" => "Concluído", "Inativo" => "Inativo", "Pendente" => "Pendente", "Inadimplente" => "Inadimplente"][$status_student] ?? ($status_student ?: "Sem status");
$responsible_whats = preg_replace("/\D+/", "", (string) ($student->responsavel_whats ?? ""));
if ($responsible_whats !== "" && strlen($responsible_whats) <= 11) $responsible_whats = "55" . $responsible_whats;
?>

<div id="gd-aluno-overview-modal" class="modal-body p-0 gd-overview-root">
    <div class="gd-overview-shell">
        <section class="gd-overview-hero">
            <img class="gd-overview-avatar" src="<?php echo esc($student_photo_url); ?>" alt="Foto de <?php echo esc($student->nome_aluno ?? "aluno"); ?>" onerror="this.onerror=null;this.src='<?php echo esc($student_photo_default_url); ?>';">
            <div class="gd-overview-hero-main">
                <h2><?php echo esc($value($student->nome_aluno ?? "Aluno")); ?></h2>
                <div class="gd-overview-subtitle"><?php echo esc($value($student->nome_unidade ?? "Unidade não informada")); ?><?php if (!empty($student->unidade_cidade)): ?> · <?php echo esc($student->unidade_cidade); ?><?php endif; ?></div>
                <div class="gd-overview-chips">
                    <span class="gd-overview-chip">Matrícula <?php echo esc($value($student->matricula ?? "", (string) ($student->id ?? "-"))); ?></span>
                    <span class="gd-overview-chip"><?php echo esc($value($student_age ?? "-")); ?></span>
                    <span class="gd-overview-chip gd-overview-chip--status"><?php echo esc($status_student_label); ?></span>
                </div>
            </div>
            <?php if ($can_edit): ?>
                <?php echo modal_anchor(get_uri("grupo_donato/operacional/aluno_modal_form"), "<i data-feather='edit-3' class='icon-16'></i><span class='gd-overview-edit-label ms-1'>Editar ficha</span>", ["class" => "btn btn-primary gd-overview-hero-action", "title" => "Editar aluno", "data-post-id" => (int) $student->id]); ?>
            <?php endif; ?>
        </section>

        <section class="gd-overview-metrics" aria-label="Resumo financeiro">
            <div class="gd-overview-metric gd-overview-metric--balance"><span>Saldo em aberto</span><strong><?php echo $money($finance_summary["balance_amount"] ?? 0); ?></strong></div>
            <div class="gd-overview-metric"><span>Total pago</span><strong><?php echo $money($finance_summary["paid_amount"] ?? 0); ?></strong></div>
            <div class="gd-overview-metric"><span>Cobranças reais</span><strong><?php echo (int) ($finance_summary["open_count"] ?? 0); ?> em aberto</strong></div>
            <div class="gd-overview-metric gd-overview-metric--danger"><span>Vencidas</span><strong><?php echo (int) ($finance_summary["overdue_count"] ?? 0); ?></strong></div>
        </section>

        <div class="gd-overview-layout">
            <div>
                <section class="gd-overview-section">
                    <div class="gd-overview-section-heading"><div><h3>Dados do aluno</h3><p>Cadastro, matrícula e vínculo com a academia.</p></div></div>
                    <div class="gd-overview-detail-grid">
                        <?php echo $detail("Nome completo", $student->nome_aluno ?? ""); ?>
                        <?php echo $detail("Nascimento", $date($student->nascimento_aluno ?? "")); ?>
                        <?php echo $detail("RG", $student->rg_aluno ?? ""); ?>
                        <?php echo $detail("CPF", $student->cpf_aluno ?? ""); ?>
                        <?php echo $detail("Matrícula", $student->matricula ?? ($student->id ?? "")); ?>
                        <?php echo $detail("Turma", $student->turma ?? ""); ?>
                        <?php echo $detail("Pelotão", $student->pelotao ?? ""); ?>
                        <?php echo $detail("Melhor horário", $student->melhor_horario_ligacao ?? ($student->horario ?? "")); ?>
                        <?php echo $detail("GD Academy / clube", $student->nome_unidade ?? ""); ?>
                        <?php echo $detail("Status", $status_student_label); ?>
                        <?php echo $detail("Data da matrícula", $date($student->data_matricula ?? ($student->data_inscricao ?? ""))); ?>
                        <?php echo $detail("Início da mensalidade", $date($student->data_inicio ?? "")); ?>
                        <?php echo $detail("Curso", $student->curso_nome ?? ""); ?>
                        <?php echo $detail("Mensalidade recorrente", $money($student->valor_mensalidade ?? ($student->valor_mensal ?? 0))); ?>
                    </div>

                    <div class="gd-overview-divider"></div>
                    <div class="gd-overview-section-heading"><div><h3>Itens e materiais</h3><p>Controle de entrega informado no cadastro.</p></div></div>
                    <div class="gd-overview-materials">
                        <?php foreach ($material_items as $material_item): ?>
                            <?php [$material_label, $material_class] = $material($material_item["status"], $material_item["legacy"]); ?>
                            <div class="gd-overview-material <?php echo esc($material_class); ?>">
                                <i data-feather="<?php echo esc($material_item["icon"]); ?>" class="icon-18"></i>
                                <div><strong><?php echo esc($material_item["label"]); ?></strong><small><?php echo esc($material_label); ?></small></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="gd-overview-section">
                    <div class="gd-overview-section-heading"><div><h3>Responsável</h3><p>Contato e endereço vinculados ao aluno.</p></div></div>
                    <div class="gd-overview-detail-grid">
                        <?php echo $detail("Nome", $student->responsavel_nome ?? ""); ?>
                        <?php echo $detail("Nascimento", $date($student->responsavel_nascimento ?? "")); ?>
                        <?php echo $detail("RG", $student->responsavel_rg ?? ""); ?>
                        <?php echo $detail("CPF", $student->responsavel_cpf ?? ""); ?>
                        <?php echo $detail("WhatsApp", $student->responsavel_whats ?? ""); ?>
                        <?php echo $detail("Celular", $student->responsavel_celular ?? ""); ?>
                        <?php echo $detail("E-mail", $student->responsavel_email ?? ""); ?>
                        <?php echo $detail("Recado", $student->responsavel_recado ?? ""); ?>
                        <?php echo $detail("Endereço", trim((string) ($student->responsavel_endereco ?? "") . ", " . (string) ($student->responsavel_numero ?? ""))); ?>
                        <?php echo $detail("Complemento", $student->responsavel_complemento ?? ""); ?>
                        <?php echo $detail("Bairro", $student->responsavel_bairro ?? ""); ?>
                        <?php echo $detail("CEP", $student->responsavel_cep ?? ""); ?>
                        <?php echo $detail("Cidade", $student->responsavel_cidade ?? ""); ?>
                    </div>
                    <?php if ($responsible_whats !== ""): ?>
                        <div class="mt-3"><a class="gd-overview-link" href="https://wa.me/<?php echo esc($responsible_whats); ?>" target="_blank" rel="noopener"><i data-feather="message-circle" class="icon-14"></i> Abrir conversa no WhatsApp</a></div>
                    <?php endif; ?>
                </section>

                <section class="gd-overview-section">
                    <details class="gd-overview-collapsible">
                        <summary>Ver dados adicionais do contrato, assinatura e exame médico</summary>
                        <div class="gd-overview-detail-grid">
                            <?php echo $detail("Inscrição", $money($student->valor_inscricao ?? 0)); ?>
                            <?php echo $detail("Quer uniforme", $value($student->quer_camisa ?? "")); ?>
                            <?php echo $detail("Tamanho da camisa", $student->tamanho_camisa ?? ($student->tamanho_camiseta ?? "")); ?>
                            <?php echo $detail("Observação de materiais", $student->materiais_observacao ?? ""); ?>
                            <?php echo $detail("Exame médico", !empty($student->exame_medico) ? "Anexado" : "Não anexado"); ?>
                            <?php echo $detail("Nome do exame", $student->exame_medico_nome ?? ""); ?>
                            <?php echo $detail("Assinatura contratada", !empty($student->assinatura_contratada) ? "Registrada" : "Não registrada"); ?>
                            <?php echo $detail("Assinatura contratante", !empty($student->assinatura_contratante) ? "Registrada" : "Não registrada"); ?>
                            <?php echo $detail("Ciente", !empty($student->li_ciente) ? "Sim" : "Não"); ?>
                            <?php echo $detail("Observação do cancelamento", $student->observacao_cancelamento ?? ""); ?>
                        </div>
                        <?php if (!empty($student->exame_medico)): ?>
                            <div class="mt-3"><a class="gd-overview-link" href="<?php echo esc(get_uri("grupo_donato/operacional/baixar_exame_medico/" . (int) $student->id)); ?>" target="_blank" rel="noopener"><i data-feather="file-text" class="icon-14"></i> Abrir exame médico</a></div>
                        <?php endif; ?>
                    </details>
                </section>
            </div>

            <div>
                <section class="gd-overview-section">
                    <div class="gd-overview-section-heading">
                        <div><h3>Saldo e histórico financeiro</h3><p>Todas as cobranças identificadas para este aluno em uma única leitura.</p></div>
                        <?php if ($can_manage_finance): ?>
                            <?php echo modal_anchor(get_uri("grupo_donato/operacional/nova_cobranca_modal_form"), "<i data-feather='plus-circle' class='icon-14'></i><span class='ms-1'>Novo lançamento</span>", ["class" => "btn btn-primary btn-sm", "title" => "Lançar nova cobrança", "data-post-aluno_id" => (int) ($student->id ?? 0)]); ?>
                        <?php endif; ?>
                    </div>
                    <?php if ((int) ($finance_summary["awaiting_charge_count"] ?? 0) > 0): ?>
                        <div class="gd-overview-finance-alert"><strong><?php echo (int) $finance_summary["awaiting_charge_count"]; ?> cobrança(s) de campeonato aguardando emissão</strong> · <?php echo $money($finance_summary["awaiting_charge_total"] ?? 0); ?> ainda não entra(m) no saldo em aberto.</div>
                    <?php endif; ?>
                    <?php if (!$finance_rows): ?>
                        <div class="gd-overview-empty">Nenhuma cobrança registrada para este aluno.</div>
                    <?php else: ?>
                        <div class="gd-overview-finance-list">
                            <?php foreach ($finance_rows as $finance_row): ?>
                                <?php
                                $row_status = (string) ($finance_row["status"] ?? "");
                                $row_category = (string) ($finance_row["category"] ?? "outros");
                                $finance_actions = "";
                                if ($can_manage_finance && ($finance_row["source"] ?? "") === "operacional" && !empty($finance_row["charge_id"])) {
                                    if ($row_status === "paid") {
                                        $finance_actions .= js_anchor("<i data-feather='rotate-ccw' class='icon-14'></i><span class='ms-1'>Desfazer</span>", ["class" => "btn btn-default btn-sm gd-overview-marcar-pendente", "title" => "Desfazer baixa", "data-id" => (int) $finance_row["charge_id"]]);
                                    } elseif (!in_array($row_status, ["cancelled", "exempt"], true)) {
                                        $finance_actions .= modal_anchor(get_uri("grupo_donato/operacional/baixa_pagamento_modal_form"), "<i data-feather='check-circle' class='icon-14'></i><span class='ms-1'>Baixar</span>", ["class" => "btn btn-primary btn-sm", "title" => "Baixar pagamento", "data-post-id" => (int) $finance_row["charge_id"], "data-post-aluno_id" => (int) ($finance_row["aluno_id"] ?? $student->id ?? 0)]);
                                    }
                                    $finance_actions .= modal_anchor(get_uri("grupo_donato/operacional/comprovante_modal_form"), "<i data-feather='file-text' class='icon-14'></i>", ["class" => "btn btn-default btn-sm", "title" => "Gerar comprovante", "data-post-cobranca_id" => (int) $finance_row["charge_id"], "data-post-aluno_id" => (int) ($finance_row["aluno_id"] ?? $student->id ?? 0)]);
                                } elseif ($can_manage_finance && ($finance_row["source"] ?? "") === "academy" && !empty($finance_row["participant_id"])) {
                                    if ($row_status === "awaiting_charge") {
                                        $finance_actions .= modal_anchor(get_uri("grupo_donato/operacional/event_charge_modal"), "<i data-feather='file-plus' class='icon-14'></i><span class='ms-1'>Gerar cobrança</span>", ["class" => "btn btn-default btn-sm", "title" => "Gerar cobrança do campeonato", "data-post-participant_id" => (int) $finance_row["participant_id"], "data-modal-class" => "gd-payment-modal"]);
                                    } elseif (in_array($row_status, ["open", "partial", "overdue"], true)) {
                                        $finance_actions .= modal_anchor(get_uri("grupo_donato/operacional/event_payment_modal"), "<i data-feather='check-circle' class='icon-14'></i><span class='ms-1'>Baixar</span>", ["class" => "btn btn-primary btn-sm", "title" => "Baixar pagamento do campeonato", "data-post-participant_id" => (int) $finance_row["participant_id"], "data-post-reload_target" => "gd-aluno-overview-modal", "data-modal-class" => "gd-payment-modal"]);
                                    } elseif ($row_status === "paid") {
                                        $finance_actions .= ajax_anchor(get_uri("grupo_donato/operacional/event_reverse_payment"), "<i data-feather='rotate-ccw' class='icon-14'></i><span class='ms-1'>Desfazer</span>", ["class" => "btn btn-default btn-sm", "title" => "Desfazer baixa do campeonato", "data-post-participant_id" => (int) $finance_row["participant_id"], "data-post-reason" => "Estorno manual de pagamento de evento", "data-reload-on-success" => 1]);
                                    }
                                }
                                ?>
                                <article class="gd-overview-finance-row">
                                    <div class="gd-overview-finance-main">
                                        <div class="gd-overview-finance-title"><span class="gd-overview-type"><?php echo esc($category_label($row_category)); ?></span><strong><?php echo esc($value($finance_row["description"] ?? "Cobrança")); ?></strong></div>
                                        <div class="gd-overview-finance-meta">
                                            <span>Vencimento: <?php echo esc($date($finance_row["due_date"] ?? "")); ?></span>
                                            <?php if (!empty($finance_row["reference"])): ?><span>Ref.: <?php echo esc($finance_row["reference"]); ?></span><?php endif; ?>
                                            <?php if (!empty($finance_row["payment_date"])): ?><span>Pago em: <?php echo esc($date($finance_row["payment_date"])); ?></span><?php endif; ?>
                                        </div>
                                        <span class="gd-overview-status <?php echo esc($status_class($row_status)); ?>"><?php echo esc($status_label($row_status)); ?></span>
                                    </div>
                                    <div class="gd-overview-finance-amount"><strong><?php echo $money($finance_row["original_amount"] ?? 0); ?></strong><?php if ((float) ($finance_row["paid_amount"] ?? 0) > 0): ?><small>Pago: <?php echo $money($finance_row["paid_amount"]); ?></small><?php endif; ?><?php if ((float) ($finance_row["balance_amount"] ?? 0) > 0): ?><small>Saldo: <?php echo $money($finance_row["balance_amount"]); ?></small><?php endif; ?><?php if ($finance_actions !== ""): ?><div class="gd-overview-finance-actions"><?php echo $finance_actions; ?></div><?php endif; ?></div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="gd-overview-section">
                    <div class="gd-overview-section-heading"><div><h3>Campeonatos convocados</h3><p>Convocações, categoria, confirmação e situação financeira.</p></div><span class="gd-overview-chip"><?php echo count($history_events); ?></span></div>
                    <?php if (!$history_events): ?>
                        <div class="gd-overview-empty">Nenhum campeonato encontrado para este aluno.</div>
                    <?php else: ?>
                        <div class="gd-overview-event-list">
                            <?php foreach ($history_events as $event): ?>
                                <?php
                                $lineup = (string) ($event->lineup_status ?? "");
                                $confirmation = (string) ($event->confirmation_status ?? "");
                                $event_finance = (string) ($event->financial_status ?? "");
                                $event_finance_ui = ["paid" => "paid", "confirmed" => "paid", "partial" => "partial", "open" => "open", "pending" => "open", "overdue" => "overdue", "cancelled" => "cancelled", "canceled" => "cancelled"][$event_finance] ?? "";
                                $lineup_labels = ["called" => "Convocado", "starter" => "Titular", "substitute" => "Reserva", "absent" => "Ausente", "cut" => "Cortado"];
                                $confirmation_labels = ["waiting" => "Aguardando confirmação", "confirmed" => "Confirmado", "refused" => "Recusado", "no_response" => "Sem resposta"];
                                ?>
                                <article class="gd-overview-event">
                                    <div class="gd-overview-event-head"><strong><?php echo esc($value($event->event_name ?? "Campeonato")); ?></strong><?php if ($event_finance_ui !== ""): ?><span class="gd-overview-status <?php echo esc($status_class($event_finance_ui)); ?>"><?php echo esc($status_label($event_finance_ui)); ?></span><?php endif; ?></div>
                                    <div class="gd-overview-event-meta">
                                        <span><?php echo esc($value($event->category_name ?? "Categoria não informada")); ?></span>
                                        <span><?php echo esc($date($event->starts_on ?? "")); ?></span>
                                        <span><?php echo esc($lineup_labels[$lineup] ?? ($lineup ?: "Convocação registrada")); ?></span>
                                        <span><?php echo esc($confirmation_labels[$confirmation] ?? ($confirmation ?: "Confirmação não informada")); ?></span>
                                        <?php if ((float) ($event->amount ?? 0) > 0): ?><span><?php echo $money($event->amount); ?></span><?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="gd-overview-section">
                    <div class="gd-overview-section-heading"><div><h3>Passeios</h3><p>Itens do tipo passeio aparecem aqui quando houver cobrança lançada.</p></div></div>
                    <?php $passeios = $finance_groups["passeio"] ?? []; ?>
                    <?php if (!$passeios): ?><div class="gd-overview-empty">Nenhum passeio lançado para este aluno.</div><?php else: ?>
                        <div class="gd-overview-finance-list">
                            <?php foreach ($passeios as $passeio): ?><div class="gd-overview-finance-row"><div class="gd-overview-finance-main"><div class="gd-overview-finance-title"><span class="gd-overview-type">Passeio</span><strong><?php echo esc($passeio["description"] ?? "Passeio"); ?></strong></div><div class="gd-overview-finance-meta"><span>Vencimento: <?php echo esc($date($passeio["due_date"] ?? "")); ?></span></div><span class="gd-overview-status <?php echo esc($status_class((string) ($passeio["status"] ?? ""))); ?>"><?php echo esc($status_label((string) ($passeio["status"] ?? ""))); ?></span></div><div class="gd-overview-finance-amount"><strong><?php echo $money($passeio["original_amount"] ?? 0); ?></strong></div></div><?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var $ajaxModal = $("#ajaxModal");
        $ajaxModal.addClass("gd-aluno-overview-open");
        $ajaxModal.off("hidden.gdAlunoOverview").on("hidden.gdAlunoOverview", function () {
            $(this).removeClass("gd-aluno-overview-open");
        });

        if (window.feather) feather.replace();

        $("body").off("click.gdAlunoOverviewPendente", "#gd-aluno-overview-modal .gd-overview-marcar-pendente").on("click.gdAlunoOverviewPendente", "#gd-aluno-overview-modal .gd-overview-marcar-pendente", function () {
            var $link = $(this);
            $link.addClass("disabled");
            appAjaxRequest({
                url: "<?php echo_uri("grupo_donato/operacional/marcar_pagamento_pendente"); ?>",
                type: "POST",
                dataType: "json",
                data: {id: $link.data("id")},
                success: function (result) {
                    if (result.success) {
                        appAlert.success(result.message);
                        $("#ajaxModal").modal("hide");
                        if (window.reloadBombeirosTable) {
                            reloadBombeirosTable("#bombeiros-pagamentos-table");
                            reloadBombeirosTable("#bombeiros-inadimplencia-table");
                        }
                        if (window.reloadBombeirosPagamentosResumo) reloadBombeirosPagamentosResumo();
                        if (window.reloadBombeirosFinanceiro) reloadBombeirosFinanceiro();
                    } else {
                        appAlert.error(result.message);
                        $link.removeClass("disabled");
                    }
                },
                error: function () {
                    $link.removeClass("disabled");
                    appAlert.error(AppLanugage.somethingWentWrong);
                }
            });

            return false;
        });
    })();
</script>
