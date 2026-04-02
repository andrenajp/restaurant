<?php
/**
 * Envoie un SMS via l'API Twilio REST (sans SDK, juste cURL).
 * Retourne true si succès, false si erreur ou si Twilio non configuré.
 */
function send_sms(string $to, string $message): bool {
    $sid   = env('TWILIO_SID', '');
    $token = env('TWILIO_TOKEN', '');
    $from  = env('TWILIO_FROM', '');

    // Si non configuré ou placeholder → log + skip silencieux
    if (!$sid || !$token || !$from
        || str_starts_with($sid, 'AC00')
        || str_contains($token, 'xxxx')) {
        error_log("[SMS] Non configuré — message non envoyé à $to : $message");
        return false;
    }

    $ch = curl_init("https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_USERPWD        => "$sid:$token",
        CURLOPT_POSTFIELDS     => http_build_query([
            'From' => $from,
            'To'   => $to,
            'Body' => $message,
        ]),
    ]);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 201) {
        $data = json_decode($response, true);
        error_log("[SMS] Erreur Twilio $http_code : " . ($data['message'] ?? $response));
        return false;
    }
    return true;
}
