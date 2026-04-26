<?php
/**
 * Eigenes Bild hochladen (Admin)
 * POST /api/upload.php  (multipart/form-data, Felder: image, csrf_token)
 * Bild wird auf max. 200 px Breite skaliert.
 */
define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method Not Allowed'], 405);
}
if (!Auth::check()) {
    jsonResponse(['success' => false, 'error' => 'Nicht autorisiert'], 401);
}
Auth::requireCsrf();

if (!function_exists('imagecreatefromjpeg')) {
    jsonResponse(['success' => false, 'error' => 'GD-Bibliothek nicht verfügbar'], 500);
}

$file = $_FILES['image'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    $errMsg = match($file['error'] ?? -1) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Datei zu groß',
        UPLOAD_ERR_NO_FILE                        => 'Keine Datei ausgewählt',
        default                                   => 'Upload-Fehler',
    };
    jsonResponse(['success' => false, 'error' => $errMsg], 400);
}

// Max. 5 MB
if ($file['size'] > 5 * 1024 * 1024) {
    jsonResponse(['success' => false, 'error' => 'Datei zu groß (max. 5 MB)'], 400);
}

// MIME-Type per Magic Bytes prüfen
$finfo    = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($mimeType, $allowed, true)) {
    jsonResponse(['success' => false, 'error' => 'Ungültiger Dateityp. Erlaubt: JPG, PNG, GIF, WEBP'], 400);
}

// Upload-Verzeichnis sicherstellen
$uploadDir = APP_ROOT . '/public/uploads/';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
    jsonResponse(['success' => false, 'error' => 'Upload-Verzeichnis nicht erstellbar'], 500);
}

// Eindeutiger Dateiname
$ext      = match($mimeType) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
    default      => 'png',
};
$filename = 'img_' . bin2hex(random_bytes(12)) . '.' . $ext;
$destPath = $uploadDir . $filename;

// Bild laden
$src = match($mimeType) {
    'image/jpeg' => @imagecreatefromjpeg($file['tmp_name']),
    'image/png'  => @imagecreatefrompng($file['tmp_name']),
    'image/gif'  => @imagecreatefromgif($file['tmp_name']),
    'image/webp' => @imagecreatefromwebp($file['tmp_name']),
    default      => false,
};
if (!$src) {
    jsonResponse(['success' => false, 'error' => 'Bild konnte nicht geladen werden'], 500);
}

$origW   = imagesx($src);
$origH   = imagesy($src);
$maxW    = 200;

if ($origW > $maxW) {
    $newW = $maxW;
    $newH = (int) round($origH * $maxW / $origW);
} else {
    $newW = $origW;
    $newH = $origH;
}

$dst = imagecreatetruecolor($newW, $newH);

// Transparenz erhalten (PNG/GIF/WEBP)
imagealphablending($dst, false);
imagesavealpha($dst, true);
$transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
imagefilledrectangle($dst, 0, 0, $newW - 1, $newH - 1, $transparent);
imagealphablending($dst, true);

imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

$saved = match($mimeType) {
    'image/jpeg' => imagejpeg($dst, $destPath, 90),
    'image/png'  => imagepng($dst, $destPath),
    'image/gif'  => imagegif($dst, $destPath),
    'image/webp' => imagewebp($dst, $destPath, 90),
    default      => false,
};

imagedestroy($src);
imagedestroy($dst);

if (!$saved) {
    jsonResponse(['success' => false, 'error' => 'Bild konnte nicht gespeichert werden'], 500);
}

jsonResponse([
    'success'   => true,
    'id'        => -random_int(1, 999_999_999),
    'image_url' => '/uploads/' . $filename,
]);
