<?php
/**
 * THÀNH VIÊN 5: GIAO DIỆN ĐẶT VÉ, STEP PLAYER & BẢNG TRACE TABLE
 */
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
    <style>
        [x-cloak] { display: none !important; }
        .ticket-notch { position: relative; }
        .ticket-notch::before, .ticket-notch::after {
            content: ''; position: absolute; top: -10px; width: 20px; height: 20px;
            background: #f8fafc; border-radius: 9999px; border: 1px solid #e2e8f0;
        }
        .ticket-notch::before { left: -10px; }
        .ticket-notch::after { right: -10px; }
        .trace-scroll { max-height: 320px; overflow: auto; }
        .trace-scroll thead th { position: sticky; top: 0; z-index: 1; }
    </style>
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
                    <template x-for="v in activeVertices" :key="v.code">
                        <option :value="v.code" x-text="`${v.code} - ${v.city}`"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold mb-1">Điểm đến (v):</label>
                <select x-model="form.target" class="w-full border rounded p-2 text-sm">
                    <template x-for="v in activeVertices" :key="v.code">
                        <option :value="v.code" x-text="`${v.code} - ${v.city}`"></option>
                    </template>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold mb-1">Tiêu chí w(u,v):</label>
                <select x-model="form.criteria" @change="onCriteriaChange()" class="w-full border rounded p-2 text-sm">
                    <option value="weight">Giá rẻ nhất ($)</option>
                    <option value="duration">Bay nhanh nhất (h)</option>
                </select>
            </div>
            <button @click="runSimulation()" :disabled="isSearching"
                    class="bg-teal-600 hover:bg-teal-700 disabled:opacity-60 text-white font-bold py-2.5 px-4 rounded"
                    x-text="isSearching ? 'Đang tính...' : 'SEARCH (Chạy Dijkstra)'"></button>
        </div>
    </section>

    <!-- 3. WORKSPACE: BẢN ĐỒ (TRÁI) + BOARDING PASS & TRACE TABLE (PHẢI) -->
    <main class="max-w-7xl mx-auto px-6 pb-12 grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Cột trái: Bản đồ + Step Player -->
        <div class="lg:col-span-7 flex flex-col gap-4 self-start">
            <div class="bg-white rounded-xl shadow border overflow-hidden">
                <div class="p-3 bg-slate-100 border-b flex items-center justify-between text-xs">
                    <span class="font-bold">Bản đồ Mô phỏng Dijkstra</span>
                    <div class="flex gap-1 items-center" x-show="steps.length > 0" x-cloak>
                        <button @click="goToStep(0)" class="px-2 py-1 bg-white border rounded" title="Về bước đầu">⏮</button>
                        <button @click="prevStep()" class="px-2 py-1 bg-white border rounded">◀</button>
                        <button @click="toggleAutoPlay()" class="px-3 py-1 bg-teal-600 text-white rounded" x-text="isPlaying ? 'Tạm dừng' : 'Chạy Step'"></button>
                        <button @click="nextStep()" class="px-2 py-1 bg-white border rounded">▶</button>
                        <button @click="goToStep(steps.length - 1)" class="px-2 py-1 bg-white border rounded" title="Đến bước cuối">⏭</button>
                        <span class="ml-2 font-mono font-bold" x-text="`Bước ${currentStepIndex}/${steps.length - 1}`"></span>
                    </div>
                </div>
                <div id="map-container" class="h-[460px] w-full"></div>
                <div class="p-3 bg-teal-50 text-xs font-mono" x-show="currentStep" x-cloak x-text="currentStep?.explanation"></div>
            </div>

            <!-- Chi tiết bước hiện tại: hàng đợi ưu tiên + các cạnh được thư giãn -->
            <div class="bg-white rounded-xl shadow border p-4" x-show="currentStep" x-cloak>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                    <div>
                        <div class="font-bold text-slate-700 mb-1">Hàng đợi ưu tiên (chưa cố định)</div>
                        <div class="flex flex-wrap gap-1">
                            <template x-for="q in (currentStep?.priority_queue || [])" :key="q.vertex">
                                <span class="px-2 py-0.5 rounded bg-slate-100 border font-mono"
                                      x-text="`${q.vertex}: ${fmtNum(q.dist)}`"></span>
                            </template>
                            <span x-show="!(currentStep?.priority_queue || []).length" class="text-slate-400">Rỗng</span>
                        </div>
                        <div class="font-bold text-slate-700 mt-3 mb-1">Tập đã cố định S</div>
                        <div class="flex flex-wrap gap-1">
                            <template x-for="c in (currentStep?.visited_set || [])" :key="c">
                                <span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-mono" x-text="c"></span>
                            </template>
                            <span x-show="!(currentStep?.visited_set || []).length" class="text-slate-400">∅</span>
                        </div>
                    </div>
                    <div>
                        <div class="font-bold text-slate-700 mb-1">Thư giãn cạnh (relaxation)</div>
                        <div class="space-y-1 max-h-32 overflow-y-auto">
                            <template x-for="(r, i) in (currentStep?.relaxed_edges || [])" :key="i">
                                <div class="flex items-center justify-between rounded px-2 py-1 font-mono"
                                     :class="r.status === 'updated' ? 'bg-amber-50 text-amber-800' : 'bg-red-50 text-red-700'">
                                    <span x-text="`${r.from} ➔ ${r.to}`"></span>
                                    <span x-text="`${fmtNum(r.old_dist)} → ${fmtNum(r.new_dist)}`"></span>
                                    <span x-text="r.status === 'updated' ? 'cập nhật' : 'bỏ qua'"></span>
                                </div>
                            </template>
                            <span x-show="!(currentStep?.relaxed_edges || []).length" class="text-slate-400">Không có cạnh nào ở bước này.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cột phải: Boarding Pass + Trace Table -->
        <div class="lg:col-span-5 flex flex-col gap-4">

            <!-- BOARDING PASS -->
            <div class="bg-white rounded-xl shadow border p-4">
                <h3 class="font-bold text-sm text-teal-700 mb-3">🎫 Kết quả hành trình (Boarding Pass)</h3>

                <template x-if="!simResult">
                    <div class="text-xs text-slate-500">Chưa có kết quả. Chọn điểm đi/đến rồi bấm SEARCH.</div>
                </template>

                <!-- Không có đường đi -->
                <template x-if="simResult && !hasPath">
                    <div class="text-xs text-red-700 bg-red-50 border border-red-200 rounded p-3 space-y-1">
                        <div>Không tìm thấy hành trình từ <b x-text="simResult.start"></b> đến <b x-text="simResult.target"></b>
                            (theo chiều bay của các chuyến hiện có).</div>
                        <div x-show="simResult.bfs_check" class="text-red-500">
                            Kiểm tra BFS: <span x-text="simResult.bfs_check?.reachable ? 'có thể tới được' : 'không thể tới được'"></span>.
                        </div>
                    </div>
                </template>

                <!-- Có đường đi -->
                <template x-if="simResult && hasPath">
                    <div>
                        <div class="rounded-t-lg bg-gradient-to-r from-teal-600 to-cyan-600 text-white p-4">
                            <div class="flex items-center justify-between">
                                <div class="text-center">
                                    <div class="text-3xl font-black" x-text="pathCodes[0]"></div>
                                    <div class="text-[11px] opacity-90" x-text="cityOf(pathCodes[0])"></div>
                                </div>
                                <div class="flex-1 px-3 text-center">
                                    <div class="text-lg">✈</div>
                                    <div class="border-t border-dashed border-white/60 my-1"></div>
                                    <div class="text-[10px] uppercase tracking-wider" x-text="`${legs.length} chặng`"></div>
                                </div>
                                <div class="text-center">
                                    <div class="text-3xl font-black" x-text="pathCodes[pathCodes.length - 1]"></div>
                                    <div class="text-[11px] opacity-90" x-text="cityOf(pathCodes[pathCodes.length - 1])"></div>
                                </div>
                            </div>
                        </div>

                        <div class="border-x px-4 py-3 text-xs bg-slate-50">
                            <div class="text-[10px] font-bold uppercase text-slate-400 mb-1">Lộ trình tối ưu</div>
                            <div class="flex flex-wrap items-center gap-1 font-mono font-bold">
                                <template x-for="(c, i) in pathCodes" :key="c + i">
                                    <span class="flex items-center gap-1">
                                        <span class="px-2 py-0.5 rounded bg-teal-100 text-teal-800" x-text="c"></span>
                                        <span x-show="i < pathCodes.length - 1" class="text-slate-400">➔</span>
                                    </span>
                                </template>
                            </div>
                        </div>

                        <div class="border-x px-4 py-2 space-y-2 max-h-52 overflow-y-auto">
                            <template x-for="(leg, i) in legs" :key="i">
                                <div class="flex items-center justify-between text-xs border rounded-lg p-2">
                                    <div>
                                        <div class="font-bold" x-text="`${i + 1}. ${leg.from} ➔ ${leg.to}`"></div>
                                        <div class="text-[11px] text-slate-500" x-text="`${leg.flight_no} • ${leg.airline}`"></div>
                                    </div>
                                    <div class="text-right font-mono">
                                        <div class="text-emerald-700 font-bold" x-text="fmtMoney(leg.price)"></div>
                                        <div class="text-slate-500" x-text="fmtHours(leg.duration)"></div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div class="ticket-notch border-t border-dashed mt-2"></div>
                        <div class="border border-t-0 rounded-b-lg px-4 py-3">
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-slate-500">Tổng giá vé (1 hành khách, Phổ thông)</span>
                                <span class="font-mono font-bold text-emerald-700" x-text="fmtMoney(totals.price)"></span>
                            </div>
                            <div class="flex justify-between text-xs mb-1">
                                <span class="text-slate-500">Tổng thời gian bay</span>
                                <span class="font-mono font-bold" x-text="fmtHours(totals.duration)"></span>
                            </div>
                            <div class="flex justify-between text-[11px] mb-3 text-slate-400">
                                <span x-text="`Chi phí tối ưu (${form.criteria === 'duration' ? 'giờ bay' : 'giá'})`"></span>
                                <span class="font-mono" x-text="costText(simResult.total_cost) + ' • ' + simResult.exec_time_ms + ' ms'"></span>
                            </div>
                            <button @click="openCheckout()"
                                    class="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold py-2 rounded text-sm">
                                ĐẶT VÉ NGAY
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <!-- TRACE TABLE -->
            <div class="bg-white rounded-xl shadow border p-4">
                <h3 class="font-bold text-sm text-slate-800 mb-2">📊 Bảng Trace Table: (dist, prev)</h3>

                <template x-if="steps.length === 0">
                    <p class="text-xs text-slate-400">Hiển thị nhãn khoảng cách tạm thời của từng đỉnh qua các bước lặp.</p>
                </template>

                <div x-show="steps.length > 0" x-cloak class="trace-scroll border rounded">
                    <table class="w-full text-[11px] font-mono border-collapse">
                        <thead>
                            <tr class="bg-slate-100 text-slate-600">
                                <th class="px-2 py-1 text-left border-b">Bước</th>
                                <th class="px-2 py-1 text-left border-b">Chọn u</th>
                                <template x-for="c in traceCodes" :key="'h' + c">
                                    <th class="px-2 py-1 text-center border-b" x-text="c"></th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(st, idx) in steps" :key="idx">
                                <tr @click="goToStep(idx)"
                                    class="cursor-pointer border-b hover:bg-teal-50"
                                    :class="idx === currentStepIndex ? 'bg-teal-100 font-bold' : ''">
                                    <td class="px-2 py-1" x-text="idx"></td>
                                    <td class="px-2 py-1 text-teal-700" x-text="st.current_u || '–'"></td>
                                    <template x-for="c in traceCodes" :key="idx + c">
                                        <td class="px-2 py-1 text-center whitespace-nowrap"
                                            :class="cellClass(st, c)"
                                            x-text="cellText(st, c)"></td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
                <p class="text-[10px] text-slate-400 mt-2" x-show="steps.length > 0" x-cloak>
                    Ô hiển thị dạng <b>dist/prev</b>. Vàng = đỉnh đang xét, xanh = đã cố định. Bấm vào một dòng để nhảy tới bước đó trên bản đồ.
                </p>
            </div>
        </div>
    </main>

    <!-- ================= MODALS ================= -->

    <!-- Modal: Quản lý Chuyến bay -->
    <div x-show="activeModal === 'flight_manager'" x-cloak
         class="fixed inset-0 z-[2000] bg-black/50 flex items-center justify-center p-4"
         @keydown.escape.window="activeModal = null">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl max-h-[90vh] flex flex-col" @click.outside="activeModal = null">
            <div class="flex items-center justify-between px-5 py-3 border-b">
                <h3 class="font-bold">Quản lý Sân bay & Chuyến bay</h3>
                <button @click="activeModal = null" class="text-slate-400 hover:text-slate-700 text-xl">&times;</button>
            </div>

            <div class="p-5 overflow-y-auto space-y-4">
                <!-- Form thêm chuyến bay -->
                <div>
                    <div class="text-xs font-bold text-slate-600 mb-1">Thêm chuyến bay (cạnh có hướng u ➔ v)</div>
                    <div class="grid grid-cols-2 md:grid-cols-7 gap-2 items-end bg-slate-50 border rounded-lg p-3">
                        <div>
                            <label class="block text-[11px] font-bold mb-1">Số hiệu</label>
                            <input type="text" x-model.trim="flightForm.flight_no" class="w-full border rounded p-1.5 text-xs uppercase" placeholder="VN-210">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold mb-1">Từ (u)</label>
                            <select x-model="flightForm.source" class="w-full border rounded p-1.5 text-xs">
                                <template x-for="v in activeVertices" :key="'fs' + v.code">
                                    <option :value="v.code" x-text="v.code"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold mb-1">Đến (v)</label>
                            <select x-model="flightForm.target" class="w-full border rounded p-1.5 text-xs">
                                <template x-for="v in activeVertices" :key="'ft' + v.code">
                                    <option :value="v.code" x-text="v.code"></option>
                                </template>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold mb-1">Giá ($)</label>
                            <input type="number" min="1" step="1" x-model.number="flightForm.weight" class="w-full border rounded p-1.5 text-xs">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold mb-1">Giờ bay (h)</label>
                            <input type="number" min="1" step="1" x-model.number="flightForm.duration" class="w-full border rounded p-1.5 text-xs">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold mb-1">Hãng</label>
                            <input type="text" x-model.trim="flightForm.airline" class="w-full border rounded p-1.5 text-xs" placeholder="Vietnam Airlines">
                        </div>
                        <button @click="saveFlight()" class="bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold py-2 rounded">
                            Thêm
                        </button>
                    </div>
                </div>

                <!-- Form thêm sân bay -->
                <div>
                    <div class="text-xs font-bold text-slate-600 mb-1">Thêm sân bay (đỉnh)</div>
                    <div class="grid grid-cols-2 md:grid-cols-7 gap-2 items-end bg-slate-50 border rounded-lg p-3">
                        <div>
                            <label class="block text-[11px] font-bold mb-1">Mã (3 chữ)</label>
                            <input type="text" maxlength="3" x-model.trim="vertexForm.code" class="w-full border rounded p-1.5 text-xs uppercase" placeholder="DAD">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold mb-1">Tên sân bay</label>
                            <input type="text" x-model.trim="vertexForm.name" class="w-full border rounded p-1.5 text-xs" placeholder="Đà Nẵng Intl">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold mb-1">Thành phố</label>
                            <input type="text" x-model.trim="vertexForm.city" class="w-full border rounded p-1.5 text-xs" placeholder="Đà Nẵng">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold mb-1">Quốc gia</label>
                            <input type="text" x-model.trim="vertexForm.country" class="w-full border rounded p-1.5 text-xs" placeholder="Vietnam">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold mb-1">Lat</label>
                            <input type="number" step="any" x-model.number="vertexForm.lat" class="w-full border rounded p-1.5 text-xs">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold mb-1">Lng</label>
                            <input type="number" step="any" x-model.number="vertexForm.lng" class="w-full border rounded p-1.5 text-xs">
                        </div>
                        <button @click="saveVertex()" class="bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold py-2 rounded">
                            Thêm
                        </button>
                    </div>
                </div>

                <!-- Danh sách chuyến bay -->
                <div class="border rounded-lg overflow-hidden">
                    <table class="w-full text-xs">
                        <thead class="bg-slate-100 text-slate-600">
                            <tr>
                                <th class="px-3 py-2 text-left">Số hiệu</th>
                                <th class="px-3 py-2 text-left">Chặng</th>
                                <th class="px-3 py-2 text-left">Hãng</th>
                                <th class="px-3 py-2 text-right">Giá ($)</th>
                                <th class="px-3 py-2 text-right">Giờ bay</th>
                                <th class="px-3 py-2 text-center">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="e in edges" :key="e.id">
                                <tr class="border-t hover:bg-slate-50">
                                    <td class="px-3 py-2 font-mono" x-text="e.flight_no"></td>
                                    <td class="px-3 py-2 font-mono font-bold" x-text="`${e.source} ➔ ${e.target}`"></td>
                                    <td class="px-3 py-2" x-text="e.airline"></td>
                                    <td class="px-3 py-2 text-right font-mono" x-text="fmtMoney(e.weight)"></td>
                                    <td class="px-3 py-2 text-right font-mono" x-text="fmtHours(e.duration)"></td>
                                    <td class="px-3 py-2 text-center">
                                        <button @click="deleteFlight(e)" class="text-red-600 hover:underline">Xóa</button>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="edges.length === 0">
                                <td colspan="6" class="px-3 py-6 text-center text-slate-400">Chưa có chuyến bay nào.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="text-right">
                    <button @click="resetDatabase()" class="text-xs text-red-600 hover:underline">
                        Khôi phục dữ liệu mặc định
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Checkout -->
    <div x-show="activeModal === 'checkout'" x-cloak
         class="fixed inset-0 z-[2000] bg-black/50 flex items-center justify-center p-4"
         @keydown.escape.window="activeModal = null">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md" @click.outside="activeModal = null">
            <div class="flex items-center justify-between px-5 py-3 border-b">
                <h3 class="font-bold">Xác nhận đặt vé</h3>
                <button @click="activeModal = null" class="text-slate-400 hover:text-slate-700 text-xl">&times;</button>
            </div>
            <div class="p-5 space-y-3 text-sm">
                <div class="bg-teal-50 border border-teal-200 rounded-lg p-3 text-xs">
                    <div class="font-mono font-bold text-teal-800" x-text="pathCodes.join(' ➔ ')"></div>
                    <div class="mt-1 text-slate-600" x-text="`${legs.length} chặng • ${fmtHours(totals.duration)}`"></div>
                </div>

                <div>
                    <label class="block text-xs font-bold mb-1">Họ và tên hành khách *</label>
                    <input type="text" x-model.trim="checkout.name" class="w-full border rounded p-2 text-sm" placeholder="NGUYEN VAN A">
                </div>
                <div>
                    <label class="block text-xs font-bold mb-1">Email *</label>
                    <input type="email" x-model.trim="checkout.email" class="w-full border rounded p-2 text-sm" placeholder="email@example.com">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold mb-1">Hạng ghế</label>
                        <select x-model="checkout.seat_class" class="w-full border rounded p-2 text-sm">
                            <template x-for="s in SEAT_CLASSES" :key="s.value">
                                <option :value="s.value" x-text="`${s.label} (x${s.mult})`"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold mb-1">Số hành khách</label>
                        <input type="number" min="1" max="9" x-model.number="checkout.passengers_count" class="w-full border rounded p-2 text-sm">
                    </div>
                </div>

                <div class="flex justify-between items-center border-t pt-3">
                    <span class="text-xs text-slate-500">Tổng thanh toán</span>
                    <span class="text-xl font-black text-emerald-700 font-mono" x-text="fmtMoney(bookingTotal)"></span>
                </div>

                <div class="flex gap-2 pt-1">
                    <button @click="activeModal = null" class="flex-1 border rounded py-2 font-semibold">Hủy</button>
                    <button @click="confirmBooking()" :disabled="isBooking"
                            class="flex-1 bg-amber-500 hover:bg-amber-600 disabled:opacity-60 text-white font-bold rounded py-2"
                            x-text="isBooking ? 'Đang xử lý...' : 'Thanh toán & Xuất vé'"></button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Vé đã đặt -->
    <div x-show="activeModal === 'bookings_list'" x-cloak
         class="fixed inset-0 z-[2000] bg-black/50 flex items-center justify-center p-4"
         @keydown.escape.window="activeModal = null">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-3xl max-h-[90vh] flex flex-col" @click.outside="activeModal = null">
            <div class="flex items-center justify-between px-5 py-3 border-b">
                <h3 class="font-bold">Vé đã đặt</h3>
                <button @click="activeModal = null" class="text-slate-400 hover:text-slate-700 text-xl">&times;</button>
            </div>
            <div class="p-5 overflow-y-auto space-y-3">
                <template x-for="b in bookings" :key="b.id">
                    <div class="border rounded-lg p-3 text-xs flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-black text-sm text-teal-700" x-text="b.pnr_code"></span>
                                <span class="px-1.5 py-0.5 rounded bg-slate-100 text-[10px]" x-text="b.seat_class"></span>
                            </div>
                            <div class="font-semibold mt-0.5" x-text="`${b.passenger_name} • ${b.passengers_count} khách`"></div>
                            <div class="font-mono text-slate-600 truncate" x-text="b.route_path"></div>
                            <div class="text-slate-400" x-text="`${b.passenger_email} • ${b.booked_at}`"></div>
                        </div>
                        <div class="text-right font-mono shrink-0">
                            <div class="font-bold text-emerald-700" x-text="fmtMoney(b.total_price)"></div>
                            <div class="text-slate-500" x-text="fmtHours(b.total_duration)"></div>
                        </div>
                    </div>
                </template>
                <div x-show="bookings.length === 0" class="text-center text-slate-400 text-sm py-8">Chưa có vé nào được đặt.</div>
            </div>
        </div>
    </div>

    <!-- Modal: Ma trận trọng số + phân tích đồ thị -->
    <div x-show="activeModal === 'matrix'" x-cloak
         class="fixed inset-0 z-[2000] bg-black/50 flex items-center justify-center p-4"
         @keydown.escape.window="activeModal = null">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-5xl max-h-[90vh] flex flex-col" @click.outside="activeModal = null">
            <div class="flex items-center justify-between px-5 py-3 border-b">
                <h3 class="font-bold">Ma trận Trọng số A[u][v]</h3>
                <div class="flex items-center gap-3">
                    <select x-model="matrixCriteria" class="border rounded p-1 text-xs">
                        <option value="weight">Giá ($)</option>
                        <option value="duration">Giờ bay (h)</option>
                    </select>
                    <button @click="activeModal = null" class="text-slate-400 hover:text-slate-700 text-xl">&times;</button>
                </div>
            </div>
            <div class="p-5 overflow-auto">
                <table class="text-[11px] font-mono border-collapse">
                    <thead>
                        <tr>
                            <th class="border bg-slate-100 px-2 py-1">u \ v</th>
                            <template x-for="c in matrixCodes" :key="'mh' + c">
                                <th class="border bg-slate-100 px-2 py-1" x-text="c"></th>
                            </template>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="u in matrixCodes" :key="'mr' + u">
                            <tr>
                                <th class="border bg-slate-100 px-2 py-1" x-text="u"></th>
                                <template x-for="v in matrixCodes" :key="u + v">
                                    <td class="border px-2 py-1 text-center"
                                        :class="matrixCellClass(u, v)"
                                        x-text="matrixCell(u, v)"></td>
                                </template>
                            </tr>
                        </template>
                    </tbody>
                </table>
                <p class="text-[11px] text-slate-400 mt-3">
                    Hàng u là điểm đi, cột v là điểm đến. ∞ = không có chuyến bay trực tiếp; nếu có nhiều chuyến cùng chặng thì lấy giá trị nhỏ nhất.
                    Ô xanh là chặng nằm trên hành trình tối ưu hiện tại.
                </p>

                <!-- Phân tích đồ thị (GraphAnalyzer) -->
                <template x-if="analysis && analysis.vertex_count !== undefined">
                    <div class="mt-5 border-t pt-4">
                        <h4 class="font-bold text-xs mb-2">Phân tích tính chất đồ thị</h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-xs">
                            <div class="bg-slate-50 border rounded p-2">Số đỉnh |V|: <b x-text="analysis.vertex_count"></b></div>
                            <div class="bg-slate-50 border rounded p-2">Số cung |E|: <b x-text="analysis.edge_count"></b></div>
                            <div class="bg-slate-50 border rounded p-2">Mật độ: <b x-text="analysis.density"></b></div>
                            <div class="bg-slate-50 border rounded p-2">Trọng số âm:
                                <b :class="analysis.has_negative_weight ? 'text-red-600' : 'text-emerald-700'"
                                   x-text="analysis.has_negative_weight ? 'Có' : 'Không'"></b></div>
                            <div class="bg-slate-50 border rounded p-2">Đỉnh cô lập:
                                <b x-text="(analysis.isolated_vertices || []).length ? analysis.isolated_vertices.join(', ') : 'Không có'"></b></div>
                            <div class="bg-slate-50 border rounded p-2">Hub lớn nhất: <b x-text="analysis.biggest_hub || '–'"></b></div>
                        </div>

                        <div class="mt-3 border rounded overflow-hidden" x-show="analysis.degrees">
                            <table class="w-full text-[11px] font-mono">
                                <thead class="bg-slate-100 text-slate-600">
                                    <tr><th class="px-2 py-1 text-left">Đỉnh</th><th class="px-2 py-1">Bậc vào</th><th class="px-2 py-1">Bậc ra</th><th class="px-2 py-1">Tổng bậc</th></tr>
                                </thead>
                                <tbody>
                                    <template x-for="(d, code) in (analysis.degrees || {})" :key="code">
                                        <tr class="border-t text-center">
                                            <td class="px-2 py-1 text-left font-bold" x-text="code"></td>
                                            <td x-text="d.in"></td><td x-text="d.out"></td><td x-text="d.total"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <script src="assets/map-visualizer.js"></script>
    <script>
        /** Endpoint khớp với api.php (TV3) */
        const API = {
            init: 'api.php?action=init_data',
            dijkstra: 'api.php?action=run_dijkstra',
            book: 'api.php?action=book_ticket',
            addEdge: 'api.php?action=add_edge',
            deleteEdge: 'api.php?action=delete_edge',
            addVertex: 'api.php?action=add_vertex',
            resetDb: 'api.php?action=reset_db'
        };

        /** Hạng ghế: hệ số nhân lên giá cơ sở (chỉnh tại đây nếu nhóm quy định khác) */
        const SEAT_CLASSES = [
            { value: 'Economy',  label: 'Phổ thông',   mult: 1 },
            { value: 'Business', label: 'Thương gia',  mult: 2.5 },
            { value: 'First',    label: 'Hạng nhất',   mult: 4 }
        ];

        function skyTravelApp() {
            return {
                vertices: [], edges: [], bookings: [], weightMatrix: {}, analysis: {},
                form: { source: 'SGN', target: 'JFK', criteria: 'weight' },
                simResult: null, steps: [], currentStepIndex: 0,
                isPlaying: false, playTimer: null, activeModal: null,

                // TV5 state
                SEAT_CLASSES,
                isSearching: false,
                isBooking: false,
                checkout: { name: '', email: '', seat_class: 'Economy', passengers_count: 1 },
                flightForm: { flight_no: '', source: '', target: '', weight: 100, duration: 2, airline: '' },
                vertexForm: { code: '', name: '', city: '', country: '', lat: null, lng: null },
                matrixCriteria: 'weight',

                get currentStep() { return this.steps[this.currentStepIndex] || null; },

                /* ---------- Dữ liệu dẫn xuất ---------- */
                get activeVertices() { return this.vertices.filter(v => Number(v.is_active ?? 1) === 1); },
                get traceCodes() {
                    // Thứ tự cột lấy theo vertices_list của backend (chỉ đỉnh active), phòng khi chưa có thì dùng danh sách đỉnh
                    return (this.simResult && this.simResult.vertices_list) || this.activeVertices.map(v => v.code);
                },
                get matrixCodes() {
                    const keys = Object.keys(this.weightMatrix || {});
                    return keys.length ? keys : this.activeVertices.map(v => v.code);
                },
                get hasPath() { return !!(this.simResult && this.simResult.found && (this.simResult.path || []).length > 0); },
                get pathCodes() { return this.hasPath ? this.simResult.path : []; },
                get legs() {
                    if (!this.hasPath) return [];
                    return (this.simResult.path_edges || []).map(e => ({
                        from: e.from, to: e.to,
                        flight_no: e.flight_no, airline: e.airline,
                        price: Number(e.price) || 0,
                        duration: Number(e.duration) || 0
                    }));
                },
                get totals() {
                    return {
                        price: this.legs.reduce((s, l) => s + l.price, 0),
                        duration: this.legs.reduce((s, l) => s + l.duration, 0)
                    };
                },
                get seatMult() {
                    const s = SEAT_CLASSES.find(x => x.value === this.checkout.seat_class);
                    return s ? s.mult : 1;
                },
                get bookingTotal() {
                    const pax = Math.max(1, Math.min(9, Number(this.checkout.passengers_count) || 1));
                    return Math.round(this.totals.price * this.seatMult * pax * 100) / 100;
                },

                /* ---------- Khởi tạo ---------- */
                async initApp() {
                    GraphMapVisualizer.initMap('map-container');
                    await this.loadGraphData();
                },

                async loadGraphData() {
                    try {
                        const res = await fetch(API.init);
                        const data = await res.json();
                        if (data.status !== 'success') throw new Error(data.message || 'Phản hồi không hợp lệ');

                        this.vertices = data.vertices;
                        this.edges = data.edges;
                        this.bookings = data.bookings || [];
                        this.weightMatrix = data.weight_matrix || {};
                        this.analysis = data.analysis || {};
                        GraphMapVisualizer.renderBaseGraph(this.vertices, this.edges, this.form.criteria);
                        this.syncSelects();
                    } catch (err) {
                        Swal.fire('Lỗi', 'Không tải được dữ liệu đồ thị: ' + err.message, 'error');
                    }
                },

                /** Đảm bảo các dropdown có giá trị hợp lệ sau khi danh sách đỉnh thay đổi */
                syncSelects() {
                    const codes = this.activeVertices.map(v => v.code);
                    if (!codes.length) return;
                    let s = codes.includes(this.form.source) ? this.form.source : codes[0];
                    let t = codes.includes(this.form.target) ? this.form.target : (codes[1] || codes[0]);
                    let fs = codes.includes(this.flightForm.source) ? this.flightForm.source : codes[0];
                    let ft = codes.includes(this.flightForm.target) ? this.flightForm.target : (codes[1] || codes[0]);
                    this.form.source = ''; this.form.target = '';
                    this.flightForm.source = ''; this.flightForm.target = '';
                    this.$nextTick(() => {
                        this.form.source = s; this.form.target = t;
                        this.flightForm.source = fs; this.flightForm.target = ft;
                    });
                },

                resetSimulation() {
                    this.stopAutoPlay();
                    this.simResult = null; this.steps = []; this.currentStepIndex = 0;
                },

                onCriteriaChange() {
                    this.resetSimulation();
                    GraphMapVisualizer.renderBaseGraph(this.vertices, this.edges, this.form.criteria);
                },

                /* ---------- Chạy mô phỏng ---------- */
                async runSimulation() {
                    this.stopAutoPlay();
                    if (this.form.source === this.form.target) {
                        return Swal.fire('Cảnh báo', 'Điểm đi và điểm đến phải khác nhau.', 'warning');
                    }
                    this.isSearching = true;
                    try {
                        const res = await fetch(API.dijkstra, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(this.form)
                        });
                        const json = await res.json();
                        if (json.status === 'error') return Swal.fire('Cảnh báo', json.message, 'error');

                        this.simResult = json.data;
                        this.steps = json.data.steps || [];
                        this.goToStep(0);
                    } catch (err) {
                        Swal.fire('Lỗi', 'Không chạy được thuật toán: ' + err.message, 'error');
                    } finally {
                        this.isSearching = false;
                    }
                },

                /* ---------- Step Player ---------- */
                goToStep(idx) {
                    if (idx < 0 || idx >= this.steps.length) return;
                    this.currentStepIndex = idx;
                    GraphMapVisualizer.renderStep(this.steps[idx], this.simResult?.path || [], idx === this.steps.length - 1);
                },
                nextStep() { this.goToStep(this.currentStepIndex + 1); },
                prevStep() { this.goToStep(this.currentStepIndex - 1); },

                toggleAutoPlay() {
                    if (this.isPlaying) return this.stopAutoPlay();
                    if (this.steps.length === 0) return;
                    if (this.currentStepIndex >= this.steps.length - 1) this.goToStep(0);
                    this.isPlaying = true;
                    this.playTimer = setInterval(() => {
                        if (this.currentStepIndex >= this.steps.length - 1) {
                            this.stopAutoPlay();
                            return;
                        }
                        this.nextStep();
                    }, 700);
                },
                stopAutoPlay() {
                    if (this.playTimer) clearInterval(this.playTimer);
                    this.playTimer = null;
                    this.isPlaying = false;
                },

                /* ---------- Định dạng ---------- */
                cityOf(code) {
                    const v = this.vertices.find(x => x.code === code);
                    return v ? v.city : '';
                },
                isInf(x) { return x === null || x === undefined || x === '∞' || x === Infinity; },
                fmtNum(x) {
                    if (this.isInf(x)) return '∞';
                    const n = Number(x);
                    if (isNaN(n)) return String(x);
                    return Number.isInteger(n) ? String(n) : String(Math.round(n * 100) / 100);
                },
                fmtMoney(n) {
                    if (n === null || n === undefined || isNaN(Number(n))) return '–';
                    return '$' + Number(n).toLocaleString('en-US', { maximumFractionDigits: 2 });
                },
                fmtHours(n) {
                    if (n === null || n === undefined || isNaN(Number(n))) return '–';
                    return Number(n) + 'h';
                },
                costText(n) {
                    if (n === null || n === undefined) return '–';
                    return this.form.criteria === 'duration' ? this.fmtHours(n) : this.fmtMoney(n);
                },

                /* ---------- Trace Table (dựa trên step.labels) ---------- */
                label(st, code) { return (st.labels && st.labels[code]) || null; },
                cellText(st, code) {
                    const l = this.label(st, code);
                    if (!l) return '';
                    if (this.isInf(l.dist)) return '∞';
                    const p = (l.prev === '-' || !l.prev) ? '–' : l.prev;
                    return `${this.fmtNum(l.dist)}/${p}`;
                },
                cellClass(st, code) {
                    const l = this.label(st, code);
                    if (!l) return '';
                    if (l.is_u) return 'bg-amber-200 text-amber-900 font-bold';
                    if (l.closed) return 'bg-emerald-50 text-emerald-700';
                    if (this.isInf(l.dist)) return 'text-slate-300';
                    return '';
                },

                /* ---------- Checkout / Đặt vé ---------- */
                openCheckout() {
                    if (!this.hasPath) return;
                    this.stopAutoPlay();
                    this.activeModal = 'checkout';
                },
                async confirmBooking() {
                    const c = this.checkout;
                    if (!c.name) return Swal.fire('Thiếu thông tin', 'Vui lòng nhập họ tên hành khách.', 'warning');
                    if (!/^\S+@\S+\.\S+$/.test(c.email)) return Swal.fire('Thiếu thông tin', 'Email không hợp lệ.', 'warning');
                    const pax = Number(c.passengers_count);
                    if (!Number.isInteger(pax) || pax < 1 || pax > 9) {
                        return Swal.fire('Thiếu thông tin', 'Số hành khách phải từ 1 đến 9.', 'warning');
                    }

                    this.isBooking = true;
                    try {
                        const res = await fetch(API.book, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            // Tên khóa khớp cột bảng bookings
                            body: JSON.stringify({
                                passenger_name: c.name,
                                passenger_email: c.email,
                                seat_class: c.seat_class,
                                passengers_count: pax,
                                source: this.simResult.start,
                                target: this.simResult.target,
                                route_path: this.pathCodes.join(' ➔ '),
                                path: this.pathCodes,
                                total_price: this.bookingTotal,
                                total_duration: this.totals.duration
                            })
                        });
                        const json = await res.json();
                        if (json.status === 'error') {
                            return Swal.fire('Đặt vé thất bại', json.message || 'Có lỗi xảy ra.', 'error');
                        }
                        this.activeModal = null;
                        this.checkout = { name: '', email: '', seat_class: 'Economy', passengers_count: 1 };
                        await this.loadGraphData();
                        Swal.fire({
                            icon: 'success',
                            title: 'Đặt vé thành công!',
                            html: json.pnr_code
                                ? `Mã đặt chỗ (PNR): <b style="font-family:monospace;font-size:1.25rem">${json.pnr_code}</b>`
                                : (json.message || '')
                        });
                    } catch (err) {
                        Swal.fire('Lỗi', 'Không kết nối được máy chủ: ' + err.message, 'error');
                    } finally {
                        this.isBooking = false;
                    }
                },

                /* ---------- Quản lý chuyến bay / sân bay ---------- */
                async saveFlight() {
                    const f = this.flightForm;
                    if (!f.flight_no) return Swal.fire('Cảnh báo', 'Vui lòng nhập số hiệu chuyến bay.', 'warning');
                    if (!f.airline) return Swal.fire('Cảnh báo', 'Vui lòng nhập tên hãng bay.', 'warning');
                    if (!f.source || !f.target || f.source === f.target) {
                        return Swal.fire('Cảnh báo', 'Chọn hai sân bay khác nhau.', 'warning');
                    }
                    if (!(f.weight > 0) || !(f.duration > 0)) {
                        return Swal.fire('Cảnh báo', 'Giá và giờ bay phải lớn hơn 0 (Dijkstra không chấp nhận trọng số âm).', 'warning');
                    }
                    await this.postAndReload(API.addEdge, {
                        flight_no: f.flight_no.toUpperCase(),
                        source: f.source, target: f.target,
                        weight: Number(f.weight),
                        duration: Math.round(Number(f.duration)),
                        airline: f.airline
                    }, 'Đã thêm chuyến bay.');
                },
                async deleteFlight(e) {
                    const ok = await Swal.fire({
                        title: 'Xóa chuyến bay?',
                        text: `${e.flight_no}: ${e.source} ➔ ${e.target}`,
                        icon: 'question', showCancelButton: true,
                        confirmButtonText: 'Xóa', cancelButtonText: 'Hủy'
                    });
                    if (!ok.isConfirmed) return;
                    await this.postAndReload(API.deleteEdge, { id: Number(e.id) }, 'Đã xóa chuyến bay.');
                },
                async saveVertex() {
                    const v = this.vertexForm;
                    const code = (v.code || '').toUpperCase();
                    if (!/^[A-Z]{3}$/.test(code)) {
                        return Swal.fire('Cảnh báo', 'Mã sân bay phải gồm đúng 3 chữ cái (ví dụ DAD).', 'warning');
                    }
                    if (!v.name || !v.city || !v.country) {
                        return Swal.fire('Cảnh báo', 'Vui lòng nhập tên sân bay, thành phố và quốc gia.', 'warning');
                    }
                    if (typeof v.lat !== 'number' || typeof v.lng !== 'number' || isNaN(v.lat) || isNaN(v.lng)
                        || v.lat < -90 || v.lat > 90 || v.lng < -180 || v.lng > 180) {
                        return Swal.fire('Cảnh báo', 'Tọa độ không hợp lệ (lat −90..90, lng −180..180).', 'warning');
                    }
                    const ok = await this.postAndReload(API.addVertex, { ...v, code }, 'Đã thêm sân bay.');
                    if (ok) this.vertexForm = { code: '', name: '', city: '', country: '', lat: null, lng: null };
                },
                async resetDatabase() {
                    const ok = await Swal.fire({
                        title: 'Khôi phục dữ liệu mặc định?',
                        text: 'Mọi sân bay và chuyến bay đã thêm sẽ bị ghi đè.',
                        icon: 'warning', showCancelButton: true,
                        confirmButtonText: 'Khôi phục', cancelButtonText: 'Hủy'
                    });
                    if (!ok.isConfirmed) return;
                    await this.postAndReload(API.resetDb, {}, 'Đã khôi phục dữ liệu mặc định.');
                },
                /** Gửi POST, tải lại đồ thị và xóa kết quả mô phỏng cũ. Trả về true nếu thành công. */
                async postAndReload(url, payload, okMsg) {
                    try {
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(payload)
                        });
                        const json = await res.json();
                        if (json.status === 'error') { Swal.fire('Lỗi', json.message, 'error'); return false; }
                        this.resetSimulation();
                        await this.loadGraphData();
                        Swal.fire({ icon: 'success', title: okMsg, timer: 1200, showConfirmButton: false });
                        return true;
                    } catch (err) {
                        Swal.fire('Lỗi', err.message, 'error');
                        return false;
                    }
                },

                /* ---------- Ma trận trọng số ---------- */
                matrixValue(u, v) {
                    if (u === v) return 0;
                    if (this.matrixCriteria === 'weight') {
                        // Backend (DijkstraCore::getWeightMatrix) dựng theo tiêu chí giá
                        const row = this.weightMatrix[u];
                        return row ? row[v] : '∞';
                    }
                    // Tiêu chí giờ bay: tự tính từ danh sách cung (cũng lấy nhỏ nhất nếu trùng chặng)
                    let best = null;
                    for (const e of this.edges) {
                        if (Number(e.is_active ?? 1) !== 1) continue;
                        if (e.source === u && e.target === v) {
                            const d = Number(e.duration);
                            if (best === null || d < best) best = d;
                        }
                    }
                    return best === null ? '∞' : best;
                },
                matrixCell(u, v) { return this.fmtNum(this.matrixValue(u, v)); },
                matrixCellClass(u, v) {
                    if (u === v) return 'bg-slate-100 text-slate-400';
                    const p = this.pathCodes;
                    for (let i = 0; i < p.length - 1; i++) {
                        if (p[i] === u && p[i + 1] === v) return 'bg-teal-200 text-teal-900 font-bold';
                    }
                    return this.isInf(this.matrixValue(u, v)) ? 'text-slate-300' : '';
                }
            };
        }
    </script>
</body>
</html>
