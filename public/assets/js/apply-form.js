/**
 * Course application form — fee calculator, modal, AJAX submit
 */
(function () {
    'use strict';

    function fmt(n) {
        return 'KSh ' + Math.round(n).toLocaleString();
    }

    function recalcForm(form) {
        if (!form) return;

        var sel = form.querySelector('[data-dh-course-select]');
        var opt = sel && sel.options[sel.selectedIndex];
        var fee = opt ? parseFloat(opt.getAttribute('data-fee')) || 0 : 0;
        var months = opt ? parseInt(opt.getAttribute('data-months'), 10) || 1 : 1;

        var plan = 'monthly';
        var mode = 'online';
        form.querySelectorAll('input[name="payment_plan"]').forEach(function (r) {
            if (r.checked) plan = r.value;
        });
        form.querySelectorAll('input[name="study_mode"]').forEach(function (r) {
            if (r.checked) mode = r.value;
        });

        var regFee = mode === 'physical' ? 1500 : 1000;
        var count = plan === 'monthly' ? Math.max(1, months) : (plan === '3months' ? 3 : 1);
        var inst = fee > 0 ? fee / count : 0;
        var label = fee === 0 ? 'Select a course above' : (plan === 'full' ? 'Full course fee' : 'First of ' + count + ' installment' + (count > 1 ? 's' : ''));

        function set(sel, val) {
            var el = form.querySelector(sel);
            if (el) el.textContent = val;
        }

        set('[data-dh-rfee]', fmt(regFee));
        set('[data-dh-ilabel]', label);
        set('[data-dh-iamt]', fee > 0 ? fmt(inst) : '—');
        set('[data-dh-total]', fee > 0 ? fmt(regFee + inst) : fmt(regFee));

        if (fee > 0) {
            set('[data-dh-hint-mo]', fmt(fee / Math.max(1, months)) + '/mo');
            set('[data-dh-hint-3mo]', fmt(fee / 3));
            set('[data-dh-hint-full]', fmt(fee));
        }
    }

    function wireForm(form) {
        if (!form || form.dataset.dhWired === '1') return;
        form.dataset.dhWired = '1';

        var sel = form.querySelector('[data-dh-course-select]');
        if (sel) sel.addEventListener('change', function () { recalcForm(form); });
        form.querySelectorAll('input[name="payment_plan"], input[name="study_mode"]').forEach(function (r) {
            r.addEventListener('change', function () { recalcForm(form); });
        });

        var fileInput = form.querySelector('[data-dh-id-upload]');
        var uploadText = form.querySelector('[data-dh-upload-text]');
        if (fileInput && uploadText) {
            fileInput.addEventListener('change', function () {
                uploadText.textContent = fileInput.files[0] ? fileInput.files[0].name : 'Upload PDF';
            });
        }

        form.addEventListener('submit', function (e) {
            var modal = document.getElementById('dhApplyModal');
            var inModal = modal && modal.contains(form);

            if (!inModal) {
                var btn = form.querySelector('[data-dh-submit-btn]');
                if (btn) {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting…';
                }
                return;
            }

            e.preventDefault();
            var submitBtn = form.querySelector('[data-dh-submit-btn]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting…';
            }

            var fd = new FormData(form);
            fd.append('dh_ajax', '1');

            fetch(form.action, {
                method: 'POST',
                body: fd,
                headers: { Accept: 'application/json' },
                credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var body = document.getElementById('dhApplyModalBody');
                    if (data.success && body) {
                        body.innerHTML = '<div class="hf-alert hf-alert-success dh-apply-success" role="status">'
                            + '<i class="fas fa-check-circle"></i>'
                            + '<div><strong>Thank you!</strong> ' + (data.message || 'Application received.') + '</div></div>';
                    } else {
                        alert(data.message || 'Submission failed. Please try again.');
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Application';
                        }
                    }
                })
                .catch(function () {
                    alert('Network error. Please try again.');
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Application';
                    }
                });
        });

        recalcForm(form);
    }

    function openApplyModal() {
        var modal = document.getElementById('dhApplyModal');
        if (!modal) {
            window.location.href = (window.DH_APPLY_URL || '/apply/');
            return;
        }
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        var form = modal.querySelector('[data-dh-apply-form]');
        wireForm(form);
        recalcForm(form);
    }

    function closeApplyModal() {
        var modal = document.getElementById('dhApplyModal');
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-dh-apply-form]').forEach(wireForm);

        document.querySelectorAll('[data-dh-apply-open]').forEach(function (el) {
            el.addEventListener('click', function (e) {
                e.preventDefault();
                openApplyModal();
            });
        });

        document.querySelectorAll('[data-dh-apply-close]').forEach(function (el) {
            el.addEventListener('click', closeApplyModal);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeApplyModal();
        });

        if (window.location.hash === '#register' || window.location.hash === '#apply') {
            openApplyModal();
        }

        document.querySelectorAll('[data-dh-apply-share]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var url = btn.getAttribute('data-url') || '';
                var title = btn.getAttribute('data-title') || 'Apply at Dazu Hub';
                var icon = btn.querySelector('i');

                function showCopied() {
                    btn.classList.add('is-copied');
                    if (icon) icon.className = 'fas fa-check';
                    setTimeout(function () {
                        btn.classList.remove('is-copied');
                        if (icon) icon.className = 'fas fa-share-nodes';
                    }, 1800);
                }

                if (navigator.share) {
                    navigator.share({ title: title, text: title, url: url }).catch(function () {});
                    return;
                }

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(showCopied).catch(function () {
                        window.prompt('Copy this link:', url);
                    });
                    return;
                }

                window.prompt('Copy this link:', url);
            });
        });
    });

    window.DhApply = { open: openApplyModal, close: closeApplyModal };
})();
