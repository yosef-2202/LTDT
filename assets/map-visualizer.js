/**
 * =====================================================================
 * THÀNH VIÊN 4: TRỰC QUAN HÓA BẢN ĐỒ LEAFLET & ANIMATION MÁY BAY
 * File: assets/map-visualizer.js
 * =====================================================================
 */
window.GraphMapVisualizer = (function () {
    'use strict';

    // -----------------------------------------------------------------
    // COLOR PALETTE
    // -----------------------------------------------------------------
    const COLORS = {
        edgeDefault:   '#94a3b8',
        edgeDim:       '#e2e8f0',
        edgeIgnored:   '#ef4444',
        edgeUpdated:   '#f59e0b',
        edgePath:      '#10b981',
        vertexDefault: '#64748b',
        vertexFrontier:'#f59e0b',
        vertexU:       '#f97316',
        vertexVisited: '#10b981',
        vertexStart:   '#3b82f6',
        vertexTarget:  '#8b5cf6',
        vertexPath:    '#059669'
    };

    // -----------------------------------------------------------------
    // STATE
    // -----------------------------------------------------------------
    const state = {
        map: null,
        edgeLayer: null,
        vertexLayer: null,
        pathLayer: null,
        planeLayer: null,

        vertexMarkers: {},
        edgePolylines: [],
        verticesMap: {},
        edgesList: [],
        criteria: 'weight',

        // ===== Step history =====
        steps: [],
        stepIndex: -1,
        finalPath: [],
        isFinalStep: false,
        startCode: null,
        targetCode: null,

        // ===== Airplane =====
        planeMarker: null,
        planeAnimFrame: null,
        planePaused: true,
        planeProgress: 0,
        planeCoords: [],
        planeLastTs: 0,
        planeBaseDuration: 0, 
        animSpeed: 1,
        planeActive: false,

        curvedCache: new Map(),
        initialized: false
    };

    // =================================================================
    // A. MAP INITIALIZATION
    // =================================================================
    function initMap(containerId) {
        if (state.initialized) return;
        const el = document.getElementById(containerId);
        if (!el) { console.error('[Map] container not found:', containerId); return; }

        state.map = L.map(containerId, {
            center: [20.0, 100.0],
            zoom: 3, minZoom: 2, maxZoom: 12,
            worldCopyJump: true, zoomControl: true
        });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors', maxZoom: 19
        }).addTo(state.map);

        state.edgeLayer   = L.layerGroup().addTo(state.map);
        state.vertexLayer = L.layerGroup().addTo(state.map);
        state.pathLayer   = L.layerGroup().addTo(state.map);
        state.planeLayer  = L.layerGroup().addTo(state.map);

        state.initialized = true;
    }

    // =================================================================
    // B. BEZIER & BEARING
    // =================================================================
    function getCurvedLatLngs(lat1, lng1, lat2, lng2, numPoints = 40) {
        const key = `${lat1.toFixed(4)},${lng1.toFixed(4)},${lat2.toFixed(4)},${lng2.toFixed(4)}`;
        if (state.curvedCache.has(key)) return state.curvedCache.get(key);

        const midLat = (lat1 + lat2) / 2;
        const midLng = (lng1 + lng2) / 2;
        const dLat = lat2 - lat1;
        const dLng = lng2 - lng1;
        const dist = Math.sqrt(dLat*dLat + dLng*dLng) || 1e-9;
        const curvature = 0.18;
        const ctrlLat = midLat - (dLng/dist)*dist*curvature;
        const ctrlLng = midLng + (dLat/dist)*dist*curvature;

        const pts = [];
        for (let i = 0; i <= numPoints; i++) {
            const t = i / numPoints, u = 1 - t;
            pts.push([
                u*u*lat1 + 2*u*t*ctrlLat + t*t*lat2,
                u*u*lng1 + 2*u*t*ctrlLng + t*t*lng2
            ]);
        }
        state.curvedCache.set(key, pts);
        return pts;
    }

    function calculateBearing(lat1, lng1, lat2, lng2) {
        const φ1 = lat1*Math.PI/180, φ2 = lat2*Math.PI/180;
        const Δλ = (lng2-lng1)*Math.PI/180;
        const y = Math.sin(Δλ)*Math.cos(φ2);
        const x = Math.cos(φ1)*Math.sin(φ2) - Math.sin(φ1)*Math.cos(φ2)*Math.cos(Δλ);
        return (Math.atan2(y,x)*180/Math.PI + 360) % 360;
    }

    // =================================================================
    // C. BASE GRAPH
    // =================================================================
    function renderBaseGraph(vertices, edges, criteria = 'weight') {
        if (!state.map) return;

        resetVisualizationState();

        state.verticesMap = {};
        state.edgesList = edges || [];
        state.criteria = criteria;
        state.curvedCache.clear();

        state.edgeLayer.clearLayers();
        state.vertexLayer.clearLayers();
        state.pathLayer.clearLayers();
        state.planeLayer.clearLayers();

        if (!Array.isArray(vertices) || vertices.length === 0) return;
        vertices.forEach(v => { state.verticesMap[v.code] = v; });

        // ---- EDGES ----
        const grouped = {};
        edges.forEach(e => {
            if (!state.verticesMap[e.source] || !state.verticesMap[e.target]) return;
            const key = `${e.source}->${e.target}`;
            (grouped[key] = grouped[key] || []).push(e);
        });

        Object.values(grouped).forEach(group => {
            const rep = group.reduce((b,c) => {
                const bw = criteria==='duration' ? b.duration : b.weight;
                const cw = criteria==='duration' ? c.duration : c.weight;
                return cw<bw ? c : b;
            });
            const a = state.verticesMap[rep.source];
            const b = state.verticesMap[rep.target];
            const pts = getCurvedLatLngs(a.lat, a.lng, b.lat, b.lng, 40);

            const line = L.polyline(pts, {
                color: COLORS.edgeDefault, weight: 2.5, opacity: 0.5, dashArray: '6 4'
            }).addTo(state.edgeLayer);

            const unit = criteria==='duration' ? 'h' : '$';
            const w = criteria==='duration' ? rep.duration : rep.weight;
            const extra = group.length>1 ? `<br><i>+${group.length-1} chuyến</i>` : '';
            line.bindTooltip(
                `<b>${rep.flight_no}</b> · ${rep.airline}<br>${rep.source} ➔ ${rep.target}<br>` +
                `<b style="color:#f59e0b">${w}${unit}</b>${extra}`,
                { sticky: true, className: 'graph-tooltip' }
            );

            state.edgePolylines.push({
                line,
                meta: { from: rep.source, to: rep.target, weight: w, edgeId: rep.id, flightNo: rep.flight_no }
            });
        });

        // ---- VERTICES ----
        vertices.forEach(v => {
            const marker = L.circleMarker([v.lat, v.lng], {
                radius: 9, color: '#1e293b', weight: 2,
                fillColor: COLORS.vertexDefault, fillOpacity: 0.9
            }).addTo(state.vertexLayer);

            marker.bindTooltip(`<b>${v.code}</b> · ${v.city}`,
                { direction: 'top', offset: [0,-10], className: 'graph-tooltip' });
            marker.bindPopup(
                `<div style="font-family:system-ui;min-width:180px">
                    <div style="font-weight:700;font-size:14px;color:#0f766e">${v.code} — ${v.name}</div>
                    <div style="font-size:12px;color:#64748b;margin:4px 0">${v.city}, ${v.country}</div>
                    <div style="font-size:11px;color:#475569;border-top:1px solid #e2e8f0;padding-top:6px">
                        📍 ${Number(v.lat).toFixed(4)}, ${Number(v.lng).toFixed(4)}
                    </div></div>`
            );
            marker._vcode = v.code;
            state.vertexMarkers[v.code] = marker;
        });

        const bounds = L.latLngBounds(vertices.map(v => [v.lat, v.lng]));
        state.map.fitBounds(bounds.pad(0.25), { maxZoom: 6 });
    }

    // =================================================================
    // D. STEP MANAGEMENT
    // =================================================================
    function loadSteps(steps, finalPath, startCode, targetCode) {
        stopPlaneAnimation();
        state.steps = Array.isArray(steps) ? steps.slice() : [];
        state.finalPath = Array.isArray(finalPath) ? finalPath.slice() : [];
        state.startCode = startCode || null;
        state.targetCode = targetCode || null;
        state.stepIndex = -1;
        state.isFinalStep = false;
    }

    function goToStep(idx) {
        if (!state.steps.length) return;
        idx = Math.max(0, Math.min(idx, state.steps.length - 1));
        state.stepIndex = idx;
        state.isFinalStep = (idx === state.steps.length - 1);
        applyStepVisual(state.steps[idx], state.finalPath, state.isFinalStep);
    }

    function nextStep() {
        if (!state.steps.length) return false;
        if (state.stepIndex < state.steps.length - 1) {
            goToStep(state.stepIndex + 1);
            return true;
        }
        return false;
    }

    function previousStep() {
        if (!state.steps.length) return false;
        if (state.stepIndex > 0) {
            goToStep(state.stepIndex - 1);
            return true;
        }
        return false;
    }

    function getStepIndex() { return state.stepIndex; }
    function getStepCount() { return state.steps.length; }

    // Hàm cũ — giữ tương thích với index.php hiện tại
    function renderStep(stepData, finalPath = [], isFinalStep = false) {
        if (!stepData) return;
        state.finalPath = Array.isArray(finalPath) ? finalPath : [];
        state.isFinalStep = !!isFinalStep;
        const foundIdx = state.steps.findIndex(s => s.step === stepData.step);
        if (foundIdx >= 0) state.stepIndex = foundIdx;
        applyStepVisual(stepData, finalPath, isFinalStep);
    }

    // =================================================================
    // E. STEP VISUALIZATION
    // =================================================================
    function applyStepVisual(stepData, finalPath, isFinalStep) {
        if (!stepData || !state.map) return;
        stopPlaneAnimation();

        const labels     = stepData.labels || {};
        const relaxed    = stepData.relaxed_edges || [];
        const pq         = stepData.priority_queue || [];
        const visitedSet = new Set(stepData.visited_set || []);
        const pqSet      = new Set(pq.map(p => p.vertex));
        const currentU   = stepData.current_u;

        let startV = state.startCode;
        if (!startV) {
            Object.entries(labels).forEach(([code, info]) => {
                if (info.dist === 0 && info.prev === '-') startV = code;
            });
        }

        // 1. Reset edges
        state.edgePolylines.forEach(({ line }) => {
            line.setStyle({
                color: COLORS.edgeDim, weight: 2, opacity: 0.3, dashArray: '6 4'
            });
        });

        // 2. Highlight relaxed edges
        relaxed.forEach(r => {
            const item = findEdgeLine(r.from, r.to);
            if (!item) return;
            const isUpdated = r.status === 'updated';
            item.line.setStyle({
                color: isUpdated ? COLORS.edgeUpdated : COLORS.edgeIgnored,
                weight: isUpdated ? 4.5 : 2.5,
                opacity: isUpdated ? 0.95 : 0.65,
                dashArray: isUpdated ? null : '4 4'
            });
            if (isUpdated) {
                item.line.bringToFront();
                const oldTxt = r.old_dist === '∞' ? '∞' : r.old_dist;
                item.line.bindTooltip(
                    `<b>${r.from} ➔ ${r.to}</b><br>w = ${r.weight}<br>` +
                    `<span style="color:#dc2626">${oldTxt}</span> → ` +
                    `<span style="color:#16a34a">${r.new_dist}</span>`,
                    { sticky: true, className: 'graph-tooltip' }
                );
            }
        });

        // 3. Color vertices
        Object.entries(state.vertexMarkers).forEach(([code, marker]) => {
            let fill = COLORS.vertexDefault, radius = 9;
            if (visitedSet.has(code)) fill = COLORS.vertexVisited;
            if (pqSet.has(code))      fill = COLORS.vertexFrontier;
            if (code === currentU)  { fill = COLORS.vertexU; radius = 12; }
            if (code === startV)      fill = COLORS.vertexStart;
            if (code === state.targetCode) fill = COLORS.vertexTarget;

            if (isFinalStep && finalPath.includes(code)) {
                fill = COLORS.vertexPath; radius = 11;
            }
            marker.setStyle({ fillColor: fill, fillOpacity: 0.95, radius });
        });

        // 4. Final path + animation
        if (isFinalStep && finalPath.length >= 2) {
            renderPath(finalPath);
            setTimeout(() => animatePlane(finalPath), 300);
        }
    }

    // =================================================================
    // F. PATH
    // =================================================================
    function renderPath(path) {
        if (!Array.isArray(path) || path.length < 2) return;
        state.pathLayer.clearLayers();

        for (let i = 0; i < path.length - 1; i++) {
            const a = state.verticesMap[path[i]];
            const b = state.verticesMap[path[i+1]];
            if (!a || !b) continue;
            const pts = getCurvedLatLngs(a.lat, a.lng, b.lat, b.lng, 60);
            L.polyline(pts, {
                color: COLORS.edgePath, weight: 6, opacity: 0.95, lineCap: 'round'
            }).addTo(state.pathLayer).bringToFront();

            const base = findEdgeLine(path[i], path[i+1]);
            if (base) {
                base.line.setStyle({ color: COLORS.edgePath, weight: 5, opacity: 1, dashArray: null });
                base.line.bringToFront();
            }
        }
        path.forEach(code => {
            const m = state.vertexMarkers[code];
            if (m) m.setStyle({
                fillColor: COLORS.vertexPath, radius: 11, fillOpacity: 1,
                color: '#064e3b', weight: 2.5
            });
        });
    }

    // =================================================================
    // G. AIRPLANE
    // =================================================================
    function buildPlaneIcon() {
        return L.divIcon({
            className: 'plane-icon',
            html: `<div class="plane-svg-wrap">
                <svg viewBox="0 0 24 24" width="34" height="34">
                    <path d="M12 1.5 L14.2 8.8 L22 11 L14.2 13.2 L12 22.5 L9.8 13.2 L2 11 L9.8 8.8 Z"
                          fill="#f97316" stroke="#7c2d12" stroke-width="0.8" stroke-linejoin="round"/>
                    <circle cx="12" cy="11" r="1.2" fill="#fff"/>
                </svg></div>`,
            iconSize: [34, 34], iconAnchor: [17, 17]
        });
    }

    function animatePlane(path) {
        if (!Array.isArray(path) || path.length < 2) return;
        stopPlaneAnimation();

        const coords = [];
        for (let i = 0; i < path.length - 1; i++) {
            const a = state.verticesMap[path[i]];
            const b = state.verticesMap[path[i+1]];
            if (!a || !b) continue;
            const seg = getCurvedLatLngs(a.lat, a.lng, b.lat, b.lng, 40);
            seg.forEach((pt, idx) => { if (i === 0 || idx > 0) coords.push(pt); });
        }
        if (coords.length < 2) return;

        state.planeCoords = coords;
        state.planeProgress = 0;
        state.planePaused = false;
        state.planeActive = true;
        state.planeLastTs = 0;

        const hops = path.length - 1;
        state.planeBaseDuration = (hops * 900) / state.animSpeed;

        state.planeMarker = L.marker(coords[0], {
            icon: buildPlaneIcon(), zIndexOffset: 2000, interactive: false
        }).addTo(state.planeLayer);

        state.planeAnimFrame = requestAnimationFrame(planeFrame);
    }

    function planeFrame(ts) {
        if (!state.planeMarker) return;
        if (!state.planeLastTs) state.planeLastTs = ts;

        if (!state.planePaused) {
            const dt = ts - state.planeLastTs;
            state.planeProgress += dt / Math.max(1, state.planeBaseDuration);
            if (state.planeProgress >= 1) state.planeProgress = 1;
        }
        state.planeLastTs = ts;

        const coords = state.planeCoords;
        const maxIdx = coords.length - 1;
        const pos = state.planeProgress * maxIdx;
        const idx = Math.min(Math.floor(pos), maxIdx - 1);
        const frac = pos - idx;
        const a = coords[idx];
        const b = coords[idx + 1] || a;

        const lat = a[0] + (b[0] - a[0]) * frac;
        const lng = a[1] + (b[1] - a[1]) * frac;
        state.planeMarker.setLatLng([lat, lng]);

        const bearing = calculateBearing(a[0], a[1], b[0], b[1]);
        const el = state.planeMarker.getElement();
        if (el) {
            const wrap = el.querySelector('.plane-svg-wrap');
            if (wrap) wrap.style.transform = `rotate(${bearing}deg)`;
        }

        if (state.planeProgress < 1) {
            state.planeAnimFrame = requestAnimationFrame(planeFrame);
        } else {
            state.planeAnimFrame = null;
            state.planeActive = false;
        }
    }

    function pauseAnimation() {
        if (!state.planeMarker) return;
        state.planePaused = true;
    }

    function playAnimation() {
        if (!state.planeMarker) return;
        state.planePaused = false;
        state.planeLastTs = 0; 
        if (!state.planeAnimFrame) {
            state.planeAnimFrame = requestAnimationFrame(planeFrame);
        }
    }

    function setAnimationSpeed(mult) {
        const newSpeed = Math.max(0.25, Math.min(4, Number(mult) || 1));
        if (!state.planeMarker) { state.animSpeed = newSpeed; return; }
        if (newSpeed === state.animSpeed) return;

        const remainFrac = 1 - state.planeProgress;
        const remainMsOld = state.planeBaseDuration * remainFrac;
        const remainMsNew = remainMsOld * (state.animSpeed / newSpeed);

        if (remainFrac > 0.001) {
            state.planeBaseDuration = remainMsNew / remainFrac;
        }
        state.animSpeed = newSpeed;
    }

    function isPlaneActive() { return state.planeActive; }
    function isPlanePaused() { return state.planePaused; }

    function stopPlaneAnimation() {
        if (state.planeAnimFrame) {
            cancelAnimationFrame(state.planeAnimFrame);
            state.planeAnimFrame = null;
        }
        if (state.planeMarker) {
            state.planeLayer.removeLayer(state.planeMarker);
            state.planeMarker = null;
        }
        state.planePaused = false;
        state.planeProgress = 0;
        state.planeActive = false;
        state.planeLastTs = 0;
    }

    // =================================================================
    // H. RESET
    // =================================================================
    function resetVisualizationState() {
        stopPlaneAnimation();
        state.steps = [];
        state.stepIndex = -1;
        state.finalPath = [];
        state.isFinalStep = false;
        state.startCode = null;
        state.targetCode = null;
        state.curvedCache.clear();
    }

    function resetVisualization() {
        resetVisualizationState();
        if (state.pathLayer) state.pathLayer.clearLayers();

        if (Object.keys(state.verticesMap).length > 0) {
            renderBaseGraph(
                Object.values(state.verticesMap),
                state.edgesList,
                state.criteria
            );
        }
    }

    // =================================================================
    // I. HELPERS
    // =================================================================
    function findEdgeLine(from, to) {
        return state.edgePolylines.find(e => e.meta.from === from && e.meta.to === to);
    }

    // =================================================================
    // PUBLIC API
    // =================================================================
    return {
        initMap,
        renderBaseGraph,
        renderStep,
        stopPlaneAnimation,
        loadSteps,
        goToStep,
        nextStep,
        previousStep,
        getStepIndex,
        getStepCount,
        renderPath,
        animatePlane,
        playAnimation,
        pauseAnimation,
        setAnimationSpeed,
        isPlaneActive,
        isPlanePaused,
        resetVisualization,
        getMap: () => state.map,
        getState: () => state
    };
})();
