<?php
/**
 * THÀNH VIÊN 1: BIỂU DIỄN ĐỒ THỊ & THUẬT TOÁN DIJKSTRA LÕI
 * File: includes/DijkstraCore.php
 */
class DijkstraCore {
    private array $vertices = []; // Danh sách mã đỉnh: ['SGN', 'HAN', ...]
    private array $adjList = [];  // Danh sách kề: $adjList['SGN'] = [['to' => 'HAN', 'weight' => 85, ...]]
    private array $edges = [];    // Danh sách cung chuẩn hóa
    private string $criteria;     // Tiêu chí tối ưu: 'weight' (giá $) hoặc 'duration' (giờ bay h)

    public function __construct(array $verticesRows, array $edgesRows, string $criteria = 'weight') {
        $this->criteria = ($criteria === 'duration') ? 'duration' : 'weight';

        // 1. Nạp danh sách đỉnh đang hoạt động (is_active = 1)
        foreach ($verticesRows as $v) {
            if ((int)($v['is_active'] ?? 1) === 1) {
                $code = strtoupper(trim($v['code']));
                $this->vertices[] = $code;
                $this->adjList[$code] = [];
            }
        }

        // 2. Nạp danh sách cung và xây dựng Danh sách kề (Adjacency List)
        foreach ($edgesRows as $e) {
            if ((int)($e['is_active'] ?? 1) !== 1) {
                continue;
            }

            $u = strtoupper(trim($e['source']));
            $v = strtoupper(trim($e['target']));

            // Chỉ nạp cung nếu cả 2 đỉnh đều tồn tại trong tập đỉnh hợp lệ
            if (!isset($this->adjList[$u]) || !isset($this->adjList[$v])) {
                continue;
            }

            // Chọn trọng số w(u, v) theo tiêu chí người dùng chọn
            $w = ($this->criteria === 'duration') ? (float)$e['duration'] : (float)$e['weight'];

            $edgeData = [
                'id'        => (int)($e['id'] ?? 0),
                'flight_no' => $e['flight_no'] ?? ('FL-' . ($e['id'] ?? '000')),
                'from'      => $u,
                'to'        => $v,
                'weight'    => $w,                 // Trọng số đang dùng để chạy Dijkstra
                'price'     => (float)$e['weight'],
                'duration'  => (int)$e['duration'],
                'airline'   => $e['airline'] ?? 'Unknown'
            ];

            $this->adjList[$u][] = $edgeData;
            $this->edges[] = $edgeData;
        }
    }

    /**
     * Xây dựng Ma trận trọng số A[u][v]
     * - Đường chéo chính A[u][u] = 0
     * - Không có cạnh trực tiếp = '∞'
     * - Nếu có nhiều chuyến bay cùng chặng (u -> v), lấy trọng số nhỏ nhất
     */
    public function getWeightMatrix(): array {
        $matrix = [];

        foreach ($this->vertices as $u) {
            $matrix[$u] = [];
            foreach ($this->vertices as $v) {
                $matrix[$u][$v] = ($u === $v) ? 0 : '∞';
            }
        }

        foreach ($this->edges as $e) {
            $u = $e['from'];
            $v = $e['to'];
            $w = $e['weight'];

            if ($matrix[$u][$v] === '∞' || $w < $matrix[$u][$v]) {
                $matrix[$u][$v] = $w;
            }
        }

        return $matrix;
    }

    public function getAdjacencyList(): array {
        return $this->adjList;
    }

    public function getEdgeList(): array {
        return $this->edges;
    }

