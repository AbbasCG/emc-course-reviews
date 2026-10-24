@extends('layouts.admin')

@section('title', 'تصدير البيانات')

@section('breadcrumbs')
<svg class="w-3.5 h-3.5 text-slate-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
<span class="text-slate-300 font-semibold truncate">تصدير البيانات</span>
@endsection

@push('head')
<style>
    .x-card { background: var(--pg-card); border: 1px solid var(--pg-border); border-radius: 1.25rem; }
    .x-muted { color: var(--pg-text-3); }
    .x-text { color: var(--pg-text); }
    .x-text-2 { color: var(--pg-text-2); }
    .x-label { font-size: .72rem; font-weight: 800; color: var(--pg-text-2); margin-bottom: .4rem; display: block; }
    .x-input { width: 100%; background: var(--pg-input-bg); border: 1px solid var(--pg-input-border); color: var(--pg-text);
               border-radius: .7rem; padding: .55rem .8rem; font-size: .85rem; outline: none; font-family: 'Cairo', sans-serif; }
    .x-input:focus { border-color: #2691C2; box-shadow: 0 0 0 3px rgba(38,145,194,.15); }

    .x-hero { background: linear-gradient(135deg, #050c1b 0%, #0b192e 55%, #060f1e 100%); }
    html:not(.dark) .x-hero { background: linear-gradient(135deg, #f0f9ff 0%, #ddf1fd 55%, #f5fbff 100%); }
    html:not(.dark) .x-hero h1 { color: #0f172a !important; }

    .x-tab { padding: .55rem 1rem; border-radius: .8rem; font-size: .85rem; font-weight: 700; color: var(--pg-text-3); transition: all .15s; }
    .x-tab.on { background: var(--pg-card); color: var(--pg-text); box-shadow: 0 1px 3px rgba(0,0,0,.12); border: 1px solid var(--pg-border-2); }

    /* Stepper */
    .x-step { display: flex; align-items: center; gap: .5rem; font-size: .78rem; font-weight: 700; color: var(--pg-text-3); white-space: nowrap; }
    .x-step-dot { width: 1.75rem; height: 1.75rem; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center;
                  border: 1.5px solid var(--pg-border-2); font-size: .75rem; flex-shrink: 0; background: var(--pg-card); }
    .x-step.on { color: var(--pg-text); }
    .x-step.on .x-step-dot { background: #2691C2; border-color: #2691C2; color: #fff; }
    .x-step.done .x-step-dot { background: rgba(38,145,194,.12); border-color: #2691C2; color: #2691C2; }
    .x-step-line { flex: 1; height: 1.5px; min-width: 1rem; background: var(--pg-border-2); }

    /* Option cards */
    .x-opt { border: 1.5px solid var(--pg-border-2); border-radius: 1rem; padding: 1rem; cursor: pointer; transition: all .15s; background: var(--pg-card); text-align: right; width: 100%; }
    .x-opt:hover { border-color: rgba(38,145,194,.5); }
    .x-opt.on { border-color: #2691C2; background: rgba(38,145,194,.07); box-shadow: 0 0 0 3px rgba(38,145,194,.12); }
    .x-opt[disabled] { opacity: .45; cursor: not-allowed; }
    .x-ico { width: 2.5rem; height: 2.5rem; border-radius: .8rem; display: flex; align-items: center; justify-content: center; }

    .x-chip { display: inline-flex; align-items: center; gap: .3rem; padding: .35rem .75rem; border-radius: 999px; font-size: .78rem; font-weight: 700;
              border: 1px solid var(--pg-border-2); color: var(--pg-text-2); cursor: pointer; transition: all .15s; background: transparent; user-select: none; }
    .x-chip.on { background: #2691C2; border-color: #2691C2; color: #fff; }
    .x-chip-o.on { background: #EC943C; border-color: #EC943C; }

    .x-check { display: flex; align-items: center; gap: .55rem; padding: .5rem .65rem; border-radius: .65rem; cursor: pointer; font-size: .82rem; color: var(--pg-text-2); }
    .x-check:hover { background: rgba(38,145,194,.06); }
    .x-check input { accent-color: #2691C2; width: 1rem; height: 1rem; }
    .x-check.locked { opacity: .5; cursor: not-allowed; }

    .x-btn { display: inline-flex; align-items: center; justify-content: center; gap: .45rem; padding: .6rem 1.15rem; border-radius: .8rem;
             font-size: .85rem; font-weight: 800; transition: all .15s; white-space: nowrap; }
    .x-btn:disabled { opacity: .5; cursor: not-allowed; }
    .x-btn-primary { background: linear-gradient(135deg, #2691C2, #1a6f97); color: #fff; }
    .x-btn-accent { background: linear-gradient(135deg, #EC943C, #c9680d); color: #fff; }
    .x-btn-ghost { border: 1px solid var(--pg-border-2); color: var(--pg-text-2); background: transparent; }
    .x-btn-ghost:hover { background: rgba(38,145,194,.06); }
    .x-btn-sm { padding: .35rem .7rem; font-size: .75rem; border-radius: .6rem; }

    .x-stat { border: 1px solid var(--pg-border); border-radius: .9rem; padding: .85rem; text-align: center; background: rgba(38,145,194,.03); }
    .x-stat b { display: block; font-size: 1.45rem; font-weight: 900; color: var(--pg-text); line-height: 1.2; }
    .x-stat span { font-size: .72rem; color: var(--pg-text-3); font-weight: 700; }

    .x-table { width: 100%; font-size: .78rem; border-collapse: collapse; }
    .x-table th { text-align: right; padding: .55rem .7rem; color: var(--pg-text-3); font-weight: 800; border-bottom: 1px solid var(--pg-border-2); white-space: nowrap; }
    .x-table td { padding: .55rem .7rem; border-bottom: 1px solid var(--pg-border); color: var(--pg-text-2); vertical-align: top; }

    .x-badge { display: inline-flex; align-items: center; gap: .3rem; padding: .15rem .55rem; border-radius: 999px; font-size: .7rem; font-weight: 800; }
    .x-badge.ok { background: rgba(16,185,129,.12); color: #059669; }
    .x-badge.info { background: rgba(38,145,194,.12); color: #2691C2; }
    .x-badge.bad { background: rgba(239,68,68,.12); color: #dc2626; }
    .x-badge.muted { background: rgba(100,116,139,.12); color: var(--pg-text-3); }
    html.dark .x-badge.ok { color: #34d399; }
    html.dark .x-badge.bad { color: #f87171; }

    .x-alert { border-radius: .9rem; padding: .75rem 1rem; font-size: .82rem; font-weight: 600; }
    .x-alert-bad { background: rgba(239,68,68,.08); border: 1px solid rgba(239,68,68,.25); color: #dc2626; }
    .x-alert-warn { background: rgba(236,148,60,.08); border: 1px solid rgba(236,148,60,.3); color: #b45309; }
    .x-alert-info { background: rgba(38,145,194,.08); border: 1px solid rgba(38,145,194,.25); color: #1d7aa5; }
    html.dark .x-alert-bad { color: #f87171; }
    html.dark .x-alert-warn { color: #fbbf24; }
    html.dark .x-alert-info { color: #7dd3fc; }

    .x-scroll { max-height: 15rem; overflow-y: auto; scrollbar-width: thin; }
    .x-spin { animation: xspin 1s linear infinite; }
    @keyframes xspin { to { transform: rotate(360deg); } }
    .x-skel { background: linear-gradient(90deg, var(--pg-border) 25%, var(--pg-border-2) 50%, var(--pg-border) 75%); background-size: 200% 100%; animation: xsk 1.2s infinite; border-radius: .6rem; }
    @keyframes xsk { to { background-position: -200% 0; } }
</style>
@endpush

@section('hero')
<div class="x-hero">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8 sm:py-10">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
            <div>
                <p class="text-[11px] font-extrabold tracking-[0.15em] text-slate-500 mb-2">نظام التصدير المتقدم</p>
                <h1 class="text-2xl sm:text-3xl font-black text-white">تصدير البيانات والتقارير</h1>
                <p class="text-slate-500 text-sm mt-1">استخرج أي مجموعة من التقييمات بصيغ Excel وCSV وPDF مع تقارير عربية جاهزة للطباعة.</p>
            </div>
        </div>
    </div>
</div>
@endsection

@section('content')
@php
    $activeTab = request('tab') === 'history' || request()->has('history_page') ? 'history' : 'wizard';
    $wizardConfig = [
        'campaigns'     => $campaigns,
        'activityTypes' => $activityTypes,
        'instructors'   => $instructors,
        'difficulties'  => $difficulties,
        'columns'       => $columns,
        'columnGroups'  => $columnGroups,
        'templates'     => $templates,
        'canExportPii'  => $canExportPii,
        'preselect'     => $preselect,
        'urls'          => [
            'preview'   => route('admin.exports.preview'),
            'store'     => route('admin.exports.store'),
            'templates' => route('admin.exports.templates.store'),
            'history'   => route('admin.exports.index', ['tab' => 'history']),
        ],
        'csrf'          => csrf_token(),
    ];
@endphp

<script>
window.exportWizard = function (cfg) {
    const piiKeys = cfg.columns.filter(c => c.pii).map(c => c.key);
    const defaultColumns = cfg.columns.filter(c => cfg.canExportPii || !c.pii).map(c => c.key);

    return {
        cfg,
        step: 1,
        steps: ['نوع التقرير', 'النطاق والفترة', 'اختيار البيانات', 'المعاينة', 'الصيغة', 'التنزيل'],
        form: {
            report_type: 'detailed', format: 'xlsx',
            campaign_scope: cfg.preselect ? 'selected' : 'all',
            campaign_ids: cfg.preselect ? [cfg.preselect] : [],
            period: cfg.preselect ? 'all' : '30', date_from: '', date_to: '', date_basis: 'submitted',
            activity_types: [], instructors: [], difficulty: [], ratings: [],
            recommend: 'any', campaign_status: 'any',
            columns: [...defaultColumns], name: '',
        },
        campaignSearch: '',
        templates: cfg.templates,
        templateName: '',
        templateMsg: '',
        preview: null, previewLoading: false,
        errors: [], generating: false, result: null, pollTimer: null,

        get reportTypes() {
            return [
                { key: 'detailed', title: 'تصدير تفصيلي', desc: 'كل تقييم في صف مستقل مع الحقول التي تختارها.', formats: ['xlsx', 'csv', 'pdf'], color: '#2691C2' },
                { key: 'summary', title: 'تقرير ملخص', desc: 'الإجماليات والمتوسطات وتوزيع النجوم ونسبة التوصية وأداء الحملات.', formats: ['xlsx', 'csv', 'pdf'], color: '#EC943C' },
                { key: 'professional', title: 'تقرير PDF احترافي', desc: 'تقرير رسمي بهوية EMC: ملخص تنفيذي، إحصائيات، جداول وآراء نصية.', formats: ['pdf'], color: '#22334A' },
            ];
        },
        get formats() {
            return [
                { key: 'xlsx', title: 'Excel (XLSX)', desc: 'جداول منسقة من اليمين لليسار، مناسبة للتحليل.' },
                { key: 'csv', title: 'CSV', desc: 'ملف نصي UTF-8 متوافق مع أي برنامج.' },
                { key: 'pdf', title: 'PDF', desc: 'ملف جاهز للطباعة بخط عربي مضمّن.' },
            ];
        },
        get allowedFormats() { return this.reportTypes.find(t => t.key === this.form.report_type).formats; },
        get filteredCampaigns() {
            const q = this.campaignSearch.trim().toLowerCase();
            return q ? cfg.campaigns.filter(c => (c.name + ' ' + (c.instructor || '')).toLowerCase().includes(q)) : cfg.campaigns;
        },
        get needsColumns() { return this.form.report_type !== 'summary'; },
        groupColumns(g) { return cfg.columns.filter(c => c.group === g); },
        isLocked(c) { return c.pii && !cfg.canExportPii; },

        setType(key) {
            this.form.report_type = key;
            if (!this.allowedFormats.includes(this.form.format)) this.form.format = this.allowedFormats[0];
        },
        toggle(list, value) {
            const i = this.form[list].indexOf(value);
            i === -1 ? this.form[list].push(value) : this.form[list].splice(i, 1);
        },
        has(list, value) { return this.form[list].includes(value); },
        selectAllColumns() { this.form.columns = [...defaultColumns]; },
        clearColumns() { this.form.columns = []; },
        selectAllCampaigns() { this.form.campaign_ids = this.filteredCampaigns.map(c => c.id); },

        clientErrors() {
            const e = [];
            if (this.step >= 2) {
                if (this.form.campaign_scope === 'selected' && !this.form.campaign_ids.length) e.push('اختر حملة واحدة على الأقل.');
                if (this.form.period === 'custom') {
                    if (!this.form.date_from || !this.form.date_to) e.push('حدد تاريخ البداية والنهاية للفترة المخصصة.');
                    else if (this.form.date_to < this.form.date_from) e.push('تاريخ النهاية يجب أن يكون بعد تاريخ البداية.');
                }
            }
            if (this.step >= 3 && this.form.report_type === 'detailed' && !this.form.columns.length) e.push('اختر حقلاً واحداً على الأقل.');
            return e;
        },
        next() {
            this.errors = this.clientErrors();
            if (this.errors.length) return;
            if (this.step === 2 && !this.needsColumns) { this.go(4); return; }
            this.go(this.step + 1);
        },
        back() {
            if (this.step === 4 && !this.needsColumns) { this.go(2); return; }
            this.go(Math.max(1, this.step - 1));
        },
        go(n) {
            this.errors = [];
            this.step = n;
            if (n === 4) this.loadPreview();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        },
        payload() {
            const p = JSON.parse(JSON.stringify(this.form));
            if (p.report_type === 'summary') p.columns = [];
            if (p.campaign_scope === 'all') p.campaign_ids = [];
            if (p.period !== 'custom') { p.date_from = null; p.date_to = null; }
            return p;
        },
        async request(url, body) {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf },
                body: JSON.stringify(body),
            });
            let data = {};
            try { data = await res.json(); } catch (e) {}
            if (res.status === 422) throw { list: Object.values(data.errors || {}).flat() };
            if (res.status === 419) throw { list: ['انتهت الجلسة، يرجى تحديث الصفحة.'] };
            if (res.status === 429) throw { list: ['طلبات كثيرة خلال وقت قصير، انتظر قليلاً ثم أعد المحاولة.'] };
            if (!res.ok && !data.status) throw { list: [data.message || 'حدث خطأ غير متوقع (' + res.status + ').'] };
            return data;
        },
        async loadPreview() {
            this.previewLoading = true; this.preview = null;
            try { this.preview = await this.request(cfg.urls.preview, this.payload()); }
            catch (e) { this.errors = e.list || ['تعذر تحميل المعاينة.']; }
            finally { this.previewLoading = false; }
        },
        async generate() {
            this.errors = []; this.result = null; this.generating = true; this.step = 6;
            try {
                this.result = await this.request(cfg.urls.store, this.payload());
                this.afterResult();
            } catch (e) {
                this.errors = e.list || ['فشل إنشاء الملف.'];
                this.generating = false;
            }
        },
        afterResult() {
            if (!this.result) return;
            if (this.result.status === 'completed') {
                this.generating = false;
                window.location.href = this.result.download_url;
            } else if (this.result.status === 'failed') {
                this.generating = false;
                this.errors = [this.result.error || 'فشل إنشاء الملف.'];
            } else {
                clearTimeout(this.pollTimer);
                this.pollTimer = setTimeout(() => this.poll(), 3000);
            }
        },
        async poll() {
            try {
                const res = await fetch(this.result.status_url, { headers: { 'Accept': 'application/json' } });
                if (res.ok) this.result = await res.json();
            } catch (e) {}
            this.afterResult();
        },
        reset() {
            this.result = null; this.generating = false; this.errors = []; this.preview = null; this.step = 1;
        },
        applyTemplate(t) {
            const o = t.options || {};
            Object.keys(this.form).forEach(k => { if (k in o && k !== 'name' && o[k] !== null) this.form[k] = JSON.parse(JSON.stringify(o[k])); });
            if (!cfg.canExportPii) this.form.columns = this.form.columns.filter(c => !piiKeys.includes(c));
            this.form.campaign_ids = (this.form.campaign_ids || []).filter(id => cfg.campaigns.some(c => c.id === id));
            if (!this.allowedFormats.includes(this.form.format)) this.form.format = this.allowedFormats[0];
            this.templateMsg = 'تم تطبيق القالب «' + t.name + '».';
        },
        async saveTemplate() {
            if (!this.templateName.trim()) { this.templateMsg = 'اكتب اسماً للقالب.'; return; }
            try {
                const t = await this.request(cfg.urls.templates, { name: this.templateName.trim(), options: this.payload() });
                this.templates = [t, ...this.templates.filter(x => x.id !== t.id)];
                this.templateMsg = 'تم حفظ القالب «' + t.name + '».';
                this.templateName = '';
            } catch (e) { this.templateMsg = (e.list || ['تعذر حفظ القالب.'])[0]; }
        },
        async deleteTemplate(t) {
            if (!confirm('حذف القالب «' + t.name + '»؟')) return;
            const res = await fetch(cfg.urls.templates + '/' + t.id, { method: 'DELETE', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf } });
            if (res.ok) this.templates = this.templates.filter(x => x.id !== t.id);
        },
        fmtSize(b) { if (!b) return ''; return b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; },
    };
};
</script>

<main class="flex-1" x-data="{ tab: @js($activeTab) }">
<div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8">

    {{-- Tabs --}}
    <div class="inline-flex gap-1 p-1 rounded-2xl mb-6" style="background:var(--pg-border);">
        <button type="button" class="x-tab" :class="tab === 'wizard' && 'on'" @click="tab = 'wizard'">معالج التصدير</button>
        <button type="button" class="x-tab" :class="tab === 'history' && 'on'" @click="tab = 'history'">
            سجل التصدير
            @if($history->total())<span class="x-badge info mr-1">{{ $history->total() }}</span>@endif
        </button>
    </div>

    {{-- ═════════════════ WIZARD ═════════════════ --}}
    <div x-show="tab === 'wizard'" x-data="exportWizard(@js($wizardConfig))" x-cloak>

        {{-- Stepper --}}
        <div class="x-card px-4 sm:px-6 py-4 mb-5 overflow-x-auto">
            <div class="flex items-center gap-2 min-w-max">
                <template x-for="(label, i) in steps" :key="i">
                    <div class="flex items-center gap-2" :class="i < steps.length - 1 ? 'flex-1' : ''">
                        <div class="x-step" :class="{ on: step === i + 1, done: step > i + 1 }">
                            <span class="x-step-dot">
                                <template x-if="step > i + 1"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></template>
                                <template x-if="step <= i + 1"><span x-text="i + 1"></span></template>
                            </span>
                            <span x-text="label" :class="step === i + 1 ? '' : 'hidden md:inline'"></span>
                        </div>
                        <div class="x-step-line" x-show="i < steps.length - 1"></div>
                    </div>
                </template>
            </div>
        </div>

        <div class="grid lg:grid-cols-[minmax(0,1fr)_18rem] gap-5 items-start">
        <div class="x-card p-5 sm:p-6 min-w-0">

            {{-- Errors --}}
            <div class="x-alert x-alert-bad mb-4" x-show="errors.length" x-cloak>
                <template x-for="e in errors"><p x-text="e"></p></template>
            </div>

            {{-- STEP 1: report type --}}
            <section x-show="step === 1">
                <h2 class="text-lg font-black x-text mb-1">اختر نوع التقرير</h2>
                <p class="text-sm x-muted mb-5">يحدد نوع التقرير شكل البيانات والصيغ المتاحة.</p>
                <div class="grid sm:grid-cols-3 gap-3">
                    <template x-for="t in reportTypes" :key="t.key">
                        <button type="button" class="x-opt" :class="form.report_type === t.key && 'on'" @click="setType(t.key)">
                            <div class="x-ico mb-3" :style="'background:' + t.color + '1f;color:' + t.color">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </div>
                            <p class="font-extrabold x-text text-sm mb-1" x-text="t.title"></p>
                            <p class="text-xs x-muted leading-relaxed" x-text="t.desc"></p>
                            <p class="text-[11px] font-bold mt-3" style="color:#2691C2;" x-text="t.formats.map(f => f.toUpperCase()).join(' · ')"></p>
                        </button>
                    </template>
                </div>
            </section>

            {{-- STEP 2: scope & filters --}}
            <section x-show="step === 2" x-cloak>
                <h2 class="text-lg font-black x-text mb-1">النطاق والفترة الزمنية</h2>
                <p class="text-sm x-muted mb-5">حدد الحملات والفترة والفلاتر المطلوبة.</p>

                <div class="space-y-6">
                    {{-- Campaigns --}}
                    <div>
                        <span class="x-label">الحملات</span>
                        <div class="flex flex-wrap gap-2 mb-3">
                            <button type="button" class="x-chip" :class="form.campaign_scope === 'all' && 'on'" @click="form.campaign_scope = 'all'">جميع الحملات</button>
                            <button type="button" class="x-chip" :class="form.campaign_scope === 'selected' && 'on'" @click="form.campaign_scope = 'selected'">
                                حملات محددة <span x-show="form.campaign_ids.length" x-text="'(' + form.campaign_ids.length + ')'"></span>
                            </button>
                        </div>
                        <div x-show="form.campaign_scope === 'selected'" x-cloak class="rounded-xl p-3" style="border:1px solid var(--pg-border-2);">
                            <div class="flex gap-2 mb-2">
                                <input type="search" class="x-input" placeholder="ابحث باسم الدورة أو المدرب…" x-model="campaignSearch">
                                <button type="button" class="x-btn x-btn-ghost x-btn-sm" @click="selectAllCampaigns()">تحديد الكل</button>
                                <button type="button" class="x-btn x-btn-ghost x-btn-sm" @click="form.campaign_ids = []">مسح</button>
                            </div>
                            <div class="x-scroll">
                                <template x-for="c in filteredCampaigns" :key="c.id">
                                    <label class="x-check">
                                        <input type="checkbox" :value="c.id" :checked="has('campaign_ids', c.id)" @change="toggle('campaign_ids', c.id)">
                                        <span class="flex-1 min-w-0">
                                            <span class="font-bold x-text block truncate" x-text="c.name"></span>
                                            <span class="text-[11px] x-muted" x-text="[c.type, c.instructor, c.status, c.reviews + ' تقييم'].filter(Boolean).join(' · ')"></span>
                                        </span>
                                    </label>
                                </template>
                                <p class="text-xs x-muted p-2" x-show="!filteredCampaigns.length">لا توجد حملات مطابقة.</p>
                            </div>
                        </div>
                    </div>

                    {{-- Period --}}
                    <div>
                        <span class="x-label">الفترة الزمنية</span>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="p in [['all','كل الفترات'],['7','آخر 7 أيام'],['30','آخر 30 يوماً'],['90','آخر 90 يوماً'],['custom','فترة مخصصة']]">
                                <button type="button" class="x-chip" :class="form.period === p[0] && 'on'" @click="form.period = p[0]" x-text="p[1]"></button>
                            </template>
                        </div>
                        <div class="grid sm:grid-cols-2 gap-3 mt-3" x-show="form.period === 'custom'" x-cloak>
                            <div><span class="x-label">من تاريخ</span><input type="date" class="x-input" x-model="form.date_from"></div>
                            <div><span class="x-label">إلى تاريخ</span><input type="date" class="x-input" x-model="form.date_to" :min="form.date_from"></div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 mt-3" x-show="form.period !== 'all'">
                            <span class="text-xs x-muted font-bold">تطبيق الفترة على:</span>
                            <button type="button" class="x-chip" :class="form.date_basis === 'submitted' && 'on'" @click="form.date_basis = 'submitted'">تاريخ إرسال التقييم</button>
                            <button type="button" class="x-chip" :class="form.date_basis === 'course' && 'on'" @click="form.date_basis = 'course'">تاريخ الدورة</button>
                        </div>
                    </div>

                    {{-- Filters --}}
                    <div class="grid sm:grid-cols-2 gap-5">
                        <div>
                            <span class="x-label">نوع النشاط</span>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="t in cfg.activityTypes" :key="t.value">
                                    <button type="button" class="x-chip" :class="has('activity_types', t.value) && 'on'" @click="toggle('activity_types', t.value)" x-text="t.label"></button>
                                </template>
                            </div>
                        </div>
                        <div>
                            <span class="x-label">حالة الحملة</span>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="s in [['any','الكل'],['active','نشطة'],['inactive','متوقفة'],['expired','منتهية']]">
                                    <button type="button" class="x-chip" :class="form.campaign_status === s[0] && 'on'" @click="form.campaign_status = s[0]" x-text="s[1]"></button>
                                </template>
                            </div>
                        </div>
                        <div>
                            <span class="x-label">التقييم العام</span>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="r in [5,4,3,2,1]" :key="r">
                                    <button type="button" class="x-chip x-chip-o" :class="has('ratings', r) && 'on'" @click="toggle('ratings', r)">
                                        <span x-text="r"></span><span>★</span>
                                    </button>
                                </template>
                            </div>
                        </div>
                        <div>
                            <span class="x-label">التوصية</span>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="s in [['any','الكل'],['yes','يوصي'],['no','لا يوصي']]">
                                    <button type="button" class="x-chip" :class="form.recommend === s[0] && 'on'" @click="form.recommend = s[0]" x-text="s[1]"></button>
                                </template>
                            </div>
                        </div>
                        <div>
                            <span class="x-label">مستوى الصعوبة</span>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="d in cfg.difficulties" :key="d">
                                    <button type="button" class="x-chip" :class="has('difficulty', d) && 'on'" @click="toggle('difficulty', d)" x-text="d"></button>
                                </template>
                            </div>
                        </div>
                        <div>
                            <span class="x-label">المدرب</span>
                            <select class="x-input" multiple size="4" x-model="form.instructors" x-show="cfg.instructors.length">
                                <template x-for="i in cfg.instructors" :key="i"><option :value="i" x-text="i"></option></template>
                            </select>
                            <p class="text-[11px] x-muted mt-1" x-show="cfg.instructors.length">اضغط Ctrl لاختيار أكثر من مدرب. بدون اختيار = جميع المدربين.</p>
                            <p class="text-xs x-muted" x-show="!cfg.instructors.length">لا توجد أسماء مدربين مسجلة.</p>
                        </div>
                    </div>
                </div>
            </section>

            {{-- STEP 3: columns --}}
            <section x-show="step === 3" x-cloak>
                <div class="flex flex-wrap items-start justify-between gap-3 mb-5">
                    <div>
                        <h2 class="text-lg font-black x-text mb-1">اختيار البيانات</h2>
                        <p class="text-sm x-muted"><span x-text="form.columns.length"></span> حقل محدد</p>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="x-btn x-btn-ghost x-btn-sm" @click="selectAllColumns()">تحديد الكل</button>
                        <button type="button" class="x-btn x-btn-ghost x-btn-sm" @click="clearColumns()">إلغاء تحديد الكل</button>
                    </div>
                </div>
                <div class="x-alert x-alert-info mb-4" x-show="form.report_type === 'professional'">
                    في التقرير الاحترافي تتحكم الحقول النصية (ما أعجبه، اقتراحات التحسين، الملاحظات) والاسم والبريد في ظهور قسم آراء المشاركين.
                </div>
                <div class="grid md:grid-cols-3 gap-4">
                    <template x-for="(gLabel, g) in cfg.columnGroups" :key="g">
                        <div class="rounded-xl p-3" style="border:1px solid var(--pg-border-2);">
                            <p class="text-xs font-extrabold x-text mb-2" x-text="gLabel"></p>
                            <template x-for="c in groupColumns(g)" :key="c.key">
                                <label class="x-check" :class="isLocked(c) && 'locked'" :title="isLocked(c) ? 'لا تملك صلاحية تصدير البيانات الشخصية' : ''">
                                    <input type="checkbox" :disabled="isLocked(c)" :checked="has('columns', c.key)" @change="toggle('columns', c.key)">
                                    <span class="flex-1" x-text="c.label"></span>
                                    <span x-show="c.pii" class="x-badge muted" x-text="isLocked(c) ? 'مقيد' : 'شخصي'"></span>
                                </label>
                            </template>
                        </div>
                    </template>
                </div>
                <p class="text-[11px] x-muted mt-3">رقم الطالب والمستوى التعليمي غير مسجلين في نموذج التقييم الحالي، لذلك لا يمكن تصديرهما.</p>
            </section>

            {{-- STEP 4: preview --}}
            <section x-show="step === 4" x-cloak>
                <h2 class="text-lg font-black x-text mb-1">معاينة النتائج</h2>
                <p class="text-sm x-muted mb-5">تحقق من عدد السجلات قبل إنشاء الملف.</p>

                <div x-show="previewLoading" class="space-y-3">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3"><div class="x-skel h-20"></div><div class="x-skel h-20"></div><div class="x-skel h-20"></div><div class="x-skel h-20"></div></div>
                    <div class="x-skel h-40"></div>
                </div>

                <template x-if="preview && !previewLoading">
                    <div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-4">
                            <div class="x-stat"><b x-text="preview.count.toLocaleString('en')"></b><span>سجل مطابق</span></div>
                            <div class="x-stat"><b x-text="preview.stats.campaigns"></b><span>حملة</span></div>
                            <div class="x-stat"><b><span x-text="preview.stats.overall_avg"></span> <span style="color:#EC943C">★</span></b><span>متوسط التقييم</span></div>
                            <div class="x-stat"><b x-text="preview.stats.recommend_pct + '%'"></b><span>نسبة التوصية</span></div>
                        </div>
                        <template x-for="w in preview.warnings"><div class="x-alert x-alert-warn mb-3" x-text="w"></div></template>
                        <div class="x-alert x-alert-info mb-3" x-show="preview.will_queue">
                            حجم البيانات كبير، سيتم إنشاء الملف في الخلفية وستجده في سجل التصدير عند اكتماله.
                        </div>
                        <div x-show="form.report_type !== 'summary' && preview.sample.length">
                            <p class="x-label">أحدث 5 سجلات</p>
                            <div class="overflow-x-auto rounded-xl" style="border:1px solid var(--pg-border-2);">
                                <table class="x-table">
                                    <thead><tr><template x-for="h in preview.columns"><th x-text="h"></th></template></tr></thead>
                                    <tbody>
                                        <template x-for="row in preview.sample">
                                            <tr><template x-for="v in row"><td><span class="line-clamp-2" x-text="v ?? '—'"></span></td></template></tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div x-show="form.report_type === 'summary' && preview.count">
                            <p class="x-label">توزيع النجوم</p>
                            <div class="space-y-1.5">
                                <template x-for="s in [5,4,3,2,1]">
                                    <div class="flex items-center gap-3 text-xs">
                                        <span class="w-10 x-text-2 font-bold" x-text="s + ' ★'"></span>
                                        <div class="flex-1 h-2 rounded-full" style="background:var(--pg-border);">
                                            <div class="h-2 rounded-full" style="background:#EC943C;" :style="'width:' + (preview.count ? preview.stats.stars[s] / preview.count * 100 : 0) + '%'"></div>
                                        </div>
                                        <span class="w-10 text-left x-muted" x-text="preview.stats.stars[s]"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </section>

            {{-- STEP 5: format --}}
            <section x-show="step === 5" x-cloak>
                <h2 class="text-lg font-black x-text mb-1">اختيار الصيغة</h2>
                <p class="text-sm x-muted mb-5">الصيغ المتاحة حسب نوع التقرير المختار.</p>
                <div class="grid sm:grid-cols-3 gap-3 mb-5">
                    <template x-for="f in formats" :key="f.key">
                        <button type="button" class="x-opt" :class="form.format === f.key && 'on'" :disabled="!allowedFormats.includes(f.key)" @click="form.format = f.key">
                            <p class="font-extrabold x-text text-sm mb-1" x-text="f.title"></p>
                            <p class="text-xs x-muted" x-text="f.desc"></p>
                        </button>
                    </template>
                </div>
                <div class="max-w-md">
                    <span class="x-label">اسم التقرير (اختياري)</span>
                    <input type="text" class="x-input" maxlength="120" x-model="form.name" placeholder="مثال: تقييمات الربع الثالث">
                </div>
            </section>

            {{-- STEP 6: download --}}
            <section x-show="step === 6" x-cloak>
                <div class="text-center py-8">
                    <template x-if="generating && (!result || result.status === 'pending' || result.status === 'processing')">
                        <div>
                            <svg class="w-12 h-12 mx-auto mb-4 x-spin" style="color:#2691C2;" fill="none" viewBox="0 0 24 24"><circle class="opacity-20" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/><path fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z"/></svg>
                            <p class="font-extrabold x-text mb-1" x-text="result ? 'جارٍ إنشاء الملف في الخلفية…' : 'جارٍ إنشاء الملف…'"></p>
                            <p class="text-sm x-muted">يمكنك مغادرة الصفحة، وسيظهر الملف في سجل التصدير عند اكتماله.</p>
                        </div>
                    </template>
                    <template x-if="result && result.status === 'completed'">
                        <div>
                            <div class="w-14 h-14 rounded-2xl mx-auto mb-4 flex items-center justify-center" style="background:rgba(16,185,129,.12);color:#10b981;">
                                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <p class="font-extrabold x-text mb-1">الملف جاهز</p>
                            <p class="text-sm x-muted mb-5"><span x-text="result.name"></span> — <span x-text="(result.record_count ?? 0) + ' سجل'"></span> <span x-text="fmtSize(result.file_size)"></span></p>
                            <a :href="result.download_url" class="x-btn x-btn-accent">تنزيل الملف</a>
                        </div>
                    </template>
                    <template x-if="!generating && errors.length">
                        <div>
                            <p class="font-extrabold x-text mb-3">تعذر إنشاء الملف</p>
                            <button type="button" class="x-btn x-btn-primary" @click="generate()">إعادة المحاولة</button>
                        </div>
                    </template>
                    <div class="flex justify-center gap-2 mt-6">
                        <button type="button" class="x-btn x-btn-ghost" @click="reset()">تصدير جديد</button>
                        <a :href="cfg.urls.history" class="x-btn x-btn-ghost">سجل التصدير</a>
                    </div>
                </div>
            </section>

            {{-- Navigation --}}
            <div class="flex items-center justify-between gap-3 mt-6 pt-5" style="border-top:1px solid var(--pg-border);" x-show="step < 6">
                <button type="button" class="x-btn x-btn-ghost" @click="back()" :disabled="step === 1">السابق</button>
                <button type="button" class="x-btn x-btn-primary" x-show="step < 5" @click="next()" :disabled="step === 4 && (previewLoading || !preview)">التالي</button>
                <button type="button" class="x-btn x-btn-accent" x-show="step === 5" @click="generate()" :disabled="generating || (preview && preview.count === 0)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    إنشاء وتنزيل
                </button>
            </div>
        </div>

        {{-- Side panel: summary + templates --}}
        <aside class="space-y-4">
            <div class="x-card p-4">
                <p class="text-xs font-extrabold x-text mb-3">ملخص الإعدادات</p>
                <dl class="text-xs space-y-2">
                    <div class="flex justify-between gap-2"><dt class="x-muted">النوع</dt><dd class="font-bold x-text-2" x-text="reportTypes.find(t => t.key === form.report_type).title"></dd></div>
                    <div class="flex justify-between gap-2"><dt class="x-muted">الحملات</dt><dd class="font-bold x-text-2" x-text="form.campaign_scope === 'all' ? 'الكل' : form.campaign_ids.length + ' محددة'"></dd></div>
                    <div class="flex justify-between gap-2"><dt class="x-muted">الفترة</dt><dd class="font-bold x-text-2" x-text="({all:'كل الفترات','7':'7 أيام','30':'30 يوماً','90':'90 يوماً',custom:(form.date_from || '…') + ' → ' + (form.date_to || '…')})[form.period]"></dd></div>
                    <div class="flex justify-between gap-2" x-show="needsColumns"><dt class="x-muted">الحقول</dt><dd class="font-bold x-text-2" x-text="form.columns.length"></dd></div>
                    <div class="flex justify-between gap-2"><dt class="x-muted">الصيغة</dt><dd class="font-bold x-text-2" x-text="form.format.toUpperCase()"></dd></div>
                </dl>
            </div>

            <div class="x-card p-4">
                <p class="text-xs font-extrabold x-text mb-3">قوالب التصدير</p>
                <div class="space-y-1.5 mb-3" x-show="templates.length">
                    <template x-for="t in templates" :key="t.id">
                        <div class="flex items-center gap-2 rounded-lg px-2 py-1.5" style="border:1px solid var(--pg-border);">
                            <button type="button" class="flex-1 text-right text-xs font-bold x-text-2 truncate" @click="applyTemplate(t)" x-text="t.name" title="استخدام القالب"></button>
                            <button type="button" class="text-[11px] font-bold" style="color:#2691C2;" @click="applyTemplate(t)">استخدام</button>
                            <button type="button" class="text-[11px] font-bold text-red-500" @click="deleteTemplate(t)" title="حذف">✕</button>
                        </div>
                    </template>
                </div>
                <p class="text-xs x-muted mb-3" x-show="!templates.length">لا توجد قوالب محفوظة بعد.</p>
                <div class="flex gap-2">
                    <input type="text" class="x-input" maxlength="120" placeholder="اسم القالب" x-model="templateName" @keydown.enter.prevent="saveTemplate()">
                    <button type="button" class="x-btn x-btn-ghost x-btn-sm" @click="saveTemplate()">حفظ</button>
                </div>
                <p class="text-[11px] mt-2 x-muted" x-show="templateMsg" x-text="templateMsg"></p>
            </div>
        </aside>
        </div>
    </div>

    {{-- ═════════════════ HISTORY ═════════════════ --}}
    <div x-show="tab === 'history'" x-cloak
         @if($history->getCollection()->contains(fn ($l) => !$l->isFinished())) x-init="setTimeout(() => { if (tab === 'history') location.reload(); }, 8000)" @endif>
        <div class="x-card overflow-hidden">
            <div class="px-5 py-4 flex flex-wrap items-center justify-between gap-2" style="border-bottom:1px solid var(--pg-border);">
                <div>
                    <h2 class="font-black x-text">سجل التصدير</h2>
                    <p class="text-xs x-muted">تُحفظ الملفات لمدة {{ $retentionDays }} أيام، ويمكن إعادة إنشاء التقارير المنتهية في أي وقت.</p>
                </div>
            </div>
            @if($history->isEmpty())
            <div class="px-5 py-14 text-center">
                <p class="font-bold x-text-2 mb-1">لا توجد عمليات تصدير بعد</p>
                <p class="text-sm x-muted">ستظهر هنا التقارير التي تنشئها من معالج التصدير.</p>
            </div>
            @else
            <div class="overflow-x-auto">
                <table class="x-table">
                    <thead>
                        <tr>
                            <th>التقرير</th>
                            <th>الصيغة</th>
                            <th>الفترة</th>
                            <th>السجلات</th>
                            <th>تاريخ التصدير</th>
                            <th>المستخدم</th>
                            <th>الحالة</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($history as $log)
                        @php $badge = $log->statusBadge(); @endphp
                        <tr>
                            <td class="min-w-[12rem]">
                                <p class="font-bold x-text">{{ $log->name }}</p>
                                <p class="text-[11px] x-muted">{{ $log->reportTypeLabel() }}</p>
                                @if($log->status === 'failed' && $log->error)
                                <p class="text-[11px] text-red-500 mt-1">{{ \Illuminate\Support\Str::limit($log->error, 120) }}</p>
                                @endif
                            </td>
                            <td><span class="x-badge muted">{{ $log->formatLabel() }}</span></td>
                            <td class="whitespace-nowrap" dir="ltr" style="text-align:right;">{{ $log->periodLabel() }}</td>
                            <td>{{ $log->record_count !== null ? number_format($log->record_count) : '—' }}</td>
                            <td class="whitespace-nowrap" dir="ltr" style="text-align:right;">{{ $log->created_at->format('Y-m-d H:i') }}</td>
                            <td class="whitespace-nowrap">{{ $log->user?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap">
                                <span class="x-badge {{ $badge['tone'] }}">
                                    @if(!$log->isFinished())<svg class="w-3 h-3 x-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>@endif
                                    {{ $badge['label'] }}
                                </span>
                                @if($log->status === 'completed' && $log->expires_at)
                                <p class="text-[10px] x-muted mt-1">متاح حتى {{ $log->expires_at->format('Y-m-d') }}</p>
                                @endif
                            </td>
                            <td class="whitespace-nowrap">
                                <div class="flex gap-1.5 justify-end">
                                    @if($log->isDownloadable())
                                    <a href="{{ route('admin.exports.download', $log) }}" class="x-btn x-btn-accent x-btn-sm">تنزيل</a>
                                    @endif
                                    @if($log->isFinished())
                                    <form method="POST" action="{{ route('admin.exports.regenerate', $log) }}"
                                          x-data="{ busy: false }" @submit="busy = true">
                                        @csrf
                                        <button type="submit" class="x-btn x-btn-ghost x-btn-sm" :disabled="busy">
                                            <span x-text="busy ? 'جارٍ…' : 'إعادة إنشاء'">إعادة إنشاء</span>
                                        </button>
                                    </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($history->hasPages())
            <div class="px-5 py-4" style="border-top:1px solid var(--pg-border);">{{ $history->appends(['tab' => 'history'])->links() }}</div>
            @endif
            @endif
        </div>
    </div>

</div>
</main>
@endsection
