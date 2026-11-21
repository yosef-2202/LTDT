<?php
/**
 * THÀNH VIÊN 3: QUẢN LÝ CSDL SQLITE (ĐỈNH, CUNG, ĐẶT VÉ)
 *
 * Bảng:  vertices (sân bay)  |  edges (chuyến bay)  |  bookings (vé đã đặt)
 * Tên trường dùng chung cả nhóm: code, source, target, weight, duration, lat, lng
 *   - weight   : giá vé (USD)
 *   - duration : thời gian bay (GIỜ, số nguyên)
 */
class Database {

    // ========================================================================
    // [CỤM 1] KHỞI TẠO KẾT NỐI
    // ========================================================================
    private PDO $pdo;

    public function __construct() {
        $dbPath = __DIR__ . '/flights.sqlite';
        $isNew = !file_exists($dbPath);

        $this->pdo = new PDO('sqlite:' . $dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA foreign_keys = ON');   // bật ràng buộc khóa ngoại

        $this->initTables();
        $this->migrateSchema();   // nâng cấp file .sqlite cũ (thiếu cột) mà không mất dữ liệu
        if ($isNew || $this->getVertexCount() === 0) {
            $this->seedDefaultGraph();
        }
    }

    private function getVertexCount(): int {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM vertices")->fetchColumn();
    }

    // ========================================================================
    // [CỤM 2] TẠO BẢNG + NÂNG CẤP SCHEMA
    // ========================================================================
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
            CREATE UNIQUE INDEX IF NOT EXISTS idx_bookings_pnr ON bookings(pnr_code);
        ");
    }

    /**
     * File flights.sqlite tạo từ phiên bản cũ có thể thiếu cột edges.flight_no.
     * Thêm cột và gán số hiệu riêng cho từng chuyến cũ (SG-101, SG-102, ...).
     */
    private function migrateSchema(): void {
        $cols = array_column($this->pdo->query("PRAGMA table_info(edges)")->fetchAll(), 'name');
        if (!in_array('flight_no', $cols, true)) {
            $this->pdo->exec("ALTER TABLE edges ADD COLUMN flight_no TEXT NOT NULL DEFAULT 'SG-101'");
            $this->pdo->exec("UPDATE edges SET flight_no = 'SG-' || (100 + id)");
        }
    }

