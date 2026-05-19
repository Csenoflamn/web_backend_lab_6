<?php
session_start();

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/application.php';

if (!function_exists('mb_strlen')) {
    function mb_strlen($str, $encoding = 'UTF-8') {
        return strlen($str);
    }
}

if (!function_exists('mb_substr')) {
    function mb_substr($str, $start, $length = null, $encoding = 'UTF-8') {
        return substr($str, $start, $length);
    }
}

$pdo = getDB();

$stmt = $pdo->query("SELECT COUNT(*) FROM admins");
$admins_count = $stmt->fetchColumn();
$need_setup = ($admins_count == 0);

$setup_error = null;
if ($need_setup && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_admin'])) {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if ($login === '' || $password === '') {
        $setup_error = 'Заполните все поля';
    } elseif ($password !== $confirm_password) {
        $setup_error = 'Пароли не совпадают';
    } elseif (strlen($password) < 6) {
        $setup_error = 'Пароль должен содержать минимум 6 символов';
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $pdo->prepare("INSERT INTO admins (login, password_hash) VALUES (:login, :hash)");
            $stmt->execute([':login' => $login, ':hash' => $password_hash]);
            $_SESSION['admin_logged_in'] = true;
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            $setup_error = 'Ошибка создания администратора: ' . $e->getMessage();
        }
    }
}

