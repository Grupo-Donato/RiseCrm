(function (window, document) {
    'use strict';

    var active = null;

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function close(result) {
        if (!active) return;
        var current = active;
        active = null;
        if (current.backdrop && current.backdrop.parentNode) current.backdrop.parentNode.removeChild(current.backdrop);
        document.removeEventListener('keydown', current.keydown, true);
        if (current.trigger && typeof current.trigger.focus === 'function') current.trigger.focus();
        current.resolve(result);
    }

    function open(options) {
        options = options || {};
        if (active) close(null);
        var trigger = document.activeElement;
        var isPrompt = options.type === 'prompt';
        var backdrop = document.createElement('div');
        var title = String(options.title || (isPrompt ? 'Editar valor' : 'Confirmar ação'));
        var message = String(options.message || '');
        var label = String(options.label || 'Valor');
        var value = String(options.value == null ? '' : options.value);
        backdrop.className = 'impulso-message-dialog-backdrop impulso-inbox-dialog-backdrop';
        backdrop.setAttribute('role', 'presentation');
        backdrop.innerHTML = '<form class="impulso-message-dialog impulso-inbox-dialog impulso-message-dialog-form" role="dialog" aria-modal="true" aria-labelledby="impulso-inbox-dialog-title">' +
            '<div class="impulso-dialog-header"><h3 id="impulso-inbox-dialog-title">' + escapeHtml(title) + '</h3><button type="button" data-inbox-dialog-cancel aria-label="Fechar">&times;</button></div>' +
            (message ? '<p class="impulso-dialog-message">' + escapeHtml(message) + '</p>' : '') +
            (isPrompt ? '<label>' + escapeHtml(label) + '<input class="form-control" name="value" type="text" value="' + escapeHtml(value) + '" placeholder="' + escapeHtml(options.placeholder || '') + '"></label>' : '') +
            '<div class="impulso-dialog-error" aria-live="polite"></div><div class="impulso-dialog-actions"><button type="button" data-inbox-dialog-cancel>' + escapeHtml(options.cancelLabel || 'Cancelar') + '</button><button type="submit" class="btn btn-primary">' + escapeHtml(options.confirmLabel || (isPrompt ? 'Salvar' : 'Confirmar')) + '</button></div></form>';
        var form = backdrop.querySelector('form');
        var input = backdrop.querySelector('input');
        var keydown = function (event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                close(null);
            }
        };
        active = { backdrop: backdrop, trigger: trigger, keydown: keydown, resolve: function () {} };
        document.body.appendChild(backdrop);
        document.addEventListener('keydown', keydown, true);
        return new Promise(function (resolve) {
            active.resolve = resolve;
            form.addEventListener('submit', function (event) {
                event.preventDefault();
                close(isPrompt ? String((input && input.value) || '') : true);
            });
            backdrop.querySelectorAll('[data-inbox-dialog-cancel]').forEach(function (button) {
                button.addEventListener('click', function () { close(null); });
            });
            backdrop.addEventListener('click', function (event) {
                if (event.target === backdrop) close(null);
            });
            if (input) input.focus();
            else {
                var confirm = form.querySelector('[type="submit"]');
                if (confirm) confirm.focus();
            }
        });
    }

    window.ImpulsoDialogs = {
        prompt: function (options) { return open(Object.assign({}, options || {}, { type: 'prompt' })); },
        confirm: function (options) { return open(Object.assign({}, options || {}, { type: 'confirm' })); },
        close: function () { close(null); }
    };
}(window, document));
