<?php
$buttons = anchor(get_uri('grupo_donato/school/classes'), '<i data-feather="arrow-left" class="icon-16"></i> ' . app_lang('back'), ['class' => 'btn btn-default']);
if (!empty($can_manage)) $buttons .= ' ' . modal_anchor(get_uri('grupo_donato/school/classes/modal'), '<i data-feather="edit" class="icon-16"></i> ' . app_lang('edit'), ['class' => 'btn btn-default', 'title' => app_lang('gd_school_class'), 'data-post-id' => (int) $class->id]);
if (!empty($can_enroll)) $buttons .= ' ' . modal_anchor(get_uri('grupo_donato/school/classes/enrollment-modal'), '<i data-feather="user-plus" class="icon-16"></i> ' . app_lang('gd_school_enroll'), ['class' => 'btn btn-primary', 'title' => app_lang('gd_school_enrollment'), 'data-post-class_id' => (int) $class->id]);
$buttons .= ' ' . anchor(get_uri('grupo_donato/calendar'), '<i data-feather="calendar" class="icon-16"></i> ' . app_lang('gd_menu_agenda'), ['class' => 'btn btn-default']);
$buttons .= ' ' . anchor(get_uri('grupo_donato/school/attendance'), '<i data-feather="check-square" class="icon-16"></i> ' . app_lang('gd_school_attendance'), ['class' => 'btn btn-default']);
?>
<?php echo view("grupo_donato_gestao\\Views\\components\\page_header", ["title" => esc($class->name), "buttons" => $buttons]); ?>
<div class="card"><div class="card-body">
    <div class="row mb-3">
        <div class="col-md-3"><small class="text-muted d-block"><?php echo app_lang('gd_type'); ?></small><?php echo app_lang('gd_school_class_type_' . $class->class_type); ?></div>
        <div class="col-md-3"><small class="text-muted d-block"><?php echo app_lang('gd_school_instructor'); ?></small><?php echo esc($instructor_name ?: '-'); ?></div>
        <div class="col-md-3"><small class="text-muted d-block"><?php echo app_lang('gd_resource'); ?></small><?php echo esc($resource_name ?: '-'); ?></div>
        <div class="col-md-3"><small class="text-muted d-block"><?php echo app_lang('gd_school_schedule'); ?></small><?php echo esc(($class->weekdays ?: '-') . ' ' . substr((string) $class->local_start_time, 0, 5) . '–' . substr((string) $class->local_end_time, 0, 5)); ?></div>
    </div>
    <h4><?php echo app_lang('gd_school_enrollments'); ?></h4>
    <div class="table-responsive"><table class="table">
        <thead><tr><th><?php echo app_lang('gd_school_student'); ?></th><th><?php echo app_lang('gd_status'); ?></th><th><?php echo app_lang('gd_school_start'); ?></th></tr></thead>
        <tbody>
        <?php if (!count($class->enrollments)) { ?>
            <tr><td colspan="3"><?php echo view("grupo_donato_gestao\\Views\\components\\empty_state", ["message" => app_lang('gd_no_records'), "icon" => "users"]); ?></td></tr>
        <?php } foreach ($class->enrollments as $e): ?>
            <tr><td><?php echo esc($e->full_name); ?></td><td><?php echo app_lang('gd_school_status_' . $e->status); ?></td><td><?php echo format_to_date($e->starts_on, false); ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
</div></div>
