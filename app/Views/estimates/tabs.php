<li><a class="<?php echo ($active_tab == 'estimates_list') ? 'active' : ''; ?>" href="<?php echo_uri('estimates'); ?>"><?php echo app_lang("estimates"); ?></a></li>
<li><a class="<?php echo ($active_tab == 'estimate_requests') ? 'active' : ''; ?>" href="<?php echo_uri('estimate_requests'); ?>"><?php echo app_lang('estimate_requests'); ?></a></li>
<?php if ($can_edit_estimates) { ?>
    <li class="hide-on-compact-view"><a class="<?php echo ($active_tab == 'estimate_forms') ? 'active' : ''; ?>" href="<?php echo_uri('estimate_requests/estimate_forms'); ?>"><?php echo app_lang('estimate_request_forms'); ?></a></li>
<?php } ?>