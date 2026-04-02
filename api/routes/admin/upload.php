<?php
// POST /api/admin/upload — upload d'une image produit
// Attend un multipart/form-data avec le champ "image"
// Retourne { url: "/assets/img/products/filename.ext" }

if ($method !== 'POST') json_error('Méthode non supportée', 405);

if (empty($_FILES['image'])) {
    json_error('Aucun fichier reçu', 422);
}

$file  = $_FILES['image'];
$error = $file['error'];

if ($error !== UPLOAD_ERR_OK) {
    $messages = [
        UPLOAD_ERR_INI_SIZE   => 'Fichier trop volumineux (limite serveur)',
        UPLOAD_ERR_FORM_SIZE  => 'Fichier trop volumineux',
        UPLOAD_ERR_PARTIAL    => 'Upload incomplet',
        UPLOAD_ERR_NO_FILE    => 'Aucun fichier',
        UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant',
        UPLOAD_ERR_CANT_WRITE => 'Impossible d\'écrire sur le disque',
    ];
    json_error($messages[$error] ?? 'Erreur upload', 422);
}

// Vérifier le type MIME réel (pas celui envoyé par le client)
$finfo    = new finfo(FILEINFO_MIME_TYPE);
$mime     = $finfo->file($file['tmp_name']);
$allowed  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

if (!isset($allowed[$mime])) {
    json_error('Format non autorisé. Utilisez JPG, PNG, WebP ou GIF.', 422);
}

// Taille max 5 Mo
if ($file['size'] > 5 * 1024 * 1024) {
    json_error('Image trop lourde (max 5 Mo)', 422);
}

$ext      = $allowed[$mime];
$filename = bin2hex(random_bytes(12)) . '.' . $ext;
$dest_dir = __DIR__ . '/../../../public/assets/img/products/';
$dest     = $dest_dir . $filename;

if (!is_dir($dest_dir)) {
    mkdir($dest_dir, 0755, true);
}

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    json_error('Erreur lors de l\'enregistrement du fichier', 500);
}

json_success(['url' => '/assets/img/products/' . $filename]);
