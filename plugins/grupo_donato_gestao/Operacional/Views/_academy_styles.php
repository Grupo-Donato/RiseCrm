<style>
    .gd-academy-page {
        --academy-bg: var(--gd-bg, #03182f);
        --academy-surface: var(--gd-surface, #082a52);
        --academy-surface-2: var(--gd-surface-2, #0b315f);
        --academy-line: var(--gd-border, #244d78);
        --academy-text: var(--gd-text, #fff);
        --academy-muted: var(--gd-muted, #b7c5d8);
        --academy-accent: var(--gd-gold, #d2a63a);
        --academy-accent-hover: var(--gd-gold-hover, #e4bc55);
        background: var(--academy-bg);
        color: var(--academy-text);
        font-size: 14px;
    }

    .gd-academy-page h1,
    .gd-academy-page h2,
    .gd-academy-page h3,
    .gd-academy-page h4,
    .gd-academy-page label { color: var(--academy-text); }

    .gd-academy-page a:not(.btn) { color: var(--academy-accent-hover); }
    .gd-academy-page a:not(.btn):hover,
    .gd-academy-page a:not(.btn):focus { color: var(--academy-accent); }

    .gd-academy-page .gd-academy-kicker {
        color: var(--academy-accent-hover);
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .gd-academy-page .gd-academy-breadcrumbs {
        color: var(--academy-muted);
        font-size: 12px;
        margin-bottom: 17px;
    }

    .gd-academy-page .gd-academy-breadcrumbs a { color: var(--academy-muted); text-decoration: none; }
    .gd-academy-page .gd-academy-breadcrumbs a:hover { color: var(--academy-accent-hover); text-decoration: underline; }
    .gd-academy-page .gd-academy-breadcrumbs .separator { margin: 0 7px; opacity: .55; }

    .gd-academy-page .gd-academy-header { align-items: flex-start; display: flex; gap: 18px; justify-content: space-between; }
    .gd-academy-page .gd-academy-header h1 { font-size: 27px; font-weight: 600; line-height: 1.15; margin: 4px 0 7px; }
    .gd-academy-page .gd-academy-subtitle { color: var(--academy-muted); font-size: 13px; line-height: 1.6; }
    .gd-academy-page .gd-academy-header-actions { align-items: center; display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; }

    .gd-academy-page .gd-academy-nav {
        border-bottom: 1px solid var(--academy-line);
        display: flex;
        gap: 4px;
        margin: 22px 0 22px;
        overflow-x: auto;
        padding-bottom: 0;
    }

    .gd-academy-page .gd-academy-nav a {
        border-bottom: 3px solid transparent;
        color: var(--academy-muted);
        font-size: 13px;
        font-weight: 600;
        padding: 11px 13px;
        text-decoration: none;
        white-space: nowrap;
    }

    .gd-academy-page .gd-academy-nav a:hover { color: var(--academy-text); }
    .gd-academy-page .gd-academy-nav a.active { border-bottom-color: var(--academy-accent); color: var(--academy-accent-hover); }
    .gd-academy-page .gd-academy-section-title { align-items: center; display: flex; gap: 12px; justify-content: space-between; margin: 0 0 16px; }
    .gd-academy-page .gd-academy-section-title h2 { font-size: 20px; font-weight: 600; margin: 0; }

    .gd-academy-page .gd-academy-card,
    .gd-academy-page .gd-academy-kpi,
    .gd-academy-page .gd-academy-list-item,
    .gd-academy-page .gd-academy-form-card,
    .gd-academy-page .gd-academy-empty {
        background: var(--academy-surface) !important;
        border: 1px solid var(--academy-line) !important;
        color: var(--academy-text);
    }

    .gd-academy-page .gd-academy-card { border-radius: 10px; height: 100%; padding: 17px; }
    .gd-academy-page .gd-academy-card h3 { font-size: 16px; font-weight: 600; margin: 0 0 9px; }
    .gd-academy-page .gd-academy-card h4 { font-size: 14px; font-weight: 600; margin: 0 0 7px; }
    .gd-academy-page .gd-academy-muted { color: var(--academy-muted) !important; font-size: 13px; }

    .gd-academy-page .gd-academy-kpi { border-radius: 10px; min-height: 82px; padding: 13px 15px; }
    .gd-academy-page .gd-academy-kpi span { color: var(--academy-muted); display: block; font-size: 11px; }
    .gd-academy-page .gd-academy-kpi strong { color: var(--academy-text); display: block; font-size: 22px; margin-top: 5px; }

    .gd-academy-page .gd-academy-list { display: grid; gap: 10px; }
    .gd-academy-page .gd-academy-list-item { align-items: center; border-radius: 10px; display: flex; gap: 13px; justify-content: space-between; padding: 13px 15px; }
    .gd-academy-page .gd-academy-list-item-main { min-width: 0; }
    .gd-academy-page .gd-academy-list-item-main strong { color: var(--academy-text); display: block; font-size: 14px; }
    .gd-academy-page .gd-academy-list-item-main small { color: var(--academy-muted); display: block; font-size: 12px; margin-top: 4px; }
    .gd-academy-page .gd-academy-evaluation-item { align-items: center; display: grid; grid-template-columns: minmax(0, 1fr) 175px auto; }
    .gd-academy-page .gd-academy-evaluation-item > .gd-academy-status { justify-self: center; }
    .gd-academy-page .gd-academy-evaluation-item > div:last-child { align-items: center; display: flex; gap: 8px; justify-content: flex-end; white-space: nowrap; }
    .gd-academy-page .gd-academy-participant-item { align-items: start; display: grid; grid-template-columns: minmax(0, 1fr) auto; }
    .gd-academy-page .gd-academy-participant-item > .d-flex:first-child { min-width: 0; }
    .gd-academy-page .gd-academy-participant-item > .d-flex.flex-wrap.gap-1 { flex: 0 0 auto; flex-wrap: nowrap; justify-content: flex-end; min-width: 175px; white-space: nowrap; }
    .gd-academy-page .gd-academy-participant-item > .w-100 { grid-column: 1 / -1; }
    .gd-academy-page .gd-academy-empty { border-style: dashed !important; border-width: 1px; border-radius: 10px; color: var(--academy-muted); padding: 34px 18px; text-align: center; }
    .gd-academy-page .gd-academy-empty h3 { color: var(--academy-text); font-size: 17px; margin: 10px 0 7px; }
    .gd-academy-page .gd-academy-form-card { border-radius: 10px; margin-bottom: 17px; padding: 16px; }
    .gd-academy-page .gd-academy-form-card h3 { color: var(--academy-text); font-size: 16px; font-weight: 600; margin: 0 0 13px; }
    .gd-academy-page .gd-academy-participants-card { background: var(--academy-surface) !important; border: 1px solid var(--academy-line) !important; border-radius: 10px; margin-bottom: 17px; padding: 16px; }
    .gd-academy-page .gd-academy-participants-header { align-items: flex-start; display: flex; gap: 14px; justify-content: space-between; margin-bottom: 13px; }
    .gd-academy-page .gd-academy-participants-header h3 { color: var(--academy-text); font-size: 16px; font-weight: 600; margin: 0 0 5px; }
    .gd-academy-page .gd-academy-participants-count { background: var(--academy-surface-2); border: 1px solid var(--academy-line); border-radius: 999px; color: var(--academy-muted); flex: 0 0 auto; font-size: 11px; font-weight: 700; padding: 5px 9px; }
    .gd-academy-page .gd-academy-participants-scroll { max-height: 580px; overflow-y: auto; margin: 0 -8px; padding: 0 3px 2px 0; scrollbar-color: var(--academy-line) transparent; scrollbar-width: thin; }
    .gd-academy-page .gd-academy-participants-scroll .gd-academy-participant-item { min-height: 176px; }
    .gd-academy-page .gd-academy-lineup-row > [class*="col-"] { display: flex; }
    .gd-academy-page .gd-academy-lineup-row .gd-academy-form-card { height: 100%; width: 100%; }
    .gd-academy-page .gd-academy-student-results { max-height: 320px; overflow-y: auto; margin-left: -8px; margin-right: -8px; padding: 0 3px 2px 0; scrollbar-color: var(--academy-line) transparent; scrollbar-width: thin; }
    .gd-academy-page .gd-academy-student-result { align-items: center; background: var(--academy-surface-2); border: 1px solid var(--academy-line); border-radius: 8px; display: flex; gap: 10px; justify-content: space-between; padding: 10px 12px; }
    .gd-academy-page .gd-academy-student-result-info { min-width: 0; }
    .gd-academy-page .gd-academy-student-result-info strong { color: var(--academy-text); display: block; font-size: 13px; }
    .gd-academy-page .gd-academy-student-result-info small { color: var(--academy-muted); display: block; font-size: 11px; margin-top: 3px; }
    .gd-academy-page .gd-academy-student-result-status { flex: 0 0 auto; }

    .gd-academy-page .form-control,
    .gd-academy-page select,
    .gd-academy-page textarea {
        background-color: var(--academy-surface-2) !important;
        border-color: var(--academy-surface-2) !important;
        color: var(--academy-text) !important;
    }

    .gd-academy-page .form-control:focus,
    .gd-academy-page select:focus,
    .gd-academy-page textarea:focus {
        background-color: var(--academy-bg) !important;
        border-color: var(--academy-border-strong, #315d8b) !important;
        color: var(--academy-text) !important;
    }

    .gd-academy-page .form-control::placeholder { color: var(--academy-muted); opacity: .8; }
    .gd-academy-page option { background: var(--academy-surface); color: var(--academy-text); }
    .gd-academy-page select[name="position"] { min-width: 150px; }
    .gd-academy-page input[type="date"],
    .gd-academy-page input[type="time"] { color-scheme: dark; }
    .gd-academy-page .btn-primary { background-color: var(--academy-accent) !important; border-color: var(--academy-accent) !important; color: var(--academy-bg) !important; }
    .gd-academy-page .btn-primary:hover,
    .gd-academy-page .btn-primary:focus { background-color: var(--academy-accent-hover) !important; border-color: var(--academy-accent-hover) !important; color: var(--academy-bg) !important; }
    .gd-academy-page .btn-link { color: var(--academy-accent-hover) !important; }
    .gd-academy-page .btn-link:hover,
    .gd-academy-page .btn-link:focus { color: var(--academy-accent) !important; }

    .gd-academy-page .gd-academy-avatar { border-radius: 50%; height: 42px; object-fit: cover; width: 42px; }
    .gd-academy-page .gd-academy-table-wrap { overflow-x: auto; }
    .gd-academy-page .gd-academy-table { --bs-table-bg: transparent; --bs-table-color: var(--academy-text); background: var(--academy-surface) !important; color: var(--academy-text) !important; margin: 0; min-width: 620px; width: 100%; }
    .gd-academy-page .gd-academy-table th { background: transparent !important; border-top: 0; color: var(--academy-muted) !important; font-size: 11px; text-transform: uppercase; }
    .gd-academy-page .gd-academy-table td { background: transparent !important; color: var(--academy-text) !important; vertical-align: middle; }
    .gd-academy-page .gd-academy-table tr { border-color: var(--academy-line); }
    .gd-academy-page .gd-academy-status { border-radius: 999px; display: inline-block; font-size: 11px; font-weight: 700; padding: 4px 8px; }
    .gd-academy-page .gd-academy-status-success { background: rgba(22, 163, 74, .20); color: #8be28b; }
    .gd-academy-page .gd-academy-status-muted { background: rgba(183, 197, 216, .14); color: var(--academy-muted); }
    .gd-academy-page .gd-academy-status-warning { background: rgba(245, 158, 11, .20); color: #ffd166; }
    .gd-academy-page .gd-academy-status-danger { background: rgba(239, 68, 68, .20); color: #ff8795; }
    .gd-academy-page .gd-academy-sticky-actions { background: var(--academy-bg); border-top: 1px solid var(--academy-line); bottom: 0; margin-top: 22px; padding-top: 13px; position: sticky; z-index: 2; }
    .gd-academy-page .gd-academy-star-input { font-size: 22px; letter-spacing: 2px; width: 82px; }
    .gd-academy-page .gd-academy-stat-grid { display: grid; gap: 10px; grid-template-columns: repeat(4, minmax(0, 1fr)); }

    @media (max-width: 767px) {
        .gd-academy-page .gd-academy-header { flex-direction: column; }
        .gd-academy-page .gd-academy-header-actions { justify-content: flex-start; width: 100%; }
        .gd-academy-page .gd-academy-header-actions .btn { flex: 1 1 auto; }
        .gd-academy-page .gd-academy-nav a { padding: 10px 11px; }
        .gd-academy-page .gd-academy-list-item { align-items: flex-start; flex-direction: column; }
        .gd-academy-page .gd-academy-list-item .btn { width: 100%; }
        .gd-academy-page .gd-academy-evaluation-item { align-items: stretch; display: grid; grid-template-columns: minmax(0, 1fr) auto; }
        .gd-academy-page .gd-academy-evaluation-item > .gd-academy-status { grid-column: 2; grid-row: 1; justify-self: end; }
        .gd-academy-page .gd-academy-evaluation-item > div:last-child { grid-column: 1 / -1; grid-row: 2; width: 100%; }
        .gd-academy-page .gd-academy-participant-item { display: grid; grid-template-columns: minmax(0, 1fr); }
        .gd-academy-page .gd-academy-participant-item > .d-flex.flex-wrap.gap-1 { justify-content: flex-start; min-width: 0; width: 100%; }
        .gd-academy-page .gd-academy-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .gd-academy-page .gd-academy-table-wrap { overflow: visible; }
        .gd-academy-page .gd-academy-table { display: block; min-width: 0; }
        .gd-academy-page .gd-academy-table thead { display: none; }
        .gd-academy-page .gd-academy-table tbody,
        .gd-academy-page .gd-academy-table tr,
        .gd-academy-page .gd-academy-table td { display: block; width: 100%; }
        .gd-academy-page .gd-academy-table tr { border: 1px solid var(--academy-line); border-radius: 10px; margin-bottom: 10px; padding: 11px; }
        .gd-academy-page .gd-academy-table td { border: 0; padding: 3px 0; }
        .gd-academy-page .gd-academy-table td:before { color: var(--academy-muted); content: attr(data-label); display: inline-block; font-size: 11px; font-weight: 700; margin-right: 7px; text-transform: uppercase; }
        .gd-academy-page .gd-academy-table td:last-child { padding-top: 9px; }
        .gd-academy-page .gd-academy-form-card { padding: 14px; }
        .gd-academy-page .gd-academy-participants-card { padding: 14px; }
        .gd-academy-page .gd-academy-participants-scroll { max-height: 1110px; }
        .gd-academy-page .gd-academy-lineup-row { display: block; }
        .gd-academy-page .gd-academy-lineup-row > [class*="col-"] { display: block; }
        .gd-academy-page .gd-academy-student-result { align-items: flex-start; flex-wrap: wrap; }
        .gd-academy-page .gd-academy-student-result .btn { margin-left: auto; }
        .gd-academy-page select[name="position"] { max-width: none !important; width: 100%; }
        .gd-academy-page .gd-academy-sticky-actions { position: static; }
    }
</style>
