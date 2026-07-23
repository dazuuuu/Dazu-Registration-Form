<?php
require_once __DIR__ . '/../_init.php';

$id = (int) ($_GET['id'] ?? 0);
$project = $sms->getProject($id);
if (!$project) {
    sms_flash('error', 'Project not found.');
    sms_redirect('admin/sms/projects/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_sale'])) {
        if ($sms->markProjectSale($id)) {
            sms_flash('success', 'Project marked as sale.');
        } else {
            sms_flash('error', 'Could not mark as sale (already marked or no payment).');
        }
    }
    if (isset($_POST['add_meeting'])) {
        $sms->saveMeeting(null, $id, [
            'title' => trim($_POST['meeting_title'] ?? ''),
            'meeting_date' => $_POST['meeting_date'] ?? '',
            'location' => trim($_POST['meeting_location'] ?? ''),
            'notes' => trim($_POST['meeting_notes'] ?? ''),
            'status' => $_POST['meeting_status'] ?? 'scheduled',
        ]);
        sms_flash('success', 'Meeting added.');
    }
    if (isset($_POST['delete_meeting'])) {
        $sms->deleteMeeting((int) $_POST['meeting_id']);
        sms_flash('success', 'Meeting deleted.');
    }
    if (isset($_POST['add_followup'])) {
        $sms->addFollowup($id, [
            'follow_up_date' => $_POST['follow_up_date'] ?? date('Y-m-d'),
            'note' => trim($_POST['followup_note'] ?? ''),
            'followup_type' => 'followup',
        ]);
        sms_flash('success', 'Follow-up added.');
    }
    if (isset($_POST['email_client'])) {
        $subject = trim($_POST['email_subject'] ?? '');
        $body = trim($_POST['email_body'] ?? '');
        if ($subject && $body) {
            $ok = $sms->emailProjectClient($id, $subject, $body);
            sms_flash($ok ? 'success' : 'error', $ok ? 'Email sent to client.' : 'Failed to send email (check client email / SMTP).');
        }
    }
    sms_redirect('admin/sms/projects/view.php?id=' . $id);
}

$meetings = $sms->projectMeetings($id);
$followups = $sms->projectFollowups($id);
$balance = SmsModuleService::balance((float)$project['estimated_cost'], (float)$project['paid_amount']);

$page_title = $project['title'];
$breadcrumbs = [
    ['label' => 'SmS', 'url' => url('admin/sms/index.php')],
    ['label' => 'Projects', 'url' => url('admin/sms/projects/index.php')],
    ['label' => $project['title'], 'active' => true],
];

ob_start();
?>
<div class="d-flex justify-content-between mb-3">
  <div>
    <h4 class="mb-1"><?= htmlspecialchars($project['title']) ?></h4>
    <small class="text-muted"><?= htmlspecialchars($project['client_name'] ?? '') ?> · <?= htmlspecialchars($project['status']) ?></small>
    <?php if (!empty($project['is_sale'])): ?><span class="badge bg-success ms-1">Sale</span><?php endif; ?>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= url('admin/sms/projects/form.php?id='.$id) ?>" class="btn btn-sm btn-primary">Edit</a>
    <?php if (empty($project['is_sale'])): ?>
    <form method="post" class="d-inline"><button name="mark_sale" value="1" class="btn btn-sm btn-success" onclick="return confirm('Mark this project as a sale?')">Mark as sale</button></form>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Estimated cost</div><div class="fw-bold"><?= sms_money((float)$project['estimated_cost']) ?></div></div></div></div>
  <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Paid</div><div class="fw-bold text-success"><?= sms_money((float)$project['paid_amount']) ?></div></div></div></div>
  <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Balance</div><div class="fw-bold text-danger"><?= sms_money($balance) ?></div></div></div></div>
  <div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="text-muted small">Client</div><div class="small"><?= htmlspecialchars($project['client_email'] ?? '—') ?><br><?= htmlspecialchars($project['client_phone'] ?? '') ?></div></div></div></div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-6">
    <div class="card shadow-sm"><div class="card-header fw-semibold">Meetings</div><div class="card-body">
      <form method="post" class="row g-2 mb-3">
        <div class="col-md-6"><input name="meeting_title" class="form-control form-control-sm" placeholder="Title" required></div>
        <div class="col-md-6"><input type="datetime-local" name="meeting_date" class="form-control form-control-sm" required></div>
        <div class="col-md-6"><input name="meeting_location" class="form-control form-control-sm" placeholder="Location"></div>
        <div class="col-md-6">
          <select name="meeting_status" class="form-select form-select-sm">
            <?php foreach (['scheduled','done','cancelled'] as $st): ?>
            <option value="<?= $st ?>"><?= ucfirst($st) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12"><input name="meeting_notes" class="form-control form-control-sm" placeholder="Notes"></div>
        <div class="col-12"><button name="add_meeting" value="1" class="btn btn-sm btn-success">Add meeting</button></div>
      </form>
      <table class="table table-sm mb-0"><thead><tr><th>When</th><th>Title</th><th>Status</th><th></th></tr></thead><tbody>
        <?php foreach ($meetings as $m): ?>
        <tr>
          <td class="small"><?= htmlspecialchars($m['meeting_date']) ?></td>
          <td><?= htmlspecialchars($m['title']) ?></td>
          <td><?= htmlspecialchars($m['status']) ?></td>
          <td>
            <form method="post" class="d-inline">
              <input type="hidden" name="meeting_id" value="<?= (int)$m['id'] ?>">
              <button name="delete_meeting" value="1" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete meeting?')">&times;</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody></table>
    </div></div>
  </div>
  <div class="col-lg-6">
    <div class="card shadow-sm"><div class="card-header fw-semibold">Follow-ups</div><div class="card-body">
      <form method="post" class="row g-2 mb-3">
        <div class="col-md-4"><input type="date" name="follow_up_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required></div>
        <div class="col-md-8"><input name="followup_note" class="form-control form-control-sm" placeholder="Note" required></div>
        <div class="col-12"><button name="add_followup" value="1" class="btn btn-sm btn-success">Add follow-up</button></div>
      </form>
      <table class="table table-sm mb-0"><thead><tr><th>Date</th><th>Note</th><th>Type</th></tr></thead><tbody>
        <?php foreach ($followups as $f): ?>
        <tr>
          <td><?= htmlspecialchars($f['follow_up_date']) ?></td>
          <td class="small"><?= htmlspecialchars($f['note'] ?? '') ?></td>
          <td><?= htmlspecialchars($f['followup_type']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody></table>
    </div></div>
  </div>
</div>

<div class="card shadow-sm"><div class="card-header fw-semibold">Email client</div><div class="card-body">
  <form method="post" class="row g-3">
    <div class="col-md-6"><label class="form-label">Subject</label><input name="email_subject" class="form-control" required></div>
    <div class="col-md-6"><label class="form-label">To</label><input class="form-control" value="<?= htmlspecialchars($project['client_email'] ?? 'No email on file') ?>" disabled></div>
    <div class="col-12"><label class="form-label">Message</label><textarea name="email_body" class="form-control" rows="4" required></textarea></div>
    <div class="col-12"><button name="email_client" value="1" class="btn btn-primary" <?= empty($project['client_email']) ? 'disabled' : '' ?>>Send email</button></div>
  </form>
</div></div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../../../templates/admin/layout.php';
