<?php
/**
 * WordCloudManager – Geschäftslogik für Sitzungen und Abstimmungen
 */
class WordCloudManager
{
    const MAX_SYMBOLS     = 20;
    const MAX_SYMBOLS_ABS = 50;
    const ARASAAC_API   = 'https://api.arasaac.org/api/pictograms';
    const ARASAAC_CDN   = 'https://static.arasaac.org/pictograms';

    private PDO $db;

    public function __construct()
    {
        $this->db = DB::get();
    }

    // =========================================================
    // Einstellungen
    // =========================================================

    public function getSetting(string $key, string $default = ''): string
    {
        static $cache = [];
        if (array_key_exists($key, $cache)) return $cache[$key];
        try {
            $stmt = $this->db->prepare(
                "SELECT setting_value FROM wordcloud_settings WHERE setting_key = :k"
            );
            $stmt->execute([':k' => $key]);
            $row = $stmt->fetch();
            return $cache[$key] = $row ? $row['setting_value'] : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    public function setSetting(string $key, string $value): void
    {
        $this->db->prepare(
            "INSERT INTO wordcloud_settings (setting_key, setting_value) VALUES (:k, :v)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        )->execute([':k' => $key, ':v' => $value]);
    }

    // =========================================================
    // Sitzungen
    // =========================================================

    public function createSession(string $title, string $mode, array $symbols, ?int $createdBy = null, string $displayMode = 'cloud', int $maxSymbols = 0): array
    {
        $symbols     = array_slice($symbols, 0, self::MAX_SYMBOLS_ABS);
        $code        = $this->generateCode();
        $displayMode = in_array($displayMode, ['cloud','list','umfrage']) ? $displayMode : 'cloud';

        $stmt = $this->db->prepare(
            "INSERT INTO wordcloud_sessions (session_code, title, mode, predefined_symbols, created_by, display_mode, max_symbols)
             VALUES (:code, :title, :mode, :sym, :uid, :dm, :ms)"
        );
        $stmt->execute([
            ':code'  => $code,
            ':title' => $title,
            ':mode'  => $mode,
            ':sym'   => empty($symbols) ? null : json_encode($symbols, JSON_UNESCAPED_UNICODE),
            ':uid'   => $createdBy ?: null,
            ':dm'    => $displayMode,
            ':ms'    => max(0, $maxSymbols),
        ]);

        return ['id' => (int) $this->db->lastInsertId(), 'code' => $code];
    }

    public function createGuestSession(string $title, string $mode, array $symbols, int $hours): array
    {
        $symbols = array_slice($symbols, 0, self::MAX_SYMBOLS);
        $code    = $this->generateCode();
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + max(1, $hours) * 3600);

        $stmt = $this->db->prepare(
            "INSERT INTO wordcloud_sessions
                (session_code, title, mode, predefined_symbols, is_guest, guest_admin_token, expires_at)
             VALUES (:code, :title, :mode, :sym, 1, :tok, :exp)"
        );
        $stmt->execute([
            ':code'  => $code,
            ':title' => $title,
            ':mode'  => $mode,
            ':sym'   => empty($symbols) ? null : json_encode($symbols, JSON_UNESCAPED_UNICODE),
            ':tok'   => $token,
            ':exp'   => $expires,
        ]);

        return ['id' => (int) $this->db->lastInsertId(), 'code' => $code, 'token' => $token];
    }

    public function updateSession(int $id, string $title, string $mode, array $symbols, string $displayMode = 'cloud', int $maxSymbols = 0): bool
    {
        // Vorherige Custom-Images merken, um Waisen zu löschen
        $old       = $this->getSession($id);
        $oldImages = $old ? $this->extractCustomImageUrls($old['predefined_symbols']) : [];

        $symbols     = array_slice($symbols, 0, self::MAX_SYMBOLS_ABS);
        $displayMode = in_array($displayMode, ['cloud','list','umfrage']) ? $displayMode : 'cloud';
        $stmt        = $this->db->prepare(
            "UPDATE wordcloud_sessions
             SET title = :title, mode = :mode, predefined_symbols = :sym, display_mode = :dm, max_symbols = :ms
             WHERE id = :id"
        );
        $result = $stmt->execute([
            ':title' => $title,
            ':mode'  => $mode,
            ':sym'   => empty($symbols) ? null : json_encode($symbols, JSON_UNESCAPED_UNICODE),
            ':dm'    => $displayMode,
            ':ms'    => max(0, $maxSymbols),
            ':id'    => $id,
        ]);

        // Nicht mehr verwendete Bilder löschen
        $newImages = $this->extractCustomImageUrls($symbols);
        foreach (array_diff($oldImages, $newImages) as $url) {
            if (!$this->isImageUsedInAnySession($url, $id)) {
                $this->deleteImageFile($url);
            }
        }

        return $result;
    }

    /**
     * @param string|null $status    null = alle, 'active', 'closed'
     * @param bool|null   $isGuest   null = alle, true = nur Gast, false = nur Normal
     * @param int|null    $ownerId   null = alle, sonst nur Sitzungen dieses Benutzers
     */
    public function getSessions(?string $status = null, ?bool $isGuest = null, ?int $ownerId = null): array
    {
        $conditions = [];
        $params     = [];

        if ($status !== null) {
            $conditions[]        = 's.status = :status';
            $params[':status']   = $status;
        }
        if ($isGuest !== null) {
            $conditions[]        = 's.is_guest = :is_guest';
            $params[':is_guest'] = $isGuest ? 1 : 0;
        }
        if ($ownerId !== null) {
            $conditions[]        = 's.created_by = :owner';
            $params[':owner']    = $ownerId;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $sql   = "SELECT s.*,
                         COUNT(DISTINCT v.participant_token) AS participant_count,
                         COUNT(v.id)                         AS total_votes
                  FROM wordcloud_sessions s
                  LEFT JOIN wordcloud_votes v ON v.session_id = s.id
                  $where
                  GROUP BY s.id
                  ORDER BY s.created_at DESC";

        $stmt = $params ? $this->db->prepare($sql) : $this->db->query($sql);
        if ($params) $stmt->execute($params);

        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['predefined_symbols'] = $this->decodeSymbols($row['predefined_symbols']);
        }
        return $rows;
    }

    public function countActiveGuestSessions(): int
    {
        try {
            return (int) $this->db->query(
                "SELECT COUNT(*) FROM wordcloud_sessions WHERE is_guest = 1 AND status = 'active'"
            )->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function getSession(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM wordcloud_sessions WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['predefined_symbols'] = $this->decodeSymbols($row['predefined_symbols']);
        return $row;
    }

    public function getSessionByCode(string $code): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM wordcloud_sessions WHERE session_code = :code"
        );
        $stmt->execute([':code' => strtoupper(trim($code))]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['predefined_symbols'] = $this->decodeSymbols($row['predefined_symbols']);
        return $row;
    }

    public function getSessionByGuestToken(string $token): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM wordcloud_sessions WHERE guest_admin_token = :tok AND is_guest = 1"
        );
        $stmt->execute([':tok' => $token]);
        $row = $stmt->fetch();
        if (!$row) return null;
        $row['predefined_symbols'] = $this->decodeSymbols($row['predefined_symbols']);
        return $row;
    }

    public function closeSession(int $id): bool
    {
        return $this->setStatus($id, 'closed');
    }

    public function reopenSession(int $id): bool
    {
        return $this->setStatus($id, 'active');
    }

    public function deleteSession(int $id): bool
    {
        $session     = $this->getSession($id);
        $customImages = $session ? $this->extractCustomImageUrls($session['predefined_symbols']) : [];

        $stmt   = $this->db->prepare("DELETE FROM wordcloud_sessions WHERE id = :id");
        $result = $stmt->execute([':id' => $id]);

        // Bilder löschen, die nirgends mehr verwendet werden
        foreach ($customImages as $url) {
            if (!$this->isImageUsedInAnySession($url, 0)) {
                $this->deleteImageFile($url);
            }
        }

        return $result;
    }

    public function resetVotes(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM wordcloud_votes WHERE session_id = :id");
        return $stmt->execute([':id' => $id]);
    }

    /** Abgelaufene Gastsitzungen schließen und nach Aufbewahrungsfrist löschen */
    public function cleanupExpiredGuestSessions(): void
    {
        try {
            // 1. Abgelaufene aktive Sitzungen schließen
            $this->db->exec(
                "UPDATE wordcloud_sessions SET status = 'closed'
                 WHERE is_guest = 1 AND status = 'active' AND expires_at < NOW()"
            );

            // 2. Sitzungen nach Ablauf der Aufbewahrungsfrist löschen
            $retHours = 0;
            try {
                $r = $this->db->prepare(
                    "SELECT setting_value FROM wordcloud_settings WHERE setting_key = 'guest_retention_hours'"
                );
                $r->execute();
                $retHours = max(0, (int) ($r->fetchColumn() ?: 0));
            } catch (\Throwable $e) {}

            if ($retHours > 0) {
                $stmt = $this->db->prepare(
                    "SELECT id FROM wordcloud_sessions
                     WHERE is_guest = 1 AND expires_at < DATE_SUB(NOW(), INTERVAL :h HOUR)"
                );
                $stmt->execute([':h' => $retHours]);
            } else {
                $stmt = $this->db->query(
                    "SELECT id FROM wordcloud_sessions
                     WHERE is_guest = 1 AND expires_at < NOW()"
                );
            }
            foreach ($stmt->fetchAll() as $row) {
                $this->deleteSession((int) $row['id']);
            }
        } catch (\Throwable $e) {}
    }

    // =========================================================
    // Abstimmung
    // =========================================================

    public function toggleVote(int $sessionId, string $token, int $arasaacId, string $label): array
    {
        $stmt = $this->db->prepare(
            "SELECT status, max_symbols FROM wordcloud_sessions WHERE id = :id"
        );
        $stmt->execute([':id' => $sessionId]);
        $session = $stmt->fetch();

        if (!$session) {
            return ['success' => false, 'voted' => false, 'error' => 'Sitzung nicht gefunden'];
        }
        if ($session['status'] !== 'active') {
            return ['success' => false, 'voted' => false, 'error' => 'Sitzung ist geschlossen'];
        }

        $check = $this->db->prepare(
            "SELECT id FROM wordcloud_votes
             WHERE session_id = :sid AND participant_token = :tok AND arasaac_id = :aid"
        );
        $check->execute([':sid' => $sessionId, ':tok' => $token, ':aid' => $arasaacId]);

        if ($check->fetch()) {
            $this->db->prepare(
                "DELETE FROM wordcloud_votes
                 WHERE session_id = :sid AND participant_token = :tok AND arasaac_id = :aid"
            )->execute([':sid' => $sessionId, ':tok' => $token, ':aid' => $arasaacId]);
            return ['success' => true, 'voted' => false];
        }

        // Limit pro Teilnehmer prüfen
        $maxVotes = (int)($session['max_symbols'] ?? 0);
        if ($maxVotes > 0) {
            $cnt = $this->db->prepare(
                "SELECT COUNT(*) FROM wordcloud_votes WHERE session_id = :sid AND participant_token = :tok"
            );
            $cnt->execute([':sid' => $sessionId, ':tok' => $token]);
            if ((int)$cnt->fetchColumn() >= $maxVotes) {
                return ['success' => false, 'voted' => false,
                        'error' => 'Limit: maximal ' . $maxVotes . ' Auswahl' . ($maxVotes !== 1 ? 'en' : '')];
            }
        }

        $this->db->prepare(
            "INSERT INTO wordcloud_votes (session_id, participant_token, arasaac_id, label)
             VALUES (:sid, :tok, :aid, :label)"
        )->execute([
            ':sid'   => $sessionId,
            ':tok'   => $token,
            ':aid'   => $arasaacId,
            ':label' => mb_substr($label, 0, 255),
        ]);
        return ['success' => true, 'voted' => true];
    }

    public function getCloudData(int $sessionId): array
    {
        $stmt = $this->db->prepare(
            "SELECT arasaac_id, label, COUNT(*) AS vote_count
             FROM wordcloud_votes
             WHERE session_id = :sid
             GROUP BY arasaac_id, label
             ORDER BY vote_count DESC, label ASC"
        );
        $stmt->execute([':sid' => $sessionId]);
        return $stmt->fetchAll();
    }

    public function getParticipantVotes(int $sessionId, string $token): array
    {
        $stmt = $this->db->prepare(
            "SELECT arasaac_id FROM wordcloud_votes
             WHERE session_id = :sid AND participant_token = :tok"
        );
        $stmt->execute([':sid' => $sessionId, ':tok' => $token]);
        return array_map('intval', array_column($stmt->fetchAll(), 'arasaac_id'));
    }

    public function activeParticipants(int $sessionId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(DISTINCT participant_token) AS n
             FROM wordcloud_votes
             WHERE session_id = :sid AND voted_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
        );
        $stmt->execute([':sid' => $sessionId]);
        return (int) $stmt->fetchColumn();
    }