    /**
     * THUẬT TOÁN DIJKSTRA TÌM ĐƯỜNG ĐI NGẮN NHẤT TỪ $start ĐẾN $target
     * Có ghi lại đầy đủ snapshot từng bước lặp để phục vụ Bản đồ (TV4) & Trace Table (TV5)
     */
    public function runDijkstra(string $start, string $target): array {
        $startTime = microtime(true);
        $start = strtoupper(trim($start));
        $target = strtoupper(trim($target));
        $unit = ($this->criteria === 'duration') ? 'h' : '$';

        // Kiểm tra tính hợp lệ của đỉnh đầu vào
        if (!isset($this->adjList[$start]) || !isset($this->adjList[$target])) {
            throw new Exception("Sân bay đi ($start) hoặc đến ($target) không tồn tại trong đồ thị.");
        }

        // Kiểm tra điều kiện tiên quyết của Dijkstra: Không chấp nhận trọng số âm
        foreach ($this->edges as $e) {
            if ($e['weight'] < 0) {
                throw new Exception("Thuật toán Dijkstra không hỗ trợ cạnh có trọng số âm ({$e['from']} -> {$e['to']} = {$e['weight']}).");
            }
        }

        // =====================================================================
        // BƯỚC 1: KHỞI TẠO (INITIALIZATION)
        // =====================================================================
        $dist = [];      // dist[v]: Chi phí ngắn nhất tạm thời từ $start đến v
        $prev = [];      // prev[v]: Đỉnh liền trước v trên đường đi ngắn nhất
        $prevEdge = [];  // prevEdge[v]: Lưu thông tin chuyến bay cụ thể đi vào v
        $visited = [];   // visited[v]: Đánh dấu đỉnh v đã được cố định (thuộc tập S) hay chưa

        foreach ($this->vertices as $v) {
            $dist[$v] = INF;
            $prev[$v] = null;
            $prevEdge[$v] = null;
            $visited[$v] = false;
        }
        $dist[$start] = 0;

        $steps = [];
        $stepIndex = 0;

        // Lưu Snapshot Bước 0 (Trạng thái khởi tạo)
        $steps[] = $this->createStepSnapshot(
            $stepIndex,
            null,
            [],
            $dist,
            $prev,
            $visited,
            "Bước 0 (Khởi tạo): Đặt d[$start] = 0, các đỉnh còn lại = ∞. Tập đỉnh đã cố định S = ∅."
        );

        // =====================================================================
        // BƯỚC 2: VÒNG LẶP CHÍNH CỦA DIJKSTRA
        // =====================================================================
        $totalVertices = count($this->vertices);

        for ($i = 0; $i < $totalVertices; $i++) {
            // 2.1. Chọn đỉnh u chưa thăm có dist[u] nhỏ nhất (Extract-Min)
            $u = null;
            $minDist = INF;

            foreach ($this->vertices as $v) {
                if (!$visited[$v] && $dist[$v] < $minDist) {
                    $minDist = $dist[$v];
                    $u = $v;
                }
            }

            // Nếu không còn đỉnh nào có thể đi tới được -> dừng vòng lặp
            if ($u === null || $minDist === INF) {
                break;
            }

            // 2.2. Cố định đỉnh u (đưa u vào tập S)
            $visited[$u] = true;
            $stepIndex++;

            // Nếu đỉnh vừa cố định chính là đích $target -> Dừng sớm (Early Exit)
            if ($u === $target) {
                $steps[] = $this->createStepSnapshot(
                    $stepIndex,
                    $u,
                    [],
                    $dist,
                    $prev,
                    $visited,
                    "Bước $stepIndex: Chọn đỉnh đích $u với d[$u] = {$dist[$u]}{$unit}. Đã tìm thấy đường đi tối ưu, dừng thuật toán!"
                );
                break;
            }

            // 2.3. Thư giãn các cạnh kề (Edge Relaxation) từ u -> v
            $relaxedEdges = [];
            $updatedNotes = [];

            foreach ($this->adjList[$u] as $edge) {
                $v = $edge['to'];
                $w = $edge['weight'];

                // Bỏ qua đỉnh v đã được cố định trong tập S
                if ($visited[$v]) {
                    continue;
                }

                $oldDist = $dist[$v];
                $newDist = $dist[$u] + $w;

                if ($newDist < $oldDist) {
                    // Cập nhật nhãn mới tốt hơn
                    $dist[$v] = $newDist;
                    $prev[$v] = $u;
                    $prevEdge[$v] = $edge;

                    $relaxedEdges[] = [
                        'from'     => $u,
                        'to'       => $v,
                        'weight'   => $w,
                        'old_dist' => ($oldDist === INF) ? '∞' : $oldDist,
                        'new_dist' => $newDist,
                        'status'   => 'updated' // TV4 tô màu vàng
                    ];
                    $updatedNotes[] = "$v (" . (($oldDist === INF) ? '∞' : $oldDist . $unit) . " → {$newDist}{$unit})";
                } else {
                    // Không cải thiện được chi phí -> giữ nguyên
                    $relaxedEdges[] = [
                        'from'     => $u,
                        'to'       => $v,
                        'weight'   => $w,
                        'old_dist' => ($oldDist === INF) ? '∞' : $oldDist,
                        'new_dist' => $newDist,
                        'status'   => 'ignored' // TV4 tô màu đỏ
                    ];
                }
            }

            // Tạo câu giải thích trực quan cho bước hiện tại
            $explain = "Bước $stepIndex: Cố định đỉnh u = $u (d[$u] = {$dist[$u]}{$unit}). ";
            if (!empty($updatedNotes)) {
                $explain .= "Cập nhật nhãn (Relaxation): " . implode(', ', $updatedNotes) . ".";
            } else {
                $explain .= "Không có đỉnh kề nào được cải thiện khoảng cách.";
            }

            // Lưu Snapshot của bước lặp hiện tại
            $steps[] = $this->createStepSnapshot(
                $stepIndex,
                $u,
                $relaxedEdges,
                $dist,
                $prev,
                $visited,
                $explain
            );
        }

        // =====================================================================
        // BƯỚC 3: TRUY VẾT NGƯỢC ĐƯỜNG ĐI TỐI ƯU (BACKTRACKING)
        // =====================================================================
        $found = ($dist[$target] !== INF);
        $path = [];
        $pathEdges = [];

        if ($found) {
            $curr = $target;
            while ($curr !== null) {
                $path[] = $curr;
                if ($prevEdge[$curr] !== null) {
                    $pathEdges[] = $prevEdge[$curr];
                }
                $curr = $prev[$curr];
            }
            // Đảo ngược mảng để có thứ tự từ $start -> $target
            $path = array_reverse($path);
            $pathEdges = array_reverse($pathEdges);
        }

        return [
            'found'         => $found,
            'start'         => $start,
            'target'        => $target,
            'criteria'      => $this->criteria,
            'total_cost'    => $found ? $dist[$target] : null,
            'path'          => $path,
            'path_edges'    => $pathEdges,
            'steps'         => $steps,
            'vertices_list' => $this->vertices,
            'exec_time_ms'  => round((microtime(true) - $startTime) * 1000, 3)
        ];
    }

