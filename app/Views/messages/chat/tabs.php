<div class="chat-topbar box">
    <h4 class="strong chat-topbar-title"><?php echo app_lang("messages"); ?></h4>

    <?php echo view("messages/chat/chat_header_actions"); ?>
</div>

<div class="rise-chat-body clearfix full-height">
    <div class="tab-content">
        <div role="tabpanel" class="tab-pane fade" id="chat-inbox-tab">
            <?php echo view("messages/chat/chat_list", array("messages" => $messages)); ?>
        </div>
        <div role="tabpanel" class="tab-pane fade" id="chat-users-tab"></div>
        <div role="tabpanel" class="tab-pane fade" id="chat-clients-tab"></div>
    </div>
</div>

<div class="rise-chat-footer footer-buttons-section">
    <div class="chat-tab" data-bs-toggle="ajax-tab" role="tablist">
        <li class="box-content" id="chat-inbox-tab-button">
            <a role="presentation" href="#" data-bs-toggle="tab" data-bs-target="#chat-inbox-tab" class="btn btn-default chat-button">
                <div><i data-feather="message-square" class="icon"></i></div>
                <span class="chat-tab-text"><?php echo app_lang("messages"); ?></span>
            </a>
        </li>

        <?php if ($show_users_list) { ?>
            <li class="box-content" id="chat-users-tab-button">
                <a role="presentation" href="<?php echo_uri("messages/users_list/staff"); ?>" data-bs-toggle="tab" data-bs-target="#chat-users-tab" class="btn btn-default chat-button">
                    <div><i data-feather="users" class="icon"></i></div>
                    <span class="chat-tab-text"><?php echo app_lang("team_members"); ?></span>
                </a>
            </li>
        <?php } ?>

        <?php if ($show_clients_list) { ?>
            <li class="box-content" id="chat-clients-tab-button">
                <a role="presentation" href="<?php echo_uri("messages/users_list/client"); ?>" data-bs-toggle="tab" data-bs-target="#chat-clients-tab" class="btn btn-default chat-button">
                    <?php if ($login_user->user_type === "staff") { ?>
                        <div><i data-feather="briefcase" class="icon"></i></div>
                        <span class="chat-tab-text"><?php echo app_lang("clients"); ?></span>
                    <?php } else { ?>
                        <div><i data-feather="users" class="icon"></i></div>
                        <span class="chat-tab-text"><?php echo app_lang("users"); ?></span>
                    <?php } ?>
                </a>
            </li>
        <?php } ?>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        function updateChatTitle() {
            var activeTabText = $(".chat-tab .active .chat-tab-text").text();
            if (!activeTabText) {
                activeTabText = $(".chat-tab a:first .chat-tab-text").text();
            }
            $(".chat-topbar-title").text(activeTabText);
        }

        updateChatTitle();

        $(".chat-tab a").on("click", function() {
            updateChatTitle();
        });

        //drag and drop
        makeDraggable(".chat-topbar", ".rise-chat-wrapper", async function(pos) {
            var currentDimensions = await IDBHelper.getValue('chat_window_dimensions') || {};

            await IDBHelper.setValue('chat_window_dimensions', {
                ...currentDimensions,
                top: pos.target.offset().top,
                left: pos.target.offset().left
            });
        });
    });
</script>