    public function deleteUploadedImage(string $imageUrl, int $excludeSessionId = 0): bool
    {
        if (!preg_match('#^/uploads/img_[a-f0-9_.]+\.(jpg|png|gif|webp)$#', $imageUrl)) return false;
        if ($this->isImageUsedInAnySession($imageUrl, $excludeSessionId)) return false;
        $this->deleteImageFile($imageUrl);
        return true;
    }

    // =========================================================
    // ARASAAC-API
    // =========================================================

    public function searchArasaac(string $query, string $lang = 'de'): array
    {
        $lang = preg_replace('/[^a-z]/', '', strtolower($lang)) ?: 'de';
        $url  = self::ARASAAC_API . '/' . $lang . '/search/' . rawurlencode($query);

        $ctx = stream_context_create([
            'http' => [
                'timeout'       => 8,
                'ignore_errors' => true,
                'header'        => "User-Agent: ARASAAC-Wortwolke/1.0\r\n",
            ],
        ]);

        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) return [];

        $data = json_decode($raw, true);
        if (!is_array($data)) return [];

        $results = [];
        foreach (array_slice($data, 0, 30) as $item) {
            $id = (int) ($item['_id'] ?? 0);
            if ($id <= 0) continue;

            $keywords = [];
            foreach ($item['keywords'] ?? [] as $kw) {
                if (!empty($kw['keyword'])) $keywords[] = $kw['keyword'];
            }

            $results[] = [
                'id'        => $id,
                'keywords'  => $keywords,
                'image_url' => self::imageUrl($id),
            ];
        }
        return $results;
    }

    public static function imageUrl(int $id): string
    {
        return self::ARASAAC_CDN . '/' . $id . '/' . $id . '_300.png';
    }

    // =========================================================
    // Bild-Cleanup
    // =========================================================

    private function extractCustomImageUrls(array $symbols): array
    {
        $urls = [];
        foreach ($symbols as $sym) {
            if ((int)($sym['arasaac_id'] ?? 0) < 0 && !empty($sym['image_url'])) {
                $urls[] = $sym['image_url'];
            }
        }
        return $urls;
    }

    private function isImageUsedInAnySession(string $imageUrl, int $excludeId): bool
    {
        $sql  = $excludeId
            ? "SELECT predefined_symbols FROM wordcloud_sessions WHERE id != :eid AND predefined_symbols IS NOT NULL"
            : "SELECT predefined_symbols FROM wordcloud_sessions WHERE predefined_symbols IS NOT NULL";
        $stmt = $excludeId
            ? $this->db->prepare($sql)
            : $this->db->query($sql);
        if ($excludeId) $stmt->execute([':eid' => $excludeId]);

        foreach ($stmt->fetchAll() as $row) {
            foreach ($this->decodeSymbols($row['predefined_symbols']) as $sym) {
                if (($sym['image_url'] ?? '') === $imageUrl) return true;
            }
        }
        return false;
    }

    private function deleteImageFile(string $imageUrl): void
    {
        if (!preg_match('#^/uploads/img_[a-f0-9_.]+\.(jpg|png|gif|webp)$#', $imageUrl)) return;
        $path = defined('APP_ROOT') ? APP_ROOT . '/public' . $imageUrl : '';
        if ($path && file_exists($path)) @unlink($path);
    }

    // =========================================================
    // Hilfsmethoden
    // =========================================================

    private function setStatus(int $id, string $status): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE wordcloud_sessions SET status = :s WHERE id = :id"
        );
        return $stmt->execute([':s' => $status, ':id' => $id]);
    }

    private function decodeSymbols(?string $json): array
    {
        if (!$json) return [];
        return json_decode($json, true) ?? [];
    }

    private function generateCode(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $stmt = $this->db->prepare(
                "SELECT id FROM wordcloud_sessions WHERE session_code = :c"
            );
            $stmt->execute([':c' => $code]);
        } while ($stmt->fetch());
        return $code;
    }

    public static function isValidToken(string $token): bool
    {
        return (bool) preg_match('/^[a-f0-9\-]{32,64}$/i', $token);
    }
}
