<?php
/**
 * ============================================================================
 * FILE API ĐIỀU PHỐI BACKEND (THÀNH VIÊN 3 PHỤ TRÁCH)
 * ============================================================================
 * Danh sách các API Endpoint:
 * 1. GET  `api.php?action=init_data`    : Trả về Đỉnh, Cung, Vé đã đặt, Ma trận trọng số, Bậc đỉnh.
 * 2. POST `api.php?action=run_dijkstra` : Kiểm tra cạnh âm, kiểm tra BFS và chạy Dijkstra.
 * 3. POST `api.php?action=add_vertex`   : Thêm sân bay mới vào đồ thị.
 * 4. POST `api.php?action=add_edge`     : Mở chuyến bay mới giữa 2 sân bay.
 * 5. POST `api.php?action=delete_edge`  : Xóa một chuyến bay.
 * 6. POST `api.php?action=book_ticket`  : Lưu thông tin đặt vé và xuất mã PNR.
 * 7. POST `api.php?action=reset_db`     : Khôi phục đồ thị mặc định ban đầu.
 * ============================================================================
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/DijkstraCore.php';
require_once __DIR__ . '/includes/GraphAnalyzer.php';

try {
    $db = new Database();
    $action = $_GET['action'] ?? 'init_data';

    if ($action === 'init_data') {
        $vertices = $db->getAllVertices();
        $edges = $db->getAllEdges();
        $bookings = $db->getAllBookings();

        $core = new DijkstraCore($vertices, $edges);
        $analyzer = new GraphAnalyzer($vertices, $edges);

        echo json_encode([
            'status'        => 'success',
            'vertices'      => $vertices,
            'edges'         => $edges,
            'bookings'      => $bookings,
            'weight_matrix' => $core->getWeightMatrix(),
            'adj_list'      => $core->getAdjacencyList(),
            'analysis'      => $analyzer->analyzeProperties()
        ]);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    if ($action === 'run_dijkstra') {
        $start = strtoupper(trim($input['source'] ?? 'SGN'));
        $target = strtoupper(trim($input['target'] ?? 'JFK'));
        $criteria = $input['criteria'] ?? 'weight';

        $vertices = $db->getAllVertices();
        $edges = $db->getAllEdges();

        $analyzer = new GraphAnalyzer($vertices, $edges);
        $props = $analyzer->analyzeProperties();

        // Nếu phát hiện cạnh trọng số âm, gọi thêm Bellman-Ford của TV2 để báo cáo đối chiếu
        if ($props['has_negative_weight'] && $criteria === 'weight') {
            $bfResult = $analyzer->runBellmanFord($start, $target);
            $bfNote = $bfResult['negative_cycle']
                ? 'Đồ thị chứa chu trình âm!'
                : ('Kết quả đối chiếu bằng Bellman-Ford: $' . ($bfResult['total_cost'] ?? '∞'));

            echo json_encode([
                'status'  => 'error',
                'message' => "Đồ thị chứa cạnh có trọng số âm (w < 0)! Thuật toán Dijkstra không áp dụng được. ($bfNote)"
            ]);
            exit;
        }

        $bfsCheck = $analyzer->isReachableBFS($start, $target);
        $core = new DijkstraCore($vertices, $edges, $criteria);
        $result = $core->runDijkstra($start, $target);
        $result['bfs_check'] = $bfsCheck;

        echo json_encode([
            'status' => 'success',
            'data'   => $result
        ]);
        exit;
    }

    if ($action === 'add_vertex') {
        $code = strtoupper(trim($input['code'] ?? ''));
        $name = trim($input['name'] ?? '');
        $city = trim($input['city'] ?? '');
        $country = trim($input['country'] ?? 'Vietnam');
        $lat = (float)($input['lat'] ?? 0);
        $lng = (float)($input['lng'] ?? 0);

        if (strlen($code) !== 3 || $name === '' || $city === '') {
            throw new Exception('Mã IATA sân bay phải gồm đúng 3 chữ cái (VD: DAD, CXR, FRA) và đầy đủ tên thành phố.');
        }

        $db->addVertex($code, $name, $city, $country, $lat, $lng);
        echo json_encode(['status' => 'success', 'message' => "Đã thêm Đỉnh sân bay mới: $code - $name ($city)"]);
        exit;
    }

    if ($action === 'add_edge') {
        $flightNo = strtoupper(trim($input['flight_no'] ?? 'SG-999'));
        $source = strtoupper(trim($input['source'] ?? ''));
        $target = strtoupper(trim($input['target'] ?? ''));
        $weight = (float)($input['weight'] ?? 100);
        $duration = (int)($input['duration'] ?? 2);
        $airline = trim($input['airline'] ?? 'SkyGraph Airlines');

        if ($source === '' || $target === '' || $source === $target) {
            throw new Exception('Sân bay đi và sân bay đến không được trùng nhau.');
        }

        $db->addEdge($flightNo, $source, $target, $weight, $duration, $airline);
        echo json_encode(['status' => 'success', 'message' => "Đã mở đường bay $flightNo ($source ➔ $target) với giá $$weight"]);
        exit;
    }

    if ($action === 'delete_edge') {
        $id = (int)($input['id'] ?? 0);
        $db->deleteEdge($id);
        echo json_encode(['status' => 'success', 'message' => "Đã xóa chuyến bay #$id khỏi đồ thị"]);
        exit;
    }

    if ($action === 'book_ticket') {
        if (empty($input['passenger_name']) || empty($input['route_path'])) {
            throw new Exception('Vui lòng nhập đầy đủ họ tên hành khách trước khi xuất vé.');
        }
        $pnr = $db->createBooking($input);
        echo json_encode([
            'status'   => 'success',
            'pnr_code' => $pnr,
            'message'  => "Đặt vé thành công! Mã đặt chỗ (PNR) của bạn là: $pnr"
        ]);
        exit;
    }

    if ($action === 'reset_db') {
        $db->seedDefaultGraph();
        echo json_encode(['status' => 'success', 'message' => 'Đã khôi phục mạng đường bay quốc tế mặc định!']);
        exit;
    }

    throw new Exception('Yêu cầu API không hợp lệ.');

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}