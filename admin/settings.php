<?php
/**
 * TC BATILLY - Paramètres & Utilisateurs
 */
require_once '../backend/config/config.php';
require_once '../backend/config/database.php';

require_admin();

$db = get_db();
$page_title = 'Paramètres & Utilisateurs';
$page_description = 'Configurer le site et administrer les comptes.';

$success_message = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
$error_message = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'update_settings':
                $stmt = $db->query("SELECT setting_key, setting_type FROM settings");
                $known_settings = [];
                while ($row = $stmt->fetch()) {
                    $known_settings[$row['setting_key']] = $row['setting_type'];
                }

                foreach ($known_settings as $key => $type) {
                    if ($type === 'boolean') {
                        $value = isset($_POST['settings'][$key]) && $_POST['settings'][$key] === '1' ? '1' : '0';
                    } else {
                        $value = trim($_POST['settings'][$key] ?? '');
                    }

                    $update = $db->prepare("UPDATE settings SET setting_value = ?, updated_at = NOW() WHERE setting_key = ?");
                    $update->execute([$value, $key]);
                }

                log_activity($db, $_SESSION['user_id'], 'update', 'settings', null, 'Mise à jour des paramètres');
                redirect('settings.php?tab=settings&success=' . urlencode('Paramètres mis à jour avec succès.'));
                break;

            case 'create_user':
                $username = trim($_POST['username'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';
                $role = $_POST['role'] ?? 'editor';

                if ($username === '' || $email === '' || $password === '') {
                    throw new Exception('Tous les champs sont requis pour créer un utilisateur.');
                }

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Adresse email invalide.');
                }

                $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Un utilisateur existe déjà avec ce nom ou cette adresse email.');
                }

                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $insert = $db->prepare("INSERT INTO users (username, email, password, role, two_factor_enabled, force_password_change) VALUES (?, ?, ?, ?, ?, ?)");
                $insert->execute([$username, $email, $hashed, $role, 1, 1]);

                log_activity($db, $_SESSION['user_id'], 'create', 'user', $db->lastInsertId(), $username);
                redirect('settings.php?tab=users&success=' . urlencode('Utilisateur créé avec succès.'));
                break;

            case 'update_user':
                $id = (int)($_POST['id'] ?? 0);
                $username = trim($_POST['username'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $role = $_POST['role'] ?? 'editor';
                $new_password = $_POST['password'] ?? '';

                if ($id <= 0) {
                    throw new Exception('Utilisateur invalide.');
                }

                if ($username === '' || $email === '') {
                    throw new Exception('Le nom d\'utilisateur et l\'email sont requis.');
                }

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Adresse email invalide.');
                }

                $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND id != ?");
                $stmt->execute([$username, $email, $id]);
                if ($stmt->fetchColumn() > 0) {
                    throw new Exception('Un autre utilisateur utilise déjà ce nom ou cette adresse email.');
                }

                $db->beginTransaction();

                $update = $db->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
                $update->execute([$username, $email, $role, $id]);

                if ($new_password !== '') {
                    $hashed = password_hash($new_password, PASSWORD_BCRYPT);
                    $pwdStmt = $db->prepare("UPDATE users SET password = ?, force_password_change = 1 WHERE id = ?");
                    $pwdStmt->execute([$hashed, $id]);
                }

                $db->commit();

                log_activity($db, $_SESSION['user_id'], 'update', 'user', $id, $username);
                redirect('settings.php?tab=users&success=' . urlencode('Utilisateur mis à jour.'));
                break;

            case 'delete_user':
                $id = (int)($_POST['id'] ?? 0);

                if ($id <= 0) {
                    throw new Exception('Utilisateur invalide.');
                }

                if ($id === (int)$_SESSION['user_id']) {
                    throw new Exception('Vous ne pouvez pas supprimer votre propre compte.');
                }

                $stmt = $db->prepare("SELECT username, role FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $user = $stmt->fetch();

                if (!$user) {
                    throw new Exception('Utilisateur introuvable.');
                }

                $delete = $db->prepare("DELETE FROM users WHERE id = ?");
                $delete->execute([$id]);

                log_activity($db, $_SESSION['user_id'], 'delete', 'user', $id, $user['username']);
                redirect('settings.php?tab=users&success=' . urlencode('Utilisateur supprimé.'));
                break;

            default:
                throw new Exception('Action non supportée.');
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $tab = ($action === 'create_user' || $action === 'update_user' || $action === 'delete_user') ? 'users' : 'settings';
        redirect('settings.php?tab=' . $tab . '&error=' . urlencode($e->getMessage()));
    }
}

