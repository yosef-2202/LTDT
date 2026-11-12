<?php
/**
 * ============================================================================
 * FILE DÀNH CHO THÀNH VIÊN 5: GIAO DIỆN LANDING PAGE VÉ MÁY BAY & TRACE TABLE
 * ============================================================================
 * Thiết kế theo đúng chuẩn mẫu MDIGI TRAVEL:
 * - Header trắng tinh tế + Logo + Menu điều hướng
 * - Hero Banner bầu trời xanh, các kỳ quan thế giới, máy bay bay lượn trên mây
 * - Hộp tìm kiếm nổi (Floating Booking Box) màu trắng + Tab màu xanh Ngọc (#00b5ad)
 * - Khu vực Bản đồ tương tác + Vé máy bay Boarding Pass + Bảng Trace Table Dijkstra
 * ============================================================================
 */
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SKYGRAPH TRAVEL • Thiết kế Website Vé Máy Bay & Mô phỏng Thuật toán Dijkstra</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Leaflet CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Montserrat', sans-serif; background-color: #f4f7f9; color: #1e293b; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }

        /* Màu chủ đạo Xanh Ngọc (Teal/Turquoise) giống hệt ảnh mẫu MDIGI TRAVEL */
        .bg-brand-teal { background-color: #26b7b4; }
        .bg-brand-teal-dark { background-color: #1c9996; }
        .text-brand-teal { color: #26b7b4; }
        .border-brand-teal { border-color: #26b7b4; }

        /* Hiệu ứng máy bay bay lượn trên Hero Banner */
        @keyframes floatPlaneLeft {
            0%, 100% { transform: translateY(0px) rotate(-4deg); }
            50% { transform: translateY(-12px) rotate(-1deg); }
        }
        @keyframes floatPlaneRight {
            0%, 100% { transform: translateY(0px) rotate(12deg); }
            50% { transform: translateY(-10px) rotate(8deg); }
        }
        .hero-plane-left { animation: floatPlaneLeft 5s ease-in-out infinite; }
        .hero-plane-right { animation: floatPlaneRight 6s ease-in-out infinite; }

        /* Tooltip nhãn sân bay trên bản đồ sáng */
        .vertex-label-light {
            background: #0f766e;
            border: 1.5px solid #ffffff;
            color: #ffffff;
            border-radius: 6px;
            padding: 1px 6px;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.2);
        }
        .vertex-label-light::before { display: none; }

        .light-travel-tiles {
            filter: saturate(115%) contrast(98%);
        }
    </style>
</head>
<body class="min-h-screen flex flex-col" x-data="skyTravelApp()" x-init="initApp()">

    <!-- ========================================================================= -->
    <!-- 1. TOP HEADER TRẮNG SANG TRỌNG (GIỐNG HỆT ẢNH MẪU MDIGI TRAVEL)           -->
    <!-- ========================================================================= -->
    <header class="bg-white border-b border-slate-100 sticky top-0 z-40 shadow-sm">
        <div class="max-w-7xl mx-auto px-6 py-4 flex flex-wrap items-center justify-between gap-4">
            <!-- Logo kiểu MDIGI TRAVEL -->
            <a href="#" class="flex flex-col items-center text-center group">
                <span class="text-[10px] tracking-[0.25em] uppercase text-slate-400 font-semibold">TOGETHER WITH</span>
                <span class="text-2xl font-black tracking-tight text-slate-800 group-hover:text-teal-600 transition">SKYGRAPH TRAVEL</span>
                <div class="flex items-center gap-1.5 mt-0.5">
                    <span class="text-[10px] text-amber-500">★ ★ ★</span>
                    <span class="px-2 py-0.2 bg-slate-800 text-white text-[9px] font-bold tracking-widest uppercase rounded-sm">DIJKSTRA</span>
                    <span class="text-[10px] text-amber-500">★ ★ ★</span>
                </div>
            </a>

            <!-- Navigation Menu -->
            <nav class="flex flex-wrap items-center gap-6 text-sm font-semibold text-slate-500">
                <a href="#booking-section" class="text-brand-teal font-bold border-b-2 border-brand-teal pb-1">Home</a>
                <a href="#simulation-workspace" class="hover:text-brand-teal transition">Mô phỏng Đồ thị</a>
                <button @click="activeModal = 'flight_manager'" class="hover:text-brand-teal transition flex items-center gap-1">
                    <span>Quản lý Chuyến bay</span>
                    <span class="text-xs">▾</span>
                </button>
                <button @click="activeModal = 'matrix'" class="hover:text-brand-teal transition">Ma trận & Bậc đỉnh</button>
                <button @click="activeModal = 'bookings_list'" class="hover:text-brand-teal transition flex items-center gap-1.5">
                    <span>Vé đã đặt</span>
                    <span class="px-2 py-0.5 text-xs rounded-full bg-teal-50 text-teal-600 font-bold border border-teal-200" x-text="bookings.length"></span>
                </button>
                <button @click="activeModal = 'team'" class="hover:text-brand-teal transition">Nhóm 5 TV</button>
            </nav>
        </div>
    </header>

    <!-- ========================================================================= -->
    <!-- 2. HERO BANNER BẦU TRỜI XANH + KỲ QUAN THẾ GIỚI + MÁY BAY + KHUNG SEARCH  -->
    <!-- ========================================================================= -->
    <section id="booking-section" class="relative overflow-hidden bg-gradient-to-b from-[#6ec3e8] via-[#b8e4f9] to-[#e4f4fc] pt-10 pb-24">

        <!-- Đám mây trang trí nền -->
        <div class="absolute inset-0 pointer-events-none opacity-65"
             style="background-image: radial-gradient(circle at 20% 25%, rgba(255,255,255,0.9) 0%, rgba(255,255,255,0) 35%), radial-gradient(circle at 80% 30%, rgba(255,255,255,0.85) 0%, rgba(255,255,255,0) 40%), radial-gradient(circle at 50% 65%, rgba(255,255,255,0.95) 0%, rgba(255,255,255,0) 45%);">
        </div>

        <!-- Silhouette Kỳ quan Thế giới (Tháp Eiffel, Tượng Nữ thần Tự do, Big Ben, Tháp nghiêng Pisa) -->
        <svg class="absolute bottom-0 left-0 w-full h-56 md:h-72 text-teal-900/10 pointer-events-none" viewBox="0 0 1440 320" preserveAspectRatio="none" fill="currentColor">
            <path d="M0,288L60,272C120,256,240,224,360,218.7C480,213,600,235,720,229.3C840,224,960,192,1080,197.3C1200,203,1320,245,1380,266.7L1440,288L1440,320L1380,320C1320,320,1200,320,1080,320C960,320,840,320,720,320C600,320,480,320,360,320C240,320,120,320,60,320L0,320Z"></path>
        </svg>

        <!-- Máy bay lớn bên trái (Giống ảnh mẫu) -->
        <div class="hidden lg:block absolute top-8 left-12 xl:left-24 pointer-events-none hero-plane-left z-10">
            <img src="https://cdn-icons-png.flaticon.com/512/7893/7893979.png" alt="Airplane Left" class="w-44 h-44 object-contain drop-shadow-xl" />
        </div>

        <!-- Máy bay bên phải (Giống ảnh mẫu) -->
        <div class="hidden lg:block absolute top-16 right-16 xl:right-28 pointer-events-none hero-plane-right z-10">
            <img src="https://cdn-icons-png.flaticon.com/512/3125/3125713.png" alt="Airplane Right" class="w-28 h-28 object-contain drop-shadow-lg" />
        </div>

        <div class="max-w-6xl mx-auto px-6 relative z-20">
            <!-- Cụm Banner Text màu Xanh Ngọc (Teal Box Overlay) giống hệt ảnh mẫu -->
            <div class="max-w-2xl mt-4 mb-12">
                <div class="inline-block bg-brand-teal/90 backdrop-blur-sm text-white px-5 py-1.5 text-lg md:text-2xl font-medium shadow-md mb-1">
                    Mô phỏng thuật toán Dijkstra
                </div>
                <br>
                <div class="inline-block bg-brand-teal text-white px-6 py-2.5 text-3xl md:text-5xl font-black tracking-wide uppercase shadow-lg mb-2">
                    VÉ MÁY BAY QUỐC TẾ
                </div>
                <br>
                <div class="inline-block bg-brand-teal/95 text-white px-5 py-1.5 text-xs md:text-sm font-medium shadow">
                    Tìm hành trình bay rẻ nhất giữa các thành phố lớn trên thế giới bằng Lý thuyết Đồ thị có hướng G=(V,E,w)
                </div>
            </div>

            <!-- ================================================================= -->
            <!-- KHUNG BOOKING TÌM CHUYẾN BAY (TABS + WHITE BOX) GIỐNG HỆT ẢNH MẪU -->
            <!-- ================================================================= -->
            <div class="shadow-2xl rounded-b-xl overflow-hidden">
                <!-- Các Tab phía trên (Flights / Quản lý Đường bay / Ma trận Đồ thị / Vé đã đặt) -->
                <div class="flex flex-wrap items-center">
                    <button @click="heroTab = 'flights'"
                            :class="heroTab === 'flights' ? 'bg-white text-brand-teal font-extrabold' : 'bg-brand-teal text-white hover:bg-brand-teal-dark font-semibold'"
                            class="px-7 py-3.5 text-sm flex items-center gap-2 transition border-r border-teal-400/30">
                        <span>✈️</span>
                        <span>Flights (Tìm đường Dijkstra)</span>
                    </button>
                    <button @click="activeModal = 'flight_manager'"
                            class="bg-brand-teal hover:bg-brand-teal-dark text-white font-semibold px-6 py-3.5 text-sm flex items-center gap-2 transition border-r border-teal-400/30">
                        <span>➕</span>
                        <span>Thêm Chuyến Bay / Sân Bay</span>
                    </button>
                    <button @click="activeModal = 'matrix'"
                            class="bg-brand-teal hover:bg-brand-teal-dark text-white font-semibold px-6 py-3.5 text-sm flex items-center gap-2 transition border-r border-teal-400/30">
                        <span>📐</span>
                        <span>Ma trận Trọng số</span>
                    </button>
                    <button @click="activeModal = 'bookings_list'"
                            class="bg-brand-teal hover:bg-brand-teal-dark text-white font-semibold px-6 py-3.5 text-sm flex items-center gap-2 transition">
                        <span>🎟️</span>
                        <span>Vé Đã Đặt (<span x-text="bookings.length"></span>)</span>
                    </button>
                </div>

                <!-- Khung Form màu trắng bên dưới (Flying from, To, Criteria, Class, Passengers, SEARCH) -->
                <div class="bg-white p-6 md:p-7 border-t-0">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-4 items-end">

                        <!-- Flying from -->
                        <div class="lg:col-span-3">
                            <label class="block text-xs font-bold text-slate-600 mb-1.5">Flying from (Đỉnh xuất phát u):</label>
                            <div class="relative">
                                <select x-model="form.source" class="w-full bg-slate-50 border border-slate-200 rounded-md px-3.5 py-2.5 text-sm font-semibold text-slate-700 focus:outline-none focus:border-teal-500">
                                    <template x-for="v in vertices" :key="v.code">
                                        <option :value="v.code" x-text="`${v.code} - ${v.city} (${v.country})`"></option>
                                    </template>
                                </select>
                            </div>
                        </div>

                        <!-- To -->
                        <div class="lg:col-span-3">
                            <label class="block text-xs font-bold text-slate-600 mb-1.5">To (Đỉnh đích v):</label>
                            <div class="relative">
                                <select x-model="form.target" class="w-full bg-slate-50 border border-slate-200 rounded-md px-3.5 py-2.5 text-sm font-semibold text-slate-700 focus:outline-none focus:border-teal-500">
                                    <template x-for="v in vertices" :key="v.code">
                                        <option :value="v.code" x-text="`${v.code} - ${v.city} (${v.country})`"></option>
                                    </template>
                                </select>
                            </div>
                        </div>

                        <!-- Tiêu chí Trọng số -->
                        <div class="lg:col-span-2">
                            <label class="block text-xs font-bold text-slate-600 mb-1.5">Tiêu chí w(u,v):</label>
                            <select x-model="form.criteria" @change="refreshMapBase()" class="w-full bg-slate-50 border border-slate-200 rounded-md px-3 py-2.5 text-sm font-semibold text-slate-700 focus:outline-none focus:border-teal-500">
                                <option value="weight">Giá rẻ nhất ($)</option>
                                <option value="duration">Bay nhanh nhất (h)</option>
                            </select>
                        </div>

                        <!-- Hạng vé -->
                        <div class="lg:col-span-1">
                            <label class="block text-xs font-bold text-slate-600 mb-1.5">Class:</label>
                            <select x-model="bookingForm.seat_class" class="w-full bg-slate-50 border border-slate-200 rounded-md px-2.5 py-2.5 text-sm font-semibold text-slate-700 focus:outline-none focus:border-teal-500">
                                <option value="Economy">Eco</option>
                                <option value="Business">Biz</option>
                                <option value="First Class">First</option>
                            </select>
                        </div>

                        <!-- Số hành khách -->
                        <div class="lg:col-span-1">
                            <label class="block text-xs font-bold text-slate-600 mb-1.5">Adult:</label>
                            <input type="number" min="1" max="9" x-model.number="bookingForm.passengers_count" class="w-full bg-slate-50 border border-slate-200 rounded-md px-3 py-2.5 text-sm font-bold text-slate-700 text-center focus:outline-none focus:border-teal-500" />
                        </div>

                        <!-- Nút SEARCH màu Xanh Ngọc giống hệt ảnh mẫu -->
                        <div class="lg:col-span-2">
                            <button @click="runSimulation()" class="w-full bg-brand-teal hover:bg-brand-teal-dark text-white font-extrabold text-sm uppercase tracking-wider py-3 px-6 rounded-md shadow-md hover:shadow-lg transition">
                                SEARCH
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================================================= -->
    <!-- 3. WORKSPACE MÔ PHỎNG: BẢN ĐỒ ĐƯỜNG BAY + VÉ BOARDING PASS + TRACE TABLE  -->
    <!-- ========================================================================= -->
    <main id="simulation-workspace" class="flex-1 max-w-7xl w-full mx-auto px-6 -mt-12 relative z-30 pb-16 space-y-6">

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            <!-- CỘT TRÁI (7 CỘT): BẢN ĐỒ ĐƯỜNG BAY THẾ GIỚI & STEP PLAYER -->
            <div class="lg:col-span-7 flex flex-col gap-4">
                <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-lg">
                    <!-- Thanh tiêu đề trên bản đồ -->
                    <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span class="w-3 h-3 rounded-full bg-teal-500"></span>
                            <h2 class="font-extrabold text-sm text-slate-800 uppercase tracking-wide">Bản đồ Mô phỏng Đường bay & Thuật toán Dijkstra</h2>
                        </div>
                        <div class="flex items-center gap-3 text-xs font-mono text-slate-600">
                            <span>Đỉnh |V| = <b class="text-teal-600" x-text="analysis.vertex_count || 0"></b></span>
                            <span>Cung |E| = <b class="text-teal-600" x-text="analysis.edge_count || 0"></b></span>
                            <span>Mật độ = <b class="text-amber-600" x-text="analysis.density || 0"></b></span>
                        </div>
                    </div>

                    <!-- Khung Bản đồ Leaflet -->
                    <div class="relative h-[440px] lg:h-[480px]">
                        <div id="map-container" class="w-full h-full z-10"></div>

                        <!-- Bộ điều khiển từng bước (Step Player) nổi góc trên phải -->
                        <div class="absolute top-3 right-3 z-20 flex items-center gap-1.5 bg-white/95 backdrop-blur px-3 py-1.5 rounded-xl border border-teal-500/40 shadow-md" x-show="steps.length > 0">
                            <button @click="goToStep(0)" :disabled="currentStepIndex === 0" class="px-2 py-1 rounded bg-slate-100 hover:bg-slate-200 disabled:opacity-40 text-xs font-bold">⏮</button>
                            <button @click="prevStep()" :disabled="currentStepIndex === 0" class="px-2 py-1 rounded bg-slate-100 hover:bg-slate-200 disabled:opacity-40 text-xs font-bold">◀</button>
                            <button @click="toggleAutoPlay()" class="px-3 py-1 rounded font-bold text-xs text-white transition" :class="isPlaying ? 'bg-amber-500' : 'bg-brand-teal'">
                                <span x-text="isPlaying ? '⏸ Tạm dừng' : '▶ Chạy Step'"></span>
                            </button>
                            <button @click="nextStep()" :disabled="currentStepIndex >= steps.length - 1" class="px-2 py-1 rounded bg-slate-100 hover:bg-slate-200 disabled:opacity-40 text-xs font-bold">▶</button>
                            <button @click="goToStep(steps.length - 1)" :disabled="currentStepIndex >= steps.length - 1" class="px-2 py-1 rounded bg-slate-100 hover:bg-slate-200 disabled:opacity-40 text-xs font-bold">⏭</button>
                            <span class="text-xs font-mono font-bold text-teal-700 ml-1" x-text="`Bước ${currentStepIndex}/${steps.length - 1}`"></span>
                        </div>

                        <!-- Chú giải Trạng thái Đỉnh -->
                        <div class="absolute bottom-3 left-3 z-20 bg-white/95 backdrop-blur border border-slate-200 rounded-xl p-3 text-xs space-y-1 shadow-md text-slate-700">
                            <div class="font-extrabold text-teal-700 uppercase text-[10px] mb-1">Chú giải Trạng thái Đỉnh:</div>
                            <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-white inline-block border-2 border-slate-500"></span> <span>Chưa xét (v ∉ S, d(v) = ∞)</span></div>
                            <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-yellow-400 inline-block border border-amber-600"></span> <span>Trong hàng đợi ưu tiên (Nhãn tạm thời)</span></div>
                            <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-orange-500 inline-block border border-white"></span> <span>Đỉnh u đang xét cố định ở bước hiện tại</span></div>
                            <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-teal-600 inline-block border border-white"></span> <span>Đã cố định nhãn (u ∈ S) & Đường bay tối ưu ✈️</span></div>
                        </div>
                    </div>

                    <!-- Thuyết minh từng bước ở chân Bản đồ -->
                    <div class="px-5 py-3.5 bg-teal-50/60 border-t border-teal-100" x-show="currentStep">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-extrabold uppercase text-teal-700" x-text="`Diễn giải Thuật toán • Bước lặp ${currentStepIndex}`"></span>
                            <span class="px-2.5 py-0.5 rounded-full bg-orange-100 text-orange-700 border border-orange-300 text-xs font-mono font-bold" x-show="currentStep?.current_u" x-text="`Đỉnh cố định u = ${currentStep?.current_u}`"></span>
                        </div>
                        <p class="text-xs font-mono text-slate-700 leading-relaxed" x-text="currentStep?.explanation"></p>
                    </div>
                </div>
            </div>

            <!-- CỘT PHẢI (5 CỘT): VÉ MÁY BAY BOARDING PASS & BẢNG TRACE TABLE -->
            <div class="lg:col-span-5 flex flex-col gap-4">

                <!-- THẺ VÉ MÁY BAY (BOARDING PASS) -->
                <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-lg">
                    <div class="bg-brand-teal px-5 py-3.5 text-white flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-lg">🎫</span>
                            <h3 class="font-extrabold text-sm uppercase tracking-wider">Electronic Boarding Pass</h3>
                        </div>
                        <span class="text-xs font-mono bg-white/20 px-2.5 py-0.5 rounded-full" x-show="simResult" x-text="`${simResult?.exec_time_ms} ms`"></span>
                    </div>

                    <div class="p-5">
                        <template x-if="!simResult">
                            <div class="text-center py-6 px-4 border-2 border-dashed border-slate-200 rounded-xl">
                                <div class="text-3xl mb-2">✈️</div>
                                <p class="text-xs text-slate-500 font-medium">Chọn điểm đi, điểm đến trên thanh tìm kiếm và bấm nút <b>SEARCH</b> màu xanh ngọc để tìm lộ trình vé rẻ nhất và xem máy bay bay trên bản đồ.</p>
                            </div>
                        </template>

                        <template x-if="simResult && simResult.found">
                            <div class="space-y-4">
                                <!-- Chuỗi sân bay quá cảnh -->
                                <div class="bg-slate-50 border border-slate-200 rounded-xl p-3.5 flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <template x-for="(vCode, idx) in simResult.path" :key="vCode">
                                            <div class="flex items-center gap-1.5">
                                                <span class="px-3 py-1 rounded-lg bg-teal-600 text-white font-mono font-bold text-xs shadow-sm" x-text="vCode"></span>
                                                <span x-show="idx < simResult.path.length - 1" class="text-teal-600 font-bold text-xs">✈➔</span>
                                            </div>
                                        </template>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-[10px] uppercase font-bold text-slate-400">Tổng giá vé (<span x-text="bookingForm.passengers_count"></span> khách)</div>
                                        <div class="text-xl font-black text-rose-600 font-mono" x-text="`$${calculateFinalTicketPrice()}`"></div>
                                    </div>
                                </div>

                                <!-- Chi tiết các chuyến bay nối chuyến -->
                                <div class="space-y-1.5 max-h-32 overflow-y-auto pr-1">
                                    <template x-for="(edge, idx) in simResult.path_edges" :key="idx">
                                        <div class="flex items-center justify-between text-xs bg-slate-50 border border-slate-200/80 px-3 py-2 rounded-lg">
                                            <div class="flex items-center gap-2">
                                                <span class="px-2 py-0.5 rounded bg-teal-100 text-teal-800 font-mono font-bold text-[11px]" x-text="`Chặng ${idx + 1}`"></span>
                                                <span class="font-mono font-bold text-slate-800" x-text="`${edge.from} ➔ ${edge.to}`"></span>
                                                <span class="text-slate-500" x-text="`(${edge.airline})`"></span>
                                            </div>
                                            <span class="font-mono font-bold text-teal-700" x-text="`$${edge.price} • ${edge.duration}h`"></span>
                                        </div>
                                    </template>
                                </div>

                                <!-- Nút Đặt vé -->
                                <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
                                    <div class="text-xs text-slate-500 font-semibold">
                                        Tổng thời gian: <b class="text-slate-800" x-text="`${calculateTotalDuration()} giờ`"></b> • Quá cảnh: <b class="text-slate-800" x-text="`${simResult.path.length - 2} điểm`"></b>
                                    </div>
                                    <button @click="activeModal = 'checkout'" class="bg-rose-500 hover:bg-rose-600 text-white font-extrabold text-xs uppercase px-5 py-2.5 rounded-lg shadow-md transition">
                                        💳 Đặt Vé Ngay
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- BẢNG TRACE TABLE CHUẨN BÀI THI LÝ THUYẾT ĐỒ THỊ -->
                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-lg flex-1 flex flex-col overflow-hidden">
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <h3 class="text-xs font-extrabold text-slate-800 uppercase tracking-wider">Bảng Trace Table Dijkstra: (dist, prev)</h3>
                            <p class="text-[11px] text-slate-500">Bấm vào từng dòng bước lặp để xem trạng thái duyệt đỉnh trên bản đồ</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto overflow-y-auto max-h-[260px] border border-slate-200 rounded-xl">
                        <table class="w-full text-left border-collapse text-xs font-mono">
                            <thead class="bg-slate-100 sticky top-0 z-10 text-slate-700">
                                <tr>
                                    <th class="p-2 border-b border-r border-slate-200">Bước</th>
                                    <th class="p-2 border-b border-r border-slate-200 text-orange-600">Chọn u</th>
                                    <template x-for="vCode in (simResult?.vertices_list || [])" :key="vCode">
                                        <th class="p-2 border-b border-r border-slate-200 text-center text-teal-700 font-bold" x-text="vCode"></th>
                                    </template>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="(st, idx) in steps" :key="st.step">
                                    <tr @click="goToStep(idx)"
                                        class="cursor-pointer transition border-b border-slate-200"
                                        :class="idx === currentStepIndex ? 'bg-teal-50' : 'hover:bg-slate-50'">
                                        <td class="p-2 border-r border-slate-200 font-bold text-slate-700" x-text="st.step === 0 ? 'Khởi tạo' : `Bước ${st.step}`"></td>
                                        <td class="p-2 border-r border-slate-200 font-bold text-orange-600 text-center" x-text="st.current_u || '-'"></td>
                                        <template x-for="vCode in (simResult?.vertices_list || [])" :key="vCode">
                                            <td class="p-2 border-r border-slate-200 text-center whitespace-nowrap"
                                                :class="{
                                                    'text-orange-700 font-bold bg-orange-100/70': st.labels[vCode]?.is_u,
                                                    'text-teal-700 font-semibold': st.labels[vCode]?.closed && !st.labels[vCode]?.is_u,
                                                    'text-amber-600 font-semibold': !st.labels[vCode]?.closed && st.labels[vCode]?.dist !== '∞',
                                                    'text-slate-400': st.labels[vCode]?.dist === '∞'
                                                }">
                                                <span x-text="`(${st.labels[vCode]?.dist}, ${st.labels[vCode]?.prev})`"></span>
                                                <span x-show="st.labels[vCode]?.closed" class="text-teal-600 font-bold">*</span>
                                            </td>
                                        </template>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- ========================================================================= -->
    <!-- MODAL 1: QUẢN LÝ CHUYẾN BAY & SÂN BAY                                     -->
    <!-- ========================================================================= -->
    <div x-show="activeModal === 'flight_manager'" style="display: none;" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white border border-slate-200 rounded-2xl max-w-4xl w-full max-h-[88vh] flex flex-col overflow-hidden shadow-2xl">
            <div class="px-6 py-4 bg-brand-teal text-white flex items-center justify-between">
                <h3 class="font-extrabold text-base">✈️ Quản lý Mạng Đường Bay (Thêm Chuyến Bay & Sân Bay Mới)</h3>
                <button @click="activeModal = null" class="text-white/80 hover:text-white text-2xl">&times;</button>
            </div>

            <div class="p-6 overflow-y-auto space-y-6">
                <!-- Thêm Chuyến bay -->
                <div class="bg-slate-50 border border-slate-200 p-4 rounded-xl">
                    <div class="text-xs font-extrabold text-teal-700 uppercase mb-3">1. Mở Chuyến Bay Mới (Thêm Cung Có Hướng u ➔ v)</div>
                    <div class="grid grid-cols-2 sm:grid-cols-6 gap-2.5 text-xs">
                        <input type="text" x-model="newEdge.flight_no" placeholder="Số hiệu (VN-99)" class="bg-white border border-slate-300 rounded-lg px-3 py-2 font-mono" />
                        <select x-model="newEdge.source" class="bg-white border border-slate-300 rounded-lg px-2.5 py-2">
                            <template x-for="v in vertices" :key="v.code"><option :value="v.code" x-text="`Đi: ${v.code}`"></option></template>
                        </select>
                        <select x-model="newEdge.target" class="bg-white border border-slate-300 rounded-lg px-2.5 py-2">
                            <template x-for="v in vertices" :key="v.code"><option :value="v.code" x-text="`Đến: ${v.code}`"></option></template>
                        </select>
                        <input type="number" x-model="newEdge.weight" placeholder="Giá vé ($)" class="bg-white border border-slate-300 rounded-lg px-3 py-2" />
                        <input type="number" x-model="newEdge.duration" placeholder="Giờ bay (h)" class="bg-white border border-slate-300 rounded-lg px-3 py-2" />
                        <button @click="addNewEdge()" class="bg-brand-teal hover:bg-brand-teal-dark text-white font-bold rounded-lg px-3 py-2">
                            + Thêm Chuyến
                        </button>
                    </div>
                </div>

                <!-- Thêm Sân bay -->
                <div class="bg-slate-50 border border-slate-200 p-4 rounded-xl">
                    <div class="text-xs font-extrabold text-teal-700 uppercase mb-3">2. Thêm Sân Bay Mới (Thêm Đỉnh Vào Đồ Thị)</div>
                    <div class="grid grid-cols-2 sm:grid-cols-6 gap-2.5 text-xs">
                        <input type="text" maxlength="3" x-model="newVertex.code" placeholder="Mã IATA (DAD)" class="bg-white border border-slate-300 rounded-lg px-3 py-2 font-mono uppercase" />
                        <input type="text" x-model="newVertex.name" placeholder="Tên Sân bay" class="bg-white border border-slate-300 rounded-lg px-3 py-2" />
                        <input type="text" x-model="newVertex.city" placeholder="Thành phố" class="bg-white border border-slate-300 rounded-lg px-3 py-2" />
                        <input type="number" step="0.0001" x-model="newVertex.lat" placeholder="Vĩ độ Lat" class="bg-white border border-slate-300 rounded-lg px-3 py-2 font-mono" />
                        <input type="number" step="0.0001" x-model="newVertex.lng" placeholder="Kinh độ Lng" class="bg-white border border-slate-300 rounded-lg px-3 py-2 font-mono" />
                        <button @click="addNewVertex()" class="bg-slate-800 hover:bg-slate-700 text-white font-bold rounded-lg px-3 py-2">
                            + Thêm Sân Bay
                        </button>
                    </div>
                </div>

                <!-- Danh sách chuyến bay -->
                <div class="overflow-y-auto max-h-60 border border-slate-200 rounded-xl">
                    <table class="w-full text-xs text-left font-mono">
                        <thead class="bg-slate-100 sticky top-0 text-slate-700">
                            <tr>
                                <th class="p-2.5 border-b">Số hiệu</th>
                                <th class="p-2.5 border-b">Chặng (u ➔ v)</th>
                                <th class="p-2.5 border-b">Giá vé</th>
                                <th class="p-2.5 border-b">Giờ bay</th>
                                <th class="p-2.5 border-b">Hãng bay</th>
                                <th class="p-2.5 border-b text-right">Xóa</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="e in edges" :key="e.id">
                                <tr class="border-b border-slate-100 hover:bg-slate-50">
                                    <td class="p-2.5 font-bold text-teal-700" x-text="e.flight_no || `SG-${e.id}`"></td>
                                    <td class="p-2.5 font-bold" x-text="`${e.source} ➔ ${e.target}`"></td>
                                    <td class="p-2.5 font-bold text-rose-600" x-text="`$${e.weight}`"></td>
                                    <td class="p-2.5" x-text="`${e.duration}h`"></td>
                                    <td class="p-2.5 text-slate-500" x-text="e.airline"></td>
                                    <td class="p-2.5 text-right">
                                        <button @click="removeEdge(e.id)" class="text-rose-500 hover:underline font-bold">Xóa</button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 2: XUẤT VÉ MÁY BAY (CHECKOUT)                                       -->
    <!-- ========================================================================= -->
    <div x-show="activeModal === 'checkout'" style="display: none;" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white border border-slate-200 rounded-2xl max-w-lg w-full overflow-hidden shadow-2xl">
            <div class="px-6 py-4 bg-brand-teal text-white flex items-center justify-between">
                <h3 class="font-extrabold text-base">💳 Đặt Vé & Xuất Mã Đặt Chỗ PNR</h3>
                <button @click="activeModal = null" class="text-white/80 hover:text-white text-2xl">&times;</button>
            </div>
            <div class="p-6 space-y-4">
                <div class="bg-teal-50 border border-teal-200 rounded-xl p-3.5 text-xs space-y-1 font-mono">
                    <div class="text-teal-700 font-bold">Lộ trình tối ưu Dijkstra:</div>
                    <div class="text-sm font-extrabold text-slate-900" x-text="(simResult?.path || []).join(' ➔ ')"></div>
                    <div class="flex justify-between pt-2 border-t border-teal-200 mt-2">
                        <span x-text="`Hạng: ${bookingForm.seat_class} (${bookingForm.passengers_count} khách)`"></span>
                        <span class="font-extrabold text-rose-600 text-sm" x-text="`Tổng tiền: $${calculateFinalTicketPrice()}`"></span>
                    </div>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Họ và Tên Hành khách *</label>
                        <input type="text" x-model="bookingForm.passenger_name" placeholder="NGUYEN VAN A" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5 uppercase font-bold" />
                    </div>
                    <div>
                        <label class="block text-slate-700 font-bold mb-1">Email nhận vé điện tử *</label>
                        <input type="email" x-model="bookingForm.passenger_email" placeholder="customer@gmail.com" class="w-full bg-slate-50 border border-slate-300 rounded-xl px-3.5 py-2.5" />
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button @click="activeModal = null" class="px-4 py-2 rounded-xl bg-slate-100 text-slate-600 text-xs font-bold">Hủy</button>
                    <button @click="confirmBookingTicket()" class="px-5 py-2.5 rounded-xl bg-brand-teal hover:bg-brand-teal-dark text-white text-xs font-extrabold shadow-md">
                        ✅ Xuất Vé Ngay
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 3: DANH SÁCH VÉ ĐÃ ĐẶT                                              -->
    <!-- ========================================================================= -->
    <div x-show="activeModal === 'bookings_list'" style="display: none;" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white border border-slate-200 rounded-2xl max-w-4xl w-full max-h-[85vh] flex flex-col overflow-hidden shadow-2xl">
            <div class="px-6 py-4 bg-brand-teal text-white flex items-center justify-between">
                <h3 class="font-extrabold text-base">🎟️ Danh sách Vé Máy Bay Đã Đặt</h3>
                <button @click="activeModal = null" class="text-white/80 hover:text-white text-2xl">&times;</button>
            </div>
            <div class="p-6 overflow-y-auto space-y-3">
                <template x-if="bookings.length === 0">
                    <p class="text-center text-sm text-slate-400 py-8">Chưa có vé nào được xuất.</p>
                </template>
                <template x-for="b in bookings" :key="b.id">
                    <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 flex flex-wrap items-center justify-between gap-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="px-2.5 py-0.5 rounded bg-teal-600 text-white font-mono font-bold text-xs" x-text="`PNR: ${b.pnr_code}`"></span>
                                <span class="font-extrabold text-slate-800 text-sm uppercase" x-text="b.passenger_name"></span>
                                <span class="text-xs text-slate-500" x-text="`(${b.seat_class} • ${b.passengers_count} khách)`"></span>
                            </div>
                            <div class="text-xs font-mono text-teal-700 font-bold" x-text="`Hành trình: ${b.route_path}`"></div>
                            <div class="text-[11px] text-slate-400" x-text="`Ngày đặt: ${b.booked_at} • Email: ${b.passenger_email}`"></div>
                        </div>
                        <div class="text-right font-mono">
                            <div class="text-lg font-black text-rose-600" x-text="`$${b.total_price}`"></div>
                            <div class="text-xs text-slate-500" x-text="`Tổng giờ bay: ${b.total_duration}h`"></div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 4: MA TRẬN TRỌNG SỐ & BẬC ĐỈNH                                      -->
    <!-- ========================================================================= -->
    <div x-show="activeModal === 'matrix'" style="display: none;" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white border border-slate-200 rounded-2xl max-w-5xl w-full max-h-[85vh] flex flex-col overflow-hidden shadow-2xl">
            <div class="px-6 py-4 bg-brand-teal text-white flex items-center justify-between">
                <h3 class="font-extrabold text-base">📐 Biểu diễn Đồ thị: Ma trận Trọng số A[i][j] & Bậc của Đỉnh</h3>
                <button @click="activeModal = null" class="text-white/80 hover:text-white text-2xl">&times;</button>
            </div>
            <div class="p-6 overflow-y-auto space-y-6">
                <div class="overflow-x-auto border border-slate-200 rounded-xl">
                    <table class="w-full text-xs font-mono text-center border-collapse">
                        <thead class="bg-slate-100">
                            <tr>
                                <th class="p-2 border border-slate-200">Đỉnh</th>
                                <template x-for="v in vertices" :key="v.code">
                                    <th class="p-2 border border-slate-200 text-teal-700 font-bold" x-text="v.code"></th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="u in vertices" :key="u.code">
                                <tr>
                                    <td class="p-2 border border-slate-200 bg-slate-50 font-bold text-teal-700" x-text="u.code"></td>
                                    <template x-for="v in vertices" :key="v.code">
                                        <td class="p-2 border border-slate-200"
                                            :class="weightMatrix[u.code]?.[v.code] !== '∞' && weightMatrix[u.code]?.[v.code] !== 0 ? 'bg-teal-50 text-teal-800 font-bold' : 'text-slate-400'"
                                            x-text="weightMatrix[u.code]?.[v.code] ?? '∞'"></td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 5: PHÂN CÔNG 5 THÀNH VIÊN                                           -->
    <!-- ========================================================================= -->
    <div x-show="activeModal === 'team'" style="display: none;" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white border border-slate-200 rounded-2xl max-w-3xl w-full overflow-hidden shadow-2xl">
            <div class="px-6 py-4 bg-brand-teal text-white flex items-center justify-between">
                <h3 class="font-extrabold text-base">👥 Phân công File & Nhiệm vụ cho 5 Thành viên</h3>
                <button @click="activeModal = null" class="text-white/80 hover:text-white text-2xl">&times;</button>
            </div>
            <div class="p-6 grid grid-cols-1 gap-3 text-xs leading-relaxed text-slate-700">
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200"><b>Thành viên 1 (`includes/DijkstraCore.php`):</b> Ma trận trọng số, Danh sách kề và thuật toán Dijkstra trả về mảng `steps` lưu nhãn `(dist, prev)`.</div>
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200"><b>Thành viên 2 (`includes/GraphAnalyzer.php`):</b> Tính bậc vào/ra (deg⁻, deg⁺), kiểm tra tính liên thông bằng BFS và cảnh báo cạnh âm.</div>
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200"><b>Thành viên 3 (`includes/Database.php` & `api.php`):</b> CSDL SQLite (`vertices`, `edges`, `bookings`), API thêm chuyến bay, thêm sân bay và xuất vé PNR.</div>
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200"><b>Thành viên 4 (`assets/map-visualizer.js`):</b> Vẽ đường bay cong Bezier và hiệu ứng Máy bay phản lực ✈️ bay dọc theo đường đi ngắn nhất.</div>
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200"><b>Thành viên 5 (`index.php`):</b> Giao diện Landing Page Vé máy bay, Khung Search Booking, Boarding Pass và Bảng Trace Table.</div>
            </div>
        </div>
    </div>

    <!-- SCRIPTS -->
    <script src="assets/map-visualizer.js"></script>
    <script>
        function skyTravelApp() {
            return {
                heroTab: 'flights',
                vertices: [],
                edges: [],
                bookings: [],
                weightMatrix: {},
                analysis: {},
                form: {
                    source: 'SGN',
                    target: 'JFK',
                    criteria: 'weight'
                },
                bookingForm: {
                    passenger_name: '',
                    passenger_email: '',
                    seat_class: 'Economy',
                    passengers_count: 1
                },
                newEdge: {
                    flight_no: 'VN-888',
                    source: 'SGN',
                    target: 'CDG',
                    weight: 420,
                    duration: 11,
                    airline: 'Vietnam Airlines'
                },
                newVertex: {
                    code: 'DAD',
                    name: 'Đà Nẵng Intl',
                    city: 'Đà Nẵng',
                    country: 'Vietnam',
                    lat: 16.0439,
                    lng: 108.1994
                },
                simResult: null,
                steps: [],
                currentStepIndex: 0,
                isPlaying: false,
                playTimer: null,
                activeModal: null,

                get currentStep() {
                    return this.steps[this.currentStepIndex] || null;
                },

                async initApp() {
                    GraphMapVisualizer.initMap('map-container');
                    await this.loadGraphData();
                },

                async loadGraphData() {
                    try {
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
                    } catch (err) {
                        Swal.fire('Lỗi kết nối', 'Không thể tải dữ liệu từ api.php', 'error');
                    }
                },

                refreshMapBase() {
                    GraphMapVisualizer.renderBaseGraph(this.vertices, this.edges, this.form.criteria);
                },

                calculateTotalDuration() {
                    if (!this.simResult || !this.simResult.path_edges) return 0;
                    return this.simResult.path_edges.reduce((sum, e) => sum + parseInt(e.duration || 0), 0);
                },

                calculateFinalTicketPrice() {
                    if (!this.simResult || !this.simResult.path_edges) return 0;
                    const basePrice = this.simResult.path_edges.reduce((sum, e) => sum + parseFloat(e.price || 0), 0);
                    let multiplier = 1.0;
                    if (this.bookingForm.seat_class === 'Business') multiplier = 1.8;
                    if (this.bookingForm.seat_class === 'First Class') multiplier = 2.5;
                    return Math.round(basePrice * multiplier * (this.bookingForm.passengers_count || 1));
                },

                async runSimulation() {
                    if (this.form.source === this.form.target) {
                        Swal.fire('Trùng sân bay!', 'Điểm khởi hành và điểm đến phải khác nhau.', 'warning');
                        return;
                    }

                    this.stopAutoPlay();

                    const res = await fetch('api.php?action=run_dijkstra', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(this.form)
                    });
                    const json = await res.json();

                    if (json.status === 'error') {
                        Swal.fire('Cảnh báo Lý thuyết Đồ thị!', json.message, 'error');
                        return;
                    }

                    this.simResult = json.data;
                    this.steps = json.data.steps || [];
                    this.currentStepIndex = 0;

                    if (!this.simResult.found) {
                        Swal.fire('Không có Đường bay Liên thông!', `Không tồn tại đường đi có hướng từ ${this.form.source} tới ${this.form.target}.`, 'info');
                    }

                    this.toggleAutoPlay();
                },

                goToStep(idx) {
                    if (idx < 0 || idx >= this.steps.length) return;
                    this.currentStepIndex = idx;
                    const isFinal = (idx === this.steps.length - 1);
                    GraphMapVisualizer.renderStep(
                        this.steps[idx],
                        this.simResult?.path || [],
                        isFinal
                    );
                },

                nextStep() {
                    if (this.currentStepIndex < this.steps.length - 1) {
                        this.goToStep(this.currentStepIndex + 1);
                    } else {
                        this.stopAutoPlay();
                    }
                },

                prevStep() {
                    if (this.currentStepIndex > 0) {
                        this.goToStep(this.currentStepIndex - 1);
                    }
                },

                toggleAutoPlay() {
                    if (this.isPlaying) {
                        this.stopAutoPlay();
                    } else {
                        if (this.currentStepIndex >= this.steps.length - 1) {
                            this.currentStepIndex = 0;
                        }
                        this.isPlaying = true;
                        this.goToStep(this.currentStepIndex);
                        this.playTimer = setInterval(() => {
                            if (this.currentStepIndex < this.steps.length - 1) {
                                this.nextStep();
                            } else {
                                this.stopAutoPlay();
                            }
                        }, 700);
                    }
                },

                stopAutoPlay() {
                    this.isPlaying = false;
                    if (this.playTimer) {
                        clearInterval(this.playTimer);
                        this.playTimer = null;
                    }
                },

                async addNewEdge() {
                    const res = await fetch('api.php?action=add_edge', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(this.newEdge)
                    });
                    const json = await res.json();
                    if (json.status === 'success') {
                        await this.loadGraphData();
                        Swal.fire('Thành công!', json.message, 'success');
                    } else {
                        Swal.fire('Lỗi', json.message, 'error');
                    }
                },

                async addNewVertex() {
                    const res = await fetch('api.php?action=add_vertex', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(this.newVertex)
                    });
                    const json = await res.json();
                    if (json.status === 'success') {
                        await this.loadGraphData();
                        Swal.fire('Thành công!', json.message, 'success');
                    } else {
                        Swal.fire('Lỗi', json.message, 'error');
                    }
                },

                async removeEdge(id) {
                    const res = await fetch('api.php?action=delete_edge', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id })
                    });
                    const json = await res.json();
                    if (json.status === 'success') {
                        await this.loadGraphData();
                    }
                },

                async confirmBookingTicket() {
                    if (!this.bookingForm.passenger_name.trim()) {
                        Swal.fire('Thiếu thông tin', 'Vui lòng nhập họ tên hành khách!', 'warning');
                        return;
                    }

                    const payload = {
                        passenger_name: this.bookingForm.passenger_name,
                        passenger_email: this.bookingForm.passenger_email || 'customer@skygraph.vn',
                        seat_class: this.bookingForm.seat_class,
                        passengers_count: this.bookingForm.passengers_count,
                        source: this.form.source,
                        target: this.form.target,
                        route_path: this.simResult.path,
                        total_price: this.calculateFinalTicketPrice(),
                        total_duration: this.calculateTotalDuration()
                    };

                    const res = await fetch('api.php?action=book_ticket', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    });
                    const json = await res.json();

                    if (json.status === 'success') {
                        this.activeModal = null;
                        this.bookingForm.passenger_name = '';
                        await this.loadGraphData();

                        Swal.fire({
                            icon: 'success',
                            title: '🎉 Xuất Vé Điện Tử Thành Công!',
                            html: `Mã PNR: <b>${json.pnr_code}</b><br>Hành trình: <b>${payload.route_path.join(' ➔ ')}</b><br>Tổng tiền: <b>$${payload.total_price}</b>`,
                            confirmButtonColor: '#26b7b4'
                        });
                    } else {
                        Swal.fire('Lỗi', json.message, 'error');
                    }
                }
            };
        }
    </script>
</body>
</html>