<div class="card estimate-request-widget">
    <div class="card-header estimate-request-widget-header">
        <i data-feather="circle" class="icon-32 pulse-icon"></i>
        <div class="dash-track">
            <div class="dot" id="dot"></div>
        </div>
        <span id="changing-icon"><i data-feather="hexagon" class="icon-32"></i><span>
    </div>
    <div class="card-body p20">
        <div class="text-center">
            <h5 class="mt-0"><?php echo app_lang("need_something_new"); ?></h5>
            <p><?php echo app_lang("get_a_free_estimate"); ?></p>

            <?php echo modal_anchor(get_uri("estimate_requests/request_an_estimate_modal_form"), app_lang('request_an_estimate'), array("class" => "btn btn-primary mt-2 mb-2", "title" => app_lang('request_an_estimate'))); ?>
        </div>
    </div>
</div>
<script type="text/javascript">
    $(document).ready(function() {
        var icons = ["hexagon", "package", "codesandbox", "box", "layers", "aperture"];
        var current = 0;

        var $changingIcon = $("#changing-icon");
        var $dot = $("#dot");

        $dot.on("animationiteration", function() {
            current = (current + 1) % icons.length;
            $changingIcon.html("<i data-feather='" + icons[current] + "' class='icon-32'></i>");
            feather.replace();
        });
    });
</script>