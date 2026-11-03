<?php
/**
 * CSV-Export: Sitzungsliste oder Stimmen einer Sitzung
 * GET /admin/export.php?type=sessions
 * GET /admin/export.php?type=votes&id=123
 */
define('APP_ROOT', dirname(__DIR__, 2));
require_once APP_ROOT . '/includes/bootstrap.php';
Auth::require();

$type = get('type', 'sessions');
$id   = (int) get('id');
$pdo  = DB::get();

if ($type === 'votes' && $id > 0) {
    $mgr     = new WordCloudManager();
    $session = $mgr->getSession($id);
    if (!$session) {
        setFlash('danger', 'Sitzung nicht gefunden.');
        header('Location: /admin/');
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT arasaac_id, label, COUNT(*) AS vote_count
         FROM wordcloud_votes WHERE session_id = :sid
         GROUP BY arasaac_id, label ORDER BY vote_count DESC"
    );
    $stmt->execute([':sid' => $id]);
    $rows = $stmt->fetchAll();

    $filename = 'stimmen_' . $session['session_code'] . '_' . date('Ymd') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');

    $out = fopen('php://output', 'w');
    // BOM für Excel-Kompatibilität
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Symbol-ID', 'Bezeichnung', 'Stimmen'], ';');
    foreach ($rows as $row) {
        fputcsv($out, [$row['arasaac_id'], $row['label'], $row['vote_count']], ';');
    }
    fclose($out);
    exit;
}

// type=sessions: alle Sitzungen
$stmt = $pdo->query(
    "SELECT s.id, s.session_code, s.title, s.mode, s.status,
            s.is_guest, s.expires_at, s.created_at,
            COUNT(DISTINCT v.participant_token) AS participant_count,
            COUNT(v.id) AS total_votes
     FROM wordcloud_sessions s
     LEFT JOIN wordcloud_votes v ON v.session_id = s.id
     GROUP BY s.id
     ORDER BY s.created_at DESC"
);
$rows = $stmt->fetchAll();

$filename = 'sitzungen_' . date('Ymd_Hi') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');

$modeLabels   = ['symbols' => 'Nur Symbole', 'search' => 'Nur Suche', 'both' => 'Beides'];
$statusLabels = ['active' => 'Aktiv', 'closed' => 'Geschlossen'];

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
fputcsv($out, ['ID', 'Code', 'Titel', 'Modus', 'Status', 'Gast', 'Läuft ab', 'Teilnehmer', 'Stimmen', 'Erstellt'], ';');
foreach ($rows as $row) {
    fputcsv($out, [
        $row['id'],
        $row['session_code'],
        $row['title'],
        $modeLabels[$row['mode']] ?? $row['mode'],
        $statusLabels[$row['status']] ?? $row['status'],
        $row['is_guest'] ? 'Ja' : 'Nein',
        $row['expires_at'] ? date('d.m.Y H:i', strtotime($row['expires_at'])) : '',
        $row['participant_count'],
        $row['total_votes'],
        date('d.m.Y H:i', strtotime($row['created_at'])),
    ], ';');
}
fclose($out);
exit;
