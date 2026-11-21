<?php
/**
 * THÀNH VIÊN 3: API ĐIỀU PHỐI BACKEND
 *
 * Danh sách action (gọi qua  api.php?action=...):
 *   init_data     GET   Lấy toàn bộ dữ liệu cho giao diện (đỉnh, cạnh, vé, ma trận, phân tích)
 *   run_dijkstra  POST  Chạy Dijkstra   body JSON: {source, target, criteria}
 *   add_vertex    POST  Thêm/cập nhật sân bay  {code, name, city, country, lat, lng}
 *   add_edge      POST  Thêm chuyến bay        {flight_no?, source, target, weight, duration, airline?}
 *   delete_edge   POST  Xóa chuyến bay         {id}
 *   book_ticket   POST  Đặt vé, trả mã PNR     {passenger_name, passenger_email, seat_class,
 *                                               passengers_count, route_path, criteria?}
 *   reset_db      POST  Khôi phục dữ liệu mặc định
 *   export_graph  GET   Xuất đồ thị ra JSON (thêm &download=1 để tải file)
 *   import_graph  POST  Nạp đồ thị từ JSON  {vertices:[...], edges:[...], mode:"merge"|"replace"}
 *
 * Mọi response đều có "status": "success" | "error" (kèm "message" khi lỗi).
 */

// ============================================================================
// [CỤM 1] KHỞI TẠO: header, nạp lớp, múi giờ
// ============================================================================
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Ho_Chi_Minh');   // để booked_at đúng giờ Việt Nam

require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/DijkstraCore.php';
require_once __DIR__ . '/includes/GraphAnalyzer.php';

// ============================================================================
// [CỤM 2] HÀM TIỆN ÍCH: trả JSON, kiểm tra & làm sạch dữ liệu đầu vào
// ============================================================================

/** In JSON (giữ nguyên tiếng Việt có dấu) */
function out(array $payload): void {
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
}

/** Trả lỗi kèm dữ liệu phụ (vd kết quả Bellman-Ford) rồi dừng */
function failWith(string $message, array $extra = []): void {
    http_response_code(400);
    out(array_merge(['status' => 'error', 'message' => $message], $extra));
    exit;
}

/** Các action ghi/xóa dữ liệu bắt buộc dùng POST (tránh vô tình bấm link GET làm mất dữ liệu) */
function requirePost(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Action này yêu cầu phương thức POST.');
    }
}

/** Đếm số ký tự UTF-8 (tiếng Việt có dấu); không cần extension mbstring */
function textLen(string $s): int {
    $n = preg_match_all('/./us', $s);
    return $n === false ? strlen($s) : $n;
}

/** Mã sân bay: đúng 3 chữ cái IATA, tự viết hoa */
function cleanCode($value, string $label = 'Mã sân bay'): string {
    $code = strtoupper(trim((string)$value));
    if (!preg_match('/^[A-Z]{3}$/', $code)) {
        throw new Exception("$label phải gồm đúng 3 chữ cái (VD: SGN).");
    }
    return $code;
}

/** Kiểm tra + chuẩn hóa 1 sân bay (dùng cho add_vertex và import_graph) */
function cleanVertex(array $r): array {
    $name = trim((string)($r['name'] ?? ''));
    $city = trim((string)($r['city'] ?? ''));
    if ($name === '' || $city === '') throw new Exception('Sân bay cần có name và city.');
    if (textLen($name) > 100 || textLen($city) > 100) throw new Exception('name/city tối đa 100 ký tự.');

    if (!isset($r['lat'], $r['lng']) || !is_numeric($r['lat']) || !is_numeric($r['lng'])) {
        throw new Exception('lat và lng phải là số.');
    }
    $lat = (float)$r['lat']; $lng = (float)$r['lng'];
    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
        throw new Exception('Tọa độ không hợp lệ (lat -90..90, lng -180..180).');
    }
    $country = trim((string)($r['country'] ?? ''));
    return [
        'code'    => cleanCode($r['code'] ?? ''),
        'name'    => $name,
        'city'    => $city,
        'country' => $country !== '' ? $country : 'Unknown',
        'lat'     => $lat,
        'lng'     => $lng,
    ];
}

