(function () {
    "use strict";

    var root = document.getElementById("gd-online-enrollment");
    if (!root) return;
    var form = document.getElementById("gd-public-matricula-form");
    var storageKey = root.dataset.storageKey;
    var state = { token: "", data: null, signatureData: "", hasInk: false };
    var steps = Array.prototype.slice.call(document.querySelectorAll("[data-step]"));
    var message = document.getElementById("gd-enrollment-message");
    var idempotencyInput = document.getElementById("gd-idempotency-key");
    var canvas = document.getElementById("gd-signature-canvas");
    var classSelect = document.getElementById("horario");
    var startDateInput = document.getElementById("data_inicio");
    var context = canvas.getContext("2d");
    var drawing = false;
    var lastPoint = null;
    var automaticStartDate = startDateInput ? startDateInput.value : "";

    function nextClassDate(schedule) {
        var dayNames = { "Dom": 0, "Domingo": 0, "Seg": 1, "Segunda": 1, "Ter": 2, "Terça": 2, "Qua": 3, "Quarta": 3, "Qui": 4, "Quinta": 4, "Sex": 5, "Sexta": 5, "Sáb": 6, "Sab": 6, "Sábado": 6 };
        var dayPart = String(schedule || "").split(" ")[0];
        var weekdays = dayPart.split("/").map(function (name) { return dayNames[name]; }).filter(function (day) { return day !== undefined; });
        if (!weekdays.length) return "";

        var today = new Date();
        today.setHours(0, 0, 0, 0);
        var next = null;
        weekdays.forEach(function (weekday) {
            var daysAhead = (weekday - today.getDay() + 7) % 7;
            // "Próximo dia" significa a próxima ocorrência, não o dia atual.
            if (daysAhead === 0) daysAhead = 7;
            if (next === null || daysAhead < next) next = daysAhead;
        });
        if (next === null) return "";

        var result = new Date(today);
        result.setDate(result.getDate() + next);
        return result.getFullYear() + "-" + String(result.getMonth() + 1).padStart(2, "0") + "-" + String(result.getDate()).padStart(2, "0");
    }

    function updateDefaultStartDate() {
        if (!classSelect || !startDateInput) return;
        var next = nextClassDate(classSelect.value);
        if (!next) return;
        if (!startDateInput.value || startDateInput.value === automaticStartDate) {
            startDateInput.value = next;
            automaticStartDate = next;
        }
    }

    if (classSelect) classSelect.addEventListener("change", updateDefaultStartDate);
    if (startDateInput) startDateInput.addEventListener("input", function () { automaticStartDate = ""; });

    function randomKey() {
        if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
        return "gd-" + Date.now() + "-" + Math.random().toString(36).slice(2);
    }

    function setMessage(text, type) {
        message.textContent = text || "";
        message.className = "gd-enrollment-message" + (text ? " " + (type || "error") : "");
    }

    function endpoint(template) { return template + encodeURIComponent(state.token); }

    function request(url, data, button) {
        if (button) button.disabled = true;
        setMessage("", "");
        return fetch(url, { method: "POST", body: data, credentials: "same-origin", headers: { "X-Requested-With": "XMLHttpRequest" } })
            .then(function (response) { return response.json().catch(function () { return {}; }).then(function (result) { if (!response.ok || !result.success) throw new Error(result.message || "Não foi possível concluir esta etapa."); return result.data || result; }); })
            .finally(function () { if (button) button.disabled = false; });
    }

    function setStep(name) {
        steps.forEach(function (step) { step.hidden = step.dataset.step !== name; });
        var active = name === "contract" ? 2 : (name === "signature" || name === "success" ? 3 : 1);
        document.querySelectorAll("[data-progress]").forEach(function (item) { item.classList.toggle("active", Number(item.dataset.progress) <= active); });
        document.getElementById("gd-enrollment-actions").hidden = name === "success";
        document.getElementById("gd-step1-submit").hidden = name !== "1";
        document.getElementById("gd-step2-submit").hidden = name !== "contract";
        document.getElementById("gd-signature-submit").hidden = name !== "signature" || !!(state.data && state.data.signature_registered);
        document.getElementById("gd-finalize-submit").hidden = name !== "signature" || !(state.data && state.data.signature_registered);
        if (name === "signature") resizeCanvas();
    }

    function applySummary(data) {
        var summary = data.summary || {};
        document.querySelectorAll("[data-summary]").forEach(function (node) { node.textContent = summary[node.dataset.summary] || "-"; });
        document.querySelectorAll("[data-success]").forEach(function (node) { node.textContent = summary[node.dataset.success] || "-"; });
        var download = document.getElementById("gd-download-contract");
        if (data.download_url) { download.href = data.download_url; download.hidden = false; }
        var retry = document.getElementById("gd-retry-whatsapp");
        var delivery = data.delivery_status;
        retry.hidden = delivery !== "failed";
        document.getElementById("gd-delivery-note").textContent = delivery === "sent" ? "✓ Uma cópia do contrato foi enviada para o WhatsApp." : "A matrícula foi criada; o envio do WhatsApp está pendente. Você pode tentar novamente.";
    }

    function applyState(data) {
        state.data = data;
        applySummary(data);
        if (data.contract_html) document.getElementById("gd-contract-content").innerHTML = data.contract_html;
        var accepted = document.getElementById("gd-contract-accepted");
        accepted.checked = !!data.accepted;
        document.getElementById("gd-step2-submit").disabled = !accepted.checked;
        document.getElementById("gd-signature-status").hidden = !data.signature_registered;
        document.getElementById("gd-signature-area").hidden = !!data.signature_registered;
        setStep(data.step === "success" ? "success" : data.step === "contract" ? "contract" : "signature");
    }

    function saveToken() { localStorage.setItem(storageKey, JSON.stringify({ token: state.token, idempotency_key: idempotencyInput.value })); }
    function clearSaved() { localStorage.removeItem(storageKey); }

    function restore() {
        var saved = null;
        try { saved = JSON.parse(localStorage.getItem(storageKey) || "null"); } catch (ignore) {}
        idempotencyInput.value = (saved && saved.idempotency_key) || randomKey();
        if (!saved || !saved.token) { setStep("1"); return; }
        state.token = saved.token;
        fetch(root.dataset.stateUrl + encodeURIComponent(state.token), { credentials: "same-origin", headers: { "X-Requested-With": "XMLHttpRequest" } })
            .then(function (response) { return response.json().then(function (result) { if (!response.ok || !result.success) throw new Error(result.message || "Esta matrícula não está mais disponível."); return result.data; }); })
            .then(applyState)
            .catch(function (error) { clearSaved(); state.token = ""; setMessage(error.message, "error"); setStep("1"); });
    }

    function resizeCanvas() {
        if (!canvas || canvas.offsetWidth < 1) return;
        var ratio = Math.max(1, window.devicePixelRatio || 1);
        var width = canvas.offsetWidth;
        var height = canvas.offsetHeight;
        if (canvas.width !== Math.round(width * ratio) || canvas.height !== Math.round(height * ratio)) {
            canvas.width = Math.round(width * ratio); canvas.height = Math.round(height * ratio); context.scale(ratio, ratio); context.lineCap = "round"; context.lineJoin = "round"; context.lineWidth = 2.5; context.strokeStyle = "#172033";
        }
    }

    function point(event) { var rect = canvas.getBoundingClientRect(); return { x: event.clientX - rect.left, y: event.clientY - rect.top }; }
    canvas.addEventListener("pointerdown", function (event) { event.preventDefault(); resizeCanvas(); drawing = true; lastPoint = point(event); canvas.setPointerCapture(event.pointerId); });
    canvas.addEventListener("pointermove", function (event) { if (!drawing) return; event.preventDefault(); var current = point(event); context.beginPath(); context.moveTo(lastPoint.x, lastPoint.y); context.lineTo(current.x, current.y); context.stroke(); lastPoint = current; state.hasInk = true; });
    ["pointerup", "pointercancel", "pointerleave"].forEach(function (name) { canvas.addEventListener(name, function (event) { drawing = false; lastPoint = null; if (event.cancelable) event.preventDefault(); }); });
    window.addEventListener("resize", resizeCanvas);

    document.getElementById("gd-step1-submit").addEventListener("click", function () {
        if (!form.reportValidity()) return;
        var button = this;
        request(root.dataset.createUrl, new FormData(form), button).then(function (data) { state.token = data.token; saveToken(); applyState(data); }).catch(function (error) { setMessage(error.message, "error"); });
    });
    document.getElementById("gd-contract-accepted").addEventListener("change", function () { document.getElementById("gd-step2-submit").disabled = !this.checked; });
    document.getElementById("gd-step2-submit").addEventListener("click", function () {
        if (!document.getElementById("gd-contract-accepted").checked) return;
        var button = this; var data = new FormData(form); data.set("li_ciente", "1");
        request(endpoint(root.dataset.acceptUrl), data, button).then(applyState).catch(function (error) { setMessage(error.message, "error"); });
    });
    document.getElementById("gd-signature-clear").addEventListener("click", function () { resizeCanvas(); context.clearRect(0, 0, canvas.width, canvas.height); state.hasInk = false; state.signatureData = ""; });
    document.getElementById("gd-signature-submit").addEventListener("click", function () {
        if (!state.hasInk) { setMessage("Desenhe sua assinatura antes de continuar.", "error"); return; }
        state.signatureData = canvas.toDataURL("image/png");
        var data = new FormData(form); data.append("signature_data", state.signatureData);
        request(endpoint(root.dataset.signatureUrl), data, this).then(applyState).catch(function (error) { setMessage(error.message, "error"); });
    });
    document.getElementById("gd-finalize-submit").addEventListener("click", function () {
        var button = this;
        request(endpoint(root.dataset.finalizeUrl), new FormData(form), button).then(function (data) { applyState(data); setMessage("", ""); }).catch(function (error) { setMessage(error.message, "error"); });
    });
    document.getElementById("gd-retry-whatsapp").addEventListener("click", function () { request(endpoint(root.dataset.whatsappUrl), new FormData(form), this).then(applyState).catch(function (error) { setMessage(error.message, "error"); }); });
    document.getElementById("gd-new-enrollment").addEventListener("click", function () { clearSaved(); state = { token: "", data: null, signatureData: "", hasInk: false }; idempotencyInput.value = randomKey(); form.reset(); automaticStartDate = startDateInput ? startDateInput.value : ""; setMessage("", ""); setStep("1"); });

    restore();
}());
