<?php $relatorio = $relatorio ?? []; ?>

<div class="modal-body clearfix">
    <div class="container-fluid">
        <?php if (!$relatorio): ?>
            <div class="alert alert-warning mb0">Nenhum relatório de importação disponível.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <tbody>
                        <?php
                        $labels = [
                            "responsaveis_criados" => "Responsáveis criados",
                            "responsaveis_atualizados" => "Responsáveis atualizados",
                            "alunos_criados" => "Alunos criados",
                            "alunos_atualizados" => "Alunos atualizados",
                            "alunos_ignorados" => "Alunos ignorados",
                            "pagamentos_criados" => "Pagamentos criados",
                            "pagamentos_atualizados" => "Pagamentos atualizados",
                            "pagamentos_ignorados" => "Pagamentos ignorados",
                            "mensalidades_criadas" => "Mensalidades criadas",
                            "materiais_atualizados" => "Materiais atualizados",
                            "presencas_criadas" => "Presenças criadas",
                            "presencas_ignoradas" => "Presenças ignoradas",
                            "leads_criados" => "Leads criados",
                            "leads_atualizados" => "Leads atualizados"
                        ];
                        ?>
                        <?php foreach ($labels as $key => $label): ?>
                            <tr>
                                <td><?php echo esc($label); ?></td>
                                <td class="text-end w100"><span class="badge bg-info"><?php echo (int) ($relatorio[$key] ?? 0); ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($relatorio["duplicidades"])): ?>
                <div class="alert alert-warning">
                    <strong>Duplicidades</strong>
                    <ul class="mb0">
                        <?php foreach ($relatorio["duplicidades"] as $item): ?>
                            <li><?php echo esc($item); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($relatorio["erros"])): ?>
                <div class="alert alert-warning">
                    <strong>Erros por registro</strong>
                    <ul class="mb0">
                        <?php foreach ($relatorio["erros"] as $item): ?>
                            <li><?php echo esc($item); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($relatorio["erros_criticos"])): ?>
                <div class="alert alert-danger">
                    <strong>Erros críticos</strong>
                    <ul class="mb0">
                        <?php foreach ($relatorio["erros_criticos"] as $item): ?>
                            <li><?php echo esc($item); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($relatorio["alertas_qualidade"])): ?>
                <div class="alert alert-info">
                    <strong>Alertas de qualidade</strong>
                    <pre class="mb0"><?php echo esc(json_encode($relatorio["alertas_qualidade"], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="modal-footer">
    <button type="button" class="btn btn-default" data-bs-dismiss="modal"><span data-feather="x" class="icon-16"></span> <?php echo app_lang("close"); ?></button>
</div>
