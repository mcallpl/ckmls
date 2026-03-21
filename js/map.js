/* ============================================================
   MLS Property Search — Google Maps Integration
   Draggable radius circle + polygon draw-to-filter
   ============================================================ */

let googleMap       = null;
let infoWindow      = null;
let propertyMarkers = [];
let centerMarkerObj = null;
let radiusCircle    = null;

// Spatial mode state
let spatialMode     = 'radius';   // 'radius' | 'polygon'
let drawingActive   = false;
let polyVertices    = [];
let drawPolyline    = null;
let drawPolygon     = null;
let vertexMarkers   = [];
let mapOverlay      = null;       // for pixel projection
let radiusDragTimer = null;
let serverRadiusMiles = 0.125;    // radius used in the last server query
let pendingDragRadius = null;     // if set, initMap uses this instead of dropdown

// ── Spatial filter entry point ─────────────────────────────
function filterByCurrentSpatialMode() {
    if (spatialMode === 'radius' && radiusCircle) {
        var radiusMeters = radiusCircle.getRadius();
        var center = radiusCircle.getCenter();
        window.spatialFilter = function(prop) {
            var lat = parseFloat(prop.Latitude);
            var lng = parseFloat(prop.Longitude);
            if (!lat || !lng) return false;
            var dist = google.maps.geometry.spherical.computeDistanceBetween(
                center, new google.maps.LatLng(lat, lng)
            );
            return dist <= radiusMeters;
        };
    } else if (spatialMode === 'polygon' && drawPolygon) {
        window.spatialFilter = function(prop) {
            var lat = parseFloat(prop.Latitude);
            var lng = parseFloat(prop.Longitude);
            if (!lat || !lng) return false;
            return google.maps.geometry.poly.containsLocation(
                new google.maps.LatLng(lat, lng), drawPolygon
            );
        };
    } else {
        window.spatialFilter = null;
    }
    if (typeof applyFiltersAndRender === 'function') applyFiltersAndRender();
}

// ── Radius dropdown sync ───────────────────────────────────
function updateRadiusDropdownDisplay(miles) {
    var sel = document.getElementById('rSel');
    if (!sel) return;
    // Try to snap to nearest preset
    var best = null, bestDiff = Infinity;
    for (var i = 0; i < sel.options.length; i++) {
        if (sel.options[i].value === '_custom') continue;
        var val = parseFloat(sel.options[i].value);
        var diff = Math.abs(val - miles);
        if (diff < bestDiff) { bestDiff = diff; best = i; }
    }
    // If within 15% of a preset, snap to it
    if (best !== null && bestDiff / parseFloat(sel.options[best].value) < 0.15) {
        sel.selectedIndex = best;
        removeCustomOption(sel);
    } else {
        // Add/update a custom option
        var customOpt = sel.querySelector('option[value="_custom"]');
        var label = miles < 0.1 ? Math.round(miles * 5280) + ' ft' : miles.toFixed(2) + ' mi';
        if (!customOpt) {
            customOpt = document.createElement('option');
            customOpt.value = '_custom';
            sel.appendChild(customOpt);
        }
        customOpt.textContent = '~' + label;
        customOpt.selected = true;
    }
    // Update hidden form field
    var hR = document.getElementById('hR');
    if (hR) hR.value = miles.toFixed(4);
}

function removeCustomOption(sel) {
    var c = sel.querySelector('option[value="_custom"]');
    if (c) c.remove();
}

// ── Polygon drawing ────────────────────────────────────────
function startDrawing() {
    if (!googleMap) return;
    drawingActive = true;
    spatialMode = 'radius'; // still radius until polygon closes
    polyVertices = [];

    // Hide radius circle
    if (radiusCircle) radiusCircle.setVisible(false);

    // Clear any existing polygon
    if (drawPolygon) { drawPolygon.setMap(null); drawPolygon = null; }
    if (drawPolyline) { drawPolyline.setMap(null); drawPolyline = null; }
    vertexMarkers.forEach(function(m) { m.setMap(null); });
    vertexMarkers = [];

    googleMap.setOptions({ draggableCursor: 'crosshair' });

    var drawBtn = document.getElementById('btn-draw-poly');
    var clearBtn = document.getElementById('btn-clear-poly');
    if (drawBtn) { drawBtn.textContent = 'Drawing...'; drawBtn.classList.add('active'); }
    if (clearBtn) clearBtn.style.display = 'none';

    // Create polyline for showing edges while drawing
    drawPolyline = new google.maps.Polyline({
        map: googleMap,
        path: [],
        strokeColor: '#b07fff',
        strokeOpacity: 0.8,
        strokeWeight: 2,
    });
}

