<style>
    .report-scroll { overflow-x: auto; }
    .report-table { width: 100%; border-collapse: collapse; font-size: 0.78rem; color: inherit; }
    .report-table th, .report-table td { padding: 0.4rem 0.35rem; text-align: left; white-space: nowrap; border-bottom: 1px solid rgba(128, 128, 128, 0.3); }
    .report-table th.num, .report-table td.num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .report-table thead th { font-weight: 600; border-bottom-width: 2px; }
    .report-table tfoot td { font-weight: 700; border-top: 2px solid rgba(128, 128, 128, 0.6); border-bottom: 0; }
    .report-table td.empty { text-align: center; padding: 1.5rem; opacity: 0.7; }
    .report-subtitle { margin: 0 0 0.5rem; font-weight: 600; }
    .report-table tbody tr:nth-child(even) { background: rgba(128, 128, 128, 0.06); }
    .report-table tfoot td { background: rgba(128, 128, 128, 0.12); }
    .report-filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); gap: 1rem; align-items: end; }
    .report-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr)); gap: 1rem; }
    .report-stat { border: 1px solid rgba(128, 128, 128, 0.3); border-radius: 0.75rem; padding: 0.85rem 1rem; }
    .report-stat small { display: block; font-size: 0.75rem; opacity: 0.7; }
    .report-stat strong { display: block; margin-top: 0.15rem; font-size: 1.35rem; font-variant-numeric: tabular-nums; }
    .report-subtotals { display: grid; grid-template-columns: repeat(auto-fit, minmax(22rem, 1fr)); gap: 1.5rem; margin-top: 1.5rem; }
</style>
