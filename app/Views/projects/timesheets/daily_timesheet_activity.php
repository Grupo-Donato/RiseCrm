<div>
    <div class="card-header clearfix border-bottom-0">
        <div class="float-start clearfix timesheet-buttons">
            <span id="monthly-activity-date-range-selector" class="float-start full-width-date-range-selector"></span>
            <?php
            if (!$user_id) {
                echo form_input(array(
                    "id" => "timesheet-activity-members-dropdown",
                    "name" => "members-dropdown",
                    "class" => "select2 w200 reload-timesheet-activity ml15",
                    "placeholder" => app_lang('member')
                ));
            }

            if (!$project_id) {
                echo form_input(array(
                    "id" => "timesheet-activity-projects-dropdown",
                    "name" => "projects-dropdown",
                    "class" => "select2 w200 reload-timesheet-activity ml15 timesheet-activity-project-dropdown",
                    "placeholder" => app_lang('project')
                ));
            }
            ?>
        </div>
    </div>
    <div class="card-body">
        <div class="timesheet-activity-container">
            <table class="timesheet-activity-heatmap">
                <thead id="timesheet-activity-header"></thead>
                <tbody id="timesheet-activity-body"></tbody>
            </table>
        </div>
    </div>
</div>

<script type="text/javascript">
    $(document).ready(function() {
        var date = {};

        //initialize data
        $("#timesheet-activity-members-dropdown").select2({
            data: <?php echo $members_dropdown; ?>
        });
        $("#timesheet-activity-projects-dropdown").select2({
            data: <?php echo $projects_dropdown; ?>
        });

        //prepare timesheet statistics Chart
        prepareDailyTimesheetActivity = function() {
            appLoader.show();

            var user_id = $("#timesheet-activity-members-dropdown").val() || "0",
                project_id = $("#timesheet-activity-projects-dropdown").val() || "0";

            function getHeatmapColor(hours) {
                var colors = {
                    "1": "#e6f8f3",
                    "2": "#c2efe3",
                    "3": "#9ee6d4",
                    "4": "#7adcc4",
                    "5": "#56d3b5",
                    "6": "#3ac7aa",
                    "7": "#21be98",
                    "8": "#0abb87"
                };

                if (!hours) return "#ffffff";

                var decimalSeparator = "<?php echo get_setting('decimal_separator'); ?>";
                if (decimalSeparator === ",") {
                    hours = (hours + "").replace(",", ".");
                }

                var numericHours = Number(hours);
                if (!numericHours || numericHours <= 0) return "#ffffff";

                var roundedHours = Math.round(numericHours);
                if (roundedHours > 8) roundedHours = 8;

                return colors[roundedHours] || "#ffffff";
            }

            appAjaxRequest({
                url: "<?php echo_uri("projects/daily_timesheet_activity_data/" . $project_id . "/" . $user_id) ?>",
                data: {
                    start_date: date.start_date,
                    end_date: date.end_date,
                    user_id: user_id,
                    project_id: project_id
                },
                cache: false,
                type: 'POST',
                dataType: "json",
                success: function(response) {
                    appLoader.hide();

                    //prepare header
                    var headerHtml = "<td></td>"; // First column will be blank
                    var weekends = AppHelper.settings.weekends ? AppHelper.settings.weekends.split(',') : [];

                    //add a th for total hours
                    headerHtml += "<th class='total-hours'><?php echo app_lang('total'); ?></th>";

                    for (var i = 1; i <= response.days_of_month; i++) {
                        var date = new Date(response.start_date);
                        date.setDate(date.getDate() + (i - 1)); // Adjusting because day index is zero-based

                        var isWeekend = weekends.includes(date.getDay().toString());
                        var classes = isWeekend ? "weekends-highlight" : "";

                        headerHtml += "<th class='" + classes + "'>" + i + "</th>";
                    }

                    $("#timesheet-activity-header").html(headerHtml);

                    //prepare body
                    var html = "";
                    response.users.forEach(function(user) {
                        html += "<tr><td class='heatmap-label'>" + user.name + "</td>";

                        var weekends = AppHelper.settings.weekends ? AppHelper.settings.weekends.split(',') : [];

                        html += "<td class='total-hours'>" + user.total_hours + "</td>"; //total hours

                        user.timesheets.forEach(function(hours, index) {
                            var color = getHeatmapColor(hours);
                            var style = hours ? "background:" + color + " !important;" : "";

                            var date = new Date(response.start_date);
                            date.setDate(date.getDate() + index);

                            var isWeekend = weekends.includes(date.getDay().toString());

                            var classes = isWeekend ? "weekends-highlight" : "";

                            html += "<td class='" + classes + "' style='" + style + "'>" + (hours ? hours : "") + "</td>";
                        });

                        html += "</tr>";
                    });

                    $("#timesheet-activity-body").html(html);
                }
            });
        };

        $("#monthly-activity-date-range-selector").appDateRange({
            dateRangeType: "monthly",
            onChange: function(dateRange) {
                date = dateRange;
                prepareDailyTimesheetActivity();
            },
            onInit: function(dateRange) {
                date = dateRange;
                prepareDailyTimesheetActivity();
            }
        });

        $(".reload-timesheet-activity").change(function() {
            prepareDailyTimesheetActivity();
        });

    });
</script>