$admin_logged_in = false;
if (!$need_setup) {
    $admin_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

$login_error = null;
if (!$need_setup && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'admin_login') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($login === '' || $password === '') {
        $login_error = 'Введите логин и пароль';
    } else {
        $stmt = $pdo->prepare("SELECT password_hash FROM admins WHERE login = :login");
        $stmt->execute([':login' => $login]);
        $admin = $stmt->fetch();
        if ($admin && password_verify($password, $admin['password_hash'])) {
            $_SESSION['admin_logged_in'] = true;
            header('Location: index.php');
            exit;
        } else {
            $login_error = 'Неверный логин или пароль';
        }
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    header('Location: index.php');
    exit;
}

function safe_substr($str, $max_length = 100) {
    if (strlen($str) <= $max_length) {
        return htmlspecialchars($str);
    }

    $substr = substr($str, 0, $max_length);
    $last_space = strrpos($substr, ' ');
    
    if ($last_space !== false && $last_space > $max_length - 20) {
        $substr = substr($substr, 0, $last_space);
    }
    
    return htmlspecialchars($substr) . '…';
}

$applications = [];
$statistics = [];
$languagesList = [];

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель администратора</title>
    <link rel="stylesheet" href="../style.css">
    <style>
        .admin-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 30px; 
            font-size: 14px;
        }
        .admin-table th, .admin-table td { 
            border: 1px solid #ddd; 
            padding: 8px 10px; 
            text-align: left; 
            vertical-align: top; 
        }
        .admin-table th { 
            background: #34495e; 
            color: white; 
            font-weight: 600;
        }
        .admin-table tr:hover {
            background-color: #f5f5f5;
        }
        .btn { 
            display: inline-block; 
            padding: 5px 10px; 
            margin: 2px; 
            background: #3498db; 
            color: white; 
            text-decoration: none; 
            border-radius: 4px; 
            font-size: 0.85rem; 
            border: none;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn:hover {
            background: #2980b9;
        }
        .btn-danger { 
            background: #e74c3c; 
        }
        .btn-danger:hover {
            background: #c0392b;
        }
        .btn-warning { 
            background: #f39c12; 
        }
        .btn-warning:hover {
            background: #e67e22;
        }
        .stats-box { 
            background: #ecf0f1; 
            padding: 15px 20px; 
            border-radius: 8px; 
            margin-bottom: 30px; 
        }
        .stats-box h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #2c3e50;
        }
        .stats-box ul { 
            margin: 0; 
            padding-left: 20px; 
        }
        .stats-box li {
            margin: 5px 0;
        }
        .logout-link { 
            float: right; 
            margin-top: 10px; 
        }
        .setup-box {
            max-width: 500px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .setup-box h1 {
            margin-bottom: 20px;
            color: #2c3e50;
        }
        .setup-box p {
            margin-bottom: 20px;
            color: #7f8c8d;
        }
        .login-box {
            max-width: 400px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .login-box h1 {
            margin-bottom: 20px;
            color: #2c3e50;
        }
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #ecf0f1;
        }
        .admin-header h1 {
            margin: 0;
        }
        .action-buttons {
            white-space: nowrap;
        }
        .badge {
            background: #3498db;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
        }
        .bio-cell {
            max-width: 250px;
            word-wrap: break-word;
            font-size: 13px;
            line-height: 1.4;
        }
        @media (max-width: 768px) {
            .admin-table {
                font-size: 12px;
            }
            .admin-table th, .admin-table td {
                padding: 5px;
            }
            .btn {
                font-size: 11px;
                padding: 3px 6px;
            }
            .bio-cell {
                max-width: 150px;
            }
        }
    </style>
</head>
<body>
<div class="container" style="max-width: 1200px;">
    
    <?php if ($need_setup): ?>
        <div class="setup-box">
            <h1>Установка администратора</h1>
            <p>Создайте первого администратора для доступа к панели управления.</p>
            
            <?php if ($setup_error): ?>
                <div class="global-error" style="margin-bottom: 20px;"><?= htmlspecialchars($setup_error) ?></div>
            <?php endif; ?>
            
            <form method="post">
                <div class="form-group">
                    <label>Логин <span class="required">*</span></label>
                    <input type="text" name="login" value="admin" required>
                    <small class="hint">Рекомендуемый логин: admin</small>
                </div>
                
                <div class="form-group">
                    <label>Пароль <span class="required">*</span></label>
                    <input type="password" name="password" required>
                    <small class="hint">Минимум 6 символов</small>
                </div>
                
                <div class="form-group">
                    <label>Подтверждение пароля <span class="required">*</span></label>
                    <input type="password" name="confirm_password" required>
                </div>
                
                <button type="submit" name="setup_admin" style="margin-top: 10px;">Создать администратора</button>
            </form>
        </div>
        
    <?php elseif (!$admin_logged_in): ?>
        <div class="login-box">
            <h1>Вход в панель администратора</h1>
            
            <?php if ($login_error): ?>
                <div class="global-error" style="margin-bottom: 20px;"><?= htmlspecialchars($login_error) ?></div>
            <?php endif; ?>
            
            <form method="post">
                <input type="hidden" name="action" value="admin_login">
                <div class="form-group">
                    <label>Логин</label>
                    <input type="text" name="login" required autofocus>
                </div>
                <div class="form-group">
                    <label>Пароль</label>
                    <input type="password" name="password" required>
                </div>
                <button type="submit">Войти</button>
            </form>
            <p style="margin-top: 20px; text-align: center;">
                <a href="../index.php" style="color: #3498db;">← Вернуться на главную</a>
            </p>
        </div>
        
    <?php else: ?>
        <div class="admin-header">
            <h1>Панель администратора</h1>
            <a href="?logout=1" class="btn btn-danger" style="background:#95a5a6;">Выйти</a>
        </div>

        <div class="stats-box">
            <h3>Статистика: количество пользователей, любящих каждый язык</h3>
            <?php if (empty($statistics)): ?>
                <p style="color: #7f8c8d; margin: 0;">Нет данных о языках.</p>
            <?php else: ?>
                <ul>
                    <?php foreach ($statistics as $stat): ?>
                        <li>
                            <strong><?= htmlspecialchars($stat['language']) ?>:</strong> 
                            <span class="badge"><?= (int)$stat['cnt'] ?> чел.</span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <h3>Все заявки пользователей</h3>
        
        <?php if (empty($applications)): ?>
            <div style="background: #f8f9fa; padding: 20px; text-align: center; border-radius: 8px;">
                <p style="margin: 0; color: #7f8c8d;">Пока нет ни одной заявки.</p>
                <p style="margin-top: 10px;">
                    <a href="../index.php" class="btn" style="background:#3498db;">Создать первую заявку</a>
                </p>
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>ФИО</th>
                            <th>Email</th>
                            <th>Телефон</th>
                            <th>Дата рожд.</th>
                            <th>Пол</th>
                            <th>Биография</th>
                            <th>Языки</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): 
                            $langIds = getUserLanguageIds($app['id']);
                            $langNames = [];
                            foreach ($languagesList as $lang) {
                                if (in_array($lang['id'], $langIds)) {
                                    $langNames[] = htmlspecialchars($lang['name']);
                                }
                            }
                            $bioDisplay = safe_substr($app['biography'], 100);
                            $bioDisplay = nl2br($bioDisplay);
                        ?>
                            <tr>
                                <td style="text-align: center; font-weight: bold;"><?= $app['id'] ?></td>
                                <td><strong><?= htmlspecialchars($app['full_name']) ?></strong></td>
                                <td><?= htmlspecialchars($app['email']) ?></td>
                                <td><?= htmlspecialchars($app['phone']) ?></td>
                                <td><?= htmlspecialchars($app['birth_date']) ?></td>
                                <td><?= $app['gender'] === 'male' ? 'Мужской' : 'Женский' ?></td>
                                <td class="bio-cell"><?= $bioDisplay ?></td>
                                <td><?= !empty($langNames) ? implode(', ', $langNames) : '<em style="color:#999;">нет</em>' ?></td>
                                <td class="action-buttons">
                                    <a href="edit.php?id=<?= $app['id'] ?>" class="btn btn-warning">Ред.</a>
                                    <a href="delete.php?id=<?= $app['id'] ?>" class="btn btn-danger" onclick="return confirm('Удалить заявку пользователя «<?= htmlspecialchars($app['full_name']) ?>»?\nЭто действие нельзя отменить!')">Удал.</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top: 20px; text-align: center;">
                <p style="color: #7f8c8d; font-size: 14px;">
                    Всего заявок: <strong><?= count($applications) ?></strong>
                </p>
            </div>
        <?php endif; ?>
        
        <p style="margin-top: 30px; text-align: center;">
            <a href="../index.php" class="btn" style="background:#7f8c8d;">← Вернуться на главную страницу</a>
        </p>
        
    <?php endif; ?>
    
</div>
</body>
</html>