/** Kiểm tra + chuẩn hóa 1 chuyến bay (dùng cho add_edge và import_graph). Cho phép weight < 0 để test. */
function cleanEdge(array $r): array {
    $source = cleanCode($r['source'] ?? '', 'Sân bay đi');
    $target = cleanCode($r['target'] ?? '', 'Sân bay đến');
    if ($source === $target) throw new Exception('Sân bay đi và đến không được trùng nhau.');

    if (!isset($r['weight']) || !is_numeric($r['weight'])) throw new Exception('weight (giá vé) phải là số.');
    if (!isset($r['duration']) || !is_numeric($r['duration'])) throw new Exception('duration (giờ bay) phải là số.');
    $duration = (int)round((float)$r['duration']);
    if ($duration <= 0 || $duration > 100) throw new Exception('duration phải trong khoảng 1..100 giờ.');

    $flightNo = strtoupper(trim((string)($r['flight_no'] ?? '')));
    if ($flightNo !== '' && !preg_match('/^[A-Z0-9][A-Z0-9-]{1,9}$/', $flightNo)) {
        throw new Exception('Số hiệu chuyến bay không hợp lệ (VD: VN-210).');
    }
    $airline = trim((string)($r['airline'] ?? ''));
    if (textLen($airline) > 50) throw new Exception('Tên hãng bay tối đa 50 ký tự.');

    return [
        'flight_no' => $flightNo,                                   // rỗng -> hệ thống tự sinh
        'source'    => $source,
        'target'    => $target,
        'weight'    => (float)$r['weight'],
        'duration'  => $duration,
        'airline'   => $airline !== '' ? $airline : 'SkyGraph Air',
    ];
}

/** Đọc lộ trình: nhận mảng ['SGN','NRT'] hoặc chuỗi "SGN,NRT" / "SGN ➔ NRT" -> mảng mã 3 chữ cái */
function parseRoute($route): array {
    $parts = is_array($route) ? $route : preg_split('/[^A-Za-z]+/', (string)$route, -1, PREG_SPLIT_NO_EMPTY);
    $codes = [];
    foreach ($parts as $p) $codes[] = cleanCode($p, 'Mã sân bay trong lộ trình');
    if (count($codes) < 2) throw new Exception('Lộ trình cần ít nhất 2 sân bay.');
    for ($i = 0; $i + 1 < count($codes); $i++) {
        if ($codes[$i] === $codes[$i + 1]) throw new Exception('Lộ trình có 2 sân bay liên tiếp trùng nhau.');
    }
    return $codes;
}

