<?php
/**
 * THÀNH VIÊN 3: QUẢN LÝ CSDL SQLITE (ĐỈNH, CUNG, ĐẶT VÉ)
 */
class Database {
    private PDO $pdo;

    public function __construct() {
        $dbPath = __DIR__ . '/flights.sqlite';
        $isNew = !file_exists($dbPath);

        $this->pdo = new PDO('sqlite:' . $dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->initTables();
        if ($isNew || $this->getVertexCount() === 0) {
            $this->seedDefaultGraph();
        }
    }

    private function getVertexCount(): int {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM vertices")->fetchColumn();
    }

    private function initTables(): void {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS vertices (
                code TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                city TEXT NOT NULL,
                country TEXT NOT NULL,
                lat REAL NOT NULL,
                lng REAL NOT NULL,
                is_active INTEGER DEFAULT 1
            );
            CREATE TABLE IF NOT EXISTS edges (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                flight_no TEXT NOT NULL DEFAULT 'SG-101',
                source TEXT NOT NULL,
                target TEXT NOT NULL,
                weight REAL NOT NULL,
                duration INTEGER NOT NULL,
                airline TEXT NOT NULL,
                is_active INTEGER DEFAULT 1,
                FOREIGN KEY (source) REFERENCES vertices(code),
                FOREIGN KEY (target) REFERENCES vertices(code)
            );
            CREATE TABLE IF NOT EXISTS bookings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                pnr_code TEXT NOT NULL,
                passenger_name TEXT NOT NULL,
                passenger_email TEXT NOT NULL,
                seat_class TEXT NOT NULL,
                passengers_count INTEGER NOT NULL DEFAULT 1,
                source TEXT NOT NULL,
                target TEXT NOT NULL,
                route_path TEXT NOT NULL,
                total_price REAL NOT NULL,
                total_duration INTEGER NOT NULL,
                booked_at TEXT NOT NULL
            );
        ");
    }

    public function seedDefaultGraph(): void {
        $this->pdo->exec("DELETE FROM edges; DELETE FROM vertices;");

        // TODO (TV3): Bổ sung đủ 12 sân bay quốc tế (bao gồm 1 đỉnh cô lập REY để TV2 test)
        $vertices = [
            ['SGN', 'Tân Sơn Nhất Intl', 'TP. Hồ Chí Minh', 'Vietnam', 10.8188, 106.6519, 1],
            ['HAN', 'Nội Bài Intl', 'Hà Nội', 'Vietnam', 21.2212, 105.8072, 1],
            ['BKK', 'Suvarnabhumi Intl', 'Bangkok', 'Thailand', 13.6900, 100.7501, 1],
            ['JFK', 'John F. Kennedy Intl', 'New York', 'USA', 40.6413, -73.7781, 1],
            ['REY', 'Keflavik (Đỉnh Cô lập)', 'Reykjavik', 'Iceland', 63.9850, -22.6056, 1]
        ];
        $stmtV = $this->pdo->prepare("INSERT INTO vertices VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($vertices as $v) $stmtV->execute($v);

        // TODO (TV3): Bổ sung đủ 32 tuyến bay giả lập (số hiệu, u, v, giá $, giờ bay, hãng)
        $edges = [
            ['VN-210', 'SGN', 'HAN', 85, 2, 'Vietnam Airlines'],
            ['TG-551', 'SGN', 'BKK', 95, 2, 'Thai Airways'],
            ['VN-611', 'HAN', 'BKK', 90, 2, 'Vietnam Airlines']
        ];
        $stmtE = $this->pdo->prepare("INSERT INTO edges (flight_no, source, target, weight, duration, airline) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($edges as $e) $stmtE->execute($e);
    }

    public function getAllVertices(): array {
        return $this->pdo->query("SELECT * FROM vertices ORDER BY code ASC")->fetchAll();
    }

    public function getAllEdges(): array {
        return $this->pdo->query("SELECT * FROM edges ORDER BY id DESC")->fetchAll();
    }

    public function addVertex(string $code, string $name, string $city, string $country, float $lat, float $lng): bool {
        // TODO (TV3): Viết câu lệnh INSERT OR REPLACE vào bảng vertices
        return true;
    }

    public function addEdge(string $flightNo, string $source, string $target, float $weight, int $duration, string $airline): bool {
        // TODO (TV3): Viết câu lệnh INSERT vào bảng edges
        return true;
    }

    public function deleteEdge(int $id): bool {
        // TODO (TV3): Viết câu lệnh DELETE FROM edges WHERE id = ?
        return true;
    }

    public function createBooking(array $data): string {
        // TODO (TV3): Sinh mã PNR ngẫu nhiên và lưu vào bảng bookings
        return 'SG0000';
    }

    public function getAllBookings(): array {
        return $this->pdo->query("SELECT * FROM bookings ORDER BY id DESC")->fetchAll();
    }
}