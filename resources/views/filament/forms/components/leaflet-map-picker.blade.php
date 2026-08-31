<!-- Leaflet CSS & JS Assets -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>

<div
    x-data="leafletMapPicker({
        latState: $wire.entangle('data.latitude'),
        lngState: $wire.entangle('data.longitude'),
        typeState: $wire.entangle('data.type'),
        radiusState: $wire.entangle('data.radius_meters'),
        polyState: $wire.entangle('data.polygon_coordinates')
    })"
    x-init="initMap()"
    style="width: 100%; box-sizing: border-box;"
>
    <!-- Map Controls / Toolbar -->
    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px; padding: 10px 14px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 10px;">
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px;">
            <button
                type="button"
                @click="useCurrentLocation()"
                style="display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background-color: #2563eb; color: #ffffff; font-size: 12px; font-weight: 600; border-radius: 6px; border: none; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.05);"
            >
                <svg width="14" height="14" style="width: 14px; height: 14px; display: inline-block;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Gunakan Lokasi Saya
            </button>

            <template x-if="typeState === 'polygon'">
                <div style="display: inline-flex; align-items: center; gap: 8px;">
                    <button
                        type="button"
                        @click="removeLastPoint()"
                        style="display: inline-flex; align-items: center; padding: 6px 12px; background-color: #fffbeb; color: #b45309; font-size: 12px; font-weight: 600; border-radius: 6px; border: 1px solid #fde68a; cursor: pointer;"
                    >
                        Hapus Titik Terakhir
                    </button>
                    <button
                        type="button"
                        @click="clearPolygon()"
                        style="display: inline-flex; align-items: center; padding: 6px 12px; background-color: #fef2f2; color: #dc2626; font-size: 12px; font-weight: 600; border-radius: 6px; border: 1px solid #fecaca; cursor: pointer;"
                    >
                        Reset Polygon
                    </button>
                    <span style="display: inline-flex; align-items: center; padding: 4px 10px; background-color: #dcfce7; color: #15803d; font-size: 12px; font-weight: 700; border-radius: 12px; border: 1px solid #bbf7d0;">
                        <span x-text="getPolygonCoords().length"></span>&nbsp;titik
                    </span>
                </div>
            </template>
        </div>

        <!-- Search Input -->
        <div style="display: flex; align-items: center; gap: 6px;">
            <input
                type="text"
                x-model="searchQuery"
                @keydown.enter.prevent="searchLocation()"
                placeholder="Cari lokasi/kota..."
                style="padding: 6px 12px; font-size: 12px; border: 1px solid #cbd5e1; border-radius: 6px; width: 170px; outline: none; background: #ffffff; color: #0f172a;"
            />
            <button
                type="button"
                @click="searchLocation()"
                style="padding: 6px 12px; background-color: #475569; color: #ffffff; font-size: 12px; font-weight: 600; border-radius: 6px; border: none; cursor: pointer;"
            >
                Cari
            </button>
        </div>
    </div>

    <!-- Instruction Help Banner -->
    <div style="display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 500; color: #334155; background-color: #f1f5f9; padding: 8px 12px; border-radius: 6px; border-left: 4px solid #2563eb; margin-bottom: 10px;">
        <template x-if="typeState === 'polygon'">
            <span>📍 Klik pada peta untuk membuat titik-titik sudut area poligon geofence (minimal 3 titik). Geser lingkaran angka untuk memindahkan sudut.</span>
        </template>
        <template x-if="typeState === 'radius'">
            <span>⭕ Klik pada peta untuk menentukan titik pusat lokasi parkir MT. Atur radius dalam meter pada form.</span>
        </template>
    </div>

    <!-- Map Canvas Container -->
    <div
        x-ref="mapContainer"
        style="height: 420px; min-height: 420px; width: 100%; position: relative; z-index: 1; border-radius: 8px; border: 1px solid #cbd5e1; overflow: hidden; box-shadow: inset 0 2px 4px 0 rgba(0, 0, 0, 0.05);"
    ></div>

    <script>
        (function () {
            function registerLeafletPicker() {
                if (!window.Alpine) return;
                if (Alpine.data('leafletMapPicker')) return;

                Alpine.data('leafletMapPicker', (config) => ({
                    latState: config.latState,
                    lngState: config.lngState,
                    typeState: config.typeState,
                    radiusState: config.radiusState,
                    polyState: config.polyState,
                    searchQuery: '',
                    map: null,
                    centerMarker: null,
                    radiusCircle: null,
                    polygonLayer: null,
                    polylineLayer: null,
                    pointMarkers: [],

                    initMap() {
                        this.ensureLeafletLoaded().then(() => {
                            const defaultLat = parseFloat(this.latState) || -6.200000;
                            const defaultLng = parseFloat(this.lngState) || 106.816666;

                            if (this.map) {
                                this.map.remove();
                                this.map = null;
                            }

                            this.map = L.map(this.$refs.mapContainer).setView([defaultLat, defaultLng], 15);

                            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                attribution: '&copy; OpenStreetMap contributors',
                                maxZoom: 19
                            }).addTo(this.map);

                            this.map.on('click', (e) => this.onMapClick(e));

                            this.$watch('typeState', () => this.redrawMap());
                            this.$watch('radiusState', () => this.redrawMap());

                            const checkVis = setInterval(() => {
                                if (this.$refs.mapContainer && this.$refs.mapContainer.clientWidth > 0) {
                                    if (this.map) {
                                        this.map.invalidateSize();
                                    }
                                    this.redrawMap();
                                    clearInterval(checkVis);
                                }
                            }, 150);

                            if (window.ResizeObserver && this.$refs.mapContainer) {
                                const ro = new ResizeObserver(() => {
                                    if (this.map) {
                                        this.map.invalidateSize();
                                    }
                                });
                                ro.observe(this.$refs.mapContainer);
                            }
                        });
                    },

                    ensureLeafletLoaded() {
                        return new Promise((resolve) => {
                            if (window.L && window.L.map) {
                                resolve();
                                return;
                            }
                            let count = 0;
                            const interval = setInterval(() => {
                                count++;
                                if (window.L && window.L.map) {
                                    clearInterval(interval);
                                    resolve();
                                }
                                if (count > 50) {
                                    clearInterval(interval);
                                    resolve();
                                }
                            }, 100);
                        });
                    },

                    getPolygonCoords() {
                        let raw = this.polyState;
                        if (!raw) return [];
                        if (typeof raw === 'string') {
                            try {
                                raw = JSON.parse(raw);
                            } catch (e) {
                                return [];
                            }
                        }
                        if (!Array.isArray(raw)) return [];

                        return raw.map(p => {
                            if (typeof p === 'object' && p !== null) {
                                return {
                                    lat: parseFloat(p.lat ?? p[0] ?? 0),
                                    lng: parseFloat(p.lng ?? p[1] ?? 0)
                                };
                            }
                            return null;
                        }).filter(p => p && !isNaN(p.lat) && !isNaN(p.lng));
                    },

                    onMapClick(e) {
                        const lat = parseFloat(e.latlng.lat.toFixed(7));
                        const lng = parseFloat(e.latlng.lng.toFixed(7));

                        if (this.typeState === 'radius' || !this.typeState) {
                            this.latState = lat;
                            this.lngState = lng;
                            this.redrawMap();
                        } else if (this.typeState === 'polygon') {
                            let coords = this.getPolygonCoords();
                            coords.push({ lat: lat, lng: lng });
                            this.polyState = coords;

                            if (!this.latState || !this.lngState || coords.length === 1) {
                                this.latState = lat;
                                this.lngState = lng;
                            } else {
                                const avgLat = coords.reduce((sum, p) => sum + p.lat, 0) / coords.length;
                                const avgLng = coords.reduce((sum, p) => sum + p.lng, 0) / coords.length;
                                this.latState = parseFloat(avgLat.toFixed(7));
                                this.lngState = parseFloat(avgLng.toFixed(7));
                            }

                            this.redrawMap();
                        }
                    },

                    redrawMap() {
                        if (!this.map) return;

                        if (this.centerMarker) this.map.removeLayer(this.centerMarker);
                        if (this.radiusCircle) this.map.removeLayer(this.radiusCircle);
                        if (this.polygonLayer) this.map.removeLayer(this.polygonLayer);
                        if (this.polylineLayer) this.map.removeLayer(this.polylineLayer);

                        this.pointMarkers.forEach(m => this.map.removeLayer(m));
                        this.pointMarkers = [];

                        const lat = parseFloat(this.latState);
                        const lng = parseFloat(this.lngState);

                        if (this.typeState === 'radius' || !this.typeState) {
                            if (!isNaN(lat) && !isNaN(lng)) {
                                this.centerMarker = L.marker([lat, lng], { draggable: true }).addTo(this.map);
                                this.centerMarker.on('dragend', (e) => {
                                    const pos = e.target.getLatLng();
                                    this.latState = parseFloat(pos.lat.toFixed(7));
                                    this.lngState = parseFloat(pos.lng.toFixed(7));
                                    this.redrawMap();
                                });

                                const radius = parseInt(this.radiusState) || 100;
                                this.radiusCircle = L.circle([lat, lng], {
                                    radius: radius,
                                    color: '#2563eb',
                                    fillColor: '#3b82f6',
                                    fillOpacity: 0.25
                                }).addTo(this.map);
                            }
                        } else if (this.typeState === 'polygon') {
                            let coords = this.getPolygonCoords();

                            if (coords.length === 1) {
                                const pt = coords[0];
                                const marker = L.marker([pt.lat, pt.lng], {
                                    draggable: true,
                                    icon: L.divIcon({
                                        className: 'custom-poly-node',
                                        html: `<div style="background-color: #15803d; color: white; border-radius: 50%; width: 24px; height: 24px; text-align: center; line-height: 24px; font-size: 12px; font-weight: bold; border: 2px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.4);">1</div>`,
                                        iconSize: [24, 24],
                                        iconAnchor: [12, 12]
                                    })
                                }).addTo(this.map);

                                marker.on('dragend', (e) => {
                                    const pos = e.target.getLatLng();
                                    let newCoords = [{ lat: parseFloat(pos.lat.toFixed(7)), lng: parseFloat(pos.lng.toFixed(7)) }];
                                    this.polyState = newCoords;
                                    this.latState = newCoords[0].lat;
                                    this.lngState = newCoords[0].lng;
                                    this.redrawMap();
                                });

                                this.pointMarkers.push(marker);

                            } else if (coords.length === 2) {
                                const latLngs = coords.map(p => [p.lat, p.lng]);
                                this.polylineLayer = L.polyline(latLngs, {
                                    color: '#16a34a',
                                    weight: 3,
                                    dashArray: '5, 5'
                                }).addTo(this.map);

                                this.renderNodeMarkers(coords);

                            } else if (coords.length >= 3) {
                                const latLngs = coords.map(p => [p.lat, p.lng]);

                                this.polygonLayer = L.polygon(latLngs, {
                                    color: '#15803d',
                                    fillColor: '#22c55e',
                                    fillOpacity: 0.35,
                                    weight: 3
                                }).addTo(this.map);

                                this.renderNodeMarkers(coords);
                            }
                        }
                    },

                    renderNodeMarkers(coords) {
                        coords.forEach((pt, index) => {
                            const marker = L.marker([pt.lat, pt.lng], {
                                draggable: true,
                                icon: L.divIcon({
                                    className: 'custom-poly-node',
                                    html: `<div style="background-color: #15803d; color: white; border-radius: 50%; width: 24px; height: 24px; text-align: center; line-height: 24px; font-size: 12px; font-weight: bold; border: 2px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.4);">${index + 1}</div>`,
                                    iconSize: [24, 24],
                                    iconAnchor: [12, 12]
                                })
                            }).addTo(this.map);

                            marker.on('dragend', (e) => {
                                const pos = e.target.getLatLng();
                                let newCoords = this.getPolygonCoords();
                                newCoords[index] = {
                                    lat: parseFloat(pos.lat.toFixed(7)),
                                    lng: parseFloat(pos.lng.toFixed(7))
                                };
                                this.polyState = newCoords;

                                const avgLat = newCoords.reduce((sum, p) => sum + p.lat, 0) / newCoords.length;
                                const avgLng = newCoords.reduce((sum, p) => sum + p.lng, 0) / newCoords.length;
                                this.latState = parseFloat(avgLat.toFixed(7));
                                this.lngState = parseFloat(avgLng.toFixed(7));

                                this.redrawMap();
                            });

                            this.pointMarkers.push(marker);
                        });
                    },

                    removeLastPoint() {
                        let coords = this.getPolygonCoords();
                        if (coords.length > 0) {
                            coords.pop();
                            this.polyState = coords;
                            if (coords.length > 0) {
                                const avgLat = coords.reduce((sum, p) => sum + p.lat, 0) / coords.length;
                                const avgLng = coords.reduce((sum, p) => sum + p.lng, 0) / coords.length;
                                this.latState = parseFloat(avgLat.toFixed(7));
                                this.lngState = parseFloat(avgLng.toFixed(7));
                            }
                            this.redrawMap();
                        }
                    },

                    clearPolygon() {
                        this.polyState = [];
                        this.redrawMap();
                    },

                    useCurrentLocation() {
                        if (navigator.geolocation) {
                            navigator.geolocation.getCurrentPosition((pos) => {
                                const lat = parseFloat(pos.coords.latitude.toFixed(7));
                                const lng = parseFloat(pos.coords.longitude.toFixed(7));
                                this.latState = lat;
                                this.lngState = lng;

                                if (this.map) {
                                    this.map.setView([lat, lng], 17);
                                }
                                this.redrawMap();
                            }, (err) => {
                                alert('Gagal mendapatkan lokasi saat ini: ' + err.message);
                            });
                        } else {
                            alert('Browser Anda tidak mendukung Geolocation.');
                        }
                    },

                    searchLocation() {
                        if (!this.searchQuery) return;

                        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(this.searchQuery)}`)
                            .then(res => res.json())
                            .then(data => {
                                if (data && data.length > 0) {
                                    const lat = parseFloat(data[0].lat);
                                    const lng = parseFloat(data[0].lon);
                                    this.latState = parseFloat(lat.toFixed(7));
                                    this.lngState = parseFloat(lng.toFixed(7));

                                    if (this.map) {
                                        this.map.setView([lat, lng], 16);
                                    }
                                    this.redrawMap();
                                } else {
                                    alert('Lokasi tidak ditemukan.');
                                }
                            })
                            .catch(() => alert('Terjadi kesalahan saat mencari lokasi.'));
                    }
                }));
            }

            if (window.Alpine) {
                registerLeafletPicker();
            } else {
                document.addEventListener('alpine:init', registerLeafletPicker);
            }
        })();
    </script>
</div>
