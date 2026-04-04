<?php
/**
 * rate_limit_check — vérifie et incrémente le compteur de tentatives.
 *
 * @param string $key        Identifiant unique (ex: "login:127.0.0.1")
 * @param int    $max        Nombre max de tentatives avant blocage
 * @param int    $window     Fenêtre de comptage en secondes
 * @param int    $block      Durée du blocage en secondes
 *
 * Si la limite est dépassée, renvoie une erreur 429 et stoppe l'exécution.
 */
function rate_limit_check(string $key, int $max, int $window, int $block): void
{
    $db  = db();
    $now = date('Y-m-d H:i:s');

    $stmt = $db->prepare('SELECT * FROM rate_limit WHERE `key` = ?');
    $stmt->execute([$key]);
    $row = $stmt->fetch();

    if ($row) {
        // Encore bloqué ?
        if ($row['blocked_until'] && strtotime($row['blocked_until']) > time()) {
            $retry = ceil((strtotime($row['blocked_until']) - time()) / 60);
            http_response_code(429);
            exit(json_encode([
                'success' => false,
                'error'   => "Trop de tentatives. Réessayez dans {$retry} minute(s).",
            ]));
        }

        // Hors fenêtre → repartir à zéro
        if (strtotime($row['last_attempt']) < time() - $window) {
            $db->prepare(
                'UPDATE rate_limit SET attempts=1, blocked_until=NULL, last_attempt=? WHERE `key`=?'
            )->execute([$now, $key]);
            return;
        }

        // Dans la fenêtre → incrémenter
        $new_attempts = $row['attempts'] + 1;
        if ($new_attempts >= $max) {
            $blocked_until = date('Y-m-d H:i:s', time() + $block);
            $db->prepare(
                'UPDATE rate_limit SET attempts=?, blocked_until=?, last_attempt=? WHERE `key`=?'
            )->execute([$new_attempts, $blocked_until, $now, $key]);
            $retry = ceil($block / 60);
            http_response_code(429);
            exit(json_encode([
                'success' => false,
                'error'   => "Trop de tentatives. Réessayez dans {$retry} minute(s).",
            ]));
        }

        $db->prepare(
            'UPDATE rate_limit SET attempts=?, last_attempt=? WHERE `key`=?'
        )->execute([$new_attempts, $now, $key]);

    } else {
        // Première tentative
        $db->prepare(
            'INSERT INTO rate_limit (`key`, attempts, blocked_until, last_attempt) VALUES (?,1,NULL,?)'
        )->execute([$key, $now]);
    }
}

/**
 * rate_limit_reset — remet à zéro le compteur (ex: après un login réussi).
 */
function rate_limit_reset(string $key): void
{
    db()->prepare('DELETE FROM rate_limit WHERE `key` = ?')->execute([$key]);
}
