<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/database.php';
require_once __DIR__ . '/includes/validation.php';
require_once __DIR__ . '/includes/application.php';

$pdo = getDB();

try {
    $pdo->exec("ALTER TABLE application ADD COLUMN login VARCHAR(50) UNIQUE");
} catch (PDOException $e) {}
try {
    $pdo->exec("ALTER TABLE application ADD COLUMN password_hash VARCHAR(255)");
} catch (PDOException $e) {}

$languages = getAllLanguages();

$errors = [];
$success = false;
$auth_error = '';
$show_credentials = false;
$credentials = ['login' => '', 'password' => ''];

$form_data = [
    'full_name'         => '',
    'phone'             => '',
    'email'             => '',
    'birth_date'        => '',
    'gender'            => '',
    'biography'         => '',
    'contract_accepted' => false,
    'languages'         => []
];

if (empty($_SESSION['user_id']) && !empty($_COOKIE['saved_data'])) {
    $saved = json_decode($_COOKIE['saved_data'], true);
    if (is_array($saved)) $form_data = array_merge($form_data, $saved);
}
if (!empty($_COOKIE['form_errors'])) {
    $errors = json_decode($_COOKIE['form_errors'], true) ?: [];
    if (!empty($_COOKIE['form_data'])) {
        $form_data = array_merge($form_data, json_decode($_COOKIE['form_data'], true) ?: []);
    }
    setcookie('form_errors', '', time() - 3600, '/');
    setcookie('form_data', '', time() - 3600, '/');
}
if (!empty($_COOKIE['success'])) {
    $success = true;
    setcookie('success', '', time() - 3600, '/');
}

$is_authorized = !empty($_SESSION['user_id']);
if ($is_authorized) {
    $user_data = getApplication($_SESSION['user_id']);
    if ($user_data) {
        $form_data = [
            'full_name'         => $user_data['full_name'],
            'phone'             => $user_data['phone'],
            'email'             => $user_data['email'],
            'birth_date'        => $user_data['birth_date'],
            'gender'            => $user_data['gender'],
            'biography'         => $user_data['biography'],
            'contract_accepted' => (bool)$user_data['contract_accepted'],
            'languages'         => getUserLanguageIds($_SESSION['user_id'])
        ];
    } else {
        unset($_SESSION['user_id']);
        $is_authorized = false;
    }
}

