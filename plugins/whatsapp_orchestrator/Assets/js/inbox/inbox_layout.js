(function (window, document) {
    'use strict';

    var app = document.getElementById('impulso-hub-app');
    if (!app || app.getAttribute('data-active-tab') !== 'conversations') return;

    function setChannelPickerOpen(open) {
        var picker = document.getElementById('impulso-channel-picker');
        var toggle = app.querySelector('[data-inbox-channel-toggle]');
        if (!picker) return;
        var isOpen = !!open;
        picker.classList.toggle('impulso-hidden', !isOpen);
        picker.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        if (toggle) toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    function setMoreOpen(open) {
        var menu = document.getElementById('impulso-conversation-more');
        var toggle = app.querySelector('[data-inbox-more-toggle]');
        if (!menu) return;
        var isOpen = !!open;
        menu.classList.toggle('impulso-hidden', !isOpen);
        if (toggle) toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }

    function syncContactDrawer() {
        var drawer = document.getElementById('impulso-contact-sidebar');
        if (!drawer) return;
        var open = drawer.classList.contains('open');
        drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
        app.querySelectorAll('[data-inbox-contact-toggle], [data-impulso-action="open-contact"]').forEach(function (toggle) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    function setContactTab(tab, focus) {
        tab = ['contact', 'service', 'history'].indexOf(String(tab || '')) >= 0 ? String(tab) : 'contact';
        app.querySelectorAll('[data-contact-tab]').forEach(function (button) {
            var selected = button.getAttribute('data-contact-tab') === tab;
            button.classList.toggle('active', selected);
            button.setAttribute('aria-selected', selected ? 'true' : 'false');
            if (selected && focus) button.focus();
        });
        app.querySelectorAll('[data-contact-panel]').forEach(function (panel) {
            var selected = panel.getAttribute('data-contact-panel') === tab;
            panel.classList.toggle('active', selected);
            panel.hidden = !selected;
        });
    }

    app.querySelectorAll('[data-inbox-channel-toggle]').forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            var picker = document.getElementById('impulso-channel-picker');
            setChannelPickerOpen(!picker || picker.classList.contains('impulso-hidden'));
        });
    });

    app.querySelectorAll('[data-inbox-more-toggle]').forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            var menu = document.getElementById('impulso-conversation-more');
            setMoreOpen(!menu || menu.classList.contains('impulso-hidden'));
        });
    });

    app.querySelectorAll('[data-contact-tab]').forEach(function (button) {
        button.addEventListener('click', function () { setContactTab(this.getAttribute('data-contact-tab'), false); });
    });
    app.querySelectorAll('[data-inbox-contact-close]').forEach(function (button) {
        button.addEventListener('click', function () {
            var drawer = document.getElementById('impulso-contact-sidebar');
            if (drawer) drawer.classList.remove('open');
            syncContactDrawer();
        });
    });

    document.addEventListener('click', function (event) {
        if (event.target.closest('#impulso-conversation-more [data-impulso-action]')) setMoreOpen(false);
    }, true);

    document.addEventListener('click', function (event) {
        var channelRoot = event.target.closest('[data-inbox-channel-toggle], #impulso-channel-picker');
        if (!channelRoot) setChannelPickerOpen(false);
        var moreAction = event.target.closest('#impulso-conversation-more [data-impulso-action]');
        var moreRoot = event.target.closest('[data-inbox-more-toggle], #impulso-conversation-more');
        if (!moreRoot || moreAction) setMoreOpen(false);

        var drawer = document.getElementById('impulso-contact-sidebar');
        if (drawer && drawer.classList.contains('open') && !event.target.closest('#impulso-contact-sidebar') && !event.target.closest('[data-impulso-action="open-contact"]')) {
            drawer.classList.remove('open');
        }
        syncContactDrawer();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;
        var channel = document.getElementById('impulso-channel-picker');
        var more = document.getElementById('impulso-conversation-more');
        if (channel && !channel.classList.contains('impulso-hidden')) { setChannelPickerOpen(false); return; }
        if (more && !more.classList.contains('impulso-hidden')) { setMoreOpen(false); return; }
        syncContactDrawer();
    });

    setContactTab('contact', false);
    syncContactDrawer();
    window.ImpulsoInboxLayout = {
        setChannelPickerOpen: setChannelPickerOpen,
        setContactTab: setContactTab,
        syncContactDrawer: syncContactDrawer
    };
}(window, document));
