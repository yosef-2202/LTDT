<?php
/**
 * THÀNH VIÊN 1: BIỂU DIỄN ĐỒ THỊ & THUẬT TOÁN DIJKSTRA LÕI
 */
class DijkstraCore {
    private array $vertices = []; // ['SGN', 'HAN', ...]
    private array $adjList = [];  // $adjList['SGN'] = [['to' => 'HAN', 'weight' => 85, ...]]
    private array $edges = [];    // Danh sách cung

    public function __construct(array $verticesRows, array $edgesRows, string $criteria = 'weight') {
        // TODO (TV1): Nạp dữ liệu đỉnh vào $this->vertices và khởi tạo $this->adjList
        // TODO (TV1): Nạp dữ liệu cung vào $this->adjList và $this->edges theo $criteria ('weight' hoặc 'duration')
    }

    public function getWeightMatrix(): array {
        // TODO (TV1): Xây dựng Ma trận trọng số A[u][v] (đường chéo = 0, không có cạnh = '∞')
        return [];
    }

    public function getAdjacencyList(): array {
        return $this->adjList;
    }

    public function getEdgeList(): array {
        return $this->edges;
    }

    /**
     * Chạy Dijkstra và trả về kết quả + mảng steps để mô phỏng
     */
    public function runDijkstra(string $start, string $target): array {
        $startTime = microtime(true);

        // TODO (TV1):
        // 1. Khởi tạo dist[v] = INF, prev[v] = null, visited[v] = false, dist[$start] = 0
        // 2. Lưu snapshot Bước 0 vào $steps[] bằng createStepSnapshot()
        // 3. Vòng lặp chọn đỉnh u chưa thăm có dist[u] nhỏ nhất -> đánh dấu visited[u] = true
        // 4. Duyệt các đỉnh kề v của u để làm mịn cạnh (Relaxation) và ghi nhận $relaxedEdges
        // 5. Lưu snapshot mỗi bước vào $steps[]; dừng sớm nếu $u === $target
        // 6. Truy vết ngược từ $target về $start để tạo mảng $path và $pathEdges

        return [
            'found'         => false,
            'start'         => $start,
            'target'        => $target,
            'total_cost'    => null,
            'path'          => [],       // VD: ['SGN', 'BKK', 'DXB', 'JFK']
            'path_edges'    => [],       // Chi tiết từng chặng bay tối ưu
            'steps'         => [],       // Mảng snapshot từng bước lặp
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
        // TODO (TV1): Chuyển dist/prev/visited thành mảng $labels[$v] = ['dist', 'prev', 'closed', 'is_u']
        return [
            'step'           => $stepIndex,
            'current_u'      => $currentU,
            'relaxed_edges'  => $relaxedEdges,
            'labels'         => [],
            'priority_queue' => [],
            'visited_set'    => [],
            'explanation'    => $explanation
        ];
    }

    // TODO MỞ RỘNG (TV1): Viết hàm runDijkstraWithStopover(string $start, string $via, string $target)
}