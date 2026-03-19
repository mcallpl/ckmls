/* ============================================================
   MLS Property Search — Google Maps Integration
   ============================================================ */

let googleMap       = null;
let infoWindow      = null;
let propertyMarkers = [];
let centerMarkerObj = null;
let radiusCircle    = null;

/**
 * Called by app.js after search results arrive.
 * Guards against Google Maps API not loaded yet (async defer).
 */
window.initMap = function(geocoded, properties) {
    // If Maps API isn't ready yet, queue this call for when it is
    if (!window._googleMapsReady) {
        window._pendingMapCall = () => window.initMap(geocoded, properties);
        return;
    }

    const center = { lat: geocoded.lat, lng: geocoded.lng };

    if (!googleMap) {
        // First init
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
    } else {
        googleMap.setCenter(center);
        googleMap.setZoom(14);
        // Clear existing markers
        clearMapMarkers();
    }

    // ── Center / searched address marker ──
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

    centerMarkerObj.addListener('click', () => {
        infoWindow.setContent(`
            <div style="font-family:'DM Sans',sans-serif;padding:8px;max-width:220px;color:#1a1a2e">
                <div style="font-weight:700;font-size:.9rem;margin-bottom:4px">📍 Searched Address</div>
                <div style="font-size:.8rem;color:#555;line-height:1.4">${geocoded.display_name}</div>
            </div>`);
        infoWindow.open(googleMap, centerMarkerObj);
    });

    // ── Radius circle ──
    if (radiusCircle) radiusCircle.setMap(null);
    var radiusSel = document.getElementById('rSel');
    var radiusMiles = radiusSel ? parseFloat(radiusSel.value) : 1.0;
    radiusCircle = new google.maps.Circle({
        map: googleMap,
        center: center,
        radius: radiusMiles * 1609.34,  // convert miles to meters
        fillColor: '#5b7fff',
        fillOpacity: 0.08,
        strokeColor: '#5b7fff',
        strokeOpacity: 0.5,
        strokeWeight: 2,
        clickable: false,
    });

    // ── Property listing markers ──
    const bounds = new google.maps.LatLngBounds();
    bounds.extend(center);

    propertyMarkers = [];

    const statusColors = {
        'Active':                '#3ecf8e',
        'Coming Soon':           '#b07fff',
        'Active Under Contract': '#f5c842',
        'Pending':               '#ff9340',
        'Closed':                '#ff5c5c',
        'Canceled':              '#6b7080',
        'Expired':               '#6b7080',
    };

    properties.forEach((prop, idx) => {
        const lat = parseFloat(prop.Latitude);
        const lng = parseFloat(prop.Longitude);
        if (!lat || !lng || isNaN(lat) || isNaN(lng)) return; // skip if no coords

        const pos   = { lat, lng };
        const color = statusColors[prop.StandardStatus] || '#5b7fff';

        const marker = new google.maps.Marker({
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

        marker.addListener('click', () => {
            const price = prop.StandardStatus === 'Closed'
                ? (prop.ClosePrice || prop.ListPrice)
                : prop.ListPrice;

            infoWindow.setContent(`
                <div style="font-family:'DM Sans',sans-serif;padding:8px 4px;max-width:240px;color:#1a1a2e">
                    <div style="font-weight:700;font-size:.9rem;margin-bottom:2px">
                        ${[prop.StreetNumber, prop.StreetName].filter(Boolean).join(' ')}
                    </div>
                    <div style="font-size:.78rem;color:#555;margin-bottom:8px">
                        ${[prop.City, prop.StateOrProvince].filter(Boolean).join(', ')}
                    </div>
                    <div style="font-size:1.1rem;font-weight:800;color:#4f46e5;margin-bottom:6px">
                        ${price ? '$' + Number(price).toLocaleString() : '—'}
                    </div>
                    <div style="font-size:.78rem;color:#444;display:flex;gap:10px;margin-bottom:8px">
                        <span>🛏 ${prop.BedroomsTotal ?? '—'} bd</span>
                        <span>🛁 ${prop.BathroomsTotalInteger ?? '—'} ba</span>
                        <span>📐 ${prop.LivingArea ? Number(prop.LivingArea).toLocaleString() : '—'} sqft</span>
                    </div>
                    <div style="display:inline-block;padding:2px 8px;border-radius:100px;font-size:.68rem;
                                font-weight:700;background:${color}22;color:${color};border:1px solid ${color}44">
                        ${prop.StandardStatus || ''}
                    </div>
                    <div style="margin-top:10px">
                        <a href="#" onclick="window.highlightCard(${idx});return false;"
                           style="font-size:.78rem;color:#4f46e5;font-weight:600;text-decoration:none">
                            View details ↓
                        </a>
                    </div>
                </div>`);

            infoWindow.open(googleMap, marker);
            window.highlightCard(idx);
        });
    });

    // Fit map to show all markers
    if (!bounds.isEmpty()) {
        googleMap.fitBounds(bounds, { top: 60, right: 40, bottom: 40, left: 40 });
        // Don't zoom in too close
        const listener = googleMap.addListener('idle', () => {
            if (googleMap.getZoom() > 16) googleMap.setZoom(16);
            google.maps.event.removeListener(listener);
        });
    }
};

/**
 * Highlight a marker on the map (called from card clicks)
 */
window.highlightMapMarker = function(idx) {
    propertyMarkers.forEach((m, i) => {
        const icon = m.getIcon();
        m.setIcon({ ...icon, scale: i === idx ? 18 : 14, strokeWeight: i === idx ? 3 : 2 });
        m.setZIndex(i === idx ? 999 : 100 - i);
    });

    if (propertyMarkers[idx]) {
        googleMap.panTo(propertyMarkers[idx].getPosition());
        google.maps.event.trigger(propertyMarkers[idx], 'click');
    }
};

/**
 * Update property markers for filtered results — keeps center pin + zoom intact
 * Called by applyFiltersAndRender() every time filters or sort change
 */
window.updateMapMarkers = function(properties) {
    if (!googleMap || !window._googleMapsReady) return;

    // Remove old property markers only (keep center pin)
    propertyMarkers.forEach(m => m.setMap(null));
    propertyMarkers = [];
    if (infoWindow) infoWindow.close();

    const statusColors = {
        'Active':                '#3ecf8e',
        'Coming Soon':           '#b07fff',
        'Active Under Contract': '#f5c842',
        'Pending':               '#ff9340',
        'Closed':                '#ff5c5c',
        'Canceled':              '#6b7080',
        'Expired':               '#6b7080',
    };

    const bounds = new google.maps.LatLngBounds();
    // Include center in bounds so map doesn't drift away
    if (centerMarkerObj) bounds.extend(centerMarkerObj.getPosition());

    properties.forEach((prop, idx) => {
        const lat = parseFloat(prop.Latitude);
        const lng = parseFloat(prop.Longitude);
        if (!lat || !lng || isNaN(lat) || isNaN(lng)) return;

        const pos   = { lat, lng };
        const color = statusColors[prop.StandardStatus] || '#5b7fff';

        const marker = new google.maps.Marker({
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

        marker.addListener('click', () => {
            const price = prop.StandardStatus === 'Closed'
                ? (prop.ClosePrice || prop.ListPrice)
                : prop.ListPrice;

            infoWindow.setContent(`
                <div style="font-family:'DM Sans',sans-serif;padding:8px 4px;max-width:240px;color:#1a1a2e">
                    <div style="font-weight:700;font-size:.9rem;margin-bottom:2px">
                        ${[prop.StreetNumber, prop.StreetName].filter(Boolean).join(' ')}
                    </div>
                    <div style="font-size:.78rem;color:#555;margin-bottom:8px">
                        ${[prop.City, prop.StateOrProvince].filter(Boolean).join(', ')}
                    </div>
                    <div style="font-size:1.1rem;font-weight:800;color:#4f46e5;margin-bottom:6px">
                        ${price ? '$' + Number(price).toLocaleString() : '—'}
                    </div>
                    <div style="font-size:.78rem;color:#444;display:flex;gap:10px;margin-bottom:8px">
                        <span>🛏 ${prop.BedroomsTotal ?? '—'} bd</span>
                        <span>🛁 ${prop.BathroomsTotalInteger ?? '—'} ba</span>
                        <span>📐 ${prop.LivingArea ? Number(prop.LivingArea).toLocaleString() : '—'} sqft</span>
                    </div>
                    <div style="display:inline-block;padding:2px 8px;border-radius:100px;font-size:.68rem;
                                font-weight:700;background:${color}22;color:${color};border:1px solid ${color}44">
                        ${prop.StandardStatus || ''}
                    </div>
                    <div style="margin-top:10px">
                        <a href="#" onclick="window.highlightCard(${idx});return false;"
                           style="font-size:.78rem;color:#4f46e5;font-weight:600;text-decoration:none">
                            View details ↓
                        </a>
                    </div>
                </div>`);

            infoWindow.open(googleMap, marker);
            window.highlightCard(idx);
        });
    });

    // Fit bounds only if we have markers — don't zoom in too close
    if (propertyMarkers.length > 0 && !bounds.isEmpty()) {
        googleMap.fitBounds(bounds, { top: 60, right: 40, bottom: 40, left: 40 });
        const listener = googleMap.addListener('idle', () => {
            if (googleMap.getZoom() > 16) googleMap.setZoom(16);
            google.maps.event.removeListener(listener);
        });
    }
};

function clearMapMarkers() {
    propertyMarkers.forEach(m => m.setMap(null));
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
