<?php
session_start();

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/application.php';

$admin_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_login') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $error = null;

    if ($login === '' || $password === '') {
        $error = 'Введите логин и пароль';
    } else {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT password_hash FROM admins WHERE login = :login");
        $stmt->execute([':login' => $login]);
        $admin = $stmt->fetch();
        if ($admin && password_verify($password, $admin['password_hash'])) {
            $_SESSION['admin_logged_in'] = true;
            header('Location: index.php');
            exit;
        } else {
            $error = 'Неверный логин или пароль';
        }
    }
    $_SESSION['admin_login_error'] = $error;
    header('Location: index.php');
    exit;
}

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    header('Location: index.php');
    exit;
}

if ($admin_logged_in) {
    $applications = getAllApplications();
    $statistics = getLanguagesStatistics();
    $languagesList = getAllLanguages();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Панель администратора</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .admin-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .admin-table th, .admin-table td { border: 1px solid #ddd; padding: 8px 10px; text-align: left; vertical-align: top; }
        .admin-table th { background: #34495e; color: white; }
        .btn { display: inline-block; padding: 5px 10px; margin: 2px; background: #3498db; color: white; text-decoration: none; border-radius: 4px; font-size: 0.85rem; }
        .btn-danger { background: #e74c3c; }
        .btn-warning { background: #f39c12; }
        .stats-box { background: #ecf0f1; padding: 15px; border-radius: 8px; margin-bottom: 30px; }
        .stats-box ul { margin: 0; padding-left: 20px; }
        .logout-link { float: right; margin-top: 10px; }
    </style>
</head>
<body>
<div class="container" style="max-width: 1200px;">
    <?php if (!$admin_logged_in): ?>
        <h1>Вход в панель администратора</h1>
        <?php if (isset($_SESSION['admin_login_error'])): ?>
            <div class="global-error"><?= htmlspecialchars($_SESSION['admin_login_error']) ?></div>
            <?php unset($_SESSION['admin_login_error']); ?>
        <?php endif; ?>
        <form method="post" style="max-width: 400px; margin: 0 auto;">
            <input type="hidden" name="action" value="admin_login">
            <div class="form-group">
                <label>Логин</label>
                <input type="text" name="login" required>
            </div>
            <div class="form-group">
                <label>Пароль</label>
                <input type="password" name="password" required>
            </div>
            <button type="submit">Войти</button>
        </form>
    <?php else: ?>
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h1>Панель администратора</h1>
            <a href="?logout=1" class="btn btn-danger" style="background:#95a5a6;">Выйти</a>
        </div>

        <div class="stats-box">
            <h3>Статистика: количество пользователей, любящих каждый язык</h3>
            <?php if (empty($statistics)): ?>
                <p>Нет данных.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($statistics as $stat): ?>
                        <li><strong><?= htmlspecialchars($stat['language']) ?>:</strong> <?= (int)$stat['cnt'] ?> чел.</li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <h3>Все заявки пользователей</h3>
        <?php if (empty($applications)): ?>
            <p>Пока нет ни одной заявки.</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr><th>ID</th><th>ФИО</th><th>Email</th><th>Телефон</th><th>Дата рожд.</th><th>Пол</th><th>Биография (сокращ.)</th><th>Языки</th><th>Действия</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($applications as $app): 
                        $langIds = getUserLanguageIds($app['id']);
                        $langNames = [];
                        foreach ($languagesList as $lang) {
                            if (in_array($lang['id'], $langIds)) $langNames[] = htmlspecialchars($lang['name']);
                        }
                        $bioShort = mb_strlen($app['biography']) > 100 ? mb_substr($app['biography'], 0, 100) . '…' : $app['biography'];
                    ?>
                        <tr>
                            <td><?= $app['id'] ?></td>
                            <td><?= htmlspecialchars($app['full_name']) ?></td>
                            <td><?= htmlspecialchars($app['email']) ?></td>
                            <td><?= htmlspecialchars($app['phone']) ?></td>
                            <td><?= htmlspecialchars($app['birth_date']) ?></td>
                            <td><?= $app['gender'] === 'male' ? 'Мужской' : 'Женский' ?></td>
                            <td><?= htmlspecialchars($bioShort) ?></td>
                            <td><?= implode(', ', $langNames) ?></td>
                            <td style="white-space: nowrap;">
                                <a href="edit.php?id=<?= $app['id'] ?>" class="btn btn-warning">Редактировать</a>
                                <a href="delete.php?id=<?= $app['id'] ?>" class="btn btn-danger" onclick="return confirm('Удалить заявку пользователя <?= htmlspecialchars($app['full_name']) ?>?')">Удалить</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <p><a href="../index.php" class="btn" style="background:#7f8c8d;">← Вернуться на главную</a></p>
    <?php endif; ?>
</div>
</body>
</html>