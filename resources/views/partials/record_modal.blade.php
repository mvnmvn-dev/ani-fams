{{-- Large detail shell for contracts / documents --}}
<div id="recordModal" class="modal-overlay record-modal-overlay hidden" hidden role="dialog" aria-modal="true" aria-labelledby="recordModalTitle">
    <div class="record-modal-panel bg-white rounded-2xl shadow-xl border border-ink/10 overflow-hidden animate-fade-in-up" onclick="event.stopPropagation()">
        <div class="record-modal-header px-5 py-3.5 border-b border-ink/10 flex items-center justify-between gap-3 bg-gray-50/60">
            <h3 id="recordModalTitle" class="font-sans text-base font-bold text-ink truncate">Details</h3>
            <button type="button" onclick="closeRecordModal()" class="text-ink/40 hover:text-ink/70 transition shrink-0 p-1 rounded-lg hover:bg-ink/5" aria-label="Close">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div id="recordModalBody" class="record-modal-body">
            <div class="p-10 text-center text-ink/40 text-sm">Loading…</div>
        </div>
    </div>
</div>
