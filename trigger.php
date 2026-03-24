<?php
// --- CONFIGURATION DYNAMIQUE ET PERSISTANCE ---

// 🌟 DÉTECTION DU DOSSIER PERSISTANT (Spécifique à Home Assistant) 🌟
$data_dir = is_dir('/data') ? '/data' : __DIR__ . '/data';

$file = $data_dir . '/codes.json';
$file_settings = $data_dir . '/settings.json';

// Lecture des paramètres (ou valeurs par défaut pour l'Add-on HA)
$settings = file_exists($file_settings) ? json_decode(file_get_contents($file_settings), true) : [];
$ha_ip = !empty($settings['ha_ip']) ? $settings['ha_ip'] : 'homeassistant';
$ha_port = !empty($settings['ha_port']) ? $settings['ha_port'] : '8123';
// -------------------------------

$pin_recu = $_POST['pin'] ?? '';
$data = json_decode(file_get_contents($file), true) ?: [];

if (isset($data[$pin_recu])) {
    $info = $data[$pin_recu];
    $webhook_id = $info['webhook'];

    // 1. Gestion de l'usage unique
    if ($info['type'] === 'unique') {
        unset($data[$pin_recu]);
        file_put_contents($file, json_encode($data));
    }

    // 2. Construction de l'URL vers votre HA (Utilise l'IP/Port dynamique)
    $target_url = "http://$ha_ip:$ha_port/api/webhook/$webhook_id";
    
    $ch = curl_init($target_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); 
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    // Au cas où vous auriez activé le SSL en local
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // HA renvoie généralement 200 ou 201 si le webhook est reçu
    if ($http_code >= 200 && $http_code < 300) {
        echo "OK";
    } else {
        http_response_code(500);
        echo "Erreur HA ($http_code) - Webhook: $webhook_id";
    }
} else {
    http_response_code(401);
    echo "Code invalide ou expiré";
}
?>