<li class="js-invoices-cookie-tab" data-tab="invoices_list"><a class="<?php echo ($active_tab == 'invoices_list') ? 'active' : ''; ?>" href="<?php echo_uri('invoices'); ?>"><?php echo app_lang("invoices"); ?></a></li>
<li class="js-invoices-cookie-tab" data-tab="recurring_invoices"><a class="<?php echo ($active_tab == 'recurring_invoices') ? 'active' : ''; ?>" href="<?php echo_uri('invoices/recurring'); ?>"><?php echo app_lang('recurring_invoices'); ?></a></li>

<script>
    var selectedTab = getCookie("selected_invoices_tab_" + "<?php echo $login_user->id; ?>");

    if (selectedTab && selectedTab !== "<?php echo $active_tab ?>" && selectedTab === "recurring_invoices") {
        window.location.href = "<?php echo_uri('invoices/recurring'); ?>";
    }

    //save the selected tab in browser cookie
    $(document).ready(function() {
        $(".js-invoices-cookie-tab").click(function() {
            var tab = $(this).attr("data-tab");
            if (tab) {
                setCookie("selected_invoices_tab_" + "<?php echo $login_user->id; ?>", tab);
            }
        });
    });
</script>