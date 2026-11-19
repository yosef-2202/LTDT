<?php
/**
 * THÀNH VIÊN 2: PHÂN TÍCH ĐỒ THỊ, BFS LIÊN THÔNG & BELLMAN-FORD
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

    public function analyzeProperties(): array {
        // TODO (TV2):
        // - Tính bậc vào (in), bậc ra (out), tổng bậc của mỗi đỉnh
        // - Tìm đỉnh cô lập (total === 0) và sân bay trung chuyển lớn nhất (biggest_hub)
        // - Kiểm tra có cạnh trọng số âm không (has_negative_weight)
        // - Tính mật độ đồ thị có hướng: D = |E| / (|V| * (|V| - 1))

        return [
            'vertex_count'        => count($this->vertices),
            'edge_count'          => count($this->edges),
            'density'             => 0,
            'has_negative_weight' => false,
            'isolated_vertices'   => [],
            'biggest_hub'         => null,
            'degrees'             => []
        ];
    }

    public function isReachableBFS(string $start, string $target): array {
        // TODO (TV2): Cài đặt duyệt theo chiều rộng (BFS) dùng hàng đợi (Queue)
        return ['reachable' => true, 'bfs_order' => []];
    }

    public function runBellmanFord(string $start, string $target): array {
        // TODO (TV2): Cài đặt Bellman-Ford lặp |V|-1 lần và kiểm tra chu trình âm
        return ['found' => false, 'negative_cycle' => false, 'total_cost' => null];
    }

    // TODO MỞ RỘNG (TV2): Viết hàm findPathMaxStops(string $start, string $target, int $maxStops)
}