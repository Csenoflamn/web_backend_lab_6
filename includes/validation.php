<?php
function validateApplication(array $data, PDO $pdo): array {
    $errors = [];

    $fullName = trim($data['full_name'] ?? '');
    if ($fullName === '') {
        $errors['full_name'] = 'Поле ФИО обязательно.';
    } else {
        if (!preg_match('/^[\p{L}\s\-]+$/u', $fullName)) {
            $errors['full_name'] = 'Недопустимые символы. Разрешены: буквы, пробел, дефис.';
        } else {
            $len = function_exists('mb_strlen') ? mb_strlen($fullName, 'UTF-8') : strlen($fullName);
            if ($len < 5) $errors['full_name'] = 'ФИО слишком короткое (минимум 5 символов).';
            elseif ($len > 150) $errors['full_name'] = 'ФИО не должно превышать 150 символов.';
            $words = preg_split('/\s+/', $fullName);
            if (count($words) < 2) $errors['full_name'] = 'Введите минимум фамилию и имя.';
            elseif (count($words) > 3) $errors['full_name'] = 'ФИО должно содержать не более трёх слов.';
            if (preg_match('/\s{2,}/', $fullName)) $errors['full_name'] = 'ФИО содержит лишние пробелы.';
        }
    }

    $phone = trim($data['phone'] ?? '');
    if ($phone === '') {
        $errors['phone'] = 'Поле Телефон обязательно.';
    } else {
        if (!preg_match('/^[0-9\s\+\-\(\)]+$/', $phone)) {
            $errors['phone'] = 'Недопустимые символы. Разрешены: цифры, пробел, +, -, (, ).';
        } else {
            $digits = preg_replace('/[^0-9]/', '', $phone);
            if (strlen($phone) > 30) $errors['phone'] = 'Слишком длинный номер (макс. 30 символов).';
            elseif (strlen($digits) < 10) $errors['phone'] = 'Минимум 10 цифр в номере.';
            elseif (strlen($digits) > 15) $errors['phone'] = 'Не более 15 цифр.';
            if (substr_count($phone, '(') !== substr_count($phone, ')')) {
                $errors['phone'] = 'Проверьте скобки.';
            }
        }
    }

    $email = trim($data['email'] ?? '');
    if ($email === '') {
        $errors['email'] = 'Поле E-mail обязательно.';
    } else {
        if (!preg_match('/^[a-zA-Z0-9._%+\-@]+$/', $email)) {
            $errors['email'] = 'Недопустимые символы. Разрешены: латинские буквы, цифры, ., _, %, +, -, @.';
        } else {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Некорректный формат email.';
            } elseif (strlen($email) > 255) {
                $errors['email'] = 'Email слишком длинный (макс. 255 символов).';
            } else {
                [$local, $domain] = explode('@', $email);
                if (strpos($domain, '.') === false) $errors['email'] = 'Домен должен содержать точку.';
                else {
                    $parts = explode('.', $domain);
                    $tld = end($parts);
                    if (!preg_match('/^[a-zA-Z]{2,}$/', $tld)) {
                        $errors['email'] = 'Некорректный домен верхнего уровня.';
                    }
                }
            }
        }
    }

    $birthDate = trim($data['birth_date'] ?? '');
    if ($birthDate === '') {
        $errors['birth_date'] = 'Поле Дата рождения обязательно.';
    } else {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate)) {
            $errors['birth_date'] = 'Недопустимые символы. Используйте формат ГГГГ-ММ-ДД.';
        } else {
            $date = DateTime::createFromFormat('Y-m-d', $birthDate);
            if (!$date || $date->format('Y-m-d') !== $birthDate) {
                $errors['birth_date'] = 'Несуществующая дата.';
            } else {
                $today = new DateTime();
                if ($date > $today) $errors['birth_date'] = 'Дата не может быть в будущем.';
                else {
                    $age = $today->diff($date)->y;
                    if ($age < 18) $errors['birth_date'] = 'Возраст должен быть ≥ 18 лет.';
                    elseif ($age > 120) $errors['birth_date'] = 'Возраст не может превышать 120 лет.';
                    elseif ($date->format('Y') < 1900) $errors['birth_date'] = 'Год рождения не ранее 1900.';
                }
            }
        }
    }

    if (!in_array($data['gender'] ?? null, ['male', 'female'])) {
        $errors['gender'] = 'Выберите пол.';
    }

    $languages = $data['languages'] ?? [];
    if (empty($languages)) {
        $errors['languages'] = 'Выберите хотя бы один язык.';
    } else {
        $placeholders = implode(',', array_fill(0, count($languages), '?'));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM programming_languages WHERE id IN ($placeholders)");
        $stmt->execute($languages);
        if ($stmt->fetchColumn() != count($languages)) {
            $errors['languages'] = 'Выбран некорректный язык.';
        }
    }

    $bio = trim($data['biography'] ?? '');
    if ($bio === '') {
        $errors['biography'] = 'Поле Биография обязательно.';
    } else {
        if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $bio)) {
            $errors['biography'] = 'Недопустимые символы. Разрешены: буквы, цифры, пробелы и знаки препинания.';
        } else {
            $len = function_exists('mb_strlen') ? mb_strlen($bio, 'UTF-8') : strlen($bio);
            if ($len < 10) $errors['biography'] = 'Минимум 10 символов.';
            elseif ($len > 5000) $errors['biography'] = 'Максимум 5000 символов.';
        }
    }

    if (empty($data['contract_accepted'])) {
        $errors['contract_accepted'] = 'Необходимо подтвердить ознакомление с контрактом.';
    }

    return $errors;
}