{{-- Shared mPDF stylesheet for export reports (font: Cairo, embedded). --}}
body { font-family: cairo; direction: rtl; text-align: right; color: #1e293b; font-size: 9.5pt; line-height: 1.55; }

.hdr { background-color: #22334A; padding: 16px 26px 14px; }
.hdr-table { width: 100%; border-collapse: collapse; }
.hdr-eyebrow { font-size: 8pt; color: #94a3b8; }
.hdr-title { font-size: 17pt; font-weight: bold; color: #ffffff; }
.hdr-sub { font-size: 8.5pt; color: #cbd5e1; }
.hdr-brand { text-align: left; vertical-align: middle; width: 30%; }
.hdr-brand-name { font-size: 22pt; font-weight: bold; color: #EC943C; }
.hdr-brand-sub { font-size: 7pt; color: #94a3b8; }
.accent { height: 4px; background-color: #EC943C; }

.wrap { padding: 16px 26px 10px; }

.meta { width: 100%; border-collapse: collapse; margin-bottom: 14px; background-color: #f8fafc; border: 1px solid #e2e8f0; }
.meta td { padding: 4px 10px; font-size: 8pt; color: #64748b; border-bottom: 1px solid #eef2f6; vertical-align: top; }
.meta td.k { width: 22%; font-weight: bold; color: #334155; }

.sec { font-size: 10pt; font-weight: bold; color: #ffffff; background-color: #22334A; padding: 5px 12px; margin: 14px 0 10px; border-right: 4px solid #EC943C; }

.kpis { width: 100%; border-collapse: separate; border-spacing: 5px 0; margin-bottom: 8px; }
.kpis td { text-align: center; background-color: #f8fafc; border: 1px solid #e2e8f0; border-top: 3px solid #2691C2; padding: 9px 4px 7px; }
.kpis td.o { border-top-color: #EC943C; }
.kpis td.g { border-top-color: #16a34a; }
.kpi-num { font-size: 18pt; font-weight: bold; color: #22334A; }
.kpi-cap { font-size: 7.5pt; color: #64748b; }

.bars { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
.bars td { padding: 3px 4px; vertical-align: middle; font-size: 9pt; }
.bars td.l { width: 24%; font-weight: bold; color: #334155; }
.bars td.s { width: 16%; }
.bars td.v { width: 12%; text-align: left; font-weight: bold; color: #22334A; }
.bar-bg { background-color: #e8ecf0; height: 8px; }
.bar-fill { height: 8px; background-color: #2691C2; }

.grid { width: 100%; border-collapse: collapse; font-size: 8.5pt; margin-bottom: 8px; }
.grid th { background-color: #22334A; color: #ffffff; padding: 6px 7px; text-align: right; font-weight: bold; }
.grid td { padding: 5px 7px; border-bottom: 1px solid #edf1f5; vertical-align: top; color: #334155; }
.grid tr.alt td { background-color: #f8fafc; }
.grid td.n { text-align: center; }

.data { width: 100%; border-collapse: collapse; font-size: 7.5pt; }
.data th { background-color: #22334A; color: #ffffff; padding: 5px 5px; text-align: right; font-weight: bold; }
.data td { padding: 4px 5px; border-bottom: 1px solid #edf1f5; vertical-align: top; color: #334155; }
.data tr.alt td { background-color: #f8fafc; }

.exec { background-color: #f0f7fb; border-right: 4px solid #2691C2; padding: 10px 14px; margin-bottom: 6px; font-size: 9.5pt; color: #1e293b; }
.exec p { margin: 0 0 5px 0; }

.comment { border: 1px solid #e2e8f0; border-right: 3px solid #EC943C; padding: 7px 10px; margin-bottom: 6px; }
.comment-head { font-size: 8pt; color: #64748b; margin-bottom: 3px; }
.comment-k { font-size: 8pt; font-weight: bold; color: #22334A; }
.comment-v { font-size: 9pt; color: #1e293b; margin-bottom: 3px; }

.yes { color: #15803d; font-weight: bold; }
.no { color: #dc2626; font-weight: bold; }
.muted { color: #94a3b8; font-size: 8pt; }
.empty { text-align: center; padding: 30px; color: #94a3b8; font-size: 10pt; }
.note { font-size: 8pt; color: #64748b; margin-top: 4px; }

.ftr { font-size: 7.5pt; color: #94a3b8; border-top: 1px solid #e2e8f0; padding: 4px 26px 0; }
