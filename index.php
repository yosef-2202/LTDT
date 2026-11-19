<?php
/**
 * THÀNH VIÊN 5: GIAO DIỆN ĐẶT VÉ, STEP PLAYER & BẢNG TRACE TABLE
 */
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SKYGRAPH TRAVEL • Mô phỏng Thuật toán Dijkstra</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen" x-data="skyTravelApp()" x-init="initApp()">

    <!-- 1. HEADER & MENU -->
    <header class="bg-white border-b shadow-sm px-6 py-4 flex items-center justify-between">
        <div class="font-black text-xl text-teal-600">SKYGRAPH TRAVEL • DIJKSTRA</div>
        <nav class="flex gap-4 text-sm font-semibold">
            <button @click="activeModal = 'flight_manager'" class="hover:text-teal-600">Quản lý Chuyến bay</button>
            <button @click="activeModal = 'matrix'" class="hover:text-teal-600">Ma trận Trọng số</button>
            <button @click="activeModal = 'bookings_list'" class="hover:text-teal-600">Vé đã đặt (<span x-text="bookings.length"></span>)</button>
        </nav>
    </header>

    <!-- 2. KHUNG TÌM KIẾM CHUYẾN BAY (HERO BOOKING BOX) -->
    <section class="max-w-7xl mx-auto p-6">
        <div class="bg-white p-5 rounded-xl shadow border grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <div>
                <label class="block text-xs font-bold mb-1">Điểm đi (u):</label>
                <select x-model="form.source" class="w-full border rounded p-2 text-sm">
                    <template x-for="v in vertices" :key="v.code">
                        <option :value="v.code" x-text="`${v.code} - ${v.city}`"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold mb-1">Điểm đến (v):</label>
                <select x-model="form.target" class="w-full border rounded p-2 text-sm">
                    <template x-for="v in vertices" :key="v.code">
                        <option :value="v.code" x-text="`${v.code} - ${v.city}`"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold mb-1">Tiêu chí w(u,v):</label>
                <select x-model="form.criteria" class="w-full border rounded p-2 text-sm">
                    <option value="weight">Giá rẻ nhất ($)</option>
                    <option value="duration">Bay nhanh nhất (h)</option>
                </select>
            </div>
            <button @click="runSimulation()" class="bg-teal-600 hover:bg-teal-700 text-white font-bold py-2.5 px-4 rounded">
                SEARCH (Chạy Dijkstra)
            </button>
        </div>
    </section>

    <!-- 3. WORKSPACE: BẢN ĐỒ (TRÁI) + BOARDING PASS & TRACE TABLE (PHẢI) -->
    <main class="max-w-7xl mx-auto px-6 pb-12 grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Cột trái: Bản đồ + Step Player -->
        <div class="lg:col-span-7 bg-white rounded-xl shadow border overflow-hidden">
            <div class="p-3 bg-slate-100 border-b flex items-center justify-between text-xs">
                <span class="font-bold">Bản đồ Mô phỏng Dijkstra</span>
                <div class="flex gap-1" x-show="steps.length > 0">
                    <button @click="prevStep()" class="px-2 py-1 bg-white border rounded">◀</button>
                    <button @click="toggleAutoPlay()" class="px-3 py-1 bg-teal-600 text-white rounded" x-text="isPlaying ? 'Tạm dừng' : 'Chạy Step'"></button>
                    <button @click="nextStep()" class="px-2 py-1 bg-white border rounded">▶</button>
                    <span class="ml-2 font-mono font-bold" x-text="`Bước ${currentStepIndex}/${steps.length - 1}`"></span>
                </div>
            </div>
            <div id="map-container" class="h-[460px] w-full"></div>
            <div class="p-3 bg-teal-50 text-xs font-mono" x-show="currentStep" x-text="currentStep?.explanation"></div>
        </div>

        <!-- Cột phải: Boarding Pass + Trace Table -->
        <div class="lg:col-span-5 flex flex-col gap-4">
            <div class="bg-white rounded-xl shadow border p-4">
                <h3 class="font-bold text-sm text-teal-700 mb-2">🎫 Kết quả hành trình (Boarding Pass)</h3>
                <!-- TODO (TV5): Hiển thị chuỗi sân bay tối ưu, chi tiết từng chặng và nút Đặt vé -->
                <div class="text-xs text-slate-500" x-text="simResult ? simResult.path.join(' ➔ ') : 'Chưa có kết quả.'"></div>
            </div>

            <div class="bg-white rounded-xl shadow border p-4 overflow-x-auto">
                <h3 class="font-bold text-sm text-slate-800 mb-2">📊 Bảng Trace Table: (dist, prev)</h3>
                <!-- TODO (TV5): Render bảng Trace Table duyệt qua mảng steps, bấm vào từng dòng gọi goToStep(idx) -->
                <p class="text-xs text-slate-400">Hiển thị nhãn khoảng cách tạm thời của từng đỉnh qua các bước lặp.</p>
            </div>
        </div>
    </main>

    <!-- TODO (TV5): Bổ sung các Modal (flight_manager, checkout, bookings_list, matrix) -->

    <script src="assets/map-visualizer.js"></script>
    <script>
        function skyTravelApp() {
            return {
                vertices: [], edges: [], bookings: [], weightMatrix: {}, analysis: {},
                form: { source: 'SGN', target: 'JFK', criteria: 'weight' },
                simResult: null, steps: [], currentStepIndex: 0,
                isPlaying: false, playTimer: null, activeModal: null,

                get currentStep() { return this.steps[this.currentStepIndex] || null; },

                async initApp() {
                    GraphMapVisualizer.initMap('map-container');
                    await this.loadGraphData();
                },

                async loadGraphData() {
                    const res = await fetch('api.php?action=init_data');
                    const data = await res.json();
                    if (data.status === 'success') {
                        this.vertices = data.vertices;
                        this.edges = data.edges;
                        this.bookings = data.bookings || [];
                        this.weightMatrix = data.weight_matrix;
                        this.analysis = data.analysis;
                        GraphMapVisualizer.renderBaseGraph(this.vertices, this.edges, this.form.criteria);
                    }
                },

                async runSimulation() {
                    const res = await fetch('api.php?action=run_dijkstra', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(this.form)
                    });
                    const json = await res.json();
                    if (json.status === 'error') return Swal.fire('Cảnh báo', json.message, 'error');

                    this.simResult = json.data;
                    this.steps = json.data.steps || [];
                    this.goToStep(0);
                },

                goToStep(idx) {
                    if (idx < 0 || idx >= this.steps.length) return;
                    this.currentStepIndex = idx;
                    GraphMapVisualizer.renderStep(this.steps[idx], this.simResult?.path || [], idx === this.steps.length - 1);
                },
                nextStep() { this.goToStep(this.currentStepIndex + 1); },
                prevStep() { this.goToStep(this.currentStepIndex - 1); },
                toggleAutoPlay() {
                    // TODO (TV5): Cài đặt setInterval tự động gọi nextStep() mỗi 700ms
                }
            };
        }
    </script>
</body>
</html>