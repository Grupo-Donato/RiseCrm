<div class="rise-chat-header">
    <div class="chat-back chat-topbar-btn" id="js-back-to-chat-tabs">
        <i data-feather="chevron-left" class="icon-22"></i>
    </div>
    <div class="chat-title">
        <div><?php
                $user_id = "";
                if ($message_info->from_user_id == $login_user->id) {
                    $user_id = $message_info->to_user_id;
                } else {
                    $user_id = $message_info->from_user_id;
                }

                if ($message_info->another_user_id === $login_user->id) {
                    $user_name = $message_info->user_name;
                    $user_image = get_avatar($message_info->user_image);
                } else {
                    $user_name = $message_info->another_user_name;
                    $user_image = get_avatar($message_info->another_user_image);
                }

                $online = "";
                if (is_online_user($message_info->another_user_last_online)) {
                    $online = "<i id='js-active-chat-online-icon' class='online hide' data-user_id='$user_id'></i>";
                }

                echo "<span class='avatar avatar-xs mr10'><img src='$user_image' />$online</span>";
                echo "<span>$user_name</span>";
                ?>
        </div>
    </div>

    <?php echo view("messages/chat/chat_header_actions"); ?>
</div>

<div class="rise-chat-body clearfix">
    <div id="js-chat-messages-container" class="clearfix"></div>
    <div id="js-chat-reply-indicator"></div>
</div>

<div class="rise-chat-footer">
    <div id="chat-reply-form-dropzone" class="post-dropzone">
        <?php echo form_open(get_uri("messages/reply/1"), array("id" => "chat-message-reply-form", "class" => "general-form", "role" => "form")); ?>


        <?php echo view("includes/dropzone_preview"); ?>


        <input type="hidden" id="is_user_online" name="is_user_online" value="<?php echo is_online_user($message_info->another_user_last_online) ? 1 : 0; ?>">
        <input type="hidden" name="message_id" value="<?php echo $message_id; ?>">
        <input type="hidden" name="last_message_id" value="">

        <div class="chat-message-textarea">
            <?php
            echo form_textarea(array(
                "id" => "js-chat-message-textarea",
                "name" => "reply_message",
                "data-rule-required" => true,
                "autofocus" => true,
                "data-msg-required" => "",
                "placeholder" => app_lang('write_a_message')
            ));
            ?>
        </div>
        <div class="chat-button-section clearfix">
            <div class="chat-file-upload-icon float-start">
                <?php
                echo view("includes/upload_button", array("upload_button_text" => ""));
                ?>
            </div>
            <span class="btn btn-default float-end round message-send-button"><i data-feather="send" class="icon"></i></span>
        </div>

        <?php echo form_close(); ?>
    </div>
</div>