    // ========================================================================
    // [CỤM 3] NẠP DỮ LIỆU MẪU: 12 SÂN BAY (có REY cô lập) + 32 TUYẾN BAY
    // ========================================================================
    public function seedDefaultGraph(): void {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec("DELETE FROM edges; DELETE FROM vertices;");
            // Cho id chuyến bay đếm lại từ 1 (bảng sqlite_sequence do AUTOINCREMENT tạo ra)
            $this->pdo->exec("DELETE FROM sqlite_sequence WHERE name = 'edges'");

            // --- 12 sân bay: code, name, city, country, lat, lng, is_active ---
            $vertices = [
                ['SGN', 'Tân Sơn Nhất',      'TP. Hồ Chí Minh', 'Vietnam',     10.8188,  106.6519, 1],
                ['HAN', 'Nội Bài',           'Hà Nội',          'Vietnam',     21.2212,  105.8072, 1],
                ['BKK', 'Suvarnabhumi',      'Bangkok',         'Thailand',    13.6900,  100.7501, 1],
                ['SIN', 'Changi',            'Singapore',       'Singapore',    1.3644,  103.9915, 1],
                ['NRT', 'Narita',            'Tokyo',           'Japan',       35.7720,  140.3929, 1],
                ['ICN', 'Incheon',           'Seoul',           'South Korea', 37.4602,  126.4407, 1],
                ['DXB', 'Dubai Intl',        'Dubai',           'UAE',         25.2532,   55.3657, 1],
                ['CDG', 'Charles de Gaulle', 'Paris',           'France',      49.0097,    2.5479, 1],
                ['LHR', 'Heathrow',          'London',          'UK',          51.4700,   -0.4543, 1],
                ['JFK', 'John F. Kennedy',   'New York',        'USA',         40.6413,  -73.7781, 1],
                ['SYD', 'Kingsford Smith',   'Sydney',          'Australia',  -33.9399,  151.1753, 1],
                ['REY', 'Keflavik (Cô lập)', 'Reykjavik',       'Iceland',     63.9850,  -22.6056, 1],  // đỉnh cô lập cho TV2 test
            ];
            $stmtV = $this->pdo->prepare(
                "INSERT INTO vertices (code, name, city, country, lat, lng, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            foreach ($vertices as $v) $stmtV->execute($v);

            // --- 32 tuyến bay có hướng: số hiệu, u, v, giá $, giờ bay, hãng ---
            // (số hiệu chuyến bay là dữ liệu giả lập phục vụ mô phỏng)
            $edges = [
                ['VN-210',  'SGN', 'HAN',  85,  2, 'Vietnam Airlines'],
                ['VJ-120',  'HAN', 'SGN',  80,  2, 'Vietjet Air'],
                ['TG-551',  'SGN', 'BKK',  95,  2, 'Thai Airways'],
                ['TG-552',  'BKK', 'SGN', 100,  2, 'Thai Airways'],
                ['SQ-185',  'SGN', 'SIN', 110,  2, 'Singapore Airlines'],
                ['SQ-186',  'SIN', 'SGN', 115,  2, 'Singapore Airlines'],
                ['KE-684',  'SGN', 'ICN', 280,  5, 'Korean Air'],
                ['JL-750',  'SGN', 'NRT', 340,  6, 'Japan Airlines'],
                ['VN-611',  'HAN', 'BKK',  90,  2, 'Vietnam Airlines'],
                ['OZ-740',  'HAN', 'ICN', 250,  4, 'Asiana Airlines'],
                ['NH-884',  'HAN', 'NRT', 310,  5, 'ANA'],
                ['AF-258',  'HAN', 'CDG', 720, 12, 'Air France'],
                ['EK-373',  'BKK', 'DXB', 320,  6, 'Emirates'],
                ['KE-658',  'BKK', 'ICN', 230,  5, 'Korean Air'],
                ['BA-010',  'BKK', 'LHR', 680, 12, 'British Airways'],
                ['QF-052',  'SIN', 'SYD', 380,  8, 'Qantas'],
                ['EK-355',  'SIN', 'DXB', 350,  7, 'Emirates'],
                ['SQ-638',  'SIN', 'NRT', 290,  7, 'Singapore Airlines'],
                ['SQ-317',  'SIN', 'LHR', 700, 13, 'Singapore Airlines'],
                ['KE-703',  'ICN', 'NRT', 140,  2, 'Korean Air'],
                ['KE-081',  'ICN', 'JFK', 690, 14, 'Korean Air'],
                ['AF-267',  'ICN', 'CDG', 590, 12, 'Air France'],
                ['NH-010',  'NRT', 'JFK', 580, 13, 'ANA'],
                ['JL-771',  'NRT', 'SYD', 490,  9, 'Japan Airlines'],
                ['EK-073',  'DXB', 'CDG', 340,  7, 'Emirates'],
                ['EK-001',  'DXB', 'LHR', 360,  7, 'Emirates'],
                ['EK-201',  'DXB', 'JFK', 620, 14, 'Emirates'],
                ['AF-1081', 'CDG', 'LHR',  95,  1, 'Air France'],
                ['BA-304',  'LHR', 'CDG', 100,  1, 'British Airways'],
                ['DL-263',  'CDG', 'JFK', 390,  8, 'Delta'],
                ['BA-117',  'LHR', 'JFK', 350,  8, 'British Airways'],
                ['QF-011',  'SYD', 'JFK', 950, 19, 'Qantas'],
            ];
            $stmtE = $this->pdo->prepare(
                "INSERT INTO edges (flight_no, source, target, weight, duration, airline) VALUES (?, ?, ?, ?, ?, ?)"
            );
            foreach ($edges as $e) $stmtE->execute($e);

            $this->pdo->commit();
        } catch (Exception $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }
    }

    // ========================================================================
    // [CỤM 4] THAO TÁC BẢNG vertices (SÂN BAY)
    // ========================================================================
    public function getAllVertices(): array {
        return $this->pdo->query("SELECT * FROM vertices ORDER BY code ASC")->fetchAll();
    }

