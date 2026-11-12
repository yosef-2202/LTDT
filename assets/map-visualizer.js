/**
 * ============================================================================
 * FILE DÀNH CHO THÀNH VIÊN 4: BẢN ĐỒ DU LỊCH SÁNG MÀU & ANIMATION MÁY BAY
 * ============================================================================
 * Đã chuyển sang nền bản đồ CartoDB Voyager / OSM Light tươi sáng, đồng bộ
 * hoàn toàn với tone màu Teal (#0d9488) của giao diện Du lịch - Vé máy bay.
 * ============================================================================
 */

window.GraphMapVisualizer = {
    map: null,
    vertexMarkers: {},
    edgePolylines: [],
    verticesMap: {},
    planeMarker: null,
    planeAnimFrame: null,

    initMap(containerId) {
        this.map = L.map(containerId, {
            center: [24.0, 70.0],
            zoom: 2.5,
            minZoom: 2,
            maxZoom: 10,
            worldCopyJump: true
        });

        // Bản đồ sáng màu Voyager / OpenStreetMap tươi tắn chuẩn trang Web Du lịch
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors | SkyTravel Booking Engine',
            maxZoom: 18,
            className: 'light-travel-tiles'
        }).addTo(this.map);
    },

    getCurvedLatLngs(lat1, lng1, lat2, lng2, numPoints = 48) {
        const latlngs = [];
        const midLat = (lat1 + lat2) / 2;
        const midLng = (lng1 + lng2) / 2;

        const dx = lng2 - lng1;
        const dy = lat2 - lat1;

        const offsetScale = 0.16;
        const ctrlLat = midLat + dx * offsetScale;
        const ctrlLng = midLng - dy * offsetScale;

        for (let i = 0; i <= numPoints; i++) {
            const t = i / numPoints;
            const lat = (1 - t) * (1 - t) * lat1 + 2 * (1 - t) * t * ctrlLat + t * t * lat2;
            const lng = (1 - t) * (1 - t) * lng1 + 2 * (1 - t) * t * ctrlLng + t * t * lng2;
            latlngs.push([lat, lng]);
        }
        return latlngs;
    },

    calculateBearing(lat1, lng1, lat2, lng2) {
        const dLng = (lng2 - lng1);
        const dLat = (lat2 - lat1);
        return Math.atan2(dLng, dLat) * (180 / Math.PI);
    },

    renderBaseGraph(vertices, edges, criteria = 'weight') {
        if (!this.map) return;

        this.stopPlaneAnimation();

        Object.values(this.vertexMarkers).forEach(m => this.map.removeLayer(m));
        this.edgePolylines.forEach(item => this.map.removeLayer(item.line));
        this.vertexMarkers = {};
        this.edgePolylines = [];
        this.verticesMap = {};

        vertices.forEach(v => {
            this.verticesMap[v.code] = v;
        });

        // 1. Vẽ các Cung đường bay cong
        edges.forEach(e => {
            const u = this.verticesMap[e.source];
            const v = this.verticesMap[e.target];
            if (!u || !v) return;

            const curvePoints = this.getCurvedLatLngs(parseFloat(u.lat), parseFloat(u.lng), parseFloat(v.lat), parseFloat(v.lng));

            const line = L.polyline(curvePoints, {
                color: '#0d9488',
                weight: 2,
                opacity: 0.45,
                dashArray: '5, 6'
            }).addTo(this.map);

            line.bindTooltip(
                `<div class="px-1.5 py-0.5 text-slate-700">
                    <div class="text-xs font-extrabold text-teal-600">${e.flight_no || 'SG-101'} • ${e.airline}</div>
                    <div class="text-xs font-bold">${e.source} ➔ ${e.target}</div>
                    <div class="text-xs">Giá vé: <b class="text-rose-600">$${e.weight}</b> | Bay: <b>${e.duration}h</b></div>
                 </div>`,
                { sticky: true }
            );

            this.edgePolylines.push({
                id: e.id,
                source: e.source,
                target: e.target,
                weight: e.weight,
                curvePoints: curvePoints,
                line: line
            });
        });

        // 2. Vẽ các Đỉnh Sân bay
        vertices.forEach(v => {
            const marker = L.circleMarker([v.lat, v.lng], {
                radius: 8,
                fillColor: '#ffffff',
                color: '#0d9488',
                weight: 3,
                fillOpacity: 1
            }).addTo(this.map);

            marker.bindTooltip(
                `<span class="font-bold font-mono text-[11px] tracking-wider">${v.code}</span>`,
                { permanent: true, direction: 'top', offset: [0, -9], className: 'vertex-label-light' }
            );

            marker.bindPopup(`
                <div class="text-slate-800 p-1 min-w-[160px]">
                    <div class="text-[10px] font-bold text-teal-600 uppercase tracking-wider">${v.country}</div>
                    <div class="font-extrabold text-sm text-slate-900">${v.code} - ${v.name}</div>
                    <div class="text-xs text-slate-600">${v.city}</div>
                    <div class="text-[11px] font-mono text-slate-400 mt-1">Tọa độ: ${v.lat}, ${v.lng}</div>
                </div>
            `);

            this.vertexMarkers[v.code] = marker;
        });
    },

    renderStep(stepData, finalPath = [], isFinalStep = false) {
        if (!stepData) return;

        this.stopPlaneAnimation();

        this.edgePolylines.forEach(item => {
            item.line.setStyle({
                color: '#94a3b8',
                weight: 1.6,
                opacity: 0.35,
                dashArray: '5, 6'
            });
        });

        // Tô màu các cung đang xét (Relaxed Edges)
        (stepData.relaxed_edges || []).forEach(rel => {
            this.edgePolylines.forEach(item => {
                if (item.source === rel.from && item.target === rel.to) {
                    item.line.setStyle({
                        color: rel.status === 'updated' ? '#f59e0b' : '#ef4444',
                        weight: 4,
                        opacity: 0.95,
                        dashArray: null
                    });
                    item.line.bringToFront();
                }
            });
        });

        // Bước cuối: Tô nổi bật lộ trình + Cho máy bay bay
        if (isFinalStep && finalPath.length > 1) {
            const fullRouteCoordinates = [];

            for (let i = 0; i < finalPath.length - 1; i++) {
                const u = finalPath[i];
                const v = finalPath[i + 1];
                this.edgePolylines.forEach(item => {
                    if (item.source === u && item.target === v) {
                        item.line.setStyle({
                            color: '#0d9488',
                            weight: 5,
                            opacity: 1,
                            dashArray: null
                        });
                        item.line.bringToFront();
                        fullRouteCoordinates.push(...item.curvePoints);
                    }
                });
            }

            if (fullRouteCoordinates.length > 1) {
                this.startPlaneAnimation(fullRouteCoordinates);
            }
        }

        // Tô màu các Đỉnh theo trạng thái
        Object.keys(this.vertexMarkers).forEach(code => {
            const marker = this.vertexMarkers[code];
            const labelInfo = stepData.labels ? stepData.labels[code] : null;
            if (!labelInfo) return;

            let fillColor = '#ffffff';
            let strokeColor = '#64748b';
            let radius = 8;

            if (isFinalStep && finalPath.includes(code)) {
                fillColor = '#0d9488';
                strokeColor = '#ffffff';
                radius = 11;
            } else if (labelInfo.is_u) {
                fillColor = '#f97316';
                strokeColor = '#ffffff';
                radius = 13;
            } else if (labelInfo.closed) {
                fillColor = '#10b981';
                strokeColor = '#ffffff';
                radius = 9.5;
            } else if (labelInfo.dist !== '∞') {
                fillColor = '#eab308';
                strokeColor = '#ffffff';
                radius = 9.5;
            }

            marker.setStyle({
                fillColor: fillColor,
                color: strokeColor,
                radius: radius,
                weight: 3
            });
            marker.bringToFront();
        });
    },

    startPlaneAnimation(routeCoords) {
        this.stopPlaneAnimation();
        if (!routeCoords || routeCoords.length < 2) return;

        const planeIcon = L.divIcon({
            className: 'airplane-live-marker',
            html: `<div id="animated-jet-wrapper" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;filter:drop-shadow(0 4px 8px rgba(13,148,136,0.65));transition:transform 0.05s linear;">
                      <svg viewBox="0 0 24 24" width="34" height="34" fill="#0d9488" stroke="#ffffff" stroke-width="1.5">
                          <path d="M21 16v-2l-8-5V3.5c0-.83-.67-1.5-1.5-1.5S10 2.67 10 3.5V9l-8 5v2l8-2.5V19l-2 1.5V22l3.5-1 3.5 1v-1.5L13 19v-5.5l8 2.5z"/>
                      </svg>
                   </div>`,
            iconSize: [40, 40],
            iconAnchor: [20, 20]
        });

        this.planeMarker = L.marker(routeCoords[0], {
            icon: planeIcon,
            zIndexOffset: 2000
        }).addTo(this.map);

        let cursor = 0;
        const speed = 0.35;

        const animateFrame = () => {
            const idx = Math.floor(cursor);
            const nextIdx = Math.min(idx + 1, routeCoords.length - 1);
            const ratio = cursor - idx;

            const p1 = routeCoords[idx];
            const p2 = routeCoords[nextIdx];

            const currentLat = p1[0] + (p2[0] - p1[0]) * ratio;
            const currentLng = p1[1] + (p2[1] - p1[1]) * ratio;

            this.planeMarker.setLatLng([currentLat, currentLng]);

            if (p1[0] !== p2[0] || p1[1] !== p2[1]) {
                const angle = this.calculateBearing(p1[0], p1[1], p2[0], p2[1]);
                const jetEl = document.getElementById('animated-jet-wrapper');
                if (jetEl) {
                    jetEl.style.transform = `rotate(${angle}deg)`;
                }
            }

            cursor += speed;
            if (cursor >= routeCoords.length - 1) {
                cursor = 0;
            }

            this.planeAnimFrame = requestAnimationFrame(animateFrame);
        };

        this.planeAnimFrame = requestAnimationFrame(animateFrame);
    },

    stopPlaneAnimation() {
        if (this.planeAnimFrame) {
            cancelAnimationFrame(this.planeAnimFrame);
            this.planeAnimFrame = null;
        }
        if (this.planeMarker && this.map) {
            this.map.removeLayer(this.planeMarker);
            this.planeMarker = null;
        }
    }
};