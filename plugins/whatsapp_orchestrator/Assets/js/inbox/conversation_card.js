(function (window, document) {
    'use strict';

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function dateValue(value) {
        var date = value ? new Date(value) : new Date();
        return isNaN(date.getTime()) ? new Date() : date;
    }

    function timeLabel(value) {
        var date = dateValue(value);
        var now = new Date();
        if (date.toDateString() === now.toDateString()) {
            return new Intl.DateTimeFormat('pt-BR', { hour: '2-digit', minute: '2-digit' }).format(date);
        }
        return new Intl.DateTimeFormat('pt-BR', { day: '2-digit', month: '2-digit' }).format(date);
    }

    function priorityLabel(priority) {
        return { high: 'Alta', urgent: 'Urgente' }[String(priority || '')] || '';
    }

    function workflowSignals(conversation, options) {
        var signals = [];
        var priority = priorityLabel(conversation.priority);
        if (priority) signals.push('<span class="impulso-workflow-pill priority-' + escapeHtml(conversation.priority) + '">' + priority + '</span>');
        if (conversation.conversation_type === 'group') signals.push('<span class="impulso-workflow-pill">Grupo</span>');
        if (conversation.bot_status === 'paused' || conversation.bot_status === 'handoff') {
            signals.push('<span class="impulso-workflow-pill">Bot ' + (conversation.bot_status === 'handoff' ? 'handoff' : 'pausado') + '</span>');
        }
        if (options.showAssignee && conversation.assignee && conversation.assignee !== 'Sem agente') {
            signals.push('<span class="impulso-workflow-pill impulso-assignee-signal"><i data-feather="user"></i>' + escapeHtml(conversation.assignee) + '</span>');
        }
        (Array.isArray(conversation.tags) ? conversation.tags : []).slice(0, 2).forEach(function (tag) {
            signals.push('<span class="impulso-workflow-tag">' + escapeHtml(tag) + '</span>');
        });
        return signals.join('');
    }

    function render(conversation, options) {
        conversation = conversation || {};
        options = options || {};
        var id = Number(conversation.id || 0);
        var active = options.active === true;
        var selected = options.selected === true;
        var permission = options.canBulkSelect === true;
        var signals = workflowSignals(conversation, options);
        var bulkCheckbox = permission
            ? '<label class="impulso-bulk-select" title="Selecionar conversa"><input type="checkbox" data-bulk-select="' + id + '"' + (selected ? ' checked' : '') + ' aria-label="Selecionar ' + escapeHtml(conversation.name) + '"></label>'
            : '';
        var unread = Number(conversation.unread || 0);
        var unreadBadge = unread > 0 ? '<span class="impulso-unread" aria-label="' + unread + ' não lida(s)">' + unread + '</span>' : '';
        var signalMarkup = signals ? '<div class="impulso-workflow-tags" aria-label="Indicadores operacionais">' + signals + '</div>' : '';
        return '<article class="impulso-conversation-item impulso-conversation-card' + (active ? ' active' : '') + (unread > 0 ? ' unread' : '') + '" data-conversation-id="' + id + '" data-status="' + escapeHtml(conversation.status) + '" data-instance-id="' + Number(conversation.instance_id || 0) + '" data-assignee="' + escapeHtml(conversation.assignee || '') + '">' +
            '<button class="impulso-conversation-select" type="button" data-conversation-select="' + id + '" aria-label="Abrir conversa de ' + escapeHtml(conversation.name) + '"' + (active ? ' aria-current="page"' : '') + '>' +
            '<div class="impulso-conversation-line"><div class="impulso-avatar">' + escapeHtml(conversation.avatar || '—') + '</div><div class="impulso-conversation-copy">' +
            '<div class="impulso-conversation-title"><strong>' + escapeHtml(conversation.name || 'Contato') + '</strong><span class="impulso-conversation-time">' + escapeHtml(timeLabel(conversation.last_activity_at)) + '</span></div>' +
            '<div class="impulso-conversation-preview">' + escapeHtml(conversation.last_message || 'Sem mensagens') + '</div>' +
            (signalMarkup || '<div class="impulso-conversation-meta">' + unreadBadge + '</div>') +
            (signalMarkup ? '<div class="impulso-conversation-meta">' + unreadBadge + '</div>' : '') +
            '</div></div></button>' + bulkCheckbox +
            '<button class="impulso-conversation-menu-trigger" type="button" data-conversation-menu="' + id + '" aria-label="Ações da conversa" aria-haspopup="menu"><i data-feather="more-vertical"></i></button></article>';
    }

    window.ImpulsoConversationCard = { render: render };
}(window, document));