// ============================================================================
// [CỤM 3] XỬ LÝ REQUEST: đọc dữ liệu vào, điều phối theo action
// ============================================================================
try {
    $db = new Database();
    $action = $_GET['action'] ?? 'init_data';

    // Body JSON (fetch) có ưu tiên cao hơn form POST thường
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) $input = [];
    $input = array_merge($_POST, $input);

    switch ($action) {

        // --------------------------------------------------------------------
        // [3.1] init_data — nạp toàn bộ dữ liệu cho giao diện
        // --------------------------------------------------------------------
        case 'init_data':
            $vertices = $db->getAllVertices();
            $edges = $db->getAllEdges();
            $core = new DijkstraCore($vertices, $edges);
            $analyzer = new GraphAnalyzer($vertices, $edges);

            out([
                'status'        => 'success',
                'vertices'      => $vertices,
                'edges'         => $edges,
                'bookings'      => $db->getAllBookings(),
                'weight_matrix' => $core->getWeightMatrix(),
                'adj_list'      => $core->getAdjacencyList(),
                'analysis'      => $analyzer->analyzeProperties()
            ]);
            break;

        // --------------------------------------------------------------------
        // [3.2] run_dijkstra — kiểm tra đầu vào, chặn cạnh âm, cảnh báo không liên thông
        // --------------------------------------------------------------------
        case 'run_dijkstra':
            $start = cleanCode($input['source'] ?? 'SGN', 'Điểm đi');
            $target = cleanCode($input['target'] ?? 'JFK', 'Điểm đến');
            $criteria = (string)($input['criteria'] ?? 'weight');

            if (!in_array($criteria, ['weight', 'duration'], true)) {
                throw new Exception("Tiêu chí không hợp lệ (chỉ nhận 'weight' hoặc 'duration').");
            }
            if ($start === $target) throw new Exception('Điểm đi và điểm đến phải khác nhau.');
            if (!$db->vertexExists($start)) throw new Exception("Sân bay đi không tồn tại: $start");
            if (!$db->vertexExists($target)) throw new Exception("Sân bay đến không tồn tại: $target");

            $vertices = $db->getAllVertices();
            $edges = $db->getAllEdges();
            $analyzer = new GraphAnalyzer($vertices, $edges);

            // (a) Cạnh trọng số âm -> Dijkstra không còn đảm bảo đúng -> chặn và gọi Bellman-Ford đối chiếu.
            //     Chỉ áp dụng khi tiêu chí là giá vé (weight); duration luôn > 0 nên không bị ảnh hưởng.
            if ($criteria === 'weight') {
                $analysis = $analyzer->analyzeProperties();
                $negativeEdges = [];
                foreach ($edges as $e) {
                    if ((int)$e['is_active'] === 1 && (float)$e['weight'] < 0) {
                        $negativeEdges[] = ['id' => (int)$e['id'], 'source' => $e['source'],
                                            'target' => $e['target'], 'weight' => (float)$e['weight']];
                    }
                }
                // Kiểm tra cả cờ của TV2 lẫn quét trực tiếp, phòng khi hàm của TV2 chưa hoàn thiện
                if (!empty($analysis['has_negative_weight']) || count($negativeEdges) > 0) {
                    failWith(
                        'Đồ thị có cạnh trọng số âm (giá vé < 0) nên Dijkstra không đảm bảo đúng. '
                        . 'Hệ thống đã chạy Bellman-Ford để đối chiếu.',
                        ['bellman_ford' => $analyzer->runBellmanFord($start, $target),
                         'negative_edges' => $negativeEdges]
                    );
                }
            }

            // (b) BFS: không có đường đi (VD đến đỉnh cô lập REY) -> báo lỗi thay vì chạy vô ích
            $bfsCheck = $analyzer->isReachableBFS($start, $target);
            if (isset($bfsCheck['reachable']) && $bfsCheck['reachable'] === false) {
                failWith("Không có đường bay từ $start đến $target (đồ thị không liên thông).",
                         ['bfs_check' => $bfsCheck]);
            }

            // (c) Chạy Dijkstra của TV1
            $core = new DijkstraCore($vertices, $edges, $criteria);
            $result = $core->runDijkstra($start, $target);
            $result['bfs_check'] = $bfsCheck;

            out(['status' => 'success', 'data' => $result]);
            break;

        // --------------------------------------------------------------------
        // [3.3] add_vertex — thêm / cập nhật sân bay
        // --------------------------------------------------------------------
        case 'add_vertex':
            requirePost();
            $v = cleanVertex($input);
            $existed = $db->vertexExists($v['code']);
            $db->addVertex($v['code'], $v['name'], $v['city'], $v['country'], $v['lat'], $v['lng']);
            out(['status' => 'success',
                 'message' => $existed ? 'Đã cập nhật sân bay' : 'Đã thêm sân bay',
                 'vertex'  => $v]);
            break;

        // --------------------------------------------------------------------
        // [3.4] add_edge — thêm chuyến bay (cạnh có hướng)
        // --------------------------------------------------------------------
        case 'add_edge':
            requirePost();
            $e = cleanEdge($input);
            if (!$db->vertexExists($e['source'])) throw new Exception("Sân bay đi không tồn tại: {$e['source']}");
            if (!$db->vertexExists($e['target'])) throw new Exception("Sân bay đến không tồn tại: {$e['target']}");

            if ($e['flight_no'] === '') {
                $e['flight_no'] = $db->generateFlightNo();
            } elseif ($db->flightNoExists($e['flight_no'])) {
                throw new Exception("Số hiệu chuyến bay {$e['flight_no']} đã tồn tại.");
            }
            $db->addEdge($e['flight_no'], $e['source'], $e['target'], $e['weight'], $e['duration'], $e['airline']);
            out(['status' => 'success', 'message' => 'Đã thêm chuyến bay', 'edge' => $e]);
            break;

        // --------------------------------------------------------------------
        // [3.5] delete_edge — xóa chuyến bay theo id
        // --------------------------------------------------------------------
        case 'delete_edge':
            requirePost();
            $id = (int)($input['id'] ?? 0);
            if ($id <= 0) throw new Exception('Thiếu id chuyến bay.');
            if (!$db->deleteEdge($id)) throw new Exception("Không tìm thấy chuyến bay id=$id.");
            out(['status' => 'success', 'message' => 'Đã xóa chuyến bay']);
            break;

        // --------------------------------------------------------------------
        // [3.6] book_ticket — đặt vé, sinh mã PNR
        //       Giá & thời gian do SERVER tính lại từ bảng edges (không tin client).
        //       total_price = tổng giá các chặng x số hành khách (chưa nhân hệ số hạng ghế).
        // --------------------------------------------------------------------
        case 'book_ticket':
            requirePost();
            $name = trim((string)($input['passenger_name'] ?? ''));
            $email = trim((string)($input['passenger_email'] ?? ''));
            if (textLen($name) < 2 || textLen($name) > 100) throw new Exception('Họ tên hành khách phải từ 2 đến 100 ký tự.');
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new Exception('Email không hợp lệ.');

            $seatClass = trim((string)($input['seat_class'] ?? 'Economy'));
            if ($seatClass === '') $seatClass = 'Economy';
            if (textLen($seatClass) > 30) throw new Exception('Hạng ghế quá dài.');

            $count = (int)($input['passengers_count'] ?? 1);
            if ($count < 1 || $count > 9) throw new Exception('Số hành khách phải từ 1 đến 9.');

            $criteria = (string)($input['criteria'] ?? 'weight');
            if (!in_array($criteria, ['weight', 'duration'], true)) $criteria = 'weight';

            $codes = parseRoute($input['route_path'] ?? []);
            foreach ($codes as $c) {
                if (!$db->vertexExists($c)) throw new Exception("Sân bay không tồn tại: $c");
            }
            $route = $db->calculateRoute($codes, $criteria);   // ném lỗi nếu có chặng không tồn tại

            $booking = [
                'passenger_name'   => $name,
                'passenger_email'  => $email,
                'seat_class'       => $seatClass,
                'passengers_count' => $count,
                'source'           => $codes[0],
                'target'           => $codes[count($codes) - 1],
                'route_path'       => implode(',', $codes),            // lưu "SGN,NRT,JFK"
                'total_price'      => $route['total_price'] * $count,
                'total_duration'   => $route['total_duration'],        // giờ, cho cả hành trình
                'booked_at'        => date('Y-m-d H:i:s'),
            ];
            $pnr = $db->createBooking($booking);

            out(['status' => 'success', 'pnr_code' => $pnr, 'message' => 'Đặt vé thành công',
                 'booking' => array_merge(['pnr_code' => $pnr], $booking, ['legs' => $route['legs']])]);
            break;

        // --------------------------------------------------------------------
        // [3.7] reset_db — khôi phục đồ thị mặc định (vé đã đặt được giữ nguyên)
        // --------------------------------------------------------------------
        case 'reset_db':
            requirePost();
            $db->seedDefaultGraph();
            out(['status' => 'success', 'message' => 'Đã khôi phục dữ liệu mặc định']);
            break;

        // --------------------------------------------------------------------
        // [3.8] export_graph — xuất đồ thị ra JSON (?download=1 để tải về file)
        // --------------------------------------------------------------------
        case 'export_graph':
            if (!empty($_GET['download'])) {
                header('Content-Disposition: attachment; filename="graph.json"');
            }
            out(array_merge(['status' => 'success'], $db->exportGraph()));
            break;

        // --------------------------------------------------------------------
        // [3.9] import_graph — nạp đồ thị từ JSON (mode: merge | replace)
        // --------------------------------------------------------------------
        case 'import_graph':
            requirePost();
            $rawV = $input['vertices'] ?? [];
            $rawE = $input['edges'] ?? [];
            if (!is_array($rawV) || !is_array($rawE)) throw new Exception('vertices và edges phải là mảng.');
            $replace = (($input['mode'] ?? 'merge') === 'replace');
            if ($replace && count($rawV) === 0) throw new Exception('Chế độ replace cần ít nhất 1 sân bay.');

            $cleanV = []; $cleanE = [];
            foreach ($rawV as $i => $r) {
                try { $cleanV[] = cleanVertex(is_array($r) ? $r : []); }
                catch (Exception $ex) { throw new Exception('Sân bay thứ ' . ($i + 1) . ': ' . $ex->getMessage()); }
            }
            foreach ($rawE as $i => $r) {
                try { $cleanE[] = cleanEdge(is_array($r) ? $r : []); }
                catch (Exception $ex) { throw new Exception('Chuyến bay thứ ' . ($i + 1) . ': ' . $ex->getMessage()); }
            }
            $stat = $db->importGraph($cleanV, $cleanE, $replace);
            out(['status' => 'success', 'message' => 'Đã nhập đồ thị', 'imported' => $stat]);
            break;

        default:
            throw new Exception('Action không hợp lệ.');
    }
} catch (Exception $e) {
    http_response_code(400);
    out(['status' => 'error', 'message' => $e->getMessage()]);
}
