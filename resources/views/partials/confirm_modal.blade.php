{{-- Shared confirmation dialog (replaces native window.confirm) --}}
<div id="confirmModal" class="modal-overlay confirm-modal-overlay hidden" hidden role="dialog" aria-modal="true" aria-labelledby="confirmModalTitle">
    <div class="confirm-modal-panel bg-white rounded-2xl shadow-xl max-w-md w-full border border-ink/10 overflow-hidden animate-fade-in-up" onclick="event.stopPropagation()">
        <div class="px-6 pt-6 pb-2">
            <div class="flex items-start gap-4">
                <span class="confirm-modal-icon flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-accent-50 text-accent-700 border border-accent-200" aria-hidden="true">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                </span>
                <div class="min-w-0 pt-0.5">
                    <h3 id="confirmModalTitle" class="font-sans text-lg font-bold text-ink">Please confirm</h3>
                    <p id="confirmModalMessage" class="mt-1.5 text-sm text-ink/65 leading-relaxed"></p>
                </div>
            </div>
        </div>
        <div class="px-6 py-5 flex justify-end gap-3">
            <button type="button" id="confirmModalCancel" class="px-4 py-2.5 rounded-xl border border-ink/10 bg-white hover:bg-gray-50 text-ink/70 font-semibold text-sm transition">
                Cancel
            </button>
            <button type="button" id="confirmModalOk" class="px-5 py-2.5 rounded-xl bg-ink hover:bg-ink/90 text-sand font-semibold text-sm transition shadow-sm">
                Confirm
            </button>
        </div>
    </div>
</div>
