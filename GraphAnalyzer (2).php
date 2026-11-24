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
        $n = count($this->vertices);
        $m = count($this->edges);

        // bậc vào, bậc ra, tổng bậc của mỗi đỉnh
        $degrees = [];
        foreach ($this->vertices as $code) {
            $degrees[$code] = ['in' => 0, 'out' => 0, 'total' => 0];
        }

        $hasNegative = false;
        foreach ($this->edges as $e) {
            $s = $e['source'];
            $t = $e['target'];
            if (isset($degrees[$s])) $degrees[$s]['out']++;
            if (isset($degrees[$t])) $degrees[$t]['in']++;
            if ((float)$e['weight'] < 0) $hasNegative = true;
        }

        $isolated = [];
        $biggestHub = null;
        $maxTotal = 0;
        foreach ($degrees as $code => $d) {
            $total = $d['in'] + $d['out'];
            $degrees[$code]['total'] = $total;
            if ($total === 0) {
                $isolated[] = $code;
            }
            if ($total > $maxTotal) {
                $maxTotal = $total;
                $biggestHub = $code;
            }
        }

        // mật độ đồ thị có hướng: D = |E| / (|V| * (|V| - 1))
        $density = ($n > 1) ? round($m / ($n * ($n - 1)), 4) : 0;

        return [
            'vertex_count'        => $n,
            'edge_count'          => $m,
            'density'             => $density,
            'has_negative_weight' => $hasNegative,
            'isolated_vertices'   => $isolated,
            'biggest_hub'         => $biggestHub,
            'degrees'             => $degrees
        ];
    }

    public function isReachableBFS(string $start, string $target): array {
        if (!in_array($start, $this->vertices, true) || !in_array($target, $this->vertices, true)) {
            return ['reachable' => false, 'bfs_order' => []];
        }

        $visited = [$start => true];
        $queue = [$start];
        $order = [];

        while (count($queue) > 0) {
            $u = array_shift($queue);
            $order[] = $u;
            if ($u === $target) {
                return ['reachable' => true, 'bfs_order' => $order];
            }
            foreach ($this->edges as $e) {
                if ($e['source'] === $u && !isset($visited[$e['target']])) {
                    $visited[$e['target']] = true;
                    $queue[] = $e['target'];
                }
            }
        }

        return ['reachable' => false, 'bfs_order' => $order];
    }

    public function runBellmanFord(string $start, string $target): array {
        $notFound = ['found' => false, 'negative_cycle' => false, 'total_cost' => null, 'path' => []];

        if (!in_array($start, $this->vertices, true) || !in_array($target, $this->vertices, true)) {
            return $notFound;
        }

        $dist = [];
        $prev = [];
        foreach ($this->vertices as $code) {
            $dist[$code] = INF;
            $prev[$code] = null;
        }
        $dist[$start] = 0;

        // lặp |V|-1 lần, mỗi lần nới lỏng tất cả cạnh
        for ($i = 1; $i < count($this->vertices); $i++) {
            $changed = false;
            foreach ($this->edges as $e) {
                $u = $e['source'];
                $v = $e['target'];
                if ($dist[$u] === INF) continue;
                if ($dist[$u] + (float)$e['weight'] < $dist[$v]) {
                    $dist[$v] = $dist[$u] + (float)$e['weight'];
                    $prev[$v] = $u;
                    $changed = true;
                }
            }
            if (!$changed) break;
        }

        // thêm 1 vòng nữa: còn nới lỏng được thì có chu trình âm
        foreach ($this->edges as $e) {
            if ($dist[$e['source']] !== INF
                && $dist[$e['source']] + (float)$e['weight'] < $dist[$e['target']]) {
                $notFound['negative_cycle'] = true;
                return $notFound;
            }
        }

        if ($dist[$target] === INF) {
            return $notFound;
        }

        // truy vết đường đi từ target về start
        $path = [];
        $cur = $target;
        $limit = count($this->vertices) + 1;
        while ($cur !== null && $limit-- > 0) {
            array_unshift($path, $cur);
            $cur = $prev[$cur];
        }

        return ['found' => true, 'negative_cycle' => false, 'total_cost' => $dist[$target], 'path' => $path];
    }

    /**
     * MỞ RỘNG: đường rẻ nhất với tối đa $maxStops điểm dừng.
     * Điểm dừng K => tối đa K+1 chặng bay.
     * dp[k][v] = chi phí nhỏ nhất đến v bằng đúng k chặng.
     */
    public function findPathMaxStops(string $start, string $target, int $maxStops): array {
        $notFound = ['found' => false, 'path' => [], 'total_cost' => null, 'stops' => null];

        if ($maxStops < 0) return $notFound;
        if (!in_array($start, $this->vertices, true) || !in_array($target, $this->vertices, true)) {
            return $notFound;
        }
        if ($start === $target) {
            return ['found' => true, 'path' => [$start], 'total_cost' => 0, 'stops' => 0];
        }

        $maxLegs = $maxStops + 1;
        $dp = [0 => [$start => 0]];
        $from = [];

        for ($k = 1; $k <= $maxLegs; $k++) {
            $dp[$k] = [];
            $from[$k] = [];
            foreach ($this->edges as $e) {
                $u = $e['source'];
                $v = $e['target'];
                if (!isset($dp[$k - 1][$u])) continue;
                $cost = $dp[$k - 1][$u] + (float)$e['weight'];
                if (!isset($dp[$k][$v]) || $cost < $dp[$k][$v]) {
                    $dp[$k][$v] = $cost;
                    $from[$k][$v] = $u;
                }
            }
        }

        // chọn số chặng cho chi phí nhỏ nhất
        $bestK = null;
        $bestCost = INF;
        for ($k = 1; $k <= $maxLegs; $k++) {
            if (isset($dp[$k][$target]) && $dp[$k][$target] < $bestCost) {
                $bestCost = $dp[$k][$target];
                $bestK = $k;
            }
        }
        if ($bestK === null) return $notFound;

        // truy vết ngược từng chặng
        $path = [$target];
        $cur = $target;
        for ($k = $bestK; $k >= 1; $k--) {
            $cur = $from[$k][$cur];
            array_unshift($path, $cur);
        }

        return ['found' => true, 'path' => $path, 'total_cost' => $bestCost, 'stops' => $bestK - 1];
    }
}