    /**
     * Đóng gói trạng thái từng bước cho Bản đồ (TV4) & Trace Table (TV5)
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
        $visitedSet = [];

        foreach ($this->vertices as $v) {
            $isInf = ($dist[$v] === INF);

            $labels[$v] = [
                'dist'   => $isInf ? '∞' : $dist[$v],
                'prev'   => $prev[$v] ?? '-',
                'closed' => $visited[$v],
                'is_u'   => ($v === $currentU)
            ];

            if ($visited[$v]) {
                $visitedSet[] = $v;
            } elseif (!$isInf) {
                $priorityQueue[] = [
                    'vertex' => $v,
                    'dist'   => $dist[$v]
                ];
            }
        }

        // Sắp xếp hàng đợi ưu tiên tăng dần theo khoảng cách
        usort($priorityQueue, fn($a, $b) => $a['dist'] <=> $b['dist']);

        return [
            'step'           => $stepIndex,
            'current_u'      => $currentU,
            'relaxed_edges'  => $relaxedEdges,
            'labels'         => $labels,
            'priority_queue' => $priorityQueue,
            'visited_set'    => $visitedSet,
            'explanation'    => $explanation
        ];
    }

    /**
     * MỞ RỘNG (TV1): Tìm đường đi ngắn nhất bắt buộc quá cảnh qua 1 sân bay trung gian ($via)
     * Ý tưởng: Chạy Dijkstra 2 chặng ($start -> $via) và ($via -> $target) rồi ghép kết quả.
     */
    public function runDijkstraWithStopover(string $start, string $via, string $target): array {
        $leg1 = $this->runDijkstra($start, $via);
        $leg2 = $this->runDijkstra($via, $target);

        if (!$leg1['found'] || !$leg2['found']) {
            return [
                'found'      => false,
                'start'      => $start,
                'via'        => $via,
                'target'     => $target,
                'total_cost' => null,
                'path'       => [],
                'path_edges' => [],
                'message'    => "Không tìm thấy hành trình hợp lệ đi qua điểm quá cảnh $via."
            ];
        }

        // Ghép path (bỏ đỉnh $via bị lặp ở đầu chặng 2)
        $combinedPath = array_merge($leg1['path'], array_slice($leg2['path'], 1));
        $combinedEdges = array_merge($leg1['path_edges'], $leg2['path_edges']);

        return [
            'found'         => true,
            'start'         => $start,
            'via'           => $via,
            'target'        => $target,
            'criteria'      => $this->criteria,
            'total_cost'    => $leg1['total_cost'] + $leg2['total_cost'],
            'path'          => $combinedPath,
            'path_edges'    => $combinedEdges,
            'leg1'          => $leg1,
            'leg2'          => $leg2,
            'exec_time_ms'  => round($leg1['exec_time_ms'] + $leg2['exec_time_ms'], 3)
        ];
    }
}