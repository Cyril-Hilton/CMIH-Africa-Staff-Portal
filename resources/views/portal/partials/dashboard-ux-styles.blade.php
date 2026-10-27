<style>
.dashboard-section-summary {display:flex;align-items:center;justify-content:space-between;gap:1rem;cursor:pointer;list-style:none;font-size:1.1rem;font-weight:600;padding:1rem 0}
.dashboard-section-summary::-webkit-details-marker{display:none}.dashboard-section-summary::after{content:'+';font-size:1.4rem}.dashboard-secondary[open]>.dashboard-section-summary::after{content:'−'}
#weekly-consolidated-live-region{margin-bottom:2rem}
[data-dashboard-secondary]{display:flow-root}
[data-dashboard-secondary]>.mega-table-disclosure{margin-top:0;margin-bottom:2rem}
.dashboard-secondary{margin:1.5rem 0;border:1px solid rgba(255,255,255,.16);border-radius:1rem;padding:0 1.25rem}
.dashboard-week-toolbar{position:static;z-index:auto;background:rgba(20,23,28,.96)}
#weekly-consolidated-live-region .text-\[10px\]{font-size:.75rem;letter-spacing:.04em}#weekly-consolidated-live-region .text-brand-ash{color:#b9bdc5}
.dashboard-copy-dialog{width:min(48rem,calc(100vw - 2rem));max-height:85vh;overflow:auto;padding:1.5rem;border:1px solid #8993a0;border-radius:1rem;background:#171b22;color:#f5f7fa}
.dashboard-copy-dialog::backdrop{background:#000b}.dashboard-copy-dialog h2{font-size:1.5rem;font-weight:600}.dashboard-copy-dialog p{font-size:.875rem;line-height:1.6;margin:.8rem 0;color:#c3c9d2}
.copy-week-dates{display:grid;grid-template-columns:1fr 1fr;gap:1rem}.copy-week-dates input{display:block;width:100%;padding:.7rem;background:#262d38;border:1px solid #727c8b;border-radius:.5rem;margin-top:.4rem}
.copy-week-item{display:flex;gap:.8rem;border-bottom:1px solid #596170;padding:1rem 0;font-size:.875rem}.copy-week-item input{flex-shrink:0;margin-top:.2rem}.copy-week-item span{min-width:0;overflow-wrap:anywhere}
.dashboard-copy-dialog button{padding:.7rem 1rem;border:1px solid #9099a7;border-radius:.5rem}.dashboard-copy-dialog button:disabled{opacity:.45}.copy-week-actions{display:flex;justify-content:flex-end;flex-wrap:wrap;gap:1rem;margin-top:1rem}
.dashboard-report-actions{display:flex;flex-wrap:wrap;gap:.65rem}.dashboard-report-actions button,.dashboard-report-actions a{min-height:2.75rem}
.dashboard-presentation #portal-sidebar,.dashboard-presentation header,.dashboard-presentation [data-dashboard-secondary],.dashboard-presentation [data-weekly-entry-actions],.dashboard-presentation .dashboard-report-settings,.dashboard-presentation #weekly-column-manager,.dashboard-presentation #weekly-consolidated-form,.dashboard-presentation [data-copy-week],.dashboard-presentation [data-report-settings],.dashboard-presentation [data-weekly-add],.dashboard-presentation #weekly-consolidated-live-region form,.dashboard-presentation [data-dashboard-today]{display:none!important}
.dashboard-presentation #weekly-consolidated-live-region{margin-top:0}.dashboard-presentation main{padding:1rem!important}
.dashboard-meeting-popover{width:min(24rem,calc(100vw - 4rem));max-height:70vh;overflow:auto}
@media(max-width:480px){.copy-week-dates{grid-template-columns:1fr}.dashboard-copy-dialog{padding:1rem}.dashboard-report-actions{width:100%}.dashboard-report-actions>*{flex:1 1 auto}}

@media print{[data-dashboard-secondary],header,#portal-sidebar,[data-weekly-entry-actions],.dashboard-report-settings,[data-copy-week],[data-weekly-add],[data-dashboard-present]{display:none!important}}
</style>
