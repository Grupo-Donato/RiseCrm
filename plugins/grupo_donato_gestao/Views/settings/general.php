<?php
$active_unit_id = $active_unit && isset($active_unit->id) ? (int) $active_unit->id : 0;
?>
<div id="page-content" class="page-wrapper clearfix">
    <div class="row">
        <div class="col-sm-3 col-lg-2">
            <?php echo view("grupo_donato_gestao\\Views\\components\\settings_nav", ["active_tab" => "general"]); ?>
        </div>
        <div class="col-sm-9 col-lg-10">
            <?php
            $hub = [];
            if (!empty($can_units)) { $hub[] = ["url" => "grupo_donato/settings/units", "icon" => "map-pin", "label" => app_lang("gd_units")]; }
            if (!empty($can_areas)) { $hub[] = ["url" => "grupo_donato/settings/business-areas", "icon" => "grid", "label" => app_lang("gd_business_areas")]; }
            if (!empty($can_centers)) { $hub[] = ["url" => "grupo_donato/settings/cost-centers", "icon" => "pie-chart", "label" => app_lang("gd_cost_centers")]; }
            if (!empty($can_catalog)) { $hub[] = ["url" => "grupo_donato/catalog/products", "icon" => "package", "label" => app_lang("gd_menu_products")]; }
            if (!empty($can_resources)) { $hub[] = ["url" => "grupo_donato/resources", "icon" => "map", "label" => app_lang("gd_menu_resources")]; }
            if (!empty($can_pricing)) { $hub[] = ["url" => "grupo_donato/pricing/lists", "icon" => "tag", "label" => app_lang("gd_menu_price_lists")]; }
            if (!empty($can_audit)) { $hub[] = ["url" => "grupo_donato/audit", "icon" => "shield", "label" => app_lang("gd_audit")]; }
            if (!empty($is_admin)) { $hub[] = ["url" => "roles", "icon" => "lock", "label" => app_lang("gd_rise_permissions")]; }
            ?>
            <?php if (count($hub)) { ?>
                <div class="card mb-3"><div class="card-body">
                    <div class="widget-title mb-3"><?php echo app_lang("gd_settings_hub"); ?></div>
                    <div class="row">
                        <?php foreach ($hub as $card) { ?>
                            <div class="col-md-3 col-sm-6 mb-3">
                                <a href="<?php echo get_uri($card["url"]); ?>" class="card h-100 text-center text-decoration-none">
                                    <div class="card-body">
                                        <i data-feather="<?php echo $card["icon"]; ?>" class="icon-24 mb-2"></i>
                                        <div><?php echo htmlspecialchars((string) $card["label"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></div>
                                    </div>
                                </a>
                            </div>
                        <?php } ?>
                    </div>
                </div></div>
            <?php } ?>
            <div class="card mb-3"><div class="card-body">
                <div class="widget-title mb-2"><i data-feather="info" class="icon-16"></i> <?php echo app_lang("gd_system_info"); ?></div>
                <div class="row">
                    <div class="col-md-4"><small class="text-muted d-block"><?php echo app_lang("gd_plugin_version"); ?></small><?php echo htmlspecialchars((string) $plugin_version); ?></div>
                    <div class="col-md-4"><small class="text-muted d-block"><?php echo app_lang("gd_schema_version"); ?></small><?php echo htmlspecialchars((string) $schema_applied); ?> / <?php echo htmlspecialchars((string) $schema_target); ?></div>
                    <div class="col-md-4"><small class="text-muted d-block"><?php echo app_lang("gd_active_unit"); ?></small><?php echo $active_unit && isset($active_unit->name) ? htmlspecialchars((string) $active_unit->name, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") : "-"; ?></div>
                </div>
            </div></div>
            <div class="card">
                <div class="page-title clearfix">
                    <h4><?php echo app_lang("gd_settings_general"); ?></h4>
                </div>
                <div class="card-body">
                    <?php echo form_open(get_uri("grupo_donato/settings/general/save"), ["id" => "gd-general-form", "class" => "general-form", "role" => "form"]); ?>

                    <div class="form-group row mb-3">
                        <label for="active_unit_id" class="col-md-3 col-form-label"><?php echo app_lang("gd_active_unit"); ?></label>
                        <div class="col-md-6">
                            <select name="active_unit_id" id="active_unit_id" class="form-control" <?php echo $can_manage ? "" : "disabled"; ?>>
                                <?php foreach ($units_dropdown as $opt) {
                                    $selected = ($active_unit_id === (int) $opt["id"]) ? "selected" : "";
                                    echo "<option value='" . htmlspecialchars((string) $opt["id"], ENT_QUOTES) . "' $selected>" . htmlspecialchars((string) $opt["text"], ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8") . "</option>";
                                } ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group row mb-3">
                        <label for="document_prefix" class="col-md-3 col-form-label"><?php echo app_lang("gd_document_prefix"); ?></label>
                        <div class="col-md-6">
                            <?php echo form_input(["id" => "document_prefix", "name" => "document_prefix", "value" => $document_prefix, "class" => "form-control", "maxlength" => 20, "readonly" => $can_manage ? null : "readonly"]); ?>
                            <small class="text-muted"><?php echo app_lang("gd_document_prefix_help"); ?></small>
                        </div>
                    </div>

                    <div class="form-group row mb-3">
                        <label for="default_country" class="col-md-3 col-form-label"><?php echo app_lang("gd_default_country"); ?></label>
                        <div class="col-md-6">
                            <?php echo form_input(["id" => "default_country", "name" => "default_country", "value" => $default_country, "class" => "form-control", "maxlength" => 80, "readonly" => $can_manage ? null : "readonly"]); ?>
                            <small class="text-muted"><?php echo app_lang("gd_default_country_help"); ?></small>
                        </div>
                    </div>

                    <?php if ($can_manage) { ?>
                        <div class="form-group row">
                            <div class="col-md-9 offset-md-3">
                                <button type="submit" class="btn btn-primary"><span data-feather="check-circle" class="icon-16"></span> <?php echo app_lang("save"); ?></button>
                            </div>
                        </div>
                    <?php } ?>

                    <?php echo form_close(); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function () {
        $("#gd-general-form").appForm({
            isModal: false
        });
    });
</script>
