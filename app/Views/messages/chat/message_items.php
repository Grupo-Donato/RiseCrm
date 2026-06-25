<?php
foreach ($replies as $index => $reply_info) {
    $prev = $replies[$index - 1] ?? null;
    $next = $replies[$index + 1] ?? null;

    echo view("messages/chat/single_message", array("reply_info" => $reply_info, "prev_info" => $prev, "next_info" => $next));
}

if ($is_online) {
?>
    <script class="temp-script">
        $("#js-active-chat-online-icon").removeClass("hide");
        $(".temp-script").remove();
        $("#is_user_online").val("1");
    </script>
<?php

} else {
?>
    <script class="temp-script">
        $("#js-active-chat-online-icon").addClass("hide");
        $(".temp-script").remove();
        $("#is_user_online").val("0");
    </script>
<?php

}
