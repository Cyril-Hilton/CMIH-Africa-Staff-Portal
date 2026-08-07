@props(['id' => 'confirm-modal', 'title' => 'Ready to proceed?', 'message' => 'This action cannot be undone.', 'confirmText' => 'Yes, Do It', 'cancelText' => 'No, Cancel'])

<div
    x-data="{
        show: false,
        modalTitle: '{{ $title }}',
        modalMessage: '{{ $message }}',
        modalConfirmText: '{{ $confirmText }}',
        modalCancelText: '{{ $cancelText }}',
        callback: null,
        confirm() {
            if (this.callback) {
                this.callback();
            }
            this.show = false;
        },
        openModal(detail) {
            this.modalTitle = detail.title || 'Ready to proceed?';
            this.modalMessage = detail.message || 'This action cannot be undone.';
            this.modalConfirmText = detail.confirmText || 'Yes, Do It';
            this.modalCancelText = detail.cancelText || 'No, Cancel';
            this.callback = detail.callback || null;
            
            if (detail.url) {
                this.callback = () => {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = detail.url;
                    
                    const csrf = document.createElement('input');
                    csrf.type = 'hidden';
                    csrf.name = '_token';
                    csrf.value = document.querySelector('meta[name=\x22csrf-token\x22]').getAttribute('content');
                    form.appendChild(csrf);

                    const method = document.createElement('input');
                    method.type = 'hidden';
                    method.name = '_method';
                    method.value = 'DELETE';
                    form.appendChild(method);

                    document.body.appendChild(form);
                    form.submit();
                };
            }
            this.show = true;
        }
    }"
    @open-confirm-modal.window="openModal($event.detail);"
    x-show="show"
    class="fixed inset-0 z-[999] flex items-center justify-center p-4 backdrop-blur-sm bg-brand-black/80"
    style="display: none;"
>
    <div
        x-show="show"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        @click.outside="show = false"
        class="relative w-full max-w-md overflow-hidden rounded-2xl border border-brand-white/10 bg-brand-graphite p-6 shadow-2xl"
    >
        <div class="mb-6">
            <h3 class="text-xl font-semibold text-brand-white" x-text="modalTitle"></h3>
            <p class="mt-2 text-sm text-brand-white/70" x-text="modalMessage"></p>
        </div>

        <div class="flex items-center justify-end gap-3">
            <button
                @click="show = false"
                class="rounded-full border border-brand-white/20 px-4 py-2 text-xs uppercase tracking-[0.2em] text-brand-white/70 hover:bg-brand-white/5 hover:text-brand-white"
                x-text="modalCancelText"
            >
            </button>
            <button
                @click="confirm"
                class="rounded-full bg-brand-red px-4 py-2 text-xs font-semibold uppercase tracking-[0.2em] text-white hover:bg-brand-red-dark"
                x-text="modalConfirmText"
            >
            </button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        window.showCustomConfirm = function(message, callback) {
            window.dispatchEvent(new CustomEvent('open-confirm-modal', {
                detail: {
                    title: 'Confirm Action',
                    message: message,
                    confirmText: 'Confirm',
                    cancelText: 'Cancel',
                    callback: callback
                }
            }));
        };

        // Event delegation for catching click events with confirmation
        document.addEventListener('click', function(e) {
            const el = e.target.closest('[onclick]');
            if (!el) return;

            const onclickStr = el.getAttribute('onclick') || '';
            if ((onclickStr.includes('confirm(') || onclickStr.includes('confirm (')) && !el.dataset.customConfirmed) {
                e.preventDefault();
                e.stopImmediatePropagation();
                
                const match = onclickStr.match(/confirm\(['"](.*?)['"]\)/);
                const msg = (match && match[1]) ? match[1] : "Are you sure you want to proceed?";
                
                window.showCustomConfirm(msg, function() {
                    el.dataset.customConfirmed = "true";
                    
                    const originalConfirm = window.confirm;
                    window.confirm = () => true;
                    
                    // Re-trigger click on the element
                    el.click();
                    
                    window.confirm = originalConfirm;
                    delete el.dataset.customConfirmed;
                });
            }
        }, true);

        // Event delegation for catching submit events with confirmation
        document.addEventListener('submit', function(e) {
            const form = e.target;
            const onsubmitStr = form.getAttribute('onsubmit') || '';
            if ((onsubmitStr.includes('confirm(') || onsubmitStr.includes('confirm (')) && !form.dataset.customConfirmed) {
                e.preventDefault();
                e.stopImmediatePropagation();
                
                const match = onsubmitStr.match(/confirm\(['"](.*?)['"]\)/);
                const msg = (match && match[1]) ? match[1] : "Are you sure you want to proceed?";
                
                const submitter = e.submitter;
                
                window.showCustomConfirm(msg, function() {
                    form.dataset.customConfirmed = "true";
                    
                    const originalConfirm = window.confirm;
                    window.confirm = () => true;
                    
                    if (submitter) {
                        submitter.dataset.customConfirmed = "true";
                        submitter.click();
                        delete submitter.dataset.customConfirmed;
                    } else {
                        if (typeof form.requestSubmit === 'function') {
                            form.requestSubmit();
                        } else {
                            form.submit();
                        }
                    }
                    
                    window.confirm = originalConfirm;
                    delete form.dataset.customConfirmed;
                });
            }
        }, true);
    });
</script>
