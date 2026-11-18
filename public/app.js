(() => {
    'use strict';

    const dialog = document.getElementById('confirm-dialog');
    const optionsDialog = document.getElementById('ticket-options-dialog');
    const ticket = document.querySelector('[data-ticket-poll]');
    const board = document.querySelector('[data-staff-poll]');
    const cancellations = document.querySelector('[data-staff-cancellations]');
    const indicator = document.getElementById('refresh-state');
    const formState = document.getElementById('form-state');
    const terminal = ticket?.dataset.terminal === 'true';
    const registration = document.querySelector('[data-queue-registration]');
    let pendingForm = null;
    let pendingSubmitter = null;
    let returnFocus = null;
    let busyForm = null;
    let busyControls = [];
    let recoveryTimer = null;
    let fetching = false;
    let optionsTrigger = null;

    function setIndicator(message, stale = false) {
        if (!indicator) return;
        indicator.textContent = message;
        indicator.classList.toggle('stale', stale);
    }

    function syncStepper(stepper) {
        const input = stepper.querySelector('input');
        const value = input.valueAsNumber;
        stepper.querySelectorAll('[data-step]').forEach(button => {
            button.disabled = Number.isFinite(value) && (Number(button.dataset.step) < 0
                ? value <= Number(input.min)
                : value >= Number(input.max));
        });
    }

    function restoreSubmission() {
        clearTimeout(recoveryTimer);
        busyControls.forEach(({ button, disabled, text }) => {
            button.disabled = disabled;
            if (text !== null) button.textContent = text;
        });
        busyForm?.removeAttribute('aria-busy');
        busyForm = null;
        busyControls = [];
        document.querySelectorAll('.stepper').forEach(syncStepper);
    }

    document.querySelectorAll('.stepper').forEach(syncStepper);
    document.querySelector('[data-error-summary]')?.focus();

    function syncQueueSelection() {
        if (!registration) return;
        const selected = registration.querySelector('[name="queue_type"]:checked')?.value || '';
        registration.querySelectorAll('[data-queue-snapshot]').forEach(panel => {
            panel.hidden = panel.dataset.queueSnapshot !== selected;
        });
        const submit = registration.querySelector('[data-queue-submit]');
        submit.disabled = !selected;
        submit.textContent = selected ? 'Ambil nomor antrean' : 'Pilih layanan terlebih dahulu';
    }

    syncQueueSelection();
    registration?.addEventListener('change', syncQueueSelection);

    document.addEventListener('input', event => {
        if (event.target.closest('.stepper')) syncStepper(event.target.closest('.stepper'));
    });

    document.addEventListener('click', event => {
        const openOptions = event.target.closest('[data-open-options]');
        if (openOptions && optionsDialog?.showModal) {
            event.preventDefault();
            optionsTrigger = openOptions;
            optionsDialog.showModal();
        }
        if (event.target.closest('[data-close-options]') && optionsDialog?.open) {
            event.preventDefault();
            optionsDialog.close();
        }
        const step = event.target.closest('[data-step]');
        if (step) {
            const input = step.parentElement.querySelector('input');
            const value = Number.parseInt(input.value, 10) || Number(input.min);
            input.value = Math.min(Number(input.max), Math.max(Number(input.min), value + Number(step.dataset.step)));
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }

        const reveal = event.target.closest('[data-password-toggle]');
        if (reveal) {
            const input = document.getElementById(reveal.dataset.passwordToggle);
            const visible = input.type === 'password';
            input.type = visible ? 'text' : 'password';
            reveal.textContent = visible ? 'Sembunyi' : 'Lihat';
            reveal.setAttribute('aria-pressed', String(visible));
            reveal.setAttribute('aria-label', visible ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
        }

        if (event.target.closest('[data-close-dialog]')) dialog.close();
        if (event.target.closest('#confirm-submit') && pendingForm) {
            const form = pendingForm;
            const submitter = pendingSubmitter;
            form.dataset.confirmed = 'true';
            dialog.close();
            if (submitter) form.requestSubmit(submitter);
            else form.requestSubmit();
        }
        if (event.target.closest('[data-refresh]')) poll(true);
    });

    dialog?.addEventListener('close', () => {
        pendingForm = null;
        pendingSubmitter = null;
        if (returnFocus?.isConnected && !returnFocus.disabled) returnFocus.focus();
        returnFocus = null;
    });

    optionsDialog?.addEventListener('close', () => {
        if (optionsTrigger?.isConnected) optionsTrigger.focus();
        optionsTrigger = null;
    });

    document.addEventListener('submit', event => {
        const form = event.target;
        if (form.method.toLowerCase() !== 'post') return;
        if (busyForm) {
            event.preventDefault();
            return;
        }
        if (form.dataset.confirm && !form.dataset.confirmed) {
            event.preventDefault();
            pendingForm = form;
            pendingSubmitter = event.submitter;
            returnFocus = event.submitter || document.activeElement;
            document.getElementById('confirm-title').textContent = form.dataset.confirm;
            document.getElementById('confirm-copy').textContent = form.dataset.confirmCopy || 'Nomor akan keluar dari antrean. Untuk kembali, pelanggan perlu mengambil nomor baru.';
            dialog.querySelector('.dialog-eyebrow').textContent = form.dataset.confirmKind === 'change' ? 'KONFIRMASI PERUBAHAN' : 'KONFIRMASI PEMBATALAN';
            const confirmButton = document.getElementById('confirm-submit');
            confirmButton.textContent = form.dataset.confirmLabel || 'Ya, batalkan';
            confirmButton.classList.toggle('danger', form.dataset.confirmKind !== 'change');
            const reason = form.querySelector('[name="reason"]');
            const reasonCopy = document.getElementById('confirm-reason');
            reasonCopy.hidden = !reason;
            reasonCopy.textContent = reason ? 'Alasan: ' + reason.selectedOptions[0].textContent : '';
            dialog.querySelector('[data-close-dialog]').textContent = form.classList.contains('cancel-ticket') ? 'Tetap menunggu' : 'Kembali';
            dialog.showModal();
            return;
        }

        delete form.dataset.confirmed;
        busyForm = form;
        form.setAttribute('aria-busy', 'true');
        busyControls = Array.from(form.querySelectorAll('button')).map(button => {
            const text = button === event.submitter && button.dataset.loadingLabel ? button.textContent : null;
            const state = { button, disabled: button.disabled, text };
            if (text !== null) button.textContent = button.dataset.loadingLabel;
            button.disabled = true;
            return state;
        });
        if (formState) {
            formState.hidden = false;
            formState.textContent = 'Permintaan sedang diproses…';
        }
        recoveryTimer = setTimeout(() => {
            restoreSubmission();
            if (formState) formState.textContent = 'Respons belum diterima. Periksa koneksi, lalu coba lagi jika halaman belum berpindah.';
        }, 15000);
    });

    function filtersDirty() {
        const filters = board?.querySelector('.filters');
        if (!filters) return false;
        return Array.from(filters.elements).some(input => {
            if (input.tagName === 'INPUT') return input.value !== input.defaultValue;
            if (input.tagName === 'SELECT') {
                const initial = Array.from(input.options).find(option => option.defaultSelected) || input.options[0];
                return input.value !== initial.value;
            }
            return false;
        });
    }

    async function poll(manual = false) {
        if ((!ticket && !board) || terminal || busyForm || fetching || document.hidden || dialog?.open || optionsDialog?.open) return;
        if (!navigator.onLine) {
            setIndicator('Anda sedang offline. Status akan diperbarui setelah tersambung kembali.', true);
            return;
        }
        fetching = true;
        const refreshButtons = document.querySelectorAll('[data-refresh]');
        refreshButtons.forEach(button => { button.disabled = true; });
        if (manual) setIndicator('Memperbarui status…');
        try {
            const response = await fetch(ticket ? ticket.dataset.ticketPoll : board.dataset.staffPoll, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
                signal: AbortSignal.timeout(8000)
            });
            if (response.redirected || response.status === 401 || response.status === 419) {
                setIndicator('Sesi berakhir. Muat ulang halaman untuk masuk kembali.', true);
                return;
            }
            if (!response.ok) throw new Error('Refresh failed');
            const data = await response.json();
            if (data.queue_type !== (ticket || board).dataset.queueType) throw new Error('Queue changed');
            // A pending response must not replace controls opened or edited since fetch began.
            if (busyForm || dialog?.open || optionsDialog?.open) return;
            if (ticket) {
                if (data.status !== ticket.dataset.status) {
                    location.reload();
                    return;
                }
                const write = (selector, value) => {
                    const node = ticket.querySelector(selector);
                    if (node) node.textContent = value;
                };
                write('[data-ahead]', data.snapshot.ahead);
                write('[data-estimate]', data.snapshot.estimate_minutes === null ? 'Belum tersedia' : '± ' + data.snapshot.estimate_minutes + ' mnt');
                write('[data-latest-called]', data.snapshot.latest_called || '—');
                write('#near-title', data.snapshot.near_turn ? 'Giliran hampir tiba' : 'Pantau halaman ini');
                write('#near-copy', data.snapshot.ahead === 0 ? 'Giliran berikutnya; tunggu panggilan staf.' : 'Datang saat giliranmu hampir tiba. Estimasi dapat berubah mengikuti layanan.');
            } else {
                const latestCancellation = String(data.latest_cancellation_id ?? '');
                if (cancellations && latestCancellation !== cancellations.dataset.latestId) {
                    cancellations.innerHTML = data.cancellations_html;
                    cancellations.dataset.latestId = latestCancellation;
                }
                if (filtersDirty() || board.contains(document.activeElement)) {
                    if (manual) setIndicator('Pemberitahuan diperbarui. Selesaikan tindakan atau terapkan filter untuk memperbarui daftar.');
                    return;
                }
                const openDetails = new Set(Array.from(board.querySelectorAll('[data-detail-id][open]')).map(detail => detail.dataset.detailId));
                const reasons = new Map(Array.from(board.querySelectorAll('select[name="reason"]')).map(select => [select.id, select.value]));
                board.innerHTML = data.html;
                board.querySelectorAll('[data-detail-id]').forEach(detail => { detail.open = openDetails.has(detail.dataset.detailId); });
                board.querySelectorAll('select[name="reason"]').forEach(select => {
                    const value = reasons.get(select.id);
                    if (Array.from(select.options).some(option => option.value === value)) select.value = value;
                });
            }
            setIndicator('● Diperbarui ' + new Date().toLocaleTimeString('id-ID', {
                timeZone: 'Asia/Makassar', hour: '2-digit', minute: '2-digit', second: '2-digit'
            }) + ' WITA');
        } catch {
            setIndicator('Pembaruan terputus. Mencoba lagi otomatis; gunakan Perbarui untuk mencoba sekarang.', true);
        } finally {
            fetching = false;
            refreshButtons.forEach(button => { button.disabled = false; });
        }
    }

    function updateDeadlines() {
        document.querySelectorAll('[data-deadline]').forEach(node => {
            const seconds = Math.ceil((new Date(node.dataset.deadline) - Date.now()) / 1000);
            node.textContent = seconds > 0
                ? 'Sisa waktu ' + Math.floor(seconds / 60) + ' menit ' + seconds % 60 + ' detik'
                : 'Batas hadir terlewati. Hubungi staf untuk memastikan status antrean.';
            node.classList.toggle('danger-text', seconds <= 0);
        });
    }

    updateDeadlines();
    setInterval(updateDeadlines, 1000);
    if ((ticket || board) && !terminal) {
        setInterval(poll, 5000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
        window.addEventListener('online', () => poll(true));
        window.addEventListener('offline', () => setIndicator('Anda sedang offline. Status akan diperbarui setelah tersambung kembali.', true));
    }
    window.addEventListener('pageshow', event => {
        if (!event.persisted) return;
        restoreSubmission();
        syncQueueSelection();
        if (formState) formState.hidden = true;
        poll();
    });
})();
