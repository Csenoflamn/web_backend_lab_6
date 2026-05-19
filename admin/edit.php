<?php
session_start();
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/application.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$application = getApplication($id);
if (!$application) {
    die('Заявка не найдена.');
}

$languagesList = getAllLanguages();
$selectedLanguages = getUserLanguageIds($id);

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'full_name'         => trim($_POST['full_name'] ?? ''),
        'phone'             => trim($_POST['phone'] ?? ''),
        'email'             => trim($_POST['email'] ?? ''),
        'birth_date'        => trim($_POST['birth_date'] ?? ''),
        'gender'            => $_POST['gender'] ?? '',
        'biography'         => trim($_POST['biography'] ?? ''),
        'contract_accepted' => isset($_POST['contract_accepted']),
        'languages'         => $_POST['languages'] ?? []
    ];

    $errors = validateApplication($data, getDB());

    if (empty($errors)) {
        try {
            updateApplication($id, $data);
            $success = true;
            $application = getApplication($id);
            $selectedLanguages = getUserLanguageIds($id);
        } catch (Exception $e) {
            $errors['db'] = 'Ошибка сохранения: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Редактирование заявки #<?= $id ?></title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>
<div class="container">
    <h1>Редактирование заявки пользователя</h1>
    <?php if ($success): ?>
        <div class="success-message">✓ Данные успешно обновлены!</div>
    <?php endif; ?>
    <?php if (!empty($errors['db'])): ?>
        <div class="global-error"><?= htmlspecialchars($errors['db']) ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="form-group">
            <label>ФИО <span class="required">*</span></label>
            <input type="text" name="full_name" value="<?= htmlspecialchars($application['full_name']) ?>" class="<?= isset($errors['full_name']) ? 'field-error' : '' ?>">
            <?php if (isset($errors['full_name'])): ?>
                <div class="error-message"><?= htmlspecialchars($errors['full_name']) ?></div>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label>Телефон <span class="required">*</span></label>
            <input type="tel" name="phone" value="<?= htmlspecialchars($application['phone']) ?>" class="<?= isset($errors['phone']) ? 'field-error' : '' ?>">
            <?php if (isset($errors['phone'])): ?>
                <div class="error-message"><?= htmlspecialchars($errors['phone']) ?></div>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label>E-mail <span class="required">*</span></label>
            <input type="email" name="email" value="<?= htmlspecialchars($application['email']) ?>" class="<?= isset($errors['email']) ? 'field-error' : '' ?>">
            <?php if (isset($errors['email'])): ?>
                <div class="error-message"><?= htmlspecialchars($errors['email']) ?></div>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label>Дата рождения <span class="required">*</span></label>
            <input type="date" name="birth_date" value="<?= htmlspecialchars($application['birth_date']) ?>" class="<?= isset($errors['birth_date']) ? 'field-error' : '' ?>">
            <?php if (isset($errors['birth_date'])): ?>
                <div class="error-message"><?= htmlspecialchars($errors['birth_date']) ?></div>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label>Пол <span class="required">*</span></label>
            <div class="radio-group">
                <label><input type="radio" name="gender" value="male" <?= $application['gender'] === 'male' ? 'checked' : '' ?>> Мужской</label>
                <label><input type="radio" name="gender" value="female" <?= $application['gender'] === 'female' ? 'checked' : '' ?>> Женский</label>
            </div>
            <?php if (isset($errors['gender'])): ?>
                <div class="error-message"><?= htmlspecialchars($errors['gender']) ?></div>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label>Любимый язык программирования <span class="required">*</span></label>
            <select name="languages[]" multiple class="<?= isset($errors['languages']) ? 'field-error' : '' ?>">
                <?php foreach ($languagesList as $lang): ?>
                    <option value="<?= $lang['id'] ?>" <?= in_array($lang['id'], $selectedLanguages) ? 'selected' : '' ?>><?= htmlspecialchars($lang['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="hint">Для выбора нескольких удерживайте Ctrl (Cmd)</div>
            <?php if (isset($errors['languages'])): ?>
                <div class="error-message"><?= htmlspecialchars($errors['languages']) ?></div>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label>Биография <span class="required">*</span></label>
            <textarea name="biography" rows="5" class="<?= isset($errors['biography']) ? 'field-error' : '' ?>"><?= htmlspecialchars($application['biography']) ?></textarea>
            <?php if (isset($errors['biography'])): ?>
                <div class="error-message"><?= htmlspecialchars($errors['biography']) ?></div>
            <?php endif; ?>
        </div>
        <div class="checkbox-group">
            <input type="checkbox" name="contract_accepted" id="contract" <?= $application['contract_accepted'] ? 'checked' : '' ?>>
            <label for="contract">С контрактом ознакомлен(а) <span class="required">*</span></label>
        </div>
        <?php if (isset($errors['contract_accepted'])): ?>
            <div class="error-message"><?= htmlspecialchars($errors['contract_accepted']) ?></div>
        <?php endif; ?>
        <button type="submit">Сохранить изменения</button>
        <a href="index.php" style="display: inline-block; margin-top: 10px; text-align: center; width: 100%;">← Назад к списку</a>
    </form>
</div>
</body>
</html>