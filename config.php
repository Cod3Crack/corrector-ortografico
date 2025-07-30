<?php
// Archivo de configuración para las API Keys
// Este archivo puede ser incluido en chat.php para mayor organización

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

// Configuración adicional del sistema
$config = [
    'max_retries_per_key' => 2,
    'timeout_seconds' => 20,
    'temperature' => 0.3,
    'max_tokens' => 2048
];

// Función para obtener una API Key aleatoria (útil para distribuir carga)
function getRandomApiKey($apiKeys) {
    return $apiKeys[array_rand($apiKeys)];
}

// Función para verificar si las API Keys están configuradas
function validateApiKeys($apiKeys) {
    if (empty($apiKeys) || count($apiKeys) === 0) {
        return false;
    }
    
    // Verificar que todas las API Keys tengan el formato correcto
    foreach ($apiKeys as $key) {
        if (!preg_match('/^AIza[0-9A-Za-z_-]{35}$/', $key)) {
            return false;
        }
    }
    
    return true;
}
?> 