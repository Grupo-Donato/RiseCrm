<?php
$titulo = $titulo ?? "Mensagens";
$rows = $rows ?? [];
?>

<div class="card">
    <div class="page-title clearfix">
        <h4><?php echo esc($titulo); ?></h4>
    </div>
    <div class="p20">
        <?php if (empty($disponivel)): ?>
            <div class="alert alert-warning mb0">Módulo de mensagens ainda não conectado à base IARA.</div>
        <?php elseif (empty($rows)): ?>
            <div class="alert alert-info mb0">Base IARA conectada, sem registros para exibir.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb0">
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <td><pre class="mb0"><?php echo esc(json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
