<?php
require_once __DIR__ . '/database.php';

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

function getAllApplications(): array {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT * FROM application ORDER BY id DESC");
    return $stmt->fetchAll();
}

function getApplication(int $id): ?array {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM application WHERE id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch() ?: null;
}

function createApplication(array $data, ?string $login = null, ?string $passwordHash = null): int {
    $pdo = getDB();
    $pdo->beginTransaction();

    try {
        if ($login === null) {
            $baseLogin = strstr($data['email'], '@', true) ?: 'user';
            $baseLogin = preg_replace('/[^a-zA-Z0-9_]/', '', $baseLogin);
            $baseLogin = substr($baseLogin, 0, 30);
            $login = $baseLogin . random_int(1000, 9999);
            while ($pdo->query("SELECT COUNT(*) FROM application WHERE login = " . $pdo->quote($login))->fetchColumn() > 0) {
                $login = $baseLogin . random_int(1000, 9999);
            }
        }
        if ($passwordHash === null) {
            $plainPassword = substr(bin2hex(random_bytes(6)), 0, 10);
            $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);
            $_SESSION['credentials'] = ['login' => $login, 'password' => $plainPassword];
        }

        $stmt = $pdo->prepare("
            INSERT INTO application 
            (full_name, phone, email, birth_date, gender, biography, contract_accepted, login, password_hash)
            VALUES (:fn, :ph, :em, :bd, :g, :bio, :ca, :login, :phash)
        ");
        $stmt->execute([
            ':fn'    => $data['full_name'],
            ':ph'    => $data['phone'],
            ':em'    => $data['email'],
            ':bd'    => $data['birth_date'],
            ':g'     => $data['gender'],
            ':bio'   => $data['biography'],
            ':ca'    => $data['contract_accepted'] ? 1 : 0,
            ':login' => $login,
            ':phash' => $passwordHash
        ]);
        $appId = $pdo->lastInsertId();

        $langStmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (:aid, :lid)");
        foreach ($data['languages'] as $langId) {
            $langStmt->execute([':aid' => $appId, ':lid' => (int)$langId]);
        }

        $pdo->commit();
        return $appId;
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function updateApplication(int $id, array $data): void {
    $pdo = getDB();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("
            UPDATE application SET
                full_name = :fn,
                phone = :ph,
                email = :em,
                birth_date = :bd,
                gender = :g,
                biography = :bio,
                contract_accepted = :ca
            WHERE id = :id
        ");
        $stmt->execute([
            ':fn'  => $data['full_name'],
            ':ph'  => $data['phone'],
            ':em'  => $data['email'],
            ':bd'  => $data['birth_date'],
            ':g'   => $data['gender'],
            ':bio' => $data['biography'],
            ':ca'  => $data['contract_accepted'] ? 1 : 0,
            ':id'  => $id
        ]);

        $pdo->prepare("DELETE FROM application_languages WHERE application_id = :aid")->execute([':aid' => $id]);
        $langStmt = $pdo->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (:aid, :lid)");
        foreach ($data['languages'] as $langId) {
            $langStmt->execute([':aid' => $id, ':lid' => (int)$langId]);
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function deleteApplication(int $id): void {
    $pdo = getDB();
    $stmt = $pdo->prepare("DELETE FROM application WHERE id = :id");
    $stmt->execute([':id' => $id]);
}

function getLanguagesStatistics(): array {
    $pdo = getDB();
    $sql = "
        SELECT pl.name AS language, COUNT(al.language_id) AS cnt
        FROM programming_languages pl
        LEFT JOIN application_languages al ON pl.id = al.language_id
        GROUP BY pl.id, pl.name
        ORDER BY cnt DESC, pl.name
    ";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

function getAllLanguages(): array {
    $pdo = getDB();
    $stmt = $pdo->query("SELECT id, name FROM programming_languages ORDER BY name");
    return $stmt->fetchAll();
}

function getUserLanguageIds(int $userId): array {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT language_id FROM application_languages WHERE application_id = :aid");
    $stmt->execute([':aid' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}