function addVertex(latLng) {
    polyVertices.push(latLng);
    drawPolyline.setPath(polyVertices);

    var isFirst = polyVertices.length === 1;
    var marker = new google.maps.Marker({
        position: latLng,
        map: googleMap,
        icon: {
            path: google.maps.SymbolPath.CIRCLE,
            fillColor: isFirst ? '#b07fff' : '#fff',
            fillOpacity: 1,
            strokeColor: '#b07fff',
            strokeWeight: 2,
            scale: isFirst ? 8 : 5,
        },
        zIndex: 2000,
        clickable: isFirst && polyVertices.length >= 3,
    });
    if (isFirst) {
        marker.addListener('click', function() {
            if (polyVertices.length >= 3) closePolygon();
        });
        marker.setTitle('Click to close polygon');
    }
    vertexMarkers.push(marker);

    // Make first marker clickable once we have 3+ vertices
    if (polyVertices.length === 3 && vertexMarkers[0]) {
        vertexMarkers[0].setClickable(true);
    }
}

function closePolygon() {
    drawingActive = false;
    googleMap.setOptions({ draggableCursor: null });

    // Remove polyline and vertex markers
    if (drawPolyline) { drawPolyline.setMap(null); drawPolyline = null; }
    vertexMarkers.forEach(function(m) { m.setMap(null); });
    vertexMarkers = [];

    // Create the polygon
    drawPolygon = new google.maps.Polygon({
        paths: polyVertices,
        map: googleMap,
        fillColor: '#b07fff',
        fillOpacity: 0.12,
        strokeColor: '#b07fff',
        strokeOpacity: 0.7,
        strokeWeight: 2,
        editable: true,
        draggable: false,
    });

    // Re-filter when user edits polygon vertices
    google.maps.event.addListener(drawPolygon.getPath(), 'set_at', function() { filterByCurrentSpatialMode(); });
    google.maps.event.addListener(drawPolygon.getPath(), 'insert_at', function() { filterByCurrentSpatialMode(); });

    spatialMode = 'polygon';
    var drawBtn = document.getElementById('btn-draw-poly');
    var clearBtn = document.getElementById('btn-clear-poly');
    if (drawBtn) { drawBtn.textContent = 'Redraw Area'; drawBtn.classList.remove('active'); }
    if (clearBtn) clearBtn.style.display = '';

    filterByCurrentSpatialMode();
}

function clearPolygon() {
    if (drawPolygon) { drawPolygon.setMap(null); drawPolygon = null; }
    if (drawPolyline) { drawPolyline.setMap(null); drawPolyline = null; }
    vertexMarkers.forEach(function(m) { m.setMap(null); });
    vertexMarkers = [];
    polyVertices = [];
    drawingActive = false;

    spatialMode = 'radius';
    window.spatialFilter = null;
    googleMap.setOptions({ draggableCursor: null });

    if (radiusCircle) radiusCircle.setVisible(true);

    var drawBtn = document.getElementById('btn-draw-poly');
    var clearBtn = document.getElementById('btn-clear-poly');
    if (drawBtn) { drawBtn.textContent = 'Draw Area'; drawBtn.classList.remove('active'); }
    if (clearBtn) clearBtn.style.display = 'none';

    if (typeof applyFiltersAndRender === 'function') applyFiltersAndRender();
}

// ── Map click handler (for polygon drawing) ────────────────
function onMapClick(e) {
    if (!drawingActive) return;

    // Check if clicking near the first vertex to close
    if (polyVertices.length >= 3 && mapOverlay && mapOverlay.getProjection()) {
        var firstPx = mapOverlay.getProjection().fromLatLngToContainerPixel(polyVertices[0]);
        var clickPx = mapOverlay.getProjection().fromLatLngToContainerPixel(e.latLng);
        var dist = Math.sqrt(Math.pow(firstPx.x - clickPx.x, 2) + Math.pow(firstPx.y - clickPx.y, 2));
        if (dist < 20) {
            closePolygon();
            return;
        }
    }

    addVertex(e.latLng);
}

