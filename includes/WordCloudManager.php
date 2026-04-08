<?php
/**
 * WordCloudManager – Geschäftslogik für Sitzungen und Abstimmungen
 */
class WordCloudManager
{
    const MAX_SYMBOLS   = 20;
    const ARASAAC_API   = 'https://api.arasaac.org/api/pictograms';
    const ARASAAC_CDN   = 'https://static.arasaac.org/pictograms';

    private PDO $db;

    public function __construct()
    {
        $this->db = DB::get();
    }

    // =========================================================
    // Sitzungen
    // =========================================================

    public function createSession(string $title, string $mode, array $symbols): array
    {
        $symbols = array_slice($symbols, 0, self::MAX_SYMBOLS);
        $code    = $this->generateCode();

        $stmt = $this->db->prepare(
            "INSERT INTO wordcloud_sessions (session_code, title, mode, predefined_symbols)
             VALUES (:code, :title, :mode, :sym)"
        );
        $stmt->execute([
            ':code'  => $code,
            ':title' => $title,
            ':mode'  => $mode,
            ':sym'   => empty($symbols) ? null : json_encode($symbols, JSON_UNESCAPED_UNICODE),
        ]);

        return ['id' => (int) $this->db->lastInsertId(), 'code' => $code];
    }

    public function updateSession(int $id, string $title, string $mode, array $symbols): bool
    {
        $symbols = array_slice($symbols, 0, self::MAX_SYMBOLS);
        $stmt    = $this->db->prepare(
            "UPDATE wordcloud_sessions
             SET title = :title, mode = :mode, predefined_symbols = :sym
             WHERE id = :id"
        );
        return $stmt->execute([
            ':title' => $title,
            ':mode'  => $mode,
            ':sym'   => empty($symbols) ? null : json_encode($symbols, JSON_UNESCAPED_UNICODE),
            ':id'    => $id,
        ]);
    }

    public function getSessions(?string $status = null): array
    {
        $where = $status ? "WHERE s.status = :status" : "";
        $sql   = "SELECT s.*,
                         COUNT(DISTINCT v.participant_token) AS participant_count,
                         COUNT(v.id)                         AS total_votes
                  FROM wordcloud_sessions s
                  LEFT JOIN wordcloud_votes v ON v.session_id = s.id
                  $where
                  GROUP BY s.id
                  ORDER BY s.created_at DESC";
        $stmt = $status
            ? $this->db->prepare($sql)
            : $this->db->query($sql);
        if ($status) $stmt->execute([':status' => $status]);

        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['predefined_symbols'] = $this->decodeSymbols($row['predefined_symbols']);
        }
        return $rows;
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
        $stmt = $this->db->prepare("DELETE FROM wordcloud_sessions WHERE id = :id");
        return $stmt->execute([':id' => $id]);
    }

    public function resetVotes(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM wordcloud_votes WHERE session_id = :id");
        return $stmt->execute([':id' => $id]);
    }

    // =========================================================
    // Abstimmung
    // =========================================================

    /**
     * Stimme umschalten. Gibt ['success', 'voted', ('error')] zurück.
     */
    public function toggleVote(int $sessionId, string $token, int $arasaacId, string $label): array
    {
        // Sitzung prüfen
        $stmt = $this->db->prepare(
            "SELECT status FROM wordcloud_sessions WHERE id = :id"
        );
        $stmt->execute([':id' => $sessionId]);
        $session = $stmt->fetch();

        if (!$session) {
            return ['success' => false, 'voted' => false, 'error' => 'Sitzung nicht gefunden'];
        }
        if ($session['status'] !== 'active') {
            return ['success' => false, 'voted' => false, 'error' => 'Sitzung ist geschlossen'];
        }

        // Bereits abgestimmt?
        $check = $this->db->prepare(
            "SELECT id FROM wordcloud_votes
             WHERE session_id = :sid AND participant_token = :tok AND arasaac_id = :aid"
        );
        $check->execute([':sid' => $sessionId, ':tok' => $token, ':aid' => $arasaacId]);

        if ($check->fetch()) {
            // Stimme zurückziehen
            $this->db->prepare(
                "DELETE FROM wordcloud_votes
                 WHERE session_id = :sid AND participant_token = :tok AND arasaac_id = :aid"
            )->execute([':sid' => $sessionId, ':tok' => $token, ':aid' => $arasaacId]);
            return ['success' => true, 'voted' => false];
        }

        // Stimme hinzufügen
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

    /** Aggregierte Stimmen einer Sitzung */
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

    /** Welche Symbole hat dieser Teilnehmer gewählt? */
    public function getParticipantVotes(int $sessionId, string $token): array
    {
        $stmt = $this->db->prepare(
            "SELECT arasaac_id FROM wordcloud_votes
             WHERE session_id = :sid AND participant_token = :tok"
        );
        $stmt->execute([':sid' => $sessionId, ':tok' => $token]);
        return array_map('intval', array_column($stmt->fetchAll(), 'arasaac_id'));
    }

    /** Anzahl aktiver Teilnehmer (Stimme in letzten 5 Minuten) */
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

    // =========================================================
    // ARASAAC-API
    // =========================================================

    /** Symbol-Suche über die ARASAAC REST-API */
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