<script type="text/javascript">
    $(document).ready(function() {

        window.activeChatMessageId = "<?php echo $message_id; ?>";

        var chatBoxHeight = $(".rise-chat-wrapper").height();
        $(".rise-chat-body").height(chatBoxHeight - 152);

        var textarea = document.querySelector('.rise-chat-footer textarea');
        textarea.addEventListener('keydown', autosizeRISEChatBox);

        function autosizeRISEChatBox() {
            var el = this;
            setTimeout(function() {
                if (el.scrollHeight < 110) {
                    var chatWrapperHeight = $(".rise-chat-wrapper").height();
                    $(".rise-chat-body").height(chatWrapperHeight - el.scrollHeight - 103);
                    el.style.cssText = 'height:' + el.scrollHeight + 'px';
                }
            });
        }

        loadMessages(1);

        //drag and drop
        makeDraggable(".rise-chat-header", ".rise-chat-wrapper", async function(pos) {
            var currentDimensions = await IDBHelper.getValue('chat_window_dimensions') || {};

            await IDBHelper.setValue('chat_window_dimensions', {
                ...currentDimensions,
                top: pos.target.offset().top,
                left: pos.target.offset().left
            });
        });

        //enter to send
        $("#js-chat-message-textarea").keypress(function(e) {
            if (e.keyCode === 13 && !e.shiftKey) {
                $("#chat-message-reply-form").submit();
                $(this).attr("style", "")
                return false;
            }
        });

        $("#chat-message-reply-form").appForm({
            isModal: false,
            showLoader: false,
            beforeAjaxSubmit: function(data) {

                //send the last message id
                $.each(data, function(index, obj) {
                    if (obj.name === "last_message_id") {
                        data[index]["value"] = $(".chat-msg").last().attr("data-message_id");
                    }
                });

                //clear message input box
                $("#js-chat-message-textarea").val("");
                $("#chat-message-reply-form").append('<div id="fast-loader" class="fast-line"></div>');
            },
            onSuccess: function(response) {
                if (window.formDropzone) {
                    window.formDropzone['chat-reply-form-dropzone'].removeAllFiles();
                }
                if (response.success) {
                    renderMessages(response.data);
                    $("#fast-loader").remove();
                }

            }
        });


        //set focus
        setTimeout(function() {
            $("#js-chat-message-textarea").focus();
        }, 200);

        $("#js-back-to-chat-tabs").click(function() {
            loadChatTabs(); // this method should be loaded when chat box loaded

            //reset the previous interval timer
            if (window.activeChatChecker) {
                window.clearInterval(window.activeChatChecker);
            }
        });
        //bind scroll with chat messages and load more messages when scrolling on top
        var fatchNewData = true,
            topMessageId = 0;
        $("#js-chat-messages-container").scroll(function() {
            if ($(this).scrollTop() < 50 && fatchNewData) {
                fatchNewData = false;
                loadMoreMessages(function() {
                    fatchNewData = true; //reset the status so that it can call again
                });
            }
        });

        if ("<?php echo get_setting('enable_chat_via_pusher') ?>" && "<?php echo get_setting('enable_push_notification') ?>") {
            var pusherKey = "<?php echo get_setting("pusher_key"); ?>";
            var pusherCluster = "<?php echo get_setting("pusher_cluster"); ?>";

            var pusher = new Pusher(pusherKey, {
                cluster: pusherCluster,
                encrypted: true
            });

            var channel = pusher.subscribe("user_" + "<?php echo $login_user->id; ?>" + "_message_id_" + "<?php echo $message_id ?>" + "_channel");

            channel.bind('rise-chat-typing-event', function(data) {
                $("#js-chat-reply-indicator").html(data);
                chatScrollToBottom();

                setTimeout(function() {
                    $("#js-chat-reply-indicator").html(" ");
                }, 8000);
            });
        }

        $(".message-send-button").click(function() {
            $(this).trigger("submit");
        });

    });

    function loadMessages(firstLoad) {
        checkNewMessagesAutomatically();
        var message_id = "<?php echo $message_id; ?>";
        appAjaxRequest({
            url: "<?php echo get_uri('messages/view_chat'); ?>",
            type: "POST",
            data: {
                message_id: message_id,
                last_message_id: $(".js-chat-msg").last().attr("data-message_id"),
                is_first_load: firstLoad,
                another_user_id: $("#js-active-chat-online-icon").attr("data-user_id")
            },
            success: function(response) {
                if (response) {
                    renderMessages(response, false);
                }

            }
        });
    }

    function loadMoreMessages(callback) {
        if ($("#js-chat-old-messages").attr("no-messages") === "1")
            return false; //there is no messages to show.

        var message_id = "<?php echo $message_id; ?>";

        $("#js-chat-old-messages").prepend("<div id='loading-more-chat-messages-" + message_id + "' class='inline-loader' >....<br></br></div>");

        appAjaxRequest({
            url: "<?php echo get_uri('messages/view_chat'); ?>",
            type: "POST",
            data: {
                message_id: "<?php echo $message_id; ?>",
                top_message_id: $(".js-chat-msg").first().attr("data-message_id"),
                another_user_id: $("#js-active-chat-online-icon").attr("data-user_id")
            },
            success: function(response) {
                if (response) {
                    $("#js-chat-old-messages").prepend(response);
                    if (callback) {
                        callback(); //has more data?
                    }
                }

                //if we got empty message, then we'll add a flag to stop finding new messages for next calls.
                if (!$(response).find("#temp-script").remove().text()) {
                    $("#js-chat-old-messages").attr("no-messages", "1");
                }

                $('#loading-more-chat-messages-' + message_id).remove();

            }
        });
    }


    function renderMessages(html, isMe = true) {
        appendMessage(html, isMe);
        chatScrollToBottom();
    }

    // Track the last appended message ID globally
    var lastAppendedMessageId = null;

    function getMessageRowId($el) {
        if (!$el || !$el.length) return null;
        var cls = $el.attr("class") || "";
        var match = cls.match(/m-row-(\d+)/);
        return match ? parseInt(match[1]) : null;
    }

    function appendMessage(html, isMe = true) {
        if (!html || $.trim(html) === "") return;

        var container = $("#js-chat-messages-container");
        var lastMessage = container.find("div.chat-row").last();

        // Determine the class of the last message
        var lastClass = lastMessage.hasClass("chat-me") ? "chat-me" : (lastMessage.hasClass("chat-other") ? "chat-other" : "");

        // Determine current message class
        var currentClass = isMe ? "chat-me" : "chat-other";

        // Extract the new message's ID from the HTML
        var $temp = $("<div>").html(html);
        var newMessageDiv = $temp.find("div.chat-row").last();
        var newMessageId = getMessageRowId(newMessageDiv);

        // If message ID is same as previous, skip appending
        if (lastAppendedMessageId !== null && newMessageId <= lastAppendedMessageId) {
            return; // No new message
        }

        // Update the last known message ID
        lastAppendedMessageId = newMessageId;

        if (lastClass === currentClass) {
            var $last = $("." + currentClass).last();

            if ($last.hasClass("single-message")) {
                $last.removeClass("single-message").addClass("first-message");
            } else if ($last.hasClass("last-message")) {
                $last.removeClass("last-message").addClass("middle-message");
            }

            // Append new message with "last-message"
            container.append(html.replace("single-message", "last-message"));
        } else {
            // Just append normally
            container.append(html);
        }
    }

    //reset existing timmer and check new message after a certain time
    function checkNewMessagesAutomatically() {

        //reset the previous interval timer
        if (window.activeChatChecker) {
            window.clearInterval(window.activeChatChecker);
        }

        if ("<?php echo (get_setting('enable_chat_via_pusher') && get_setting("enable_push_notification")) != 1 ?>") {
            window.activeChatChecker = window.setInterval(function() {
                loadMessages();
            }, 5000); //check message in every 5 seconds
        }
    }

    //send typing status to pusher
    if ("<?php echo get_setting('enable_chat_via_pusher') ?>" && "<?php echo get_setting('enable_push_notification') ?>") {
        addKeyup();

        function addKeyup() {
            $("#chat-message-reply-form").one('keyup', function(e) {
                appAjaxRequest({
                    url: '<?php echo get_uri("messages/send_typing_indicator_to_pusher"); ?>',
                    type: "POST",
                    data: {
                        message_id: "<?php echo $message_id ?>"
                    }
                });

                setTimeout(addKeyup, 10000);
            });
        }
    }
</script>