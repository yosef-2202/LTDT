<?php
/**
 * ============================================================================
 * FILE DÀNH CHO THÀNH VIÊN 1: BIỂU DIỄN ĐỒ THỊ & THUẬT TOÁN DIJKSTRA LÕI
 * ============================================================================
 * 1. PHẦN CODE ĐÃ CHẠY SẴN TRONG FILE NÀY:
 *    - Chuyển đổi dữ liệu từ CSDL sang 3 cấu trúc biểu diễn đồ thị chuẩn giáo trình:
 *      + Ma trận trọng số (Weight Matrix): `getWeightMatrix()`
 *      + Danh sách kề (Adjacency List): `getAdjacencyList()`
 *      + Danh sách cung (Edge List): `getEdgeList()`
 *    - Cài đặt thuật toán Dijkstra tìm đường đi ngắn nhất từ đỉnh $start đến $target.
 *    - Ghi lại mảng `steps` (Snapshot từng bước lặp) gồm: Tập đỉnh đóng S, Hàng đợi ưu tiên,
 *      nhãn `(dist, prev)` của mọi đỉnh để Thành viên 4 vẽ bản đồ và Thành viên 5 in Trace Table.
 *
 * 2. NHIỆM VỤ MỞ RỘNG CHO THÀNH VIÊN 1 (ĐỂ LẤY ĐIỂM 9-10 KHI VẤN ĐÁP):
 *    - Hoàn thiện thêm hàm `runDijkstraWithStopover(string $start, string $via, string $target)`:
 *      Tìm chuyến bay rẻ nhất từ A đến B nhưng BẮT BUỘC phải quá cảnh qua sân bay trung gian C
 *      (Gợi ý: Chạy Dijkstra từ A -> C rồi ghép với Dijkstra từ C -> B).
 *
 * 3. CÂU HỎI GIẢNG VIÊN HAY HỎI THÀNH VIÊN 1:
 *    - Hỏi: "Tại sao đồ thị mạng bay lại dùng Danh sách kề để chạy Dijkstra mà không dùng Ma trận kề?"
 *    - Trả lời: Vì mạng đường bay thực tế là đồ thị thưa (|E| << |V|^2). Dùng Danh sách kề giúp
 *      việc duyệt các cung đi ra từ đỉnh u chỉ tốn O(deg+(u)) thay vì O(|V|), giảm độ phức tạp tổng thể.
 * ============================================================================
 */

class DijkstraCore {
    private array $vertices = []; // Danh sách mã đỉnh ['SGN', 'HAN', ...]
    private array $adjList = [];  // Danh sách kề: $adjList['SGN'] = [['to' => 'HAN', 'weight' => 85, ...]]
    private array $edges = [];    // Danh sách cung (Edge List)

    public function __construct(array $verticesRows, array $edgesRows, string $criteria = 'weight') {
        foreach ($verticesRows as $v) {
            if ((int)$v['is_active'] === 1) {
                $code = $v['code'];
                $this->vertices[] = $code;
                $this->adjList[$code] = [];
            }
        }

        foreach ($edgesRows as $e) {
            if ((int)$e['is_active'] === 1 && isset($this->adjList[$e['source']]) && isset($this->adjList[$e['target']])) {
                // Cho phép đổi trọng số cạnh linh hoạt giữa Giá vé (weight) hoặc Giờ bay (duration)
                $w = ($criteria === 'duration') ? (float)$e['duration'] : (float)$e['weight'];

                $edgeItem = [
                    'id'        => (int)$e['id'],
                    'flight_no' => $e['flight_no'] ?? ('SG-' . $e['id']),
                    'from'      => $e['source'],
                    'to'        => $e['target'],
                    'weight'    => $w,
                    'price'     => (float)$e['weight'],
                    'duration'  => (int)$e['duration'],
                    'airline'   => $e['airline']
                ];

                $this->adjList[$e['source']][] = $edgeItem;
                $this->edges[] = $edgeItem;
            }
        }
    }

    /**
     * 1. Biểu diễn Đồ thị bằng Ma trận trọng số A[u][v]
     */
    public function getWeightMatrix(): array {
        $matrix = [];
        foreach ($this->vertices as $u) {
            $matrix[$u] = [];
            foreach ($this->vertices as $v) {
                $matrix[$u][$v] = ($u === $v) ? 0 : '∞';
            }
            foreach ($this->adjList[$u] as $edge) {
                $v = $edge['to'];
                // Nếu giữa 2 sân bay có nhiều chuyến bay (đa cung), chọn cung có trọng số nhỏ nhất
                if ($matrix[$u][$v] === '∞' || $edge['weight'] < $matrix[$u][$v]) {
                    $matrix[$u][$v] = $edge['weight'];
                }
            }
        }
        return $matrix;
    }

    /**
     * 2. Biểu diễn Đồ thị bằng Danh sách kề (Adjacency List)
     */
    public function getAdjacencyList(): array {
        return $this->adjList;
    }

    /**
     * 3. Biểu diễn Đồ thị bằng Danh sách cung (Edge List)
     */
    public function getEdgeList(): array {
        return $this->edges;
    }

