<?php
/**
 * ============================================================================
 * FILE DÀNH CHO THÀNH VIÊN 3: QUẢN LÝ CSDL ĐỒ THỊ, CHUYẾN BAY & ĐẶT VÉ PNR
 * ============================================================================
 * 1. PHẦN CODE ĐÃ CHẠY SẴN TRONG FILE NÀY:
 *    - Tự động khởi tạo SQLite (`flights.sqlite`) với 3 bảng:
 *      + `vertices`: Lưu thông tin các Đỉnh sân bay và tọa độ GPS (lat, lng).
 *      + `edges`: Lưu các Cung có hướng (Chuyến bay, số hiệu, giá vé, thời gian, hãng bay).
 *      + `bookings`: Lưu lịch sử Đặt vé của khách hàng và sinh mã đặt chỗ PNR tự động.
 *    - Tự động nạp 12 đỉnh sân bay quốc tế và 32 chuyến bay mẫu.
 *
 * 2. NHIỆM VỤ MỞ RỘNG CHO THÀNH VIÊN 3:
 *    - Xây dựng thêm tính năng Import / Export đồ thị ra file `.json` hoặc `.txt`
 *      để giảng viên có thể tải đề bài bất kỳ vào kiểm tra.
 * ============================================================================
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
        // 1. Bảng Đỉnh (Vertices - Sân bay)
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
        ");

        // 2. Bảng Cung có hướng (Directed Edges - Chuyến bay)
        $this->pdo->exec("
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
        ");

        // 3. Bảng Đặt vé (Bookings)
        $this->pdo->exec("
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
        $this->pdo->exec("DELETE FROM edges;");
        $this->pdo->exec("DELETE FROM vertices;");

        $vertices = [
            ['SGN', 'Tân Sơn Nhất Intl', 'TP. Hồ Chí Minh', 'Vietnam', 10.8188, 106.6519, 1],
            ['HAN', 'Nội Bài Intl', 'Hà Nội', 'Vietnam', 21.2212, 105.8072, 1],
            ['BKK', 'Suvarnabhumi Intl', 'Bangkok', 'Thailand', 13.6900, 100.7501, 1],
            ['SIN', 'Changi Airport', 'Singapore', 'Singapore', 1.3644, 103.9915, 1],
            ['NRT', 'Narita Intl', 'Tokyo', 'Japan', 35.7720, 140.3929, 1],
            ['ICN', 'Incheon Intl', 'Seoul', 'South Korea', 37.4602, 126.4407, 1],
            ['DXB', 'Dubai Intl', 'Dubai', 'UAE', 25.2532, 55.3657, 1],
            ['CDG', 'Charles de Gaulle', 'Paris', 'France', 49.0097, 2.5479, 1],
            ['LHR', 'Heathrow Airport', 'London', 'UK', 51.4700, -0.4543, 1],
            ['JFK', 'John F. Kennedy Intl', 'New York', 'USA', 40.6413, -73.7781, 1],
            ['SYD', 'Kingsford Smith', 'Sydney', 'Australia', -33.9399, 151.1753, 1],
            ['REY', 'Keflavik (Đỉnh Cô lập)', 'Reykjavik', 'Iceland', 63.9850, -22.6056, 1]
        ];

        $stmtV = $this->pdo->prepare("INSERT INTO vertices (code, name, city, country, lat, lng, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($vertices as $v) {
            $stmtV->execute($v);
        }

        $edges = [
            ['VN-210', 'SGN', 'HAN', 85, 2, 'Vietnam Airlines'],
            ['VJ-150', 'HAN', 'SGN', 80, 2, 'Vietjet Air'],
            ['TG-551', 'SGN', 'BKK', 95, 2, 'Thai Airways'],
            ['TG-552', 'BKK', 'SGN', 100, 2, 'Thai Airways'],
            ['SQ-177', 'SGN', 'SIN', 110, 2, 'Singapore Airlines'],
            ['SQ-178', 'SIN', 'SGN', 115, 2, 'Singapore Airlines'],
            ['KE-476', 'SGN', 'ICN', 280, 5, 'Korean Air'],
            ['JL-750', 'SGN', 'NRT', 340, 6, 'Japan Airlines'],
            ['VN-611', 'HAN', 'BKK', 90, 2, 'Vietnam Airlines'],
            ['OZ-734', 'HAN', 'ICN', 250, 4, 'Asiana Airlines'],
            ['NH-898', 'HAN', 'NRT', 310, 5, 'All Nippon Airways'],
            ['AF-253', 'HAN', 'CDG', 720, 12, 'Air France'],
            ['EK-385', 'BKK', 'DXB', 320, 6, 'Emirates'],
            ['KE-652', 'BKK', 'ICN', 230, 5, 'Korean Air'],
            ['BA-010', 'BKK', 'LHR', 680, 12, 'British Airways'],
            ['QF-002', 'SIN', 'SYD', 380, 8, 'Qantas'],
            ['EK-355', 'SIN', 'DXB', 350, 7, 'Emirates'],
            ['SQ-638', 'SIN', 'NRT', 290, 7, 'Singapore Airlines'],
            ['SQ-322', 'SIN', 'LHR', 700, 13, 'Singapore Airlines'],
            ['KE-703', 'ICN', 'NRT', 140, 2, 'Korean Air'],
            ['KE-081', 'ICN', 'JFK', 690, 14, 'Korean Air'],
            ['AF-267', 'ICN', 'CDG', 590, 12, 'Air France'],
            ['NH-110', 'NRT', 'JFK', 580, 13, 'All Nippon Airways'],
            ['JL-051', 'NRT', 'SYD', 490, 9, 'Japan Airlines'],
            ['EK-073', 'DXB', 'CDG', 340, 7, 'Emirates'],
            ['EK-001', 'DXB', 'LHR', 360, 7, 'Emirates'],
            ['EK-201', 'DXB', 'JFK', 620, 14, 'Emirates'],
            ['AF-168', 'CDG', 'LHR', 95, 1, 'Air France'],
            ['BA-304', 'LHR', 'CDG', 100, 1, 'British Airways'],
            ['DL-263', 'CDG', 'JFK', 390, 8, 'Delta Air Lines'],
            ['BA-117', 'LHR', 'JFK', 350, 8, 'British Airways'],
            ['QF-011', 'SYD', 'JFK', 950, 19, 'Qantas']
        ];

        $stmtE = $this->pdo->prepare("INSERT INTO edges (flight_no, source, target, weight, duration, airline) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($edges as $e) {
            $stmtE->execute($e);
        }
    }

    public function getAllVertices(): array {
        return $this->pdo->query("SELECT * FROM vertices ORDER BY code ASC")->fetchAll();
    }

    public function getAllEdges(): array {
        return $this->pdo->query("SELECT * FROM edges ORDER BY id DESC")->fetchAll();
    }

    public function addVertex(string $code, string $name, string $city, string $country, float $lat, float $lng): bool {
        $stmt = $this->pdo->prepare("INSERT OR REPLACE INTO vertices (code, name, city, country, lat, lng, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
        return $stmt->execute([strtoupper($code), $name, $city, $country, $lat, $lng]);
    }

    public function addEdge(string $flightNo, string $source, string $target, float $weight, int $duration, string $airline): bool {
        $stmt = $this->pdo->prepare("INSERT INTO edges (flight_no, source, target, weight, duration, airline) VALUES (?, ?, ?, ?, ?, ?)");
        return $stmt->execute([strtoupper($flightNo), strtoupper($source), strtoupper($target), $weight, $duration, $airline]);
    }

    public function deleteEdge(int $id): bool {
        $stmt = $this->pdo->prepare("DELETE FROM edges WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function createBooking(array $data): string {
        $pnr = 'SG' . strtoupper(substr(md5(uniqid('', true)), 0, 4));
        $stmt = $this->pdo->prepare("
            INSERT INTO bookings (pnr_code, passenger_name, passenger_email, seat_class, passengers_count, source, target, route_path, total_price, total_duration, booked_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $pnr,
            $data['passenger_name'],
            $data['passenger_email'],
            $data['seat_class'],
            (int)$data['passengers_count'],
            $data['source'],
            $data['target'],
            implode(' ➔ ', $data['route_path']),
            (float)$data['total_price'],
            (int)$data['total_duration'],
            date('Y-m-d H:i:s')
        ]);
        return $pnr;
    }

    public function getAllBookings(): array {
        return $this->pdo->query("SELECT * FROM bookings ORDER BY id DESC")->fetchAll();
    }
}