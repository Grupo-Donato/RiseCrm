<?php
//load chat ui if chat module is enabled
$can_chat = can_access_messages_module();

if (get_setting("module_chat") && $can_chat) {
?>
    <div id="js-rise-chat-wrapper" class="rise-chat-wrapper hide"></div>

    <script type="text/javascript">
        $(document).ready(function() {

            var hideChatIcon = <?php echo get_setting("hide_chat_icon") ? "true" : "false"; ?>;

            var $chatIconWrapper = $('<div id="js-init-chat-icon" class="init-chat-icon"></div>');

            if (!hideChatIcon) {
                //allowed data-type= open/close/unread
                $chatIconWrapper.append(' <span id="js-chat-min-icon" data-type="open" class="chat-min-icon"><i data-feather="message-circle" class="icon"></i></span>');
            }

            var $chatBoxWrapper = '<div id="js-rise-chat-wrapper" class="rise-chat-wrapper hide"></div>';
            if (isMobile()) {
                if (!hideChatIcon) {
                    $('#mobile-chat-menu-button').append($chatIconWrapper).find(".init-chat-icon").removeClass("init-chat-icon");
                }
                $('#mobile-chat-menu-button').append($chatBoxWrapper);
            } else {
                if (!hideChatIcon) {
                    $('body').append($chatIconWrapper);
                }
                $('body').append($chatBoxWrapper);
            }

            chatIconContent = {
                "open": "<i data-feather='message-circle' class='icon'></i>",
                "close": "<i data-feather='chevron-down' class='icon-22'></i>",
                "unread": ""
            };

            //we'll wait for 15 sec after clicking on the unread icon to see more notifications again.
            setChatIcon = function(type, count) {
                //don't show count if the data-prevent-notification-count is 1
                if ($("#js-chat-min-icon").attr("data-prevent-notification-count") === "1" && type === "unread") {
                    return false;
                }

                $("#js-chat-min-icon").attr("data-type", type).html(count ? count : chatIconContent[type]);

                if (type === "open") {
                    $("#js-rise-chat-wrapper").addClass("hide"); //hide chat box
                    $("#js-init-chat-icon").removeClass("has-message");
                } else if (type === "close") {
                    $("#js-rise-chat-wrapper").removeClass("hide"); //show chat box
                    $("#js-init-chat-icon").removeClass("has-message");
                } else if (type === "unread") {
                    $("#js-init-chat-icon").addClass("has-message");
                }
            
            };

            changeChatIconPosition = function(type) {
                if (type === "close") {
                    $("#js-init-chat-icon").addClass("move-chat-icon");
                } else if (type === "open") {
                    $("#js-init-chat-icon").removeClass("move-chat-icon");
                }
            };

            //is there any active chat? open the popup
            //otherwise show the chat icon only
            var activeChatId = getCookie("active_chat_id"),
                isChatBoxOpen = getCookie("chatbox_open"),
                $chatIcon = $("#js-init-chat-icon");

            $chatIcon.click(function() {
                $("#js-rise-chat-wrapper").html("");

                NotificationHelper.updateLastMessageCheckingStatus();

                var $chatIcon = $("#js-chat-min-icon");

                if ($chatIcon.attr("data-type") === "unread") {
                    $chatIcon.attr("data-prevent-notification-count", "1");

                    //after clicking on the unread icon, we'll wait 11 sec to show more notifications again.
                    setTimeout(function() {
                        $chatIcon.attr("data-prevent-notification-count", "0");
                    }, 11000);
                }

                var windowSize = window.matchMedia("(max-width: 767px)");

                if ($chatIcon.attr("data-type") !== "close") {
                    //have to reload
                    setTimeout(function() {
                        loadChatTabs();
                    }, 200);
                    setChatIcon("close"); //show close icon
                    setCookie("chatbox_open", "1");
                    if (windowSize.matches) {
                        changeChatIconPosition("close");
                    }
                } else {
                    //have to close the chat box
                    setChatIcon("open"); //show open icon
                    setCookie("chatbox_open", "");
                    setCookie("active_chat_id", "");
                    if (windowSize.matches) {
                        changeChatIconPosition("open");
                    }
                }

                if (window.activeChatChecker) {
                    window.clearInterval(window.activeChatChecker);
                }

                if (typeof window.placeCartBox === "function") {
                    window.placeCartBox();
                }

                feather.replace();
            });

            //open chat box
            if (isChatBoxOpen) {

                if (activeChatId) {
                    getActiveChat(activeChatId);
                } else {
                    loadChatTabs();
                }
            }

            var windowSize = window.matchMedia("(max-width: 767px)");
            if (windowSize.matches) {
                if (isChatBoxOpen) {
                    $("#js-init-chat-icon").addClass("move-chat-icon");
                }
            }

            $('body #js-rise-chat-wrapper').on('click', '.js-message-row', function() {
                getActiveChat($(this).attr("data-id"));
            });

            $('body #js-rise-chat-wrapper').on('click', '.js-message-row-of-team-members-tab', function() {
                getChatlistOfUser($(this).attr("data-id"), "team_members");
            });

            $('body #js-rise-chat-wrapper').on('click', '.js-message-row-of-clients-tab', function() {
                getChatlistOfUser($(this).attr("data-id"), "clients");
            });

            // Function to restore chat dimensions
            async function restoreChatDimensions() {
                var dimensions = await IDBHelper.getValue('chat_window_dimensions');
                if (dimensions) {
                    var $chatWrapper = $('#js-rise-chat-wrapper');

                    var headerHeight = $('.navbar').outerHeight() || 66;
                    var sidebarWidth = $('.sidebar').outerWidth() || 70;

                    var winWidth = $(window).width();
                    var winHeight = $(window).height() - headerHeight;

                    var chatBoxWidth = dimensions.width || 430;
                    var chatBoxHeight = dimensions.height || "auto";
                    var left = dimensions.left;
                    var top = dimensions.top;

                    // Clamp width/height to viewport
                    if (chatBoxWidth > winWidth) chatBoxWidth = winWidth;
                    if (chatBoxHeight > winHeight) chatBoxHeight = winHeight;

                    // Clamp left/top so the box is visible
                    if (left + chatBoxWidth > winWidth) left = winWidth - chatBoxWidth;
                    if (top + chatBoxHeight > winHeight) top = winHeight - chatBoxHeight;

                    if (left < sidebarWidth) left = sidebarWidth;
                    if (top < headerHeight) top = headerHeight;

                    $chatWrapper.css({
                        width: chatBoxWidth + 'px',
                        height: chatBoxHeight + 'px',
                        top: top + 'px',
                        left: left + 'px'
                    });

                    adjustChatBodyHeight();
                }
            }

            // Restore dimensions on page load
            restoreChatDimensions();

            //make resizable
            makeResizable('#js-rise-chat-wrapper', {
                minWidth: 310,
                maxWidth: 800,
                minHeight: 400,
                maxHeight: $(window).height() - 100,
                handle: ['left', 'right', 'top', 'bottom'],
                onResize: function(wrapper) {
                    adjustChatBodyHeight();

                    IDBHelper.setValue('chat_window_dimensions', {
                        width: wrapper.width(),
                        height: wrapper.height(),
                        top: wrapper.offset().top,
                        left: wrapper.offset().left
                    });
                }
            });

            //close chat box
            $(document).on('click', '#chat-close-icon', function() {
                setChatIcon("open");
                setCookie("chatbox_open", "");
                setCookie("active_chat_id", "");
                feather.replace();
            });

            //reset chat dimensions
            $(document).on('click', '.reset-chat-dimension', function() {
                IDBHelper.setValue('chat_window_dimensions', null);
                $('#js-rise-chat-wrapper').removeAttr('style');
                $('#js-rise-chat-wrapper').removeClass('full-screen');
                $('.chat-full-screen').removeClass('hide');
                $('.chat-exit-full-screen').addClass('hide');

                adjustChatBodyHeight();
            });

            //chat full screen
            $(document).on('click', '.chat-full-screen', function() {
                enterChatFullScreen();
            });

            //chat exit full screen
            $(document).on('click', '.chat-exit-full-screen', function() {
                exitChatFullScreen();
            });

            $(document).on('click', '.chat-back', function() {
                handleFullScreenOrExitFullScreen();
            });
        });

        function enterChatFullScreen() {
            var $wrapper = $("#js-rise-chat-wrapper");
            $wrapper.addClass("full-screen");
            $(".chat-full-screen").addClass("hide");
            $(".chat-exit-full-screen").removeClass("hide");
            adjustChatBodyHeight();
        }

        function exitChatFullScreen() {
            var $wrapper = $("#js-rise-chat-wrapper");
            $wrapper.removeClass("full-screen");
            $(".chat-exit-full-screen").addClass("hide");
            $(".chat-full-screen").removeClass("hide");
            adjustChatBodyHeight();
        }

        function handleFullScreenOrExitFullScreen() {
            var $wrapper = $("#js-rise-chat-wrapper");
            if ($wrapper.hasClass("full-screen")) {
                setTimeout(function() {
                    $(".chat-exit-full-screen").removeClass("hide");
                    $(".chat-full-screen").addClass("hide");
                }, 150);
            }
        }

        // Adjust inner chat body dynamically
        function adjustChatBodyHeight() {
            var chatBoxHeight = $('#js-rise-chat-wrapper').height();
            var headerHeight = $('.rise-chat-header').outerHeight() || 60;
            var footerHeight = $('.rise-chat-footer').outerHeight() || 77;

            $('.rise-chat-body').height(chatBoxHeight - (headerHeight + footerHeight));
        }

        function getChatlistOfUser(user_id, tab_type) {

            setChatIcon("close"); //show close icon

            appLoader.show({
                container: "#js-rise-chat-wrapper",
                css: "bottom: 40%; right: 35%;"
            });

            appAjaxRequest({
                url: "<?php echo get_uri("messages/get_chatlist_of_user"); ?>",
                type: "POST",
                data: {
                    user_id: user_id,
                    tab_type: tab_type
                },
                success: function(response) {
                    $("#js-rise-chat-wrapper").html(response);
                    appLoader.hide();
                }
            });

            handleFullScreenOrExitFullScreen();
        }

        function loadChatTabs(trigger_from_user_chat) {

            setChatIcon("close"); //show close icon

            setCookie("active_chat_id", "");
            appLoader.show({
                container: "#js-rise-chat-wrapper",
                css: "bottom: 40%; right: 35%;"
            });

            appAjaxRequest({
                url: "<?php echo get_uri("messages/chat_list"); ?>",
                data: {
                    type: "inbox"
                },
                success: function(response) {
                    $("#js-rise-chat-wrapper").html(response);

                    if (!trigger_from_user_chat) {
                        $("#chat-inbox-tab-button a").trigger("click");
                    } else if (trigger_from_user_chat === "team_members") {
                        $("#chat-users-tab-button").find("a").trigger("click");
                    } else if (trigger_from_user_chat === "clients") {
                        $("#chat-clients-tab-button").find("a").trigger("click");
                    }
                    appLoader.hide();
                }
            });

            setTimeout(function() {
                adjustChatBodyHeight();

                //append resizable handles
                resizableHandles("#js-rise-chat-wrapper");
            }, 300);
        }

        function getActiveChat(message_id) {
            setChatIcon("close"); //show close icon

            appLoader.show({
                container: "#js-rise-chat-wrapper",
                css: "bottom: 40%; right: 35%;"
            });

            appAjaxRequest({
                url: "<?php echo get_uri('messages/get_active_chat'); ?>",
                type: "POST",
                data: {
                    message_id: message_id
                },
                success: function(response) {
                    $("#js-rise-chat-wrapper").html(response);
                    appLoader.hide();
                    setCookie("active_chat_id", message_id);
                    $("#js-chat-message-textarea").focus();

                    //append resizable handles
                    setTimeout(function() {
                        resizableHandles("#js-rise-chat-wrapper");
                    }, 200);
                }
            });

            handleFullScreenOrExitFullScreen();
        }

        window.prepareUnreadMessageChatBox = function(totalMessages) {
            setChatIcon("unread", totalMessages); //show close icon
        };

        window.triggerActiveChat = function(message_id) {
            getActiveChat(message_id);
        }
        setTimeout(function() {
            feather.replace();
        }, 200);
    </script>

<?php } ?>