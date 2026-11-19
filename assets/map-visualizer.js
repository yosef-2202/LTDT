/**
 * THÀNH VIÊN 4: TRỰC QUAN HÓA BẢN ĐỒ LEAFLET & ANIMATION MÁY BAY
 */
window.GraphMapVisualizer = {
    map: null,
    vertexMarkers: {},
    edgePolylines: [],
    verticesMap: {},
    planeMarker: null,
    planeAnimFrame: null,

    initMap(containerId) {
        this.map = L.map(containerId, { center: [24.0, 70.0], zoom: 2.5, minZoom: 2 });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(this.map);
    },

    getCurvedLatLngs(lat1, lng1, lat2, lng2, numPoints = 48) {
        // TODO (TV4): Tính đường cong Bezier bậc 2 nối (lat1, lng1) -> (lat2, lng2)
        return [[lat1, lng1], [lat2, lng2]];
    },

    calculateBearing(lat1, lng1, lat2, lng2) {
        // TODO (TV4): Tính góc xoay đầu máy bay theo hướng bay
        return 0;
    },

    renderBaseGraph(vertices, edges, criteria = 'weight') {
        if (!this.map) return;
        this.stopPlaneAnimation();
        // TODO (TV4):
        // 1. Xóa layer cũ trên bản đồ
        // 2. Vẽ tất cả các cung đường bay (polyline) kèm tooltip hiển thị hãng, giá, giờ bay
        // 3. Vẽ tất cả các đỉnh sân bay (circleMarker) kèm nhãn mã IATA và popup tọa độ
    },

    renderStep(stepData, finalPath = [], isFinalStep = false) {
        if (!stepData) return;
        this.stopPlaneAnimation();
        // TODO (TV4):
        // 1. Làm mờ các cạnh chưa xét
        // 2. Tô màu các cạnh đang xét trong stepData.relaxed_edges (vàng: updated, đỏ: ignored)
        // 3. Tô màu các đỉnh theo trạng thái: trắng (chưa xét), vàng (hàng đợi), cam (đỉnh u), xanh (đã cố định S)
        // 4. Nếu isFinalStep === true: tô đậm tuyến đường finalPath và gọi startPlaneAnimation()
    },

    startPlaneAnimation(routeCoords) {
        // TODO (TV4): Tạo marker máy bay SVG và dùng requestAnimationFrame di chuyển dọc routeCoords
    },

    stopPlaneAnimation() {
        if (this.planeAnimFrame) cancelAnimationFrame(this.planeAnimFrame);
        if (this.planeMarker && this.map) this.map.removeLayer(this.planeMarker);
        this.planeAnimFrame = null;
        this.planeMarker = null;
    }
};