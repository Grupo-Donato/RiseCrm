<div class="card bg-white">
    <div class="card-header d-flex align-items-center">
        <i data-feather="grid" class="icon-16 me-1"></i>
        <span><?php echo app_lang("open_projects"); ?></span>
    </div>

    <div class="card-body pt10 rounded-bottom" id="open-projects-container">
        <?php if ($projects) { ?>
            <?php foreach ($projects as $project) {
                $progress = $project->total_points ? round(($project->completed_points / $project->total_points) * 100) : 0;
                $bar_class = "bg-primary";
                $progress_text_class = "text-primary";
                if ($progress == 100) {
                    $bar_class = "progress-bar-success";
                    $progress_text_class = "text-success";
                } else if ($progress == 0) {
                    $progress_text_class = "text-off";
                }
            ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <?php echo anchor(get_uri("projects/view/" . $project->id), $project->title, array("class" => "dark")); ?>
                        <div class=" <?php echo $progress_text_class; ?>"><?php echo $progress; ?>%</div>
                    </div>

                    <div class="text-off small">
                        <?php
                        $devider = "";
                        if (is_date_exists($project->start_date) && is_date_exists($project->deadline)) {
                            $devider = " | ";
                        }

                        if (is_date_exists($project->start_date)) {
                            echo app_lang("start_date") . ": " . format_to_date($project->start_date, false);
                            echo $devider;
                        }

                        if (is_date_exists($project->deadline)) {
                            echo app_lang("deadline") . ": " . format_to_date($project->deadline, false);
                        }
                        ?>
                    </div>

                    <div class="progress mt5 widget-progress-bar" title="<?php echo $progress; ?>%">
                        <div class="progress-bar <?php echo $bar_class; ?>" role="progressbar"
                            style="width: <?php echo $progress; ?>%;"
                            aria-valuenow="<?php echo $progress; ?>" aria-valuemin="0" aria-valuemax="100">
                        </div>
                    </div>
                </div>
            <?php } ?>
        <?php } else { ?>
            <div class="text-center text-off py-3">
                <?php echo app_lang("no_open_projects"); ?>
            </div>
        <?php } ?>
    </div>
</div>

<script>
    $(document).ready(function() {
        if (!isMobile()) {
            initScrollbar('#open-projects-container', {
                setHeight: 330
            });
        }
    });
</script>