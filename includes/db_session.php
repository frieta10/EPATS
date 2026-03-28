<?php
// ============================================================
// Database-backed PHP Session Handler
// Required for Vercel (no shared filesystem between invocations)
// ============================================================

class DbSessionHandler implements SessionHandlerInterface {

    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function open(string $path, string $name): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read(string $id): string|false {
        $stmt = $this->db->prepare(
            'SELECT session_data FROM php_sessions WHERE session_id = ? AND expires_at > NOW()'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['session_data'] : '';
    }

    public function write(string $id, string $data): bool {
        $stmt = $this->db->prepare(
            'INSERT INTO php_sessions (session_id, session_data, expires_at)
             VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 2 HOUR))
             ON DUPLICATE KEY UPDATE
               session_data = VALUES(session_data),
               expires_at   = VALUES(expires_at)'
        );
        return $stmt->execute([$id, $data]);
    }

    public function destroy(string $id): bool {
        $stmt = $this->db->prepare('DELETE FROM php_sessions WHERE session_id = ?');
        return $stmt->execute([$id]);
    }

    public function gc(int $max_lifetime): int|false {
        $stmt = $this->db->prepare('DELETE FROM php_sessions WHERE expires_at < NOW()');
        $stmt->execute();
        return $stmt->rowCount();
    }
}

// ============================================================
// Boot: register the handler and start the session
// ============================================================
function startDbSession(): void {
    static $started = false;
    if ($started) return;
    $started = true;

    try {
        $handler = new DbSessionHandler(getDB());
        session_set_save_handler($handler, true);

        // Secure cookie settings
        session_set_cookie_params([
            'lifetime' => 7200,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']) || (getenv('VERCEL_URL') !== false),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    } catch (Exception $e) {
        // Fallback to native sessions if DB not ready
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
