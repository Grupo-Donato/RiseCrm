<?php
$files = unserialize($reply_info->files);
$total_files = count($files);

$download_caption = "";
if ($total_files) {
    $download_lang = app_lang('download');
    if ($total_files > 1) {
        $download_lang = sprintf(app_lang('download_files'), $total_files);
    }

    $download_caption = anchor(get_uri("messages/download_message_files/" . $reply_info->id), "<i data-feather='paperclip' class='icon-16 me-1'></i>" . $download_lang, array("class" => "", "title" => $download_lang));
}

$prev = $prev_info ?? null;
$next = $next_info ?? null;

$currSender = $reply_info->from_user_id ?? ($reply_info['from_user_id'] ?? null); // Get the sender ID of the current message
$prevSender = $prev->from_user_id ?? ($prev['from_user_id'] ?? null); // Get the sender ID of the previous message
$nextSender = $next->from_user_id ?? ($next['from_user_id'] ?? null); // Get the sender ID of the next message

// Determine if this is the first message in a group
// (True if there's no previous message or previous sender is different)
$isFirst = !$prev || $prevSender !== $currSender;

// Determine if this is the last message in a group
// (True if there's no next message or next sender is different)
$isLast  = !$next || $nextSender !== $currSender;

// - If both first and last => it's a standalone message
// - If only first         => it's the start of a group
// - If only last          => it's the end of a group
// - Else                  => it's in the middle of a group
if ($isFirst && $isLast) {
    $message_position_class = "single-message";
} elseif ($isFirst) {
    $message_position_class = "first-message";
} elseif ($isLast) {
    $message_position_class = "last-message";
} else {
    $message_position_class = "middle-message";
}

$message_class = "m-row-" . $reply_info->id;
if ($reply_info->from_user_id === $login_user->id) {
?>
    <div class="chat-me chat-row <?php echo $message_class . ' ' . $message_position_class; ?>">
        <div class="row">
            <div class="col-md-12">
                <div class="chat-msg js-chat-msg" data-message_id="<?php echo $reply_info->id; ?>">
                    <?php
                    echo custom_nl2br(link_it(process_images_from_content($reply_info->message)));
                    if ($download_caption) {
                        echo view("includes/timeline_preview", array("files" => $files, "is_message_row" => true));
                        echo $download_caption;
                    }
                    ?></div>
            </div>
        </div>
    </div>
<?php } else {
?>

    <div class="chat-other chat-row <?php echo $message_class . ' ' . $message_position_class; ?>">
        <div class="row">
            <div class="col-md-12">
                <div class="avatar-xs avatar mr10">
                    <?php
                    $avatar = get_avatar($reply_info->user_image);
                    if ($reply_info->user_type == "client") {
                        echo get_client_contact_profile_link($reply_info->from_user_id, " <img alt='...' src='" . $avatar . "' /> ", array("class" => "dark strong avatar-link"));
                    } else {
                        echo get_team_member_profile_link($reply_info->from_user_id, " <img alt='...' src='" . $avatar . "' /> ", array("class" => "dark strong avatar-link"));
                    }
                    ?>
                </div>
                <div class="chat-msg js-chat-msg" data-message_id="<?php echo $reply_info->id ?>">
                    <?php
                    echo custom_nl2br(link_it(process_images_from_content($reply_info->message)));
                    if ($download_caption) {
                        echo view("includes/timeline_preview", array("files" => $files, "is_message_row" => true));
                        echo $download_caption;
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

<?php } ?>

<script class="temp-script33">
    //don't show duplicate messages
    $("<?php echo '.' . $message_class; ?>:first").nextAll("<?php echo '.' . $message_class; ?>").remove();
</script>