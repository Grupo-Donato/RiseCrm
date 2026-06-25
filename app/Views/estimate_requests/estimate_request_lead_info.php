<div class="card">
    <div class="card-header fw-bold">
        <i data-feather="layers" class="icon-16"></i> &nbsp;<?php echo app_lang("lead_info"); ?>
    </div>

    <div class="card-body">
        <ul class="list-group info-list">
            <li class="list-group-item"><strong><?php echo app_lang("company_name") . ": "; ?></strong><span><?php echo $lead_info->company_name; ?></span></li>

            <?php if ($lead_info->address) { ?>
                <li class="list-group-item "><strong><?php echo app_lang("address") . ": "; ?></strong><span><?php echo nl2br($lead_info->address ? $lead_info->address : ""); ?></span></li>
            <?php } ?>

            <?php if ($lead_info->city) { ?>
                <li class="list-group-item"><strong><?php echo app_lang("city") . ": "; ?></strong><span><?php echo $lead_info->city; ?></span></li>
            <?php } ?>

            <?php if ($lead_info->state) { ?>
                <li class="list-group-item"><strong><?php echo app_lang("state") . ": "; ?></strong><span><?php echo $lead_info->state; ?></span></li>
            <?php } ?>

            <?php if ($lead_info->zip) { ?>
                <li class="list-group-item"><strong><?php echo app_lang("zip") . ": "; ?></strong><span><?php echo $lead_info->zip; ?></span></li>
            <?php } ?>

            <?php if ($lead_info->country) { ?>
                <li class="list-group-item"><strong><?php echo app_lang("country") . ": "; ?></strong><span><?php echo $lead_info->country; ?></span></li>
            <?php } ?>
        </ul>
    </div>
</div>