// ── Map controls (HTML buttons in index.php, bind events once) ──
function bindMapControls() {
    var drawBtn = document.getElementById('btn-draw-poly');
    var clearBtn = document.getElementById('btn-clear-poly');
    if (!drawBtn || drawBtn._bound) return;
    drawBtn._bound = true;

    drawBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        if (drawingActive) return;
        startDrawing();
    });
    clearBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        clearPolygon();
    });
}

// ══════════════════════════════════════════════════════════
//  initMap — called by app.js after search results arrive
// ══════════════════════════════════════════════════════════
window.initMap = function(geocoded, properties) {
    if (!window._googleMapsReady) {
        window._pendingMapCall = function() { window.initMap(geocoded, properties); };
        return;
    }

    var center = { lat: geocoded.lat, lng: geocoded.lng };

    // Reset polygon state on new search
    if (drawPolygon) { drawPolygon.setMap(null); drawPolygon = null; }
    if (drawPolyline) { drawPolyline.setMap(null); drawPolyline = null; }
    vertexMarkers.forEach(function(m) { m.setMap(null); });
    vertexMarkers = [];
    polyVertices = [];
    drawingActive = false;
    spatialMode = 'radius';
    window.spatialFilter = null;

    if (!googleMap) {
        googleMap = new google.maps.Map(document.getElementById('map'), {
            center: center,
            zoom: 14,
            styles: DARK_MAP_STYLE,
            mapTypeControl: false,
            streetViewControl: true,
            fullscreenControl: true,
            zoomControlOptions: {
                position: google.maps.ControlPosition.RIGHT_CENTER
            },
        });
        infoWindow = new google.maps.InfoWindow();

        // Overlay for pixel projection (used by polygon close detection)
        mapOverlay = new google.maps.OverlayView();
        mapOverlay.draw = function() {};
        mapOverlay.setMap(googleMap);

        // Map click handler for polygon drawing
        googleMap.addListener('click', onMapClick);
    } else {
        googleMap.setCenter(center);
        googleMap.setZoom(14);
        clearMapMarkers();
    }

    // Bind draw controls (HTML buttons, idempotent)
    bindMapControls();

    // Reset draw button text
    var drawBtn = document.getElementById('btn-draw-poly');
    var clearBtn = document.getElementById('btn-clear-poly');
    if (drawBtn) { drawBtn.textContent = 'Draw Area'; drawBtn.classList.remove('active'); }
    if (clearBtn) clearBtn.style.display = 'none';

    // ── Center marker ──
    centerMarkerObj = new google.maps.Marker({
        position: center,
        map: googleMap,
        title: geocoded.display_name,
        icon: {
            path: google.maps.SymbolPath.CIRCLE,
            fillColor: '#f5c842',
            fillOpacity: 1,
            strokeColor: '#fff',
            strokeWeight: 2.5,
            scale: 12,
        },
        zIndex: 1000,
        animation: google.maps.Animation.DROP,
    });

    centerMarkerObj.addListener('click', function() {
        infoWindow.setContent(
            '<div style="font-family:\'DM Sans\',sans-serif;padding:8px;max-width:220px;color:#1a1a2e">' +
            '<div style="font-weight:700;font-size:.9rem;margin-bottom:4px">Searched Address</div>' +
            '<div style="font-size:.8rem;color:#555;line-height:1.4">' + geocoded.display_name + '</div>' +
            '</div>');
        infoWindow.open(googleMap, centerMarkerObj);
    });

    // ── Radius circle (editable) ──
    if (radiusCircle) radiusCircle.setMap(null);
    var radiusSel = document.getElementById('rSel');
    var radiusMiles;
    if (pendingDragRadius) {
        radiusMiles = pendingDragRadius;
        pendingDragRadius = null;
    } else {
        radiusMiles = radiusSel ? parseFloat(radiusSel.value) : 1.0;
    }
    serverRadiusMiles = radiusMiles;

    radiusCircle = new google.maps.Circle({
        map: googleMap,
        center: center,
        radius: radiusMiles * 1609.34,
        fillColor: '#5b7fff',
        fillOpacity: 0.08,
        strokeColor: '#5b7fff',
        strokeOpacity: 0.5,
        strokeWeight: 2,
        editable: true,
        clickable: false,
    });

    // Prevent center drag (keep circle anchored to searched address)
    var origCenter = radiusCircle.getCenter();
    radiusCircle.addListener('center_changed', function() {
        radiusCircle.setCenter(origCenter);
    });

    // Live filter as user drags the radius edge
    radiusCircle.addListener('radius_changed', function() {
        var newMiles = radiusCircle.getRadius() / 1609.34;
        updateRadiusDropdownDisplay(newMiles);

        // Debounce filtering
        clearTimeout(radiusDragTimer);
        radiusDragTimer = setTimeout(function() {
            // If user dragged larger than server data, re-fetch
            if (newMiles > serverRadiusMiles * 1.1) {
                pendingDragRadius = newMiles;
                var hR = document.getElementById('hR');
                if (hR) hR.value = newMiles.toFixed(4);
                var form = document.getElementById('searchForm');
                if (form) form.requestSubmit();
            } else {
                filterByCurrentSpatialMode();
            }
        }, 200);
    });

    // ── Property markers ──
    var bounds = new google.maps.LatLngBounds();
    bounds.extend(center);

    propertyMarkers = [];

    var statusColors = {
        'Active':                '#3ecf8e',
        'Coming Soon':           '#b07fff',
        'Active Under Contract': '#f5c842',
        'Pending':               '#ff9340',
        'Closed':                '#ff5c5c',
        'Canceled':              '#6b7080',
        'Expired':               '#6b7080',
    };

    properties.forEach(function(prop, idx) {
        var lat = parseFloat(prop.Latitude);
        var lng = parseFloat(prop.Longitude);
        if (!lat || !lng || isNaN(lat) || isNaN(lng)) return;

        var pos   = { lat: lat, lng: lng };
        var color = statusColors[prop.StandardStatus] || '#5b7fff';

        var marker = new google.maps.Marker({
            position: pos,
            map: googleMap,
            title: [prop.StreetNumber, prop.StreetName, prop.City].filter(Boolean).join(' '),
            label: {
                text: String(idx + 1),
                color: '#ffffff',
                fontSize: '11px',
                fontWeight: '700',
                fontFamily: 'Syne, sans-serif',
            },
            icon: {
                path: google.maps.SymbolPath.CIRCLE,
                fillColor: color,
                fillOpacity: 1,
                strokeColor: '#fff',
                strokeWeight: 2,
                scale: 14,
            },
            zIndex: 100 - idx,
        });

        marker._propIndex = idx;
        propertyMarkers.push(marker);
        bounds.extend(pos);

        marker.addListener('click', function() {
            var price = prop.StandardStatus === 'Closed'
                ? (prop.ClosePrice || prop.ListPrice)
                : prop.ListPrice;

            infoWindow.setContent(
                '<div style="font-family:\'DM Sans\',sans-serif;padding:8px 4px;max-width:240px;color:#1a1a2e">' +
                    '<div style="font-weight:700;font-size:.9rem;margin-bottom:2px">' +
                        [prop.StreetNumber, prop.StreetName].filter(Boolean).join(' ') +
                    '</div>' +
                    '<div style="font-size:.78rem;color:#555;margin-bottom:8px">' +
                        [prop.City, prop.StateOrProvince].filter(Boolean).join(', ') +
                    '</div>' +
                    '<div style="font-size:1.1rem;font-weight:800;color:#4f46e5;margin-bottom:6px">' +
                        (price ? '$' + Number(price).toLocaleString() : '—') +
                    '</div>' +
                    '<div style="font-size:.78rem;color:#444;display:flex;gap:10px;margin-bottom:8px">' +
                        '<span>🛏 ' + (prop.BedroomsTotal != null ? prop.BedroomsTotal : '—') + ' bd</span>' +
                        '<span>🛁 ' + (prop.BathroomsTotalInteger != null ? prop.BathroomsTotalInteger : '—') + ' ba</span>' +
                        '<span>📐 ' + (prop.LivingArea ? Number(prop.LivingArea).toLocaleString() : '—') + ' sqft</span>' +
                    '</div>' +
                    '<div style="display:inline-block;padding:2px 8px;border-radius:100px;font-size:.68rem;' +
                                'font-weight:700;background:' + color + '22;color:' + color + ';border:1px solid ' + color + '44">' +
                        (prop.StandardStatus || '') +
                    '</div>' +
                    '<div style="margin-top:10px">' +
                        '<a href="#" onclick="window.highlightCard(' + idx + ');return false;"' +
                           ' style="font-size:.78rem;color:#4f46e5;font-weight:600;text-decoration:none">' +
                            'View details ↓' +
                        '</a>' +
                    '</div>' +
                '</div>');

            infoWindow.open(googleMap, marker);
            window.highlightCard(idx);
        });
    });

    if (!bounds.isEmpty()) {
        googleMap.fitBounds(bounds, { top: 60, right: 40, bottom: 40, left: 40 });
        var listener = googleMap.addListener('idle', function() {
            if (googleMap.getZoom() > 16) googleMap.setZoom(16);
            google.maps.event.removeListener(listener);
        });
    }
};