    public function vertexExists(string $code): bool {
        $st = $this->pdo->prepare("SELECT 1 FROM vertices WHERE code = ?");
        $st->execute([$code]);
        return (bool)$st->fetchColumn();
    }

    /**
     * Thêm sân bay; nếu mã đã tồn tại thì cập nhật thông tin (INSERT OR REPLACE).
     * Dùng UPSERT (ON CONFLICT) thay cho REPLACE để không xóa dòng cũ -> không vướng khóa ngoại.
     */
    public function addVertex(string $code, string $name, string $city, string $country, float $lat, float $lng): bool {
        $st = $this->pdo->prepare("
            INSERT INTO vertices (code, name, city, country, lat, lng, is_active)
            VALUES (?, ?, ?, ?, ?, ?, 1)
            ON CONFLICT(code) DO UPDATE SET
                name = excluded.name, city = excluded.city, country = excluded.country,
                lat = excluded.lat, lng = excluded.lng, is_active = 1
        ");
        return $st->execute([$code, $name, $city, $country, $lat, $lng]);
    }

    // ========================================================================
    // [CỤM 5] THAO TÁC BẢNG edges (CHUYẾN BAY)
    // ========================================================================
    public function getAllEdges(): array {
        return $this->pdo->query("SELECT * FROM edges ORDER BY id DESC")->fetchAll();
    }

    public function flightNoExists(string $flightNo): bool {
        $st = $this->pdo->prepare("SELECT 1 FROM edges WHERE flight_no = ?");
        $st->execute([$flightNo]);
        return (bool)$st->fetchColumn();
    }

    /** Sinh số hiệu chuyến bay "SG-xxx" chưa bị trùng (khi người dùng không nhập). */
    public function generateFlightNo(): string {
        for ($i = 0; $i < 100; $i++) {
            $no = 'SG-' . random_int(100, 9999);
            if (!$this->flightNoExists($no)) return $no;
        }
        throw new Exception('Không sinh được số hiệu chuyến bay duy nhất.');
    }

    /** Thêm 1 chuyến bay (cạnh có hướng source -> target). Cho phép weight < 0 để test cạnh âm. */
    public function addEdge(string $flightNo, string $source, string $target, float $weight, int $duration, string $airline): bool {
        $st = $this->pdo->prepare("
            INSERT INTO edges (flight_no, source, target, weight, duration, airline)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        return $st->execute([$flightNo, $source, $target, $weight, $duration, $airline]);
    }

    /** Xóa chuyến bay theo id. Trả false nếu không có dòng nào bị xóa. */
    public function deleteEdge(int $id): bool {
        $st = $this->pdo->prepare("DELETE FROM edges WHERE id = ?");
        $st->execute([$id]);
        return $st->rowCount() > 0;
    }

    // ========================================================================
    // [CỤM 6] ĐẶT VÉ + SINH MÃ PNR
    // ========================================================================

    /**
     * Tính lại giá và thời gian của lộ trình TỪ BẢNG edges (không tin số liệu client gửi).
     * Mỗi chặng chọn chuyến đang hoạt động tốt nhất theo $criteria ('weight' hoặc 'duration').
     * @param string[] $codes danh sách mã sân bay theo thứ tự, vd ['SGN','NRT','JFK']
     * @return array ['total_price', 'total_duration', 'legs' => [...]]  (giá cho 1 hành khách)
     */
    public function calculateRoute(array $codes, string $criteria = 'weight'): array {
        $orderCol = ($criteria === 'duration') ? 'duration' : 'weight';  // whitelist cột, tránh SQL injection
        $total = 0.0;
        $hours = 0;
        $legs = [];
        for ($i = 0; $i + 1 < count($codes); $i++) {
            $st = $this->pdo->prepare("
                SELECT id, flight_no, source, target, weight, duration, airline
                FROM edges
                WHERE source = ? AND target = ? AND is_active = 1
                ORDER BY $orderCol ASC LIMIT 1
            ");
            $st->execute([$codes[$i], $codes[$i + 1]]);
            $leg = $st->fetch();
            if (!$leg) {
                throw new Exception("Không có chuyến bay trực tiếp {$codes[$i]} → {$codes[$i + 1]}.");
            }
            $total += (float)$leg['weight'];
            $hours += (int)$leg['duration'];
            $legs[] = $leg;
        }
        return ['total_price' => $total, 'total_duration' => $hours, 'legs' => $legs];
    }

    /**
     * Lưu vé vào bảng bookings và trả về mã PNR.
     * $data cần: passenger_name, passenger_email, seat_class, passengers_count,
     *            source, target, route_path (chuỗi "SGN,NRT,JFK"), total_price, total_duration
     * (dữ liệu đã được api.php kiểm tra hợp lệ trước khi gọi hàm này)
     */
    public function createBooking(array $data): string {
        $pnr = $this->generatePnr();
        $st = $this->pdo->prepare("
            INSERT INTO bookings
                (pnr_code, passenger_name, passenger_email, seat_class, passengers_count,
                 source, target, route_path, total_price, total_duration, booked_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $st->execute([
            $pnr,
            $data['passenger_name'],
            $data['passenger_email'],
            $data['seat_class'],
            $data['passengers_count'],
            $data['source'],
            $data['target'],
            $data['route_path'],
            $data['total_price'],
            $data['total_duration'],
            $data['booked_at'] ?? date('Y-m-d H:i:s'),
        ]);
        return $pnr;
    }

    public function getAllBookings(): array {
        return $this->pdo->query("SELECT * FROM bookings ORDER BY id DESC")->fetchAll();
    }

    /** Sinh PNR dạng "SG" + 4 ký tự (bỏ I, O, 0, 1 cho khỏi nhầm), lặp đến khi không trùng. */
    private function generatePnr(): string {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $st = $this->pdo->prepare("SELECT 1 FROM bookings WHERE pnr_code = ?");
        for ($try = 0; $try < 50; $try++) {
            $pnr = 'SG';
            for ($i = 0; $i < 4; $i++) $pnr .= $chars[random_int(0, strlen($chars) - 1)];
            $st->execute([$pnr]);
            if (!$st->fetchColumn()) return $pnr;
        }
        throw new Exception('Không sinh được mã PNR duy nhất, vui lòng thử lại.');
    }

    // ========================================================================
    // [CỤM 7] IMPORT / EXPORT ĐỒ THỊ (.json)
    // ========================================================================

    /** Xuất toàn bộ đồ thị (đỉnh + cạnh) để lưu ra file .json */
    public function exportGraph(): array {
        return ['vertices' => $this->getAllVertices(), 'edges' => $this->getAllEdges()];
    }

    /**
     * Nạp đồ thị từ dữ liệu đã làm sạch (api.php kiểm tra từng dòng trước).
     * $replace = true  : xóa đồ thị hiện tại rồi nạp mới (vé đã đặt được giữ nguyên)
     * $replace = false : gộp thêm; chuyến bay trùng số hiệu bị bỏ qua
     * Toàn bộ nằm trong 1 transaction: lỗi ở đâu thì hủy hết, không để dữ liệu dở dang.
     */
    public function importGraph(array $vertices, array $edges, bool $replace): array {
        $countV = 0; $countE = 0; $skipped = 0;
        $this->pdo->beginTransaction();
        try {
            if ($replace) {
                $this->pdo->exec("DELETE FROM edges; DELETE FROM vertices;");
                $this->pdo->exec("DELETE FROM sqlite_sequence WHERE name = 'edges'");
            }
            foreach ($vertices as $v) {
                $this->addVertex($v['code'], $v['name'], $v['city'], $v['country'], $v['lat'], $v['lng']);
                $countV++;
            }
            foreach ($edges as $e) {
                if (!$this->vertexExists($e['source']) || !$this->vertexExists($e['target'])) {
                    throw new Exception("Chuyến {$e['source']} → {$e['target']} tham chiếu sân bay chưa tồn tại.");
                }
                $no = $e['flight_no'] !== '' ? $e['flight_no'] : $this->generateFlightNo();
                if ($this->flightNoExists($no)) { $skipped++; continue; }
                $this->addEdge($no, $e['source'], $e['target'], $e['weight'], $e['duration'], $e['airline']);
                $countE++;
            }
            $this->pdo->commit();
        } catch (Exception $ex) {
            $this->pdo->rollBack();
            throw $ex;
        }
        return ['vertices' => $countV, 'edges' => $countE, 'skipped' => $skipped];
    }
}
