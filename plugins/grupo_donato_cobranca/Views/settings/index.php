<?php echo view('grupo_donato_cobranca\\Views\\components\\nav', ['active' => 'settings']); ?>
<div class="card">
    <div class="page-title clearfix">
        <h1><?php echo app_lang('gdc_integration'); ?></h1>
        <div class="title-button-group">
            <?php echo ajax_anchor(get_uri('cobranca/settings/health'), '<i data-feather="activity" class="icon-16"></i> ' . app_lang('gdc_test_connection'), ['class' => 'btn btn-default', 'data-reload-on-success' => 0]); ?>
        </div>
    </div>
    <div class="card-body">
        <?php echo form_open(get_uri('cobranca/settings/save'), ['id' => 'gdc-settings-form', 'class' => 'general-form']); ?>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label><?php echo app_lang('gdc_provider_code'); ?></label>
                    <input type="text" name="provider_code" class="form-control" maxlength="60" value="<?php echo esc($settings['provider_code']); ?>" placeholder="ex.: asaas, itau, mercado_pago">
                    <small class="text-muted"><?php echo app_lang('gdc_provider_code_help'); ?></small>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label><?php echo app_lang('gdc_connector_label'); ?></label>
                    <input type="text" name="connector_label" class="form-control" maxlength="190" value="<?php echo esc($settings['connector_label']); ?>" placeholder="ex.: Conta principal Grupo Donato">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label><?php echo app_lang('gdc_environment'); ?></label>
                    <select name="environment" class="form-control" required>
                        <option value="sandbox" <?php echo $settings['environment'] === 'sandbox' ? 'selected' : ''; ?>>Sandbox</option>
                        <option value="production" <?php echo $settings['environment'] === 'production' ? 'selected' : ''; ?>>Produção</option>
                    </select>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label><?php echo app_lang('gdc_financial_account'); ?></label>
                    <select name="financial_account_id" class="form-control select2" required>
                        <option value=""></option>
                        <?php foreach ($accounts as $account): ?>
                            <option value="<?php echo (int) $account['id']; ?>" <?php echo (string) $settings['financial_account_id'] === (string) $account['id'] ? 'selected' : ''; ?>><?php echo esc($account['name'] . ' — ' . $account['account_type']); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted"><?php echo app_lang('gdc_financial_account_help'); ?></small>
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    <label><?php echo app_lang('gdc_automatic_billing'); ?></label>
                    <select name="automatic_billing" class="form-control">
                        <option value="0" <?php echo $settings['automatic_billing'] !== '1' ? 'selected' : ''; ?>><?php echo app_lang('no'); ?></option>
                        <option value="1" <?php echo $settings['automatic_billing'] === '1' ? 'selected' : ''; ?>><?php echo app_lang('yes'); ?></option>
                    </select>
                </div>
            </div>
        </div>
        <div class="alert alert-warning">
            <strong><?php echo app_lang('gdc_security'); ?>:</strong> <?php echo app_lang('gdc_credentials_notice'); ?>
        </div>
        <div class="alert alert-info">
            <?php echo app_lang('gdc_connector_contract_notice'); ?>
        </div>
        <button type="submit" class="btn btn-primary"><i data-feather="save" class="icon-16"></i> <?php echo app_lang('save'); ?></button>
        <?php echo form_close(); ?>
    </div>
</div>
<script>
$(document).ready(function(){"use strict";$("#gdc-settings-form .select2").select2();$("#gdc-settings-form").appForm({onSuccess:function(){location.reload();}});});
</script>
