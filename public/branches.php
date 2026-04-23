<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/branch.php';
require_once __DIR__ . '/../includes/helpers.php';

$user = require_admin(); // only admin can manage branches

$page = 'branches';
$page_title = 'Branches';

$errors = [];
$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['_csrf'] ?? null)) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        if ($action === 'create') {
            $name = trim((string)($_POST['name'] ?? ''));
            $code = strtoupper(trim((string)($_POST['code'] ?? '')));
            $location = trim((string)($_POST['location'] ?? ''));
            if ($name === '') $errors[] = 'Name is required.';
            if ($code === '') $errors[] = 'Code is required.';
            if ($location === '') $errors[] = 'Location is required.';
            if (empty($errors)) {
                try {
                    $id = create_branch(null, $name, $code, $location);
                    flash('success', 'Branch created.');
                    header('Location: /branches.php'); exit;
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }
        } elseif ($action === 'update') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $code = strtoupper(trim((string)($_POST['code'] ?? '')));
            $location = trim((string)($_POST['location'] ?? ''));
            if ($name === '') $errors[] = 'Name is required.';
            if ($code === '') $errors[] = 'Code is required.';
            if ($location === '') $errors[] = 'Location is required.';
            if ($id <= 0) $errors[] = 'Invalid branch id.';
            if (empty($errors)) {
                try {
                    update_branch(null, $id, $name, $code, $location);
                    flash('success', 'Branch updated.');
                    header('Location: /branches.php'); exit;
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }
        } elseif ($action === 'delete') {
          $id = (int)($_POST['id'] ?? 0);
          if ($id <= 0) $errors[] = 'Invalid branch id.';
          if (empty($errors)) {
            try {
              delete_branch(null, $id);
              flash('success', 'Branch deleted.');
              header('Location: /branches.php'); exit;
            } catch (Exception $e) {
              $errors[] = $e->getMessage();
            }
          }
        }
    }
}

$branches = list_branches();
$flash = flash('success');
require_once __DIR__ . '/../includes/header.php';
?>
<h1>Branches</h1>
<?php if ($flash): ?><div class="alert alert-success"><?= e($flash) ?></div><?php endif; ?>
<?php if (!empty($errors)): ?><div class="alert alert-error"><?php foreach ($errors as $er) echo '<div>'.e($er).'</div>'; ?></div><?php endif; ?>

<section class="card">
  <h2>Create Branch</h2>
  <form method="post" action="/branches.php">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <label>Name<br><input id="create-name" name="name" required></label><br>
    <label>Code<br><input id="create-code" name="code" required></label><br>
    <label>Location<br><input id="create-location" name="location" required></label><br>
    <button class="btn" type="submit">Create</button>
  </form>
</section>

<section class="card">
  <h2>Existing Branches</h2>
  <table class="table">
    <thead><tr><th>ID</th><th>Name</th><th>Code</th><th>Location</th><th>Employees</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach ($branches as $b): ?>
        <tr>
          <td><?= e((string)$b['id']) ?></td>
          <td><?= e($b['name']) ?></td>
          <td><?= e($b['code']) ?></td>
          <td><?= e($b['location']) ?></td>
          <td><?= e((string)($b['employee_count'] ?? 0)) ?></td>
          <td>
            <button class="btn btn-sm" onclick="showEdit(<?= (int)$b['id'] ?>,'<?= e($b['name']) ?>','<?= e($b['code']) ?>','<?= e($b['location']) ?>')">Edit</button>
            <?php if (($b['employee_count'] ?? 0) > 0): ?>
              <button class="btn btn-danger btn-sm" disabled title="Cannot delete branch with assigned employees">Delete</button>
            <?php else: ?>
              <form method="post" action="/branches.php" style="display:inline" onsubmit="return confirm('Delete branch?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                <button class="btn btn-danger btn-sm" type="submit">Delete</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</section>

<section id="editModal" class="modal" style="display:none">
  <div class="modal-content">
    <h3>Edit Branch</h3>
    <form method="post" action="/branches.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" id="edit-id">
      <label>Name<br><input name="name" id="edit-name" required></label><br>
      <label>Code<br><input name="code" id="edit-code" required></label><br>
      <label>Location<br><input name="location" id="edit-location" required></label><br>
      <button class="btn" type="submit">Save</button>
      <button type="button" class="btn btn-ghost" onclick="hideEdit()">Cancel</button>
    </form>
  </div>
</section>

<script>
function showEdit(id,name,code,location){
  document.getElementById('edit-id').value = id;
  document.getElementById('edit-name').value = name;
  document.getElementById('edit-code').value = code;
  document.getElementById('edit-location').value = location;
  document.getElementById('editModal').style.display = 'block';
}
function hideEdit(){ document.getElementById('editModal').style.display = 'none'; }
</script>

<script>
// Client-side validation and normalization for create/edit forms
document.addEventListener('DOMContentLoaded', function(){
  var createForm = document.querySelector('form[action="/branches.php"][method="post"]');
  if (createForm) {
    createForm.addEventListener('submit', function(e){
      var name = document.getElementById('create-name').value.trim();
      var code = document.getElementById('create-code').value.trim();
      var loc = document.getElementById('create-location').value.trim();
      if (!name || !code || !loc) {
        alert('Please fill name, code and location');
        e.preventDefault(); return false;
      }
      document.getElementById('create-code').value = code.toUpperCase();
    });
  }

  // edit form inputs
  var editForm = document.querySelector('#editModal form');
  if (editForm) {
    editForm.addEventListener('submit', function(e){
      var name = document.getElementById('edit-name').value.trim();
      var code = document.getElementById('edit-code').value.trim();
      var loc = document.getElementById('edit-location').value.trim();
      if (!name || !code || !loc) {
        alert('Please fill name, code and location');
        e.preventDefault(); return false;
      }
      document.getElementById('edit-code').value = code.toUpperCase();
    });
  }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php';
