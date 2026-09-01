<?php
$student_photo_default_url = get_avatar();
$attendance_students = [];

foreach ($alunos as $aluno) {
    $status = $historico[$aluno->id] ?? "sem_registro";
    $status = in_array($status, ["presente", "falta"], true) ? $status : "sem_registro";
    $photo_url = $student_photo_default_url;

    if (!empty($aluno->photo_path)) {
        $photo_url = get_uri("grupo_donato/operacional/foto_aluno/" . (int) $aluno->id)
            . "?v=" . rawurlencode(pathinfo((string) $aluno->photo_path, PATHINFO_FILENAME));
    }

    $attendance_students[] = [
        "id" => (int) $aluno->id,
        "name" => (string) $aluno->nome_aluno,
        "photo" => $photo_url,
        "status" => $status,
    ];
}
?>

<?php if (empty($attendance_students)): ?>
    <div class="alert alert-warning mb0">Nenhum aluno ativo encontrado para esta turma.</div>
<?php else: ?>
    <style>
        .gd-attendance-shell {
            --gd-attendance-green: #16855b;
            --gd-attendance-red: #d84b5b;
            --gd-attendance-ink: #172033;
            --gd-attendance-muted: #748094;
            margin: 0 auto;
            max-width: 640px;
        }

        .gd-attendance-intro {
            align-items: flex-start;
            display: flex;
            gap: 16px;
            justify-content: space-between;
            margin-bottom: 18px;
        }

        .gd-attendance-kicker {
            color: var(--gd-attendance-green);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .08em;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .gd-attendance-intro h3 {
            color: var(--gd-attendance-ink);
            font-size: 23px;
            font-weight: 800;
            line-height: 1.15;
            margin: 0;
        }

        .gd-attendance-date {
            color: var(--gd-attendance-muted);
            font-size: 13px;
            margin: 5px 0 0;
        }

        .gd-attendance-counter {
            background: rgba(22, 133, 91, .1);
            border-radius: 14px;
            color: var(--gd-attendance-green);
            flex: 0 0 auto;
            font-size: 12px;
            font-weight: 800;
            padding: 9px 12px;
            text-align: center;
        }

        .gd-attendance-counter strong {
            display: block;
            font-size: 20px;
            line-height: 1;
        }

        .gd-attendance-progress {
            margin-bottom: 18px;
        }

        .gd-attendance-progress-track {
            background: rgba(116, 128, 148, .16);
            border-radius: 99px;
            height: 7px;
            overflow: hidden;
        }

        .gd-attendance-progress-bar {
            background: linear-gradient(90deg, #20a673, #16855b);
            border-radius: inherit;
            height: 100%;
            transition: width .25s ease;
            width: 0;
        }

        .gd-attendance-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
            margin-top: 10px;
        }

        .gd-attendance-stat {
            border: 1px solid rgba(116, 128, 148, .18);
            border-radius: 999px;
            color: var(--gd-attendance-muted);
            font-size: 12px;
            padding: 5px 9px;
        }

        .gd-attendance-stat strong {
            color: var(--gd-attendance-ink);
            margin-right: 3px;
        }

        .gd-attendance-stat.is-present strong {
            color: var(--gd-attendance-green);
        }

        .gd-attendance-stat.is-absent strong {
            color: var(--gd-attendance-red);
        }

        .gd-attendance-board {
            margin: 0 auto;
            max-width: 430px;
        }

        .gd-attendance-stack {
            height: min(62vh, 525px);
            min-height: 360px;
            position: relative;
        }

        .gd-attendance-card {
            background: #e9edf2;
            border-radius: 24px;
            box-shadow: 0 18px 42px rgba(23, 32, 51, .16);
            inset: 0;
            overflow: hidden;
            position: absolute;
            transform-origin: 50% 100%;
            transition: transform .24s ease, opacity .24s ease;
            user-select: none;
        }

        .gd-attendance-card.is-back-one {
            opacity: .72;
            transform: translateY(11px) scale(.96);
            z-index: 1;
        }

        .gd-attendance-card.is-back-two {
            opacity: .4;
            transform: translateY(21px) scale(.92);
            z-index: 0;
        }

        .gd-attendance-card.is-current {
            cursor: grab;
            touch-action: pan-y;
            z-index: 3;
        }

        .gd-attendance-card.is-current.is-dragging {
            transition: none;
        }

        .gd-attendance-card.is-current:active {
            cursor: grabbing;
        }

        .gd-attendance-card.is-current.swipe-left {
            opacity: 0;
            transform: translate(-125%, -10px) rotate(-17deg);
        }

        .gd-attendance-card.is-current.swipe-right {
            opacity: 0;
            transform: translate(125%, -10px) rotate(17deg);
        }

        .gd-attendance-card-image {
            height: 100%;
            object-fit: cover;
            pointer-events: none;
            width: 100%;
        }

        .gd-attendance-card::after {
            background: linear-gradient(180deg, transparent 43%, rgba(11, 17, 29, .86) 100%);
            content: "";
            inset: 0;
            pointer-events: none;
            position: absolute;
        }

        .gd-attendance-card-content {
            bottom: 0;
            color: #fff;
            left: 0;
            padding: 26px 22px 20px;
            position: absolute;
            right: 0;
            z-index: 1;
        }

        .gd-attendance-card-label {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .08em;
            opacity: .82;
            text-transform: uppercase;
        }

        .gd-attendance-card-name {
            font-size: clamp(23px, 5vw, 31px);
            font-weight: 800;
            line-height: 1.08;
            margin: 4px 0 0;
            overflow-wrap: anywhere;
        }

        .gd-attendance-empty {
            align-items: center;
            background: rgba(22, 133, 91, .06);
            border: 1px dashed rgba(22, 133, 91, .35);
            border-radius: 20px;
            color: var(--gd-attendance-muted);
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 230px;
            padding: 24px;
            text-align: center;
        }

        .gd-attendance-empty strong {
            color: var(--gd-attendance-ink);
            display: block;
            font-size: 18px;
            margin-bottom: 6px;
        }

        .gd-attendance-actions {
            align-items: center;
            display: flex;
            gap: 12px;
            justify-content: center;
            margin: 18px auto 0;
            max-width: 430px;
        }

        .gd-attendance-action {
            align-items: center;
            background: #fff;
            border: 2px solid;
            border-radius: 16px;
            display: inline-flex;
            flex: 1 1 0;
            font-size: 15px;
            font-weight: 800;
            justify-content: center;
            min-height: 58px;
            padding: 10px 12px;
            transition: transform .15s ease, box-shadow .15s ease;
        }

        .gd-attendance-action:hover,
        .gd-attendance-action:focus {
            box-shadow: 0 7px 18px rgba(23, 32, 51, .1);
            transform: translateY(-2px);
        }

        .gd-attendance-action:active {
            transform: scale(.97);
        }

        .gd-attendance-action .icon-18 {
            margin-right: 7px;
        }

        .gd-attendance-action.is-absent {
            border-color: rgba(216, 75, 91, .6);
            color: var(--gd-attendance-red);
        }

        .gd-attendance-action.is-present {
            border-color: rgba(22, 133, 91, .6);
            color: var(--gd-attendance-green);
        }

        .gd-attendance-swipe-hint {
            color: var(--gd-attendance-muted);
            font-size: 11px;
            line-height: 1.25;
            max-width: 70px;
            text-align: center;
        }

        .gd-attendance-swipe-hint strong {
            color: var(--gd-attendance-ink);
            display: block;
            font-size: 12px;
        }

        .gd-attendance-bottom {
            align-items: center;
            display: flex;
            gap: 10px;
            justify-content: space-between;
            margin-top: 18px;
        }

        .gd-attendance-undo {
            color: var(--gd-attendance-muted);
            font-size: 13px;
            padding-left: 0;
            padding-right: 0;
        }

        .gd-attendance-save {
            border-radius: 12px;
            font-weight: 700;
            min-height: 44px;
            min-width: 170px;
        }

        .gd-attendance-save:disabled {
            cursor: not-allowed;
            opacity: .55;
        }

        .gd-attendance-review {
            border-top: 1px solid rgba(116, 128, 148, .16);
            margin-top: 20px;
            padding-top: 17px;
        }

        .gd-attendance-review-heading {
            color: var(--gd-attendance-ink);
            font-size: 14px;
            font-weight: 800;
            margin-bottom: 10px;
        }

        .gd-attendance-review-list {
            display: flex;
            flex-wrap: wrap;
            gap: 7px;
        }

        .gd-attendance-review-item {
            align-items: center;
            background: rgba(116, 128, 148, .07);
            border: 1px solid rgba(116, 128, 148, .15);
            border-radius: 999px;
            color: var(--gd-attendance-ink);
            display: inline-flex;
            font-size: 12px;
            gap: 6px;
            padding: 6px 9px;
        }

        .gd-attendance-review-item::before {
            border-radius: 50%;
            content: "";
            height: 8px;
            width: 8px;
        }

        .gd-attendance-review-item.is-present::before {
            background: var(--gd-attendance-green);
        }

        .gd-attendance-review-item.is-absent::before {
            background: var(--gd-attendance-red);
        }

        .gd-attendance-review-item.is-pending {
            color: var(--gd-attendance-muted);
        }

        .gd-attendance-review-item.is-pending::before {
            background: #c5ccd7;
        }

        .gd-attendance-review-item[data-edit-student] {
            cursor: pointer;
        }

        @media (max-width: 575.98px) {
            .gd-attendance-intro h3 {
                font-size: 20px;
            }

            .gd-attendance-counter {
                border-radius: 12px;
                padding: 8px 9px;
            }

            .gd-attendance-stack {
                height: min(61vh, 490px);
                min-height: 340px;
            }

            .gd-attendance-card {
                border-radius: 20px;
            }

            .gd-attendance-card-content {
                padding: 24px 18px 17px;
            }

            .gd-attendance-actions {
                gap: 7px;
            }

            .gd-attendance-action {
                min-height: 54px;
                padding-left: 8px;
                padding-right: 8px;
            }

            .gd-attendance-swipe-hint {
                display: none;
            }

            .gd-attendance-bottom {
                align-items: stretch;
                flex-direction: column-reverse;
            }

            .gd-attendance-save {
                width: 100%;
            }

            .gd-attendance-undo {
                align-self: center;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .gd-attendance-card,
            .gd-attendance-progress-bar,
            .gd-attendance-action {
                transition: none;
            }
        }
    </style>

    <?php echo form_open(get_uri("grupo_donato/operacional/salvar_presenca"), ["id" => "bombeiros-presenca-form", "class" => "general-form", "role" => "form"]); ?>
        <input type="hidden" name="data_aula" value="<?php echo esc($data_aula, "attr"); ?>" />
        <input type="hidden" name="turma" value="<?php echo esc($turma, "attr"); ?>" />

        <?php foreach ($attendance_students as $student): ?>
            <input
                type="hidden"
                name="presencas[<?php echo (int) $student["id"]; ?>]"
                value="<?php echo esc($student["status"], "attr"); ?>"
                data-attendance-student-id="<?php echo (int) $student["id"]; ?>"
            />
        <?php endforeach; ?>

        <div class="gd-attendance-shell">
            <div class="gd-attendance-intro">
                <div>
                    <div class="gd-attendance-kicker">Chamada da aula</div>
                    <h3><?php echo esc($turma); ?></h3>
                    <p class="gd-attendance-date"><?php echo esc($data_aula); ?> &middot; toque ou deslize para marcar</p>
                </div>
                <div class="gd-attendance-counter" aria-live="polite">
                    <strong id="gd-attendance-current">0</strong>
                    <span>respondidos</span>
                </div>
            </div>

            <div class="gd-attendance-progress" aria-label="Progresso da chamada">
                <div class="gd-attendance-progress-track">
                    <div id="gd-attendance-progress-bar" class="gd-attendance-progress-bar"></div>
                </div>
                <div class="gd-attendance-stats">
                    <span class="gd-attendance-stat is-present"><strong id="gd-attendance-present">0</strong> presentes</span>
                    <span class="gd-attendance-stat is-absent"><strong id="gd-attendance-absent">0</strong> faltas</span>
                    <span class="gd-attendance-stat"><strong id="gd-attendance-pending">0</strong> restantes</span>
                </div>
            </div>

            <div class="gd-attendance-board">
                <div id="gd-attendance-stack" class="gd-attendance-stack" aria-live="polite"></div>
                <div id="gd-attendance-empty" class="gd-attendance-empty hide">
                    <strong>Chamada completa</strong>
                    <span>Confira o resumo abaixo e salve os registros.</span>
                </div>

                <div id="gd-attendance-actions" class="gd-attendance-actions">
                    <button type="button" class="gd-attendance-action is-absent" data-attendance-action="falta" aria-label="Marcar como falta">
                        <i data-feather="x" class="icon-18"></i> Falta
                    </button>
                    <div class="gd-attendance-swipe-hint"><strong>Deslize</strong>esquerda ou direita</div>
                    <button type="button" class="gd-attendance-action is-present" data-attendance-action="presente" aria-label="Marcar como presente">
                        <i data-feather="check" class="icon-18"></i> Presente
                    </button>
                </div>

                <div class="gd-attendance-bottom">
                    <button type="button" id="gd-attendance-undo" class="btn btn-link gd-attendance-undo" disabled>
                        <i data-feather="rotate-ccw" class="icon-14"></i> Desfazer última
                    </button>
                    <button type="submit" id="gd-attendance-save" class="btn btn-primary gd-attendance-save" disabled>
                        <i data-feather="check-circle" class="icon-16"></i> Salvar chamada
                    </button>
                </div>

                <div class="gd-attendance-review">
                    <div class="gd-attendance-review-heading">Conferência rápida <span class="text-off">&middot; toque em um nome para revisar</span></div>
                    <div id="gd-attendance-review-list" class="gd-attendance-review-list"></div>
                </div>
            </div>
        </div>
    <?php echo form_close(); ?>

    <script type="text/javascript">
        $(document).ready(function () {
            "use strict";

            var students = <?php echo json_encode($attendance_students, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
            var defaultPhoto = <?php echo json_encode($student_photo_default_url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
            var actionHistory = [];
            var isAnimating = false;
            var pointerStartX = null;
            var pointerDeltaX = 0;
            var cardElement = null;
            var focusStudentId = null;

            var $form = $("#bombeiros-presenca-form");
            var $stack = $("#gd-attendance-stack");
            var $empty = $("#gd-attendance-empty");
            var $actions = $("#gd-attendance-actions");
            var $save = $("#gd-attendance-save");
            var $undo = $("#gd-attendance-undo");

            function escapeHtml(value) {
                return String(value).replace(/[&<>"']/g, function (character) {
                    return {
                        "&": "&amp;",
                        "<": "&lt;",
                        ">": "&gt;",
                        "\"": "&quot;",
                        "'": "&#039;"
                    }[character];
                });
            }

            function getPendingStudents() {
                var pending = students.filter(function (student) {
                    return student.status !== "presente" && student.status !== "falta";
                });

                if (focusStudentId !== null) {
                    var focusedIndex = pending.findIndex(function (student) {
                        return String(student.id) === String(focusStudentId);
                    });
                    if (focusedIndex > 0) {
                        pending.unshift(pending.splice(focusedIndex, 1)[0]);
                    }
                }

                return pending;
            }

            function getStudent(id) {
                return students.find(function (student) {
                    return String(student.id) === String(id);
                });
            }

            function syncStudentInput(student) {
                $("[data-attendance-student-id='" + student.id + "']").val(student.status);
            }

            function updateSummary() {
                var present = students.filter(function (student) { return student.status === "presente"; }).length;
                var absent = students.filter(function (student) { return student.status === "falta"; }).length;
                var pending = students.length - present - absent;
                var answered = present + absent;
                var percentage = students.length ? (answered / students.length) * 100 : 0;

                $("#gd-attendance-current").text(answered);
                $("#gd-attendance-present").text(present);
                $("#gd-attendance-absent").text(absent);
                $("#gd-attendance-pending").text(pending);
                $("#gd-attendance-progress-bar").css("width", percentage + "%");
                $save.prop("disabled", pending > 0);
                $undo.prop("disabled", actionHistory.length === 0);
            }

            function renderReview() {
                var html = students.map(function (student) {
                    var statusClass = student.status === "presente"
                        ? "is-present"
                        : (student.status === "falta" ? "is-absent" : "is-pending");
                    var statusLabel = student.status === "presente"
                        ? "Presente"
                        : (student.status === "falta" ? "Falta" : "Pendente");

                    return "<button type='button' class='gd-attendance-review-item " + statusClass + "' data-edit-student='" + student.id + "' title='" + statusLabel + ": revisar " + escapeHtml(student.name) + "'>"
                        + escapeHtml(student.name) + "</button>";
                }).join("");

                $("#gd-attendance-review-list").html(html);
            }

            function bindCardImageFallback() {
                $stack.find("img[data-fallback]").on("error", function () {
                    this.onerror = null;
                    this.src = defaultPhoto;
                });
            }

            function renderCards() {
                var pending = getPendingStudents();

                if (!pending.length) {
                    $stack.addClass("hide");
                    $empty.removeClass("hide");
                    $actions.addClass("hide");
                    renderReview();
                    updateSummary();
                    if (typeof feather !== "undefined") {
                        feather.replace();
                    }
                    return;
                }

                $stack.removeClass("hide");
                $empty.addClass("hide");
                $actions.removeClass("hide");

                var cards = pending.slice(0, 3).map(function (student, index) {
                    var stackClass = index === 0 ? "is-current" : (index === 1 ? "is-back-one" : "is-back-two");
                    return "<article class='gd-attendance-card " + stackClass + "' data-card-student='" + student.id + "' aria-label='" + escapeHtml(student.name) + "'>"
                        + "<img class='gd-attendance-card-image' src='" + escapeHtml(student.photo) + "' data-fallback alt='Foto de " + escapeHtml(student.name) + "' draggable='false'>"
                        + "<div class='gd-attendance-card-content'><div class='gd-attendance-card-label'>Aluno</div><div class='gd-attendance-card-name'>" + escapeHtml(student.name) + "</div></div>"
                        + "</article>";
                }).reverse().join("");
                focusStudentId = null;

                $stack.html(cards);
                cardElement = $stack.find(".is-current")[0] || null;
                bindCardImageFallback();
                bindCardPointer();
                renderReview();
                updateSummary();

                if (typeof feather !== "undefined") {
                    feather.replace();
                }
            }

            function bindCardPointer() {
                if (!cardElement) {
                    return;
                }

                cardElement.addEventListener("pointerdown", function (event) {
                    if (isAnimating) {
                        return;
                    }
                    pointerStartX = event.clientX;
                    pointerDeltaX = 0;
                    cardElement.classList.add("is-dragging");
                    if (cardElement.setPointerCapture) {
                        cardElement.setPointerCapture(event.pointerId);
                    }
                });

                cardElement.addEventListener("pointermove", function (event) {
                    if (pointerStartX === null || isAnimating) {
                        return;
                    }

                    pointerDeltaX = event.clientX - pointerStartX;
                    var rotation = pointerDeltaX / 18;
                    cardElement.style.transform = "translateX(" + pointerDeltaX + "px) rotate(" + rotation + "deg)";
                });

                cardElement.addEventListener("pointerup", finishPointer);
                cardElement.addEventListener("pointercancel", finishPointer);

                function finishPointer() {
                    if (pointerStartX === null || isAnimating) {
                        return;
                    }

                    var delta = pointerDeltaX;
                    pointerStartX = null;
                    pointerDeltaX = 0;
                    cardElement.classList.remove("is-dragging");
                    cardElement.style.transform = "";

                    if (Math.abs(delta) >= 90) {
                        choose(delta > 0 ? "presente" : "falta");
                    }
                }
            }

            function choose(status) {
                if (isAnimating) {
                    return;
                }

                var pending = getPendingStudents();
                var student = pending[0];
                if (!student) {
                    return;
                }

                actionHistory.push({id: student.id, previous: student.status});
                student.status = status;
                syncStudentInput(student);
                updateSummary();
                isAnimating = true;

                if (cardElement) {
                    cardElement.classList.add(status === "presente" ? "swipe-right" : "swipe-left");
                }

                window.setTimeout(function () {
                    isAnimating = false;
                    renderCards();
                }, 230);
            }

            $("[data-attendance-action]").on("click", function () {
                choose($(this).data("attendance-action"));
            });

            $undo.on("click", function () {
                if (!actionHistory.length || isAnimating) {
                    return;
                }

                var last = actionHistory.pop();
                var student = getStudent(last.id);
                if (student) {
                    student.status = last.previous;
                    syncStudentInput(student);
                }
                renderCards();
            });

            $(document).off("click.gdAttendanceReview", "[data-edit-student]").on("click.gdAttendanceReview", "[data-edit-student]", function () {
                if (isAnimating) {
                    return;
                }

                var student = getStudent($(this).data("edit-student"));
                if (!student || student.status !== "presente" && student.status !== "falta") {
                    return;
                }

                actionHistory.push({id: student.id, previous: student.status});
                student.status = "sem_registro";
                syncStudentInput(student);
                focusStudentId = student.id;
                renderCards();
            });

            $form.appForm({
                isModal: false,
                beforeAjaxSubmit: function () {
                    if (getPendingStudents().length) {
                        appAlert.error("Marque todos os alunos como presente ou falta antes de salvar.");
                        return false;
                    }
                    return true;
                },
                onSuccess: function (result) {
                    if (result.success) {
                        appAlert.success(result.message);
                    }
                }
            });

            renderCards();
        });
    </script>
<?php endif; ?>
