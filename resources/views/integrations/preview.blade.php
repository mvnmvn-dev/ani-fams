@extends('layouts.app')

@section('content')
<div class="animate-fade-in-up space-y-8">
    <?php
    render_page_header([
        'title' => 'Integration Preview',
        'subtitle' => 'Integration coverage across HR1–HR4 and the planned SCM, Fleet, Finance, commerce, legal, and analytics systems.',
        'badge' => '<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-accent-50 text-accent-700 text-[11px] font-semibold border border-accent-200"><span class="w-1.5 h-1.5 rounded-full bg-accent-500"></span>Preview mode</span>',
    ]);
    ?>

    <div class="rounded-2xl border border-accent-200 bg-accent-50/60 p-5 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.16em] text-accent-700">Safe visualization layer</p>
                <h2 class="mt-1 text-lg font-bold text-ink">These are placeholders, not live records</h2>
                <p class="mt-1 max-w-3xl text-sm text-ink/65">The sample payloads below show the containers FAMS will use when the other systems connect. They are deliberately kept out of Visitors, Documents, and Records so demo data cannot be mistaken for real operational data.</p>
            </div>
            <span class="shrink-0 rounded-lg border border-accent-200 bg-white px-3 py-2 text-xs font-semibold text-accent-700">No database writes</span>
        </div>
    </div>

    <section>
        <div class="mb-4 flex items-end justify-between gap-4">
            <div><h2 class="section-title">Connection containers</h2><p class="type-meta mt-1">Each card identifies a planned integration partner and distinguishes implemented FAMS endpoints from connections still awaiting a contract.</p></div>
            <span class="hidden text-xs font-semibold text-ink/45 sm:block">{{ count($connections) }} systems and cross-system links mapped</span>
        </div>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <?php foreach ($connections as $connection): ?>
                <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-[0_4px_18px_rgba(20,46,76,0.05)]">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-sm font-bold text-brand-700"><?= htmlspecialchars($connection['code']) ?></div>
                        <span class="rounded-full border border-ink/10 bg-ink/5 px-2 py-1 text-[10px] font-bold text-ink/55">Integration scope</span>
                    </div>
                    <h3 class="mt-4 text-sm font-bold text-ink"><?= htmlspecialchars($connection['name']) ?></h3>
                    <p class="mt-1 text-xs leading-5 text-ink/55"><?= htmlspecialchars($connection['description']) ?></p>
                    <div class="mt-4 border-t border-ink/8 pt-3"><p class="text-[10px] font-bold uppercase tracking-wider text-ink/40">Status</p><p class="mt-1 text-xs font-semibold text-accent-700"><?= htmlspecialchars($connection['status']) ?></p></div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section>
        <div class="mb-4"><h2 class="section-title">Incoming data containers</h2><p class="type-meta mt-1">These cards show the shape of the records FAMS will receive and where each payload goes.</p></div>
        <div class="grid gap-5 xl:grid-cols-3">
            <?php foreach ($inboxes as $inbox): ?>
                <article class="overflow-hidden rounded-2xl border border-ink/10 bg-white shadow-[0_4px_18px_rgba(20,46,76,0.05)]">
                    <div class="border-b border-ink/8 bg-gray-50/70 p-5">
                        <div class="flex items-center justify-between gap-3"><span class="rounded-lg bg-brand-600 px-2.5 py-1 text-[10px] font-bold text-white"><?= htmlspecialchars($inbox['code']) ?></span><span class="rounded-full border border-brand-200 bg-brand-50 px-2 py-1 text-[10px] font-semibold text-brand-700"><?= htmlspecialchars($inbox['badge']) ?></span></div>
                        <h3 class="mt-4 text-base font-bold text-ink"><?= htmlspecialchars($inbox['title']) ?></h3>
                        <p class="mt-1 text-xs leading-5 text-ink/55"><?= htmlspecialchars($inbox['subtitle']) ?></p>
                    </div>
                    <div class="space-y-4 p-5">
                        <div class="rounded-lg bg-ink/[0.03] p-3"><p class="text-[10px] font-bold uppercase tracking-wider text-ink/40">Integration endpoint</p><code class="mt-1 block break-all text-[11px] font-semibold text-brand-700"><?= htmlspecialchars($inbox['endpoint']) ?></code></div>
                        <div class="space-y-2">
                            <?php foreach ($inbox['fields'] as $field): ?>
                                <div class="flex items-start justify-between gap-3 border-b border-ink/5 pb-2 last:border-0 last:pb-0"><span class="text-xs text-ink/50"><?= htmlspecialchars($field['label']) ?></span><span class="max-w-[58%] text-right text-xs font-semibold text-ink"><?= htmlspecialchars($field['value']) ?></span></div>
                            <?php endforeach; ?>
                        </div>
                        <details class="rounded-lg border border-ink/8 bg-gray-50/60 p-3"><summary class="cursor-pointer text-xs font-bold text-ink/70">View sample payload</summary><pre class="mt-3 overflow-x-auto whitespace-pre-wrap text-[10px] leading-5 text-ink/65"><?= htmlspecialchars($inbox['json']) ?></pre></details>
                        <div class="flex items-center gap-2 text-[11px] font-semibold text-accent-700"><span class="h-2 w-2 rounded-full bg-accent-500"></span>Waiting for external system connection</div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="rounded-2xl border border-ink/10 bg-white p-5 shadow-[0_4px_18px_rgba(20,46,76,0.05)] sm:p-6">
        <div class="mb-4"><h2 class="section-title">Event and callback containers</h2><p class="type-meta mt-1">Outbound and inbound events that will appear in the integration log after connection.</p></div>
        <div class="overflow-x-auto"><table class="module-table min-w-[640px]"><thead><tr><th>Source</th><th>Event</th><th>Target</th><th>Current state</th></tr></thead><tbody>
            <?php foreach ($eventRows as $row): ?><tr><td><span class="rounded-md bg-brand-50 px-2 py-1 text-[10px] font-bold text-brand-700"><?= htmlspecialchars($row['source']) ?></span></td><td class="font-semibold text-ink"><?= htmlspecialchars($row['event']) ?></td><td class="text-ink/60"><?= htmlspecialchars($row['target']) ?></td><td><span class="text-xs font-semibold text-accent-700"><?= htmlspecialchars($row['state']) ?></span></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </section>

    <div class="flex flex-col gap-3 rounded-2xl border border-brand-200 bg-brand-50/60 p-5 text-sm text-ink/70 sm:flex-row sm:items-center sm:justify-between">
        <p><strong class="text-ink">When the real systems are ready:</strong> create a scoped integration client, replace the placeholder payload with the other group’s agreed contract, and send the data to the displayed endpoint.</p>
        <span class="shrink-0 rounded-lg border border-brand-200 bg-white px-3 py-2 text-xs font-bold text-brand-700">Use the API contract when connecting</span>
    </div>
</div>
@endsection
