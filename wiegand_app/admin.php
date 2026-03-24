<?php
session_start();

// --- 1. SÉCURITÉ ET INITIALISATION DU DOSSIER DATA ---
// 🌟 DÉTECTION DU DOSSIER PERSISTANT (Spécifique à Home Assistant) 🌟
$data_dir = is_dir('/data') ? '/data' : __DIR__ . '/data';

$file_users = $data_dir . '/users.json';
$file_codes = $data_dir . '/codes.json';
$file_webhooks = $data_dir . '/webhooks.json';
$file_settings = $data_dir . '/settings.json'; // NOUVEAU

// Création du dossier sécurisé s'il n'existe pas
if (!is_dir($data_dir)) {
    mkdir($data_dir, 0777, true);
    // On ne met le .htaccess que si on est dans un dossier web classique (pas dans /data)
    if ($data_dir !== '/data') {
        file_put_contents($data_dir . '/.htaccess', "Order Deny,Allow\nDeny from all\nRequire all denied");
        file_put_contents($data_dir . '/index.php', "<?php // Silence is golden.");
    }
}

// Création de l'administrateur par défaut s'il n'y a pas d'utilisateurs
if (!file_exists($file_users)) {
    $default_users = ['admin' => password_hash('admin', PASSWORD_DEFAULT)];
    file_put_contents($file_users, json_encode($default_users));
}

$users = json_decode(file_get_contents($file_users), true) ?: [];

// --- 2. GESTION DE LA CONNEXION / DÉCONNEXION ---
$error_msg = '';
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (isset($users[$username]) && password_verify($password, $users[$username])) {
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        header("Location: admin.php");
        exit;
    } else {
        $error_msg = "Identifiants incorrects.";
    }
}