// ── Highlight marker ───────────────────────────────────────
window.highlightMapMarker = function(idx) {
    propertyMarkers.forEach(function(m, i) {
        var icon = m.getIcon();
        m.setIcon(Object.assign({}, icon, { scale: i === idx ? 18 : 14, strokeWeight: i === idx ? 3 : 2 }));
        m.setZIndex(i === idx ? 999 : 100 - i);
    });

    if (propertyMarkers[idx]) {
        googleMap.panTo(propertyMarkers[idx].getPosition());
        google.maps.event.trigger(propertyMarkers[idx], 'click');
    }
};

// ── Update markers for filtered results ────────────────────
window.updateMapMarkers = function(properties) {
    if (!googleMap || !window._googleMapsReady) return;

    propertyMarkers.forEach(function(m) { m.setMap(null); });
    propertyMarkers = [];
    if (infoWindow) infoWindow.close();

    var statusColors = {
        'Active':                '#3ecf8e',
        'Coming Soon':           '#b07fff',
        'Active Under Contract': '#f5c842',
        'Pending':               '#ff9340',
        'Closed':                '#ff5c5c',
        'Canceled':              '#6b7080',
        'Expired':               '#6b7080',
    };

    var bounds = new google.maps.LatLngBounds();
    if (centerMarkerObj) bounds.extend(centerMarkerObj.getPosition());

    properties.forEach(function(prop, idx) {
        var lat = parseFloat(prop.Latitude);
        var lng = parseFloat(prop.Longitude);
        if (!lat || !lng || isNaN(lat) || isNaN(lng)) return;

        var pos   = { lat: lat, lng: lng };
        var color = statusColors[prop.StandardStatus] || '#5b7fff';

        var marker = new google.maps.Marker({
            position: pos,
            map: googleMap,
            title: [prop.StreetNumber, prop.StreetName, prop.City].filter(Boolean).join(' '),
            label: {
                text: String(idx + 1),
                color: '#ffffff',
                fontSize: '11px',
                fontWeight: '700',
                fontFamily: 'Syne, sans-serif',
            },
            icon: {
                path: google.maps.SymbolPath.CIRCLE,
                fillColor: color,
                fillOpacity: 1,
                strokeColor: '#fff',
                strokeWeight: 2,
                scale: 14,
            },
            zIndex: 100 - idx,
        });

        marker._propIndex = idx;
        propertyMarkers.push(marker);
        bounds.extend(pos);

        marker.addListener('click', function() {
            var price = prop.StandardStatus === 'Closed'
                ? (prop.ClosePrice || prop.ListPrice)
                : prop.ListPrice;

            infoWindow.setContent(
                '<div style="font-family:\'DM Sans\',sans-serif;padding:8px 4px;max-width:240px;color:#1a1a2e">' +
                    '<div style="font-weight:700;font-size:.9rem;margin-bottom:2px">' +
                        [prop.StreetNumber, prop.StreetName].filter(Boolean).join(' ') +
                    '</div>' +
                    '<div style="font-size:.78rem;color:#555;margin-bottom:8px">' +
                        [prop.City, prop.StateOrProvince].filter(Boolean).join(', ') +
                    '</div>' +
                    '<div style="font-size:1.1rem;font-weight:800;color:#4f46e5;margin-bottom:6px">' +
                        (price ? '$' + Number(price).toLocaleString() : '—') +
                    '</div>' +
                    '<div style="font-size:.78rem;color:#444;display:flex;gap:10px;margin-bottom:8px">' +
                        '<span>🛏 ' + (prop.BedroomsTotal != null ? prop.BedroomsTotal : '—') + ' bd</span>' +
                        '<span>🛁 ' + (prop.BathroomsTotalInteger != null ? prop.BathroomsTotalInteger : '—') + ' ba</span>' +
                        '<span>📐 ' + (prop.LivingArea ? Number(prop.LivingArea).toLocaleString() : '—') + ' sqft</span>' +
                    '</div>' +
                    '<div style="display:inline-block;padding:2px 8px;border-radius:100px;font-size:.68rem;' +
                                'font-weight:700;background:' + color + '22;color:' + color + ';border:1px solid ' + color + '44">' +
                        (prop.StandardStatus || '') +
                    '</div>' +
                    '<div style="margin-top:10px">' +
                        '<a href="#" onclick="window.highlightCard(' + idx + ');return false;"' +
                           ' style="font-size:.78rem;color:#4f46e5;font-weight:600;text-decoration:none">' +
                            'View details ↓' +
                        '</a>' +
                    '</div>' +
                '</div>');

            infoWindow.open(googleMap, marker);
            window.highlightCard(idx);
        });
    });

    if (propertyMarkers.length > 0 && !bounds.isEmpty()) {
        googleMap.fitBounds(bounds, { top: 60, right: 40, bottom: 40, left: 40 });
        var listener = googleMap.addListener('idle', function() {
            if (googleMap.getZoom() > 16) googleMap.setZoom(16);
            google.maps.event.removeListener(listener);
        });
    }
};

