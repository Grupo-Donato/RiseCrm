<style>
.gd-rentals-shell .gd-rentals-subtitle{max-width:820px;line-height:1.45}
.gd-rentals-shell .gd-toolbar{display:flex;flex-wrap:wrap;align-items:center;gap:8px}
.gd-rentals-shell .gd-toolbar .btn{margin:0}
.gd-rentals-shell .gd-filter-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:12px;align-items:end}
.gd-rentals-shell .gd-filter-grid .form-group{margin-bottom:0;min-width:0}
.gd-rentals-shell .gd-filter-grid .select2-container{display:block;width:100%!important;max-width:100%}
.gd-rentals-shell .gd-filter-grid .select2-container .select2-choice{width:100%;min-height:34px;box-sizing:border-box}
.gd-rentals-shell .gd-filter-actions{display:flex;flex-wrap:wrap;gap:8px}
.gd-rentals-shell .w200,.gd-rentals-shell .w180,.gd-rentals-shell .w120{width:auto!important;max-width:100%}
.gd-rentals-shell .w200{min-width:210px}
.gd-rentals-shell .w180{min-width:180px}
.gd-rentals-shell .w120{min-width:120px}
.gd-rentals-shell .dataTables_wrapper .select2-container{max-width:100%}
.gd-rentals-shell .gd-legend{display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.gd-rentals-shell .gd-legend-item{display:inline-flex;align-items:center;gap:6px;white-space:nowrap;padding:6px 10px;border:1px solid var(--gd-border,rgba(0,0,0,.12));border-radius:6px;background:var(--gd-surface-2,#f8f9fa)}
.gd-rentals-shell .gd-legend-item.text-warning{color:var(--gd-warning,#f59e0b)!important}
.gd-rentals-shell .gd-legend-item.text-primary{color:#6f8fff!important}
.gd-rentals-shell .gd-legend-item.text-success{color:var(--gd-success,#198754)!important}
.gd-rentals-shell .gd-legend-item.text-danger{color:var(--gd-danger,#dc3545)!important}
.gd-rentals-shell .gd-legend-item.text-muted{color:var(--gd-muted,#6c757d)!important}
.gd-rentals-shell .gd-legend-dot{width:10px;height:10px;border-radius:50%;display:inline-block;background:currentColor;flex:0 0 10px}
.gd-rentals-shell .gd-table-note{padding:12px 16px;border-bottom:1px solid rgba(0,0,0,.08)}
.gd-rentals-shell .gd-stat-line{display:flex;flex-wrap:wrap;gap:10px 20px;align-items:center}
.gd-rentals-shell .gd-calendar-card>.card-body{overflow:visible}

/* FullCalendar: correção local para o tema escuro do Rise, sem alterar o CRM inteiro. */
.gd-rentals-shell #gd-calendar{color:var(--gd-text,#212529)}
.gd-rentals-shell #gd-calendar .fc-toolbar{gap:10px;flex-wrap:wrap;margin-bottom:14px}
.gd-rentals-shell #gd-calendar .fc-toolbar-title{font-size:1.15rem;color:var(--gd-text,#212529)}
.gd-rentals-shell #gd-calendar .fc-toolbar-chunk{display:flex;align-items:center;flex-wrap:wrap}
.gd-rentals-shell #gd-calendar .fc-button-group{display:inline-flex}
.gd-rentals-shell #gd-calendar .fc-button-primary{display:inline-flex;align-items:center;justify-content:center;min-width:36px;min-height:34px;padding:.4rem .7rem;box-shadow:none!important;background:var(--gd-border,#2c3e50)!important;border-color:var(--gd-border,#2c3e50)!important;color:var(--gd-text,#fff)!important}
.gd-rentals-shell #gd-calendar .fc-button-primary:hover,.gd-rentals-shell #gd-calendar .fc-button-primary:focus{background:var(--gd-surface-2,#1f2d3d)!important;border-color:var(--gd-surface-2,#1f2d3d)!important;color:var(--gd-text,#fff)!important}
.gd-rentals-shell #gd-calendar .fc-button-primary:not(:disabled).fc-button-active{background:var(--gd-surface-3,#182433)!important;border-color:var(--gd-border-strong,var(--gd-border,#2c3e50))!important;color:var(--gd-text,#fff)!important}
.gd-rentals-shell #gd-calendar .fc-button-primary:disabled{opacity:.62;color:var(--gd-text,#fff)!important}
.gd-rentals-shell #gd-calendar .fc-button-primary .fc-icon{display:inline-block;color:inherit!important;opacity:1!important;font-size:1.25em;line-height:1}
.gd-rentals-shell #gd-calendar .fc-view-harness,.gd-rentals-shell #gd-calendar .fc-scrollgrid,.gd-rentals-shell #gd-calendar .fc-timegrid-body,.gd-rentals-shell #gd-calendar .fc-daygrid-body{background:var(--gd-surface,#fff)}
.gd-rentals-shell #gd-calendar .fc-theme-standard .fc-scrollgrid,.gd-rentals-shell #gd-calendar .fc-theme-standard td,.gd-rentals-shell #gd-calendar .fc-theme-standard th{border-color:var(--gd-border,#dee2e6)!important}
.gd-rentals-shell #gd-calendar .fc-col-header-cell,.gd-rentals-shell #gd-calendar .fc-timegrid-axis{background:var(--gd-surface-2,#f8f9fa)!important}
.gd-rentals-shell #gd-calendar .fc-col-header-cell-cushion,.gd-rentals-shell #gd-calendar .fc-timegrid-axis-cushion,.gd-rentals-shell #gd-calendar .fc-timegrid-slot-label-cushion,.gd-rentals-shell #gd-calendar .fc-daygrid-day-number,.gd-rentals-shell #gd-calendar .fc-list-day-text,.gd-rentals-shell #gd-calendar .fc-list-day-side-text{color:var(--gd-text,#495057)!important}
.gd-rentals-shell #gd-calendar .fc-timegrid-slot,.gd-rentals-shell #gd-calendar .fc-timegrid-col,.gd-rentals-shell #gd-calendar .fc-daygrid-day{background:var(--gd-surface,#fff)!important}
.gd-rentals-shell #gd-calendar .fc-timegrid-col.fc-day-today,.gd-rentals-shell #gd-calendar .fc-daygrid-day.fc-day-today{background:rgba(210,166,58,.12)!important}
.gd-rentals-shell #gd-calendar .fc-list,.gd-rentals-shell #gd-calendar .fc-list-table td{background:var(--gd-surface,#fff)!important;color:var(--gd-text,#212529)!important}
.gd-rentals-shell #gd-calendar .fc-list-day-cushion{background:var(--gd-surface-2,#f8f9fa)!important}
.gd-rentals-shell #gd-calendar .fc-list-event:hover td{background:var(--gd-surface-2,#f8f9fa)!important}
.gd-rentals-shell #gd-calendar .fc-scrollgrid-section-header>*,.gd-rentals-shell #gd-calendar .fc-scrollgrid-section-footer>*{background:var(--gd-surface-2,#f8f9fa)!important}
.gd-rentals-shell #gd-calendar .fc-now-indicator-line{border-color:var(--gd-danger,#ef4444)}
.gd-rentals-shell #gd-calendar .fc-now-indicator-arrow{border-top-color:transparent;border-bottom-color:transparent;border-right-color:var(--gd-danger,#ef4444)}

.gd-rentals-shell .gd-resource-card{height:100%;cursor:pointer;transition:border-color .15s ease,box-shadow .15s ease}
.gd-rentals-shell .gd-resource-card.is-selected{box-shadow:0 0 0 1px currentColor inset}
.gd-rentals-shell .gd-resource-buffer{display:none}
.gd-rentals-shell .gd-resource-card.is-selected .gd-resource-buffer{display:flex}
.gd-rentals-shell .gd-section-title{display:flex;align-items:center;gap:8px;margin-bottom:14px}
.gd-rentals-shell .gd-section-title h5{margin:0}
.gd-rentals-shell .gd-form-help{font-size:12px;line-height:1.4}
.gd-rentals-shell .gd-weekday-list{display:flex;flex-wrap:wrap;gap:8px}
.gd-rentals-shell .gd-weekday-option{display:inline-flex;align-items:center;gap:6px;border:1px solid rgba(0,0,0,.12);border-radius:4px;padding:7px 10px;margin:0;cursor:pointer}
.gd-rentals-shell .gd-detail-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:18px}
.gd-rentals-shell .gd-detail-item small{display:block;margin-bottom:3px}
.gd-rentals-shell .gd-actions-stack{display:flex;flex-wrap:wrap;gap:8px}
.gd-rentals-shell .gd-empty-inline{padding:20px;text-align:center}
.gd-rentals-shell .gd-mobile-only{display:none}
@media (max-width:767.98px){
  .gd-rentals-shell .page-title h4{float:none;margin-bottom:10px}
  .gd-rentals-shell .page-title .title-button-group{float:none;display:flex;flex-wrap:wrap;gap:8px}
  .gd-rentals-shell .page-title .title-button-group .btn{margin:0;flex:1 1 160px;text-align:center}
  .gd-rentals-shell .gd-filter-grid{grid-template-columns:1fr}
  .gd-rentals-shell .w200,.gd-rentals-shell .w180,.gd-rentals-shell .w120{width:100%!important;min-width:0!important}
  .gd-rentals-shell .gd-desktop-only{display:none!important}
  .gd-rentals-shell .gd-mobile-only{display:block}
  .gd-rentals-shell #gd-calendar .fc-toolbar{display:flex;flex-direction:column;align-items:stretch}
  .gd-rentals-shell #gd-calendar .fc-toolbar-chunk{justify-content:center}
  .gd-rentals-shell #gd-calendar .fc-toolbar-title{text-align:center}
  .gd-rentals-shell .gd-resource-buffer{display:none!important}
  .gd-rentals-shell .modal-body .row>[class*='col-']{margin-bottom:10px}
}
</style>