// Si non connecté, on affiche UNIQUEMENT la page de login
if (empty($_SESSION['logged_in'])) {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Connexion - Accès HA</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body { font-family: 'Roboto', sans-serif; background: #101114; color: #e1e1e1; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
            .login-box { background: #1c1c1e; padding: 30px; border-radius: 12px; width: 100%; max-width: 350px; border: 1px solid #333; text-align: center; }
            input { background: #2c2c2e; border: 1px solid #444; color: white; padding: 12px; width: 100%; margin-bottom: 15px; box-sizing: border-box; border-radius: 6px; }
            button { background: #03a9f4; color: white; border: none; padding: 12px; border-radius: 6px; cursor: pointer; width: 100%; font-weight: bold; font-size: 1rem; }
            .error { color: #f44336; margin-bottom: 15px; font-size: 0.9rem; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h2><i class="fas fa-lock"></i> Accès Sécurisé</h2>
            <?php if($error_msg): ?><div class="error"><?php echo $error_msg; ?></div><?php endif; ?>
            <form method="post">
                <input type="text" name="username" placeholder="Nom d'utilisateur" required>
                <input type="password" name="password" placeholder="Mot de passe" required>
                <button type="submit" name="login">Se connecter</button>
            </form>
            <p style="font-size: 0.8rem; color: #666; margin-top: 20px;">Par défaut : admin / admin</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// --- 3. CHARGEMENT DES DONNÉES ---
$data_codes = file_exists($file_codes) ? json_decode(file_get_contents($file_codes), true) : [];
$data_webhooks = file_exists($file_webhooks) ? json_decode(file_get_contents($file_webhooks), true) : [];

// Initialisation des paramètres système s'ils n'existent pas
$settings = file_exists($file_settings) ? json_decode(file_get_contents($file_settings), true) : [];
if (empty($settings)) {
    $settings = ['ha_ip' => 'homeassistant', 'ha_port' => '8123'];
    file_put_contents($file_settings, json_encode($settings));
}

// --- 4. TRAITEMENT DES FORMULAIRES ---

// Sauvegarde des réglages réseau
if (isset($_POST['save_settings'])) {
    $settings['ha_ip'] = trim($_POST['ha_ip']);
    $settings['ha_port'] = trim($_POST['ha_port']);
    file_put_contents($file_settings, json_encode($settings));
}

// Ajout d'un Utilisateur Web
if (isset($_POST['add_user'])) {
    $new_user = trim($_POST['new_username']);
    $new_pass = $_POST['new_password'];
    if (!empty($new_user) && !empty($new_pass)) {
        $users[$new_user] = password_hash($new_pass, PASSWORD_DEFAULT);
        file_put_contents($file_users, json_encode($users));
    }
}
// Suppression d'un Utilisateur Web
if (isset($_GET['del_user']) && $_GET['del_user'] !== $_SESSION['username']) {
    unset($users[$_GET['del_user']]);
    file_put_contents($file_users, json_encode($users));
    header("Location: admin.php"); exit;
}

// Ajout d'un Webhook
if (isset($_POST['add_webhook'])) {
    $data_webhooks[$_POST['w_label']] = $_POST['w_id'];
    file_put_contents($file_webhooks, json_encode($data_webhooks));
}
// Suppression d'un Webhook
if (isset($_GET['del_w'])) {
    unset($data_webhooks[$_GET['del_w']]);
    file_put_contents($file_webhooks, json_encode($data_webhooks));
    header("Location: admin.php"); exit;
}

// Ajout d'un Code/Badge
if (isset($_POST['add_code'])) {
    $new_pin = !empty($_POST['pin']) ? $_POST['pin'] : sprintf("%06d", mt_rand(0, 999999));
    $data_codes[$new_pin] = [
        "type" => $_POST['type'],
        "webhook" => $_POST['webhook'],
        "label" => $_POST['label'] ?: "Sans nom"
    ];
    file_put_contents($file_codes, json_encode($data_codes));
}
// Suppression d'un Code/Badge
if (isset($_POST['delete_code'])) {
    unset($data_codes[$_POST['delete_code']]);
    file_put_contents($file_codes, json_encode($data_codes));
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Accès HA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Roboto', sans-serif; background: #101114; color: #e1e1e1; padding: 20px; display: flex; flex-direction: column; align-items: center; }
        .top-bar { width: 100%; max-width: 450px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background: #1c1c1e; padding: 10px 20px; border-radius: 8px; box-sizing: border-box; border: 1px solid #333;}
        .card { background: #1c1c1e; padding: 20px; border-radius: 12px; width: 100%; max-width: 450px; border: 1px solid #333; margin-bottom: 20px; box-sizing: border-box;}
        h2, h3 { color: #03a9f4; margin-top: 0; font-weight: 400; }
        .input-group { margin: 10px 0; text-align: left; }
        label { font-size: 0.8rem; color: #888; display: block; margin-bottom: 4px; }
        input, select { background: #2c2c2e; border: 1px solid #444; color: white; padding: 10px; width: 100%; box-sizing: border-box; border-radius: 6px; font-size: 0.9rem; }
        .flex { display: flex; gap: 8px; }
        button.primary { background: #03a9f4; color: white; border: none; padding: 12px; border-radius: 6px; cursor: pointer; width: 100%; font-weight: bold; }
        .btn-gen { background: #444; color:white; border:none; width: auto; font-size: 0.7rem; padding: 0 15px; border-radius: 6px; cursor:pointer; }
        .code-item { background: #1c1c1e; padding: 12px; margin-bottom: 8px; border-radius: 8px; border-left: 4px solid #03a9f4; display: flex; justify-content: space-between; align-items: center; width: 100%; max-width: 450px; box-sizing: border-box; }
        .badge { font-size: 0.65rem; padding: 3px 6px; border-radius: 4px; text-transform: uppercase; font-weight: bold; margin-left: 5px; background: #ef6c00; color:white; }
        .badge-perm { background: #2e7d32; }
        .badge-type-tag { background: #5e35b1; margin-left: 0; margin-right: 5px;}
        .del-btn { background: #c62828; color:white; border:none; padding: 6px 10px; font-size: 0.7rem; border-radius:4px; cursor:pointer; text-decoration: none;}
        hr { border: 0; border-top: 1px solid #333; margin: 20px 0; width: 100%; max-width: 450px;}
    </style>
</head>
<body>

<div class="top-bar">
        <div><i class="fas fa-user-circle"></i> Connecté : <b><?php echo htmlspecialchars($_SESSION['username']); ?></b></div>
        <div style="display: flex; gap: 15px; align-items: center;">
            <a href="pin.html" style="color: #4CAF50; text-decoration: none; font-size: 0.9rem; font-weight:bold; border: 1px solid #4CAF50; padding: 5px 10px; border-radius: 4px;"><i class="fas fa-th"></i> Tester un code</a>
            <a href="?logout=1" style="color: #f44336; text-decoration: none; font-size: 0.9rem;"><i class="fas fa-sign-out-alt"></i> Quitter</a>
        </div>
    </div>

    <div class="card">
        <h2>Ajouter un accès</h2>
        <form method="post">
            <div class="input-group">
                <label>Nom ou Description</label>
                <input type="text" name="label" required placeholder="ex: Femme de ménage / Badge">
            </div>
            <div class="input-group">
                <label>Code PIN ou N° de Badge</label>
                <div class="flex">
                    <input type="text" name="pin" id="pinInput" placeholder="Saisir ou Aléatoire">
                    <button type="button" class="btn-gen" onclick="generatePin()">Générer PIN</button>
                </div>
            </div>
            <div class="input-group">
                <label>Action (Webhook)</label>
                <select name="webhook" required>
                    <option value="">-- Choisir une action --</option>
                    <?php foreach($data_webhooks as $l => $id): ?>
                        <option value="<?php echo htmlspecialchars($id); ?>"><?php echo htmlspecialchars($l); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="input-group">
                <label>Validité</label>
                <select name="type">
                    <option value="unique">Usage Unique</option>
                    <option value="permanent" selected>Usage Permanent</option>
                </select>
            </div>
            <button type="submit" name="add_code" class="primary">Enregistrer l'accès</button>
        </form>
    </div>

    <h3 style="margin: 0 0 10px 0; width: 100%; max-width: 450px; text-align: left;">Accès Actifs</h3>
    <?php if (empty($data_codes)): ?>
        <p style="color:#666; width: 100%; max-width: 450px;">Aucun accès actif.</p>
    <?php endif; ?>
    <?php foreach ($data_codes as $pin => $info): ?>
        <div class="code-item">
            <div>
                <?php if(strlen((string)$pin) > 6): ?>
                    <span class="badge badge-type-tag"><i class="fas fa-id-card"></i> BADGE</span>
                <?php endif; ?>
                <b><?php echo htmlspecialchars($pin); ?></b>
                <span class="badge <?php echo ($info['type']=='permanent')?'badge-perm':''; ?>"><?php echo htmlspecialchars($info['type']); ?></span><br>
                <small style="color:#888"><?php echo htmlspecialchars($info['label']); ?> → <?php echo htmlspecialchars($info['webhook']); ?></small>
            </div>
            <form method="post">
                <input type="hidden" name="delete_code" value="<?php echo htmlspecialchars($pin); ?>">
                <button type="submit" class="del-btn">Supprimer</button>
            </form>
        </div>
    <?php endforeach; ?>

    <hr>

    <div class="card" style="opacity: 0.9;">
        <h3>Configuration des Actions (Webhooks)</h3>
        <form method="post" class="flex" style="flex-direction: column;">
            <input type="text" name="w_label" placeholder="Nom (ex: Portail Principal)" required>
            <input type="text" name="w_id" placeholder="ID Webhook Home Assistant" required>
            <button type="submit" name="add_webhook" style="background:#444; color:white; border:none; padding:10px; border-radius:6px; cursor:pointer;">Ajouter l'action</button>
        </form>
        <div style="margin-top:15px; font-size:0.8rem;">
            <?php foreach($data_webhooks as $l => $id): ?>
                <div style="display:flex; justify-content:space-between; padding:5px 0; border-bottom:1px solid #333;">
                    <span><?php echo htmlspecialchars($l); ?> (<code><?php echo htmlspecialchars($id); ?></code>)</span>
                    <a href="?del_w=<?php echo urlencode($l); ?>" style="color:#f44336; text-decoration:none;">✕</a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card" style="opacity: 0.9; border-color: #00bcd4;">
        <h3 style="color: #00bcd4;"><i class="fas fa-network-wired"></i> Système & Réseau</h3>
        <p style="font-size: 0.75rem; color: #aaa; margin-top: 0;">Laissez <b>homeassistant</b> si ceci tourne en Add-on HA.</p>
        <form method="post" class="flex">
            <input type="text" name="ha_ip" placeholder="IP" value="<?php echo htmlspecialchars($settings['ha_ip']); ?>" required>
            <input type="text" name="ha_port" placeholder="Port" value="<?php echo htmlspecialchars($settings['ha_port']); ?>" style="width: 80px;" required>
            <button type="submit" name="save_settings" style="background:#00bcd4; color:white; border:none; border-radius:6px; cursor:pointer; padding: 0 15px;"><i class="fas fa-save"></i></button>
        </form>
    </div>

    <div class="card" style="opacity: 0.9; border-color: #ff9800;">
        <h3 style="color: #ff9800;"><i class="fas fa-users-cog"></i> Utilisateurs de l'Administration</h3>
        <form method="post" class="flex" style="flex-direction: column;">
            <input type="text" name="new_username" placeholder="Nouvel identifiant" required>
            <input type="password" name="new_password" placeholder="Nouveau mot de passe" required>
            <button type="submit" name="add_user" style="background:#ff9800; color:white; border:none; padding:10px; border-radius:6px; cursor:pointer;">Ajouter un Administrateur</button>
        </form>
        <div style="margin-top:15px; font-size:0.8rem;">
            <?php foreach($users as $u => $hash): ?>
                <div style="display:flex; justify-content:space-between; padding:5px 0; border-bottom:1px solid #333;">
                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($u); ?></span>
                    <?php if($u !== $_SESSION['username']): ?>
                        <a href="?del_user=<?php echo urlencode($u); ?>" style="color:#f44336; text-decoration:none;">Supprimer</a>
                    <?php else: ?>
                        <span style="color: #888;">(Vous)</span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        function generatePin() {
            const pin = Math.floor(100000 + Math.random() * 900000);
            document.getElementById('pinInput').value = pin;
        }
    </script>
</body>
</html>
