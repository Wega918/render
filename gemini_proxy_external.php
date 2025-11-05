<?php
// gemini_proxy_external.php (На ВНЕШНЕМ ПРОКСИ-СЕРВЕРЕ, НАПРИМЕР, RENDER)

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// --- 1. НАСТРОЙКА КЛЮЧА GEMINI API ---
// КЛЮЧ СЧИТЫВАЕТСЯ ИЗ ПЕРЕМЕННОЙ ОКРУЖЕНИЯ RENDER (GEMINI_API_KEY)
if (!getenv('GEMINI_API_KEY')) {
    http_response_code(500);
    echo json_encode(['error' => 'API Key environment variable is not set.']);
    exit;
}
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY')); 
define('GEMINI_MODEL', 'gemini-2.5-flash'); 
// ----------------------------------------

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Метод не поддерживается']);
    exit;
}

$input_json = file_get_contents("php://input");
$input_data = json_decode($input_json, true);

if (!isset($input_data['messages'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Неверный формат входных данных']);
    exit;
}

$messages_to_gemini = $input_data['messages'];

// --- 2. ВЫЗОВ GEMINI API через cURL ---

$curl = curl_init();
$api_url = "https://generativelanguage.googleapis.com/v1beta/models/" . GEMINI_MODEL . ":generateContent?key=" . GEMINI_API_KEY;

// ИСПРАВЛЕНО: 'config' заменено на 'generationConfig'
$request_payload = json_encode([
    'contents' => $messages_to_gemini,
    'generationConfig' => [ 
        'temperature' => 0.5,
    ],
]);

curl_setopt_array($curl, [
    CURLOPT_URL => $api_url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30, 
    CURLOPT_CUSTOMREQUEST => "POST",
    CURLOPT_POSTFIELDS => $request_payload,
    CURLOPT_HTTPHEADER => ["Content-Type: application/json", "Accept: application/json"],
    // !!! ВРЕМЕННО ОТКЛЮЧАЕМ ПРОВЕРКУ SSL ДЛЯ ДИАГНОСТИКИ !!!
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

// --- 3. ОБРАБОТКА ОТВЕТА И ВОЗВРАТ НА ИГРОВОЙ ХОСТИНГ ---

if ($err) {
    http_response_code(502);
    echo json_encode(['error' => 'Прокси cURL Error: ' . $err]);
    exit;
}

$response_data = json_decode($response, true);

if (isset($response_data['error'])) {
    http_response_code(500);
    echo json_encode(['error' => 'Gemini API Error: ' . ($response_data['error']['message'] ?? 'Unknown error')]);
    exit;
}

$assistant_response = $response_data['candidates'][0]['content']['parts'][0]['text'] ?? "Извините, я не смог сгенерировать ответ.";

echo json_encode(['success' => true, 'response' => $assistant_response]);
?>