$settings_stmt = $db->query("SELECT * FROM settings ORDER BY setting_key ASC");
$settings = $settings_stmt->fetchAll();

$users_stmt = $db->query("SELECT id, username, email, role, created_at FROM users ORDER BY created_at DESC");
$users = $users_stmt->fetchAll();

$current_user_id = (int)$_SESSION['user_id'];

include 'includes/header.php';

$active_tab = $_GET['tab'] ?? 'settings';
if (!in_array($active_tab, ['settings', 'users'], true)) {
    $active_tab = 'settings';
}
$settings_tab_active = $active_tab === 'settings';
$users_tab_active = $active_tab === 'users';
?>

<div class="content-section" aria-labelledby="settings-tabs">
    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="view-tabs" role="tablist" aria-label="Paramètres et utilisateurs" id="settings-tabs">
        <button type="button" class="view-tab" id="tab-settings" role="tab" aria-controls="panel-settings" aria-selected="<?php echo $settings_tab_active ? 'true' : 'false'; ?>">
            <span aria-hidden="true">⚙️</span>
            <span>Paramètres du site</span>
        </button>
        <button type="button" class="view-tab" id="tab-users" role="tab" aria-controls="panel-users" aria-selected="<?php echo $users_tab_active ? 'true' : 'false'; ?>">
            <span aria-hidden="true">👥</span>
            <span>Utilisateurs & rôles</span>
        </button>
    </div>

    <div class="tab-panels">
        <section class="tab-panel" id="panel-settings" role="tabpanel" aria-labelledby="tab-settings" aria-hidden="<?php echo $settings_tab_active ? 'false' : 'true'; ?>">
            <form method="POST" class="panel-grid" aria-describedby="settings-help">
                <input type="hidden" name="action" value="update_settings">
                <div class="panel-grid two-columns">
                    <?php foreach ($settings as $setting): ?>
                        <?php
                            $key = $setting['setting_key'];
                            $value = $setting['setting_value'];
                            $type = $setting['setting_type'];
                            $description = $setting['description'];
                        ?>
                        <div class="form-group">
                            <label for="setting_<?php echo htmlspecialchars($key); ?>"><?php echo htmlspecialchars(str_replace('_', ' ', ucfirst($key))); ?></label>
                            <?php if ($type === 'textarea' || strlen((string)$value) > 120): ?>
                                <textarea id="setting_<?php echo htmlspecialchars($key); ?>" name="settings[<?php echo htmlspecialchars($key); ?>]" rows="4"><?php echo htmlspecialchars($value); ?></textarea>
                            <?php elseif ($type === 'boolean'): ?>
                                <input type="hidden" name="settings[<?php echo htmlspecialchars($key); ?>]" value="0">
                                <label class="toggle-label">
                                    <input type="checkbox" name="settings[<?php echo htmlspecialchars($key); ?>]" value="1" <?php echo $value ? 'checked' : ''; ?>>
                                    <span>Activé</span>
                                </label>
                            <?php elseif ($type === 'number'): ?>
                                <input type="number" id="setting_<?php echo htmlspecialchars($key); ?>" name="settings[<?php echo htmlspecialchars($key); ?>]" value="<?php echo htmlspecialchars($value); ?>">
                            <?php else: ?>
                                <input type="text" id="setting_<?php echo htmlspecialchars($key); ?>" name="settings[<?php echo htmlspecialchars($key); ?>]" value="<?php echo htmlspecialchars($value); ?>">
                            <?php endif; ?>
                            <?php if (!empty($description)): ?>
                                <small><?php echo htmlspecialchars($description); ?></small>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <p id="settings-help" style="color:var(--ink-muted);font-size:13px;">Ces paramètres impactent immédiatement le site public.</p>

                <div class="form-actions" style="margin-top: 12px;">
                    <button type="submit" class="btn-primary">Enregistrer les paramètres</button>
                </div>
            </form>
        </section>

        <section class="tab-panel" id="panel-users" role="tabpanel" aria-labelledby="tab-users" aria-hidden="<?php echo $users_tab_active ? 'false' : 'true'; ?>">
            <div class="panel-grid two-columns">
                <div class="user-table-card">
                    <h3 style="margin-bottom:16px;">Comptes existants</h3>
                    <div class="table-container" role="application" aria-label="Tableau des utilisateurs">
                        <table>
                            <thead>
                                <tr>
                                    <th>Nom d'utilisateur</th>
                                    <th>Email</th>
                                    <th>Rôle</th>
                                    <th>Créé le</th>
                                    <th style="width:220px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="5" style="text-align:center;padding:24px;color:var(--ink-muted);">Aucun utilisateur trouvé.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <span class="badge <?php echo $user['role'] === 'admin' ? 'badge-success' : 'badge-secondary'; ?>"><?php echo htmlspecialchars($user['role']); ?></span>
                                            </td>
                                            <td><?php echo format_date($user['created_at'], DATETIME_FORMAT); ?></td>
                                            <td>
                                                <details>
                                                    <summary class="btn-secondary" style="cursor:pointer;">Modifier</summary>
                                                    <div class="details-content" style="margin-top:12px;">
                                                        <form method="POST" class="inline-form">
                                                            <input type="hidden" name="id" value="<?php echo (int)$user['id']; ?>">
                                                            <div class="form-group">
                                                                <label>Nom d'utilisateur</label>
                                                                <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Email</label>
                                                                <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Rôle</label>
                                                                <select name="role">
                                                                    <option value="editor" <?php echo $user['role'] === 'editor' ? 'selected' : ''; ?>>Éditeur</option>
                                                                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Administrateur</option>
                                                                </select>
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Nouveau mot de passe (optionnel)</label>
                                                                <input type="password" name="password" placeholder="Laisser vide pour ne pas changer">
                                                            </div>
                                                            <div class="form-actions" style="justify-content:flex-start;gap:12px;">
                                                                <button type="submit" name="action" value="update_user" class="btn-primary">Enregistrer</button>
                                                                <?php if ((int)$user['id'] !== $current_user_id): ?>
                                                                    <button type="submit" name="action" value="delete_user" class="btn-danger" onclick="return confirm('Supprimer cet utilisateur ?');">Supprimer</button>
                                                                <?php else: ?>
                                                                    <span class="badge badge-info">Votre compte</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </details>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="user-create-card" style="background:var(--surface-strong);border:1px solid var(--border);border-radius:var(--radius-lg);padding:24px;box-shadow:var(--shadow-sm);">
                    <h3>Créer un nouvel utilisateur</h3>
                    <p style="color:var(--ink-muted);font-size:13px;margin-bottom:20px;">Les comptes administrateurs ont accès à toutes les fonctionnalités.</p>
                    <form method="POST" class="panel-grid" aria-label="Créer un utilisateur">
                        <input type="hidden" name="action" value="create_user">
                        <div class="form-group">
                            <label>Nom d'utilisateur</label>
                            <input type="text" name="username" required>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label>Mot de passe</label>
                            <input type="password" name="password" minlength="6" required>
                        </div>
                        <div class="form-group">
                            <label>Rôle</label>
                            <select name="role">
                                <option value="editor">Éditeur</option>
                                <option value="admin">Administrateur</option>
                            </select>
                        </div>
                        <div class="form-actions" style="justify-content:flex-start;">
                            <button type="submit" class="btn-primary">Créer l'utilisateur</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </div>
</div>

<?php include 'includes/footer.php'; ?>