    /**
     * Thuật toán Dijkstra tìm đường đi ngắn nhất & Ghi nhật ký Trace Table
     */
    public function runDijkstra(string $start, string $target): array {
        $startTime = microtime(true);

        if (!in_array($start, $this->vertices) || !in_array($target, $this->vertices)) {
            return [
                'found'   => false,
                'message' => 'Đỉnh sân bay xuất phát hoặc sân bay đích không tồn tại.'
            ];
        }

        $dist = [];
        $prev = [];
        $visited = []; // Tập S chứa các đỉnh đã cố định nhãn
        $steps = [];   // Lịch sử từng bước lặp

        // Bước 0: Khởi tạo nhãn
        foreach ($this->vertices as $v) {
            $dist[$v] = INF;
            $prev[$v] = null;
            $visited[$v] = false;
        }
        $dist[$start] = 0;

        $steps[] = $this->createStepSnapshot(
            0,
            null,
            [],
            $dist,
            $prev,
            $visited,
            "Khởi tạo: Gán d($start) = 0, các đỉnh v ≠ $start gán d(v) = ∞. Tập đỉnh cố định S = ∅."
        );

        $stepCount = 1;
        $n = count($this->vertices);

        for ($i = 0; $i < $n; $i++) {
            // Chọn đỉnh u chưa thuộc S có nhãn d(u) nhỏ nhất
            $u = null;
            $minDist = INF;
            foreach ($this->vertices as $v) {
                if (!$visited[$v] && $dist[$v] < $minDist) {
                    $minDist = $dist[$v];
                    $u = $v;
                }
            }

            // Nếu không còn đỉnh nào liên thông có thể tới được
            if ($u === null || $minDist === INF) {
                break;
            }

            // Đưa u vào tập S (Cố định nhãn của đỉnh u)
            $visited[$u] = true;
            $relaxedEdges = [];
            $logDetails = [];

            // Duyệt tất cả các cung đi ra từ u: (u, v)
            foreach ($this->adjList[$u] as $edge) {
                $v = $edge['to'];
                $w = $edge['weight'];

                if (!$visited[$v]) {
                    $newDist = $dist[$u] + $w;
                    if ($newDist < $dist[$v]) {
                        $oldText = ($dist[$v] === INF) ? '∞' : $dist[$v];
                        $dist[$v] = $newDist;
                        $prev[$v] = $u;
                        $relaxedEdges[] = ['from' => $u, 'to' => $v, 'weight' => $w, 'status' => 'updated'];
                        $logDetails[] = "Cập nhật $v: d($v) = min($oldText, {$dist[$u]} + $w) = $newDist (qua $u)";
                    } else {
                        $relaxedEdges[] = ['from' => $u, 'to' => $v, 'weight' => $w, 'status' => 'ignored'];
                        $logDetails[] = "Giữ nguyên $v: {$dist[$u]} + $w = $newDist ≥ d($v) ({$dist[$v]})";
                    }
                }
            }

            $explanation = "Chọn u = $u cố định vào tập S với d($u) = {$dist[$u]}. ";
            $explanation .= empty($logDetails) ? "Không có đỉnh kề nào được cải thiện nhãn." : implode(" | ", $logDetails);

            $steps[] = $this->createStepSnapshot(
                $stepCount++,
                $u,
                $relaxedEdges,
                $dist,
                $prev,
                $visited,
                $explanation
            );

            // Dừng sớm khi đỉnh đích đã được cố định nhãn
            if ($u === $target) {
                break;
            }
        }

        // Truy vết đường đi từ $target ngược về $start
        $path = [];
        $pathEdges = [];
        $found = ($dist[$target] !== INF);

        if ($found) {
            $curr = $target;
            while ($curr !== null) {
                array_unshift($path, $curr);
                $p = $prev[$curr];
                if ($p !== null) {
                    foreach ($this->adjList[$p] as $e) {
                        if ($e['to'] === $curr && abs(($dist[$p] + $e['weight']) - $dist[$curr]) < 0.0001) {
                            array_unshift($pathEdges, [
                                'flight_no' => $e['flight_no'],
                                'from'      => $p,
                                'to'        => $curr,
                                'weight'    => $e['weight'],
                                'price'     => $e['price'],
                                'duration'  => $e['duration'],
                                'airline'   => $e['airline']
                            ]);
                            break;
                        }
                    }
                }
                $curr = $p;
            }
        }

        $execTimeMs = round((microtime(true) - $startTime) * 1000, 3);

        return [
            'found'         => $found,
            'start'         => $start,
            'target'        => $target,
            'total_cost'    => $found ? $dist[$target] : null,
            'path'          => $path,
            'path_edges'    => $pathEdges,
            'steps'         => $steps,
            'vertices_list' => $this->vertices,
            'exec_time_ms'  => $execTimeMs
        ];
    }

    /**
     * Đóng gói trạng thái từng bước lặp cho Bảng Trace Table & Bản đồ
     */
    private function createStepSnapshot(
        int $stepIndex,
        ?string $currentU,
        array $relaxedEdges,
        array $dist,
        array $prev,
        array $visited,
        string $explanation
    ): array {
        $labels = [];
        $priorityQueue = [];

        foreach ($this->vertices as $v) {
            $dVal = ($dist[$v] === INF) ? '∞' : $dist[$v];
            $pVal = $prev[$v] ?? '-';
            $labels[$v] = [
                'dist'   => $dVal,
                'prev'   => $pVal,
                'closed' => $visited[$v],
                'is_u'   => ($v === $currentU)
            ];

            if (!$visited[$v] && $dist[$v] !== INF) {
                $priorityQueue[] = ['vertex' => $v, 'dist' => $dist[$v]];
            }
        }

        usort($priorityQueue, fn($a, $b) => $a['dist'] <=> $b['dist']);

        return [
            'step'           => $stepIndex,
            'current_u'      => $currentU,
            'relaxed_edges'  => $relaxedEdges,
            'labels'         => $labels,
            'priority_queue' => $priorityQueue,
            'visited_set'    => array_keys(array_filter($visited)),
            'explanation'    => $explanation
        ];
    }
}