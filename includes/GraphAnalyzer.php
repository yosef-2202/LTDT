<?php
/**
 * ============================================================================
 * FILE DÀNH CHO THÀNH VIÊN 2: PHÂN TÍCH ĐỒ THỊ, TÍNH LIÊN THÔNG & BELLMAN-FORD
 * ============================================================================
 * 1. PHẦN CODE ĐÃ CHẠY SẴN TRONG FILE NÀY:
 *    - Tính bậc vào deg-(v), bậc ra deg+(v) của mọi đỉnh và tìm Sân bay trung chuyển lớn nhất (Hub).
 *    - Tính mật độ đồ thị có hướng: D = |E| / (|V| * (|V| - 1)).
 *    - Thuật toán BFS kiểm tra tính liên thông giữa 2 sân bay bất kỳ và phát hiện đỉnh cô lập.
 *    - Thuật toán Bellman-Ford xử lý trường hợp đồ thị có cạnh trọng số âm (w < 0).
 *
 * 2. NHIỆM VỤ MỞ RỘNG CHO THÀNH VIÊN 2:
 *    - Viết thêm hàm `findPathMaxStops(string $start, string $target, int $maxStops)`:
 *      Tìm chuyến bay rẻ nhất nhưng giới hạn tối đa K điểm dừng quá cảnh.
 *
 * 3. CÂU HỎI GIẢNG VIÊN HAY HỎI THÀNH VIÊN 2:
 *    - Hỏi: "Vì sao khi có một chặng bay khuyến mãi hoàn tiền (trọng số âm w < 0) thì Dijkstra lại sai?"
 *    - Trả lời: Vì Dijkstra dựa trên giả thuyết tham lam là khi một đỉnh u đã đưa vào tập cố định S,
 *      nhãn d(u) là nhỏ nhất toàn cục vì mọi cạnh đi thêm đều dương. Nếu có cạnh âm, một đường đi
 *      vòng qua cạnh âm có thể làm giảm khoảng cách tới đỉnh đã đóng trong S.
 * ============================================================================
 */

class GraphAnalyzer {
    private array $vertices;
    private array $edges;

    public function __construct(array $verticesRows, array $edgesRows) {
        $this->vertices = array_values(array_column(
            array_filter($verticesRows, fn($v) => (int)$v['is_active'] === 1),
            'code'
        ));
        $this->edges = array_values(array_filter($edgesRows, fn($e) => (int)$e['is_active'] === 1));
    }

    /**
     * Phân tích các đặc trưng Lý thuyết đồ thị: |V|, |E|, Bậc đỉnh, Đỉnh cô lập, Mật độ
     */
    public function analyzeProperties(): array {
        $inDegree = array_fill_keys($this->vertices, 0);
        $outDegree = array_fill_keys($this->vertices, 0);
        $hasNegativeWeight = false;

        foreach ($this->edges as $e) {
            $u = $e['source'];
            $v = $e['target'];
            if (isset($outDegree[$u])) $outDegree[$u]++;
            if (isset($inDegree[$v])) $inDegree[$v]++;
            if ((float)$e['weight'] < 0) {
                $hasNegativeWeight = true;
            }
        }

        $isolatedVertices = [];
        $degrees = [];
        $maxHubCode = null;
        $maxHubDegree = -1;

        foreach ($this->vertices as $v) {
            $in = $inDegree[$v] ?? 0;
            $out = $outDegree[$v] ?? 0;
            $total = $in + $out;
            $degrees[$v] = ['in' => $in, 'out' => $out, 'total' => $total];

            if ($total === 0) {
                $isolatedVertices[] = $v;
            }
            if ($total > $maxHubDegree) {
                $maxHubDegree = $total;
                $maxHubCode = $v;
            }
        }

        $vCount = count($this->vertices);
        $eCount = count($this->edges);
        $density = ($vCount > 1) ? round($eCount / ($vCount * ($vCount - 1)), 4) : 0;

        return [
            'vertex_count'        => $vCount,
            'edge_count'          => $eCount,
            'density'             => $density,
            'has_negative_weight' => $hasNegativeWeight,
            'isolated_vertices'   => $isolatedVertices,
            'biggest_hub'         => $maxHubCode,
            'degrees'             => $degrees
        ];
    }

    /**
     * Duyệt đồ thị theo chiều rộng (BFS) kiểm tra xem có đường bay từ $start tới $target không
     */
    public function isReachableBFS(string $start, string $target): array {
        $adj = array_fill_keys($this->vertices, []);
        foreach ($this->edges as $e) {
            if (isset($adj[$e['source']])) {
                $adj[$e['source']][] = $e['target'];
            }
        }

        $visited = [];
        $queue = [$start];
        $visited[$start] = true;
        $bfsOrder = [];

        while (!empty($queue)) {
            $u = array_shift($queue);
            $bfsOrder[] = $u;
            if ($u === $target) {
                return ['reachable' => true, 'bfs_order' => $bfsOrder];
            }
            foreach ($adj[$u] ?? [] as $v) {
                if (empty($visited[$v])) {
                    $visited[$v] = true;
                    $queue[] = $v;
                }
            }
        }

        return ['reachable' => false, 'bfs_order' => $bfsOrder];
    }

    /**
     * Thuật toán Bellman-Ford dùng để đối chiếu khi đồ thị có cạnh trọng số âm
     */
    public function runBellmanFord(string $start, string $target): array {
        $dist = array_fill_keys($this->vertices, INF);
        $prev = array_fill_keys($this->vertices, null);
        $dist[$start] = 0;
        $vCount = count($this->vertices);

        // Lặp |V| - 1 lần làm mịn tất cả các cung
        for ($i = 1; $i <= $vCount - 1; $i++) {
            $changed = false;
            foreach ($this->edges as $e) {
                $u = $e['source'];
                $v = $e['target'];
                $w = (float)$e['weight'];
                if ($dist[$u] !== INF && $dist[$u] + $w < $dist[$v]) {
                    $dist[$v] = $dist[$u] + $w;
                    $prev[$v] = $u;
                    $changed = true;
                }
            }
            if (!$changed) break;
        }

        // Kiểm tra chu trình âm (Negative Cycle)
        foreach ($this->edges as $e) {
            $u = $e['source'];
            $v = $e['target'];
            $w = (float)$e['weight'];
            if ($dist[$u] !== INF && $dist[$u] + $w < $dist[$v]) {
                return ['found' => false, 'negative_cycle' => true];
            }
        }

        return [
            'found'          => ($dist[$target] !== INF),
            'negative_cycle' => false,
            'total_cost'     => ($dist[$target] !== INF) ? $dist[$target] : null
        ];
    }
}