if (!empty($_SESSION['credentials'])) {
    $show_credentials = true;
    $credentials = $_SESSION['credentials'];
    unset($_SESSION['credentials']);
}
if (!empty($_SESSION['auth_error'])) {
    $auth_error = $_SESSION['auth_error'];
    unset($_SESSION['auth_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';

    if ($action === 'login') {
        $login = trim($_POST['login'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($login === '' || $password === '') {
            $_SESSION['auth_error'] = 'Введите логин и пароль';
        } else {
            $stmt = $pdo->prepare("SELECT id, password_hash FROM application WHERE login = :login");
            $stmt->execute([':login' => $login]);
            $user = $stmt->fetch();
            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                setcookie('form_errors', '', time() - 3600, '/');
                setcookie('form_data', '', time() - 3600, '/');
                header('Location: ' . $_SERVER['SCRIPT_NAME']);
                exit;
            } else {
                $_SESSION['auth_error'] = 'Неверный логин или пароль';
            }
        }
        header('Location: ' . $_SERVER['SCRIPT_NAME']);
        exit;
    }

    if ($action === 'logout') {
        unset($_SESSION['user_id']);
        header('Location: ' . $_SERVER['SCRIPT_NAME']);
        exit;
    }

    $form_data = [
        'full_name'         => trim($_POST['full_name'] ?? ''),
        'phone'             => trim($_POST['phone'] ?? ''),
        'email'             => trim($_POST['email'] ?? ''),
        'birth_date'        => trim($_POST['birth_date'] ?? ''),
        'gender'            => $_POST['gender'] ?? '',
        'biography'         => trim($_POST['biography'] ?? ''),
        'contract_accepted' => isset($_POST['contract_accepted']),
        'languages'         => $_POST['languages'] ?? []
    ];

    $errors = validateApplication($form_data, $pdo);

    if (empty($errors)) {
        try {
            if ($is_authorized) {
                updateApplication($_SESSION['user_id'], $form_data);
            } else {
                createApplication($form_data);
            }

            setcookie('success', '1', 0, '/');
            setcookie('form_errors', '', time() - 3600, '/');
            setcookie('form_data', '', time() - 3600, '/');
            header('Location: ' . $_SERVER['SCRIPT_NAME']);
            exit;
        } catch (Exception $e) {
            $errors['db'] = 'Ошибка сохранения: ' . $e->getMessage();
        }
    }

    if (!empty($errors)) {
        setcookie('form_errors', json_encode($errors), 0, '/');
        setcookie('form_data', json_encode($form_data), 0, '/');
        header('Location: ' . $_SERVER['SCRIPT_NAME']);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Форма заявки</title>
    <link href="style.css" rel="stylesheet">
</head>
<body>
<div class="container">
    <h1>Анкета участника</h1>

    <?php if ($show_credentials): ?>
        <div class="credentials-box">
            <strong>Ваши учётные данные для редактирования:</strong><br>
            Логин: <code><?= htmlspecialchars($credentials['login']) ?></code><br>
            Пароль: <code><?= htmlspecialchars($credentials['password']) ?></code><br>
            <em>Запомните их! После обновления страницы они не отобразятся снова.</em>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success-message">✔ Данные успешно сохранены!</div>
    <?php endif; ?>

    <?php if (!empty($errors) && empty($errors['db']) && !$success): ?>
        <div class="global-error">Пожалуйста, исправьте ошибки в форме.</div>
    <?php endif; ?>
    <?php if (!empty($errors['db'])): ?>
        <div class="global-error"><?= htmlspecialchars($errors['db']) ?></div>
    <?php endif; ?>

    <?php if (!$is_authorized): ?>
        <div class="auth-section">
            <h3>Уже есть логин?</h3>
            <?php if ($auth_error): ?>
                <div class="auth-error"><?= htmlspecialchars($auth_error) ?></div>
            <?php endif; ?>
            <form method="post" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <input type="hidden" name="action" value="login">
                <input type="text" name="login" placeholder="Логин" required>
                <input type="password" name="password" placeholder="Пароль" required>
                <button type="submit">Войти</button>
            </form>
        </div>
    <?php else: ?>
        <div class="auth-section" style="display: flex; justify-content: space-between; align-items: center;">
            <span>Вы вошли как <strong><?= htmlspecialchars($form_data['email']) ?></strong></span>
            <form method="post" style="margin:0;">
                <input type="hidden" name="action" value="logout">
                <button type="submit" style="width: auto; padding: 8px 20px; background: #95a5a6;">Выйти</button>
            </form>
        </div>
    <?php endif; ?>

    <form method="post" novalidate>
        <input type="hidden" name="action" value="save">

        <div class="form-group">
            <label>ФИО <span class="required">*</span></label>
            <input type="text" name="full_name" value="<?= htmlspecialchars($form_data['full_name']) ?>" class="<?= isset($errors['full_name']) ? 'field-error' : '' ?>">
            <?php if (isset($errors['full_name'])): ?>
                <div class="error-message"><?= htmlspecialchars($errors['full_name']) ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label>Телефон <span class="required">*</span></label>
            <input type="tel" name="phone" value="<?= htmlspecialchars($form_data['phone']) ?>" class="<?= isset($errors['phone']) ? 'field-error' : '' ?>">
            <?php if (isset($errors['phone'])): ?>
                <div class="error-message"><?= htmlspecialchars($errors['phone']) ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label>E-mail <span class="required">*</span></label>
            <input type="email" name="email" value="<?= htmlspecialchars($form_data['email']) ?>" class="<?= isset($errors['email']) ? 'field-error' : '' ?>">
            <?php if (isset($errors['email'])): ?>
                <div class="error-message"><?= htmlspecialchars($errors['email']) ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label>Дата рождения <span class="required">*</span></label>
            <input type="date" name="birth_date" value="<?= htmlspecialchars($form_data['birth_date']) ?>" class="<?= isset($errors['birth_date']) ? 'field-error' : '' ?>">
            <?php if (isset($errors['birth_date'])): ?>
                <div class="error-message"><?= htmlspecialchars($errors['birth_date']) ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label>Пол <span class="required">*</span></label>
            <div class="radio-group">
                <label><input type="radio" name="gender" value="male" <?= $form_data['gender'] === 'male' ? 'checked' : '' ?>> Мужской</label>
                <label><input type="radio" name="gender" value="female" <?= $form_data['gender'] === 'female' ? 'checked' : '' ?>> Женский</label>
            </div>
            <?php if (isset($errors['gender'])): ?>
                <div class="error-message"><?= htmlspecialchars($errors['gender']) ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label>Любимый язык программирования <span class="required">*</span></label>
            <select name="languages[]" multiple class="<?= isset($errors['languages']) ? 'field-error' : '' ?>">
                <?php foreach ($languages as $lang): ?>
                    <option value="<?= $lang['id'] ?>" <?= in_array($lang['id'], $form_data['languages']) ? 'selected' : '' ?>><?= htmlspecialchars($lang['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="hint">Для выбора нескольких удерживайте Ctrl (Cmd на Mac)</div>
            <?php if (isset($errors['languages'])): ?>
                <div class="error-message"><?= htmlspecialchars($errors['languages']) ?></div>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label>Биография <span class="required">*</span></label>
            <textarea name="biography" rows="5" class="<?= isset($errors['biography']) ? 'field-error' : '' ?>"><?= htmlspecialchars($form_data['biography']) ?></textarea>
            <?php if (isset($errors['biography'])): ?>
                <div class="error-message"><?= htmlspecialchars($errors['biography']) ?></div>
            <?php endif; ?>
        </div>

        <div class="checkbox-group">
            <input type="checkbox" name="contract_accepted" id="contract" <?= $form_data['contract_accepted'] ? 'checked' : '' ?>>
            <label for="contract">С контрактом ознакомлен(а) <span class="required">*</span></label>
        </div>
        <?php if (isset($errors['contract_accepted'])): ?>
            <div class="error-message"><?= htmlspecialchars($errors['contract_accepted']) ?></div>
        <?php endif; ?>

        <button type="submit">Сохранить</button>
    </form>
    <p style="margin-top: 20px; text-align: center;"><a href="admin/index.php" class="btn" style="background:#7f8c8d;">Панель администратора</a></p>
</div>
</body>
</html>