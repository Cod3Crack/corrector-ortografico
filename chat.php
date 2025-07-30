<?php
// Establecer la cabecera para devolver JSON
header('Content-Type: application/json');

// --- CONFIGURACIÓN DE MÚLTIPLES API KEYS ---
// Array de API Keys de Gemini para fallback automático
$apiKeys = [
    'AIzaSyDh7VMx3iIWJWLUjypzoTuZCSSmpGBPX_A',
    'AIzaSyAZVXmhJp4ZGLyGr9nSvnjJ0NoAVvPco-Y',
    'AIzaSyCTIIbrnMt_B3_w0oTE4nP-pC-eLPPODIA',
    'AIzaSyCgD165YrA5Eh9b5gVSi-R_BnHJleonq2Y',
    'AIzaSyBnDaGDJ8q92G61W7qv8WBYaASd3A77sYE',
    'AIzaSyBMJmQaKTIQDdT1UCIjhPo7JM1l5KXJx9E',
    'AIzaSyDkHKHzPkWkzsRHmUmvwBUdcHw0Wqbv0ZQ',
    'AIzaSyChlqSiRbljWspRqRJodIuluk9PLVhQQdU',
    'AIzaSyApSWJEpCaX_JO0qhegjBDz77aNgkHu9ec'
];

// Verificar que hay al menos una API Key válida
if (empty($apiKeys) || count($apiKeys) === 0) {
    http_response_code(500);
    echo json_encode(['error' => 'No se han configurado API Keys. Por favor, añade al menos una API Key válida.']);
    exit;
}

// Verificar que la solicitud sea de tipo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Método no permitido. Solo se aceptan solicitudes POST.']);
    exit;
}

// Obtener el cuerpo de la solicitud JSON
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Verificar si el historial de conversación está presente y es un array
if (!isset($data['history']) || !is_array($data['history'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Datos de entrada inválidos. Se esperaba un objeto JSON con una clave "history" que contenga un array.']);
    exit;
}

$conversationHistory = $data['history'];

// Preparar los datos para la API de Gemini con configuración optimizada para corrección
$postData = [
    'contents' => $conversationHistory,
    'generationConfig' => [
        'temperature' => 0.3, // Temperatura más baja para respuestas más consistentes
        'topK' => 1,
        'topP' => 1,
        'maxOutputTokens' => 2048,
    ],
    'safetySettings' => [
        ['category' => 'HARM_CATEGORY_HARASSMENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
        ['category' => 'HARM_CATEGORY_HATE_SPEECH', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
        ['category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
        ['category' => 'HARM_CATEGORY_DANGEROUS_CONTENT', 'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'],
    ]
];

// Función para hacer la solicitud a la API con una API Key específica
function makeApiRequestWithKey($apiKey, $postData, $maxRetries = 2) {
    $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . $apiKey;
    
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20); // Timeout reducido para fallback más rápido

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);

        if ($curl_error) {
            if ($attempt === $maxRetries) {
                return ['error' => 'Error de conexión', 'details' => $curl_error, 'api_key_used' => substr($apiKey, 0, 10) . '...'];
            }
            sleep(1);
            continue;
        }

        $responseData = json_decode($response, true);
        
        // Si el modelo está sobrecargado, intentar de nuevo con la misma API Key
        if ($httpcode === 503 && isset($responseData['error']['message']) && 
            strpos($responseData['error']['message'], 'overloaded') !== false) {
            if ($attempt === $maxRetries) {
                return ['error' => 'API Key sobrecargada', 'api_key_used' => substr($apiKey, 0, 10) . '...'];
            }
            sleep(1);
            continue;
        }

        // Para otros errores HTTP, devolver el error
        if ($httpcode >= 200 && $httpcode < 300) {
            return ['success' => true, 'data' => $responseData, 'httpcode' => $httpcode, 'api_key_used' => substr($apiKey, 0, 10) . '...'];
        } else {
            return ['error' => 'Error de la API', 'details' => $responseData, 'httpcode' => $httpcode, 'api_key_used' => substr($apiKey, 0, 10) . '...'];
        }
    }
}

// Función principal para intentar con todas las API Keys
function tryAllApiKeys($apiKeys, $postData) {
    $totalKeys = count($apiKeys);
    $attemptedKeys = 0;
    
    foreach ($apiKeys as $index => $apiKey) {
        $attemptedKeys++;
        
        // Log para debugging (opcional)
        error_log("Intentando API Key " . ($index + 1) . " de " . $totalKeys);
        
        $result = makeApiRequestWithKey($apiKey, $postData);
        
        if (isset($result['success'])) {
            // Éxito - devolver resultado
            return $result;
        }
        
        // Si no es la última API Key, continuar con la siguiente
        if ($attemptedKeys < $totalKeys) {
            error_log("API Key " . ($index + 1) . " falló, intentando siguiente...");
            continue;
        }
    }
    
    // Si todas las API Keys fallaron
    return [
        'error' => 'Todas las API Keys han fallado. El servicio puede estar temporalmente no disponible.',
        'attempted_keys' => $attemptedKeys
    ];
}

// Ejecutar la solicitud con todas las API Keys
$result = tryAllApiKeys($apiKeys, $postData);

// Manejar la respuesta
if (isset($result['success'])) {
    $responseData = $result['data'];
    // Extraer el texto de la respuesta del modelo
    $botText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? 'No se pudo obtener una respuesta válida del modelo.';
    
    // Limpiar la respuesta para asegurar que solo contenga el texto corregido
    $botText = trim($botText);
    
    // Si la respuesta contiene explicaciones adicionales, intentar extraer solo el texto corregido
    if (strpos($botText, 'Texto corregido:') !== false) {
        $parts = explode('Texto corregido:', $botText);
        $botText = trim(end($parts));
    }
    
    echo json_encode([
        'response' => $botText,
        'api_key_used' => $result['api_key_used'] ?? 'unknown'
    ]);
} else {
    // Si hay un error, devolverlo
    http_response_code(503);
    echo json_encode($result);
}
?>
