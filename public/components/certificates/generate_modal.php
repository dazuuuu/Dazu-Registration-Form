<?php
// "Generate Certificate" modal with a live student search. Included from admin|superadmin/certificates/index.php;
// searches via search.php and posts registration_id back to the same index.php.
?>
<div class="modal fade" id="generateModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form method="POST" action="" class="modal-content" id="certGenForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="registration_id" id="certRegId">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-certificate text-success me-2"></i>Generate Certificate</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-2">
                    <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                    <input type="search" id="certSearch" class="form-control" autocomplete="off"
                           placeholder="Search by name, registration no, email, phone or course…">
                </div>
                <div class="d-flex flex-wrap gap-3 small text-muted mb-3">
                    <label class="form-check-label"><input type="checkbox" class="form-check-input me-1" id="certOnlyEligible" checked>Only students who can get a certificate</label>
                    <span>Students need a <strong>zero fee balance</strong> and no existing certificate for that course.</span>
                </div>
                <div id="certResults" class="list-group">
                    <div class="text-center text-muted py-4 small">Type at least 2 characters to search.</div>
                </div>
            </div>
            <div class="modal-footer">
                <span class="me-auto small text-muted" id="certPicked">No student selected.</span>
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success" id="certGenBtn" disabled>Generate &amp; Prepare</button>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    const input = document.getElementById('certSearch');
    const box = document.getElementById('certResults');
    const only = document.getElementById('certOnlyEligible');
    const regId = document.getElementById('certRegId');
    const picked = document.getElementById('certPicked');
    const btn = document.getElementById('certGenBtn');
    let timer = null, seq = 0, rows = [];

    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const kes = n => 'KES ' + Number(n).toLocaleString();
    const hl = (s, q) => {
        s = esc(s); if (!q) return s;
        const i = s.toLowerCase().indexOf(esc(q).toLowerCase());
        return i < 0 ? s : s.slice(0, i) + '<mark class="p-0">' + s.slice(i, i + esc(q).length) + '</mark>' + s.slice(i + esc(q).length);
    };

    function render() {
        const q = input.value.trim();
        const list = only.checked ? rows.filter(r => r.eligible) : rows;
        if (q.length < 2) { box.innerHTML = '<div class="text-center text-muted py-4 small">Type at least 2 characters to search.</div>'; return; }
        if (!list.length) {
            box.innerHTML = '<div class="text-center text-muted py-4 small">No ' + (only.checked && rows.length ? 'eligible ' : '') + 'students match “' + esc(q) + '”.'
                + (only.checked && rows.length ? ' <a href="#" id="certShowAll">Show all ' + rows.length + ' matches</a>' : '') + '</div>';
            const a = document.getElementById('certShowAll');
            if (a) a.onclick = e => { e.preventDefault(); only.checked = false; render(); };
            return;
        }
        box.innerHTML = list.map(r => {
            let status;
            if (r.certificate_id) status = '<span class="badge bg-secondary">Has certificate</span>';
            else if (r.balance > 0) status = '<span class="badge bg-danger">Balance ' + kes(r.balance) + '</span>';
            else status = '<span class="badge bg-success">Cleared · eligible</span>';
            return '<button type="button" class="list-group-item list-group-item-action d-flex justify-content-between align-items-start gap-2' + (r.eligible ? '' : ' opacity-75') + '" data-id="' + r.id + '"' + (r.eligible ? '' : ' data-disabled="1"') + '>'
                + '<div class="text-start"><div class="fw-semibold">' + hl(r.name, q) + ' <span class="text-muted small">' + hl(r.reg_no || '', q) + '</span></div>'
                + '<div class="small">' + hl(r.course, q) + '</div>'
                + '<div class="small text-muted">' + hl(r.email, q) + ' · ' + hl(r.phone, q) + ' · Tutor: ' + esc(r.tutor || 'not assigned') + '</div></div>'
                + '<div class="text-end">' + status + '</div></button>';
        }).join('');
    }

    async function search() {
        const q = input.value.trim(), my = ++seq;
        if (q.length < 2) { rows = []; render(); return; }
        box.innerHTML = '<div class="text-center text-muted py-4 small"><span class="spinner-border spinner-border-sm me-2"></span>Searching…</div>';
        try {
            const res = await fetch('search.php?q=' + encodeURIComponent(q), {headers: {'Accept': 'application/json'}});
            const data = await res.json();
            if (my !== seq) return;
            rows = data.results || [];
        } catch (e) { rows = []; }
        render();
    }

    input.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(search, 250); });
    only.addEventListener('change', render);
    box.addEventListener('click', e => {
        const b = e.target.closest('[data-id]');
        if (!b) return;
        const r = rows.find(x => String(x.id) === b.dataset.id);
        box.querySelectorAll('.active').forEach(x => x.classList.remove('active'));
        if (b.dataset.disabled) {
            regId.value = ''; btn.disabled = true;
            picked.innerHTML = '<span class="text-danger">' + esc(r.name) + ': ' + (r.certificate_id ? 'already has a certificate for this course.' : 'clear the ' + kes(r.balance) + ' balance first.') + '</span>';
            return;
        }
        b.classList.add('active');
        regId.value = r.id; btn.disabled = false;
        picked.innerHTML = 'Selected: <strong>' + esc(r.name) + '</strong> — ' + esc(r.course);
    });
    document.getElementById('generateModal').addEventListener('shown.bs.modal', () => input.focus());
})();
</script>