function clearMapMarkers() {
    propertyMarkers.forEach(function(m) { m.setMap(null); });
    propertyMarkers = [];
    if (centerMarkerObj) { centerMarkerObj.setMap(null); centerMarkerObj = null; }
    if (radiusCircle) { radiusCircle.setMap(null); radiusCircle = null; }
    if (infoWindow) infoWindow.close();
}

// ── Dark map style ──────────────────────────────────────────────
const DARK_MAP_STYLE = [
    { elementType: 'geometry', stylers: [{ color: '#1a1b2e' }] },
    { elementType: 'labels.text.stroke', stylers: [{ color: '#1a1b2e' }] },
    { elementType: 'labels.text.fill', stylers: [{ color: '#8892a4' }] },
    { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#22253a' }] },
    { featureType: 'road', elementType: 'geometry.stroke', stylers: [{ color: '#1a1b2e' }] },
    { featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#2d3050' }] },
    { featureType: 'road.highway', elementType: 'geometry.stroke', stylers: [{ color: '#1a1b2e' }] },
    { featureType: 'road.highway', elementType: 'labels.text.fill', stylers: [{ color: '#f3d19c' }] },
    { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#0e1626' }] },
    { featureType: 'water', elementType: 'labels.text.fill', stylers: [{ color: '#515c6d' }] },
    { featureType: 'water', elementType: 'labels.text.stroke', stylers: [{ color: '#17263c' }] },
    { featureType: 'poi', elementType: 'geometry', stylers: [{ color: '#1e2040' }] },
    { featureType: 'poi', elementType: 'labels.text.fill', stylers: [{ color: '#6b7280' }] },
    { featureType: 'poi.park', elementType: 'geometry', stylers: [{ color: '#1a2e1a' }] },
    { featureType: 'poi.park', elementType: 'labels.text.fill', stylers: [{ color: '#447744' }] },
    { featureType: 'transit', elementType: 'geometry', stylers: [{ color: '#2f3356' }] },
    { featureType: 'transit.station', elementType: 'labels.text.fill', stylers: [{ color: '#d59563' }] },
    { featureType: 'administrative', elementType: 'geometry.stroke', stylers: [{ color: '#4b4b70' }] },
    { featureType: 'administrative.land_parcel', elementType: 'labels.text.fill', stylers: [{ color: '#64748b' }] },
];
