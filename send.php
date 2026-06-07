<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$rawBody = file_get_contents('php://input') ?: '';
$data = json_decode($rawBody, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad_json']);
    exit;
}

if (!empty($data['company'])) {
    echo json_encode(['ok' => true]);
    exit;
}

function clean_text($value, int $limit = 500): string
{
    $value = is_scalar($value) ? (string) $value : '';
    $value = trim($value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    $value = str_replace(["\r", "\n"], ' ', $value);
    return mb_substr($value, 0, $limit, 'UTF-8');
}

$phone = clean_text($data['phone'] ?? '', 40);
$site = clean_text($data['site'] ?? '', 300);
$source = clean_text($data['source'] ?? 'Форма на сайте', 120);
$page = clean_text($data['page'] ?? '', 300);

$phoneDigits = preg_replace('/\D+/', '', $phone) ?? '';
if (strlen($phoneDigits) < 10) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'bad_phone']);
    exit;
}

$to = 'info@allerhand.ru';
$subject = 'Новая заявка с seodental.ru';
$date = date('d.m.Y H:i:s');
$ip = clean_text($_SERVER['REMOTE_ADDR'] ?? '', 80);
$userAgent = clean_text($_SERVER['HTTP_USER_AGENT'] ?? '', 300);

$message = implode("\n", [
    'Новая заявка с seodental.ru',
    '',
    'Телефон: ' . $phone,
    'Сайт клиники: ' . ($site !== '' ? $site : 'не указан'),
    'Форма: ' . $source,
    'Страница: ' . ($page !== '' ? $page : 'не указана'),
    'Дата: ' . $date,
    'IP: ' . ($ip !== '' ? $ip : 'не определён'),
    'User-Agent: ' . ($userAgent !== '' ? $userAgent : 'не определён'),
]);

$encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'From: seodental.ru <no-reply@seodental.ru>',
    'Reply-To: info@allerhand.ru',
];

$sent = mail($to, $encodedSubject, $message, implode("\r\n", $headers));

if (!$sent) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'mail_failed']);
    exit;
}

echo json_encode(['ok' => true]);
