<?php
/**
 * THÀNH VIÊN 3: API ĐIỀU PHỐI BACKEND
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/DijkstraCore.php';
require_once __DIR__ . '/includes/GraphAnalyzer.php';

try {
    $db = new Database();
    $action = $_GET['action'] ?? 'init_data';
    $input = json_decode(file_get_contents('php://input'), true) ?? [];

    switch ($action) {
        case 'init_data':
            $vertices = $db->getAllVertices();
            $edges = $db->getAllEdges();
            $core = new DijkstraCore($vertices, $edges);
            $analyzer = new GraphAnalyzer($vertices, $edges);

            echo json_encode([
                'status'        => 'success',
                'vertices'      => $vertices,
                'edges'         => $edges,
                'bookings'      => $db->getAllBookings(),
                'weight_matrix' => $core->getWeightMatrix(),
                'adj_list'      => $core->getAdjacencyList(),
                'analysis'      => $analyzer->analyzeProperties()
            ]);
            break;

        case 'run_dijkstra':
            $start = strtoupper(trim($input['source'] ?? 'SGN'));
            $target = strtoupper(trim($input['target'] ?? 'JFK'));
            $criteria = $input['criteria'] ?? 'weight';

            $vertices = $db->getAllVertices();
            $edges = $db->getAllEdges();

            // TODO (TV3): Gọi GraphAnalyzer kiểm tra cạnh âm -> nếu có gọi Bellman-Ford và báo lỗi
            $analyzer = new GraphAnalyzer($vertices, $edges);
            $bfsCheck = $analyzer->isReachableBFS($start, $target);

            $core = new DijkstraCore($vertices, $edges, $criteria);
            $result = $core->runDijkstra($start, $target);
            $result['bfs_check'] = $bfsCheck;

            echo json_encode(['status' => 'success', 'data' => $result]);
            break;

        case 'add_vertex':
            // TODO (TV3): Validate dữ liệu và gọi $db->addVertex(...)
            echo json_encode(['status' => 'success', 'message' => 'Đã thêm sân bay']);
            break;

        case 'add_edge':
            // TODO (TV3): Validate dữ liệu và gọi $db->addEdge(...)
            echo json_encode(['status' => 'success', 'message' => 'Đã thêm chuyến bay']);
            break;

        case 'delete_edge':
            // TODO (TV3): Gọi $db->deleteEdge((int)$input['id'])
            echo json_encode(['status' => 'success', 'message' => 'Đã xóa chuyến bay']);
            break;

        case 'book_ticket':
            // TODO (TV3): Validate thông tin khách và gọi $db->createBooking($input)
            echo json_encode(['status' => 'success', 'pnr_code' => 'SG0000', 'message' => 'Đặt vé thành công']);
            break;

        case 'reset_db':
            $db->seedDefaultGraph();
            echo json_encode(['status' => 'success', 'message' => 'Đã khôi phục dữ liệu mặc định']);
            break;

        default:
            throw new Exception('Action không hợp lệ.');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}