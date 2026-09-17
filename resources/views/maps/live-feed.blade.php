<script>
    function applyLiveReading(trip) {
        const live = liveTelemetryByTruck.get(trip.id);
        if (!live) return;
        if (Date.parse(live.gps?.recorded_at) > (Date.parse(trip.gps?.recorded_at) || 0)) {
            trip.gps = live.gps;
        }
        if (Date.parse(live.reading?.recorded_at) > (Date.parse(trip.latestTelemetry?.recorded_at) || 0)) {
            const temperature = live.reading.temperature;
            const min = trip.product?.min_temp, max = trip.product?.max_temp;
            const known = temperature !== null && min != null && max != null;
            const low = known && temperature < Number(min), high = known && temperature > Number(max);
            trip.latestTelemetry = {
                ...live.reading,
                // Device packets do not contain the server's shelf-life calculation.
                rsl_hours: null,
                temperature_status: {
                    label: !known ? 'No limits or reading' : low ? 'Too Low' : high ? 'Too High' : 'Safe',
                    class: !known ? 'neutral' : low ? 'warning' : high ? 'critical' : 'safe',
                    is_breach: low || high,
                },
            };
        }
    }

    function renderFleet() {
        const list = document.getElementById('tripList');
        list.innerHTML = '';
        trips.forEach(trip => {
            applyLiveReading(trip);
            const latest = trip.latestTelemetry;
            const condition = latest?.temperature_status;
            const conditionClass = ["neutral", "safe", "warning", "critical"].includes(condition?.class) ? condition.class : "neutral";
            const position = getBestTruckPosition(trip);
            const gpsLabel = position
                ? (trip.gps?.source === 'demo' ? 'Demo location' : 'Live GPS')
                : trip.gps ? 'GPS update overdue' : 'Waiting for GPS';
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'trip-button';
            button.id = `trip-button-${trip.id}`;
            button.dataset.search = [trip.truck?.plate_number, trip.driver?.name, trip.product?.name, trip.status].join(' ').toLowerCase();
            button.addEventListener('click', () => focusTrip(trip.id));
            button.innerHTML = `
                <div class="trip-top"><div><strong>${escapeHtml(trip.truck?.plate_number ?? 'Truck')}</strong>
                <span>${escapeHtml(trip.product?.name ?? 'No active delivery')}</span></div>
                <em class="status-badge">${escapeHtml(trip.status.replaceAll('_', ' '))}</em></div>
                <div class="trip-meta"><small>Driver: ${escapeHtml(trip.driver?.name ?? 'Not assigned')}</small>
                <small>${gpsLabel}</small></div>
                <div class="trip-bottom"><span class="temperature-pill ${conditionClass}">${latest?.temperature == null ? 'No temperature' : escapeHtml(String(latest.temperature)) + ' °C'}
                <small>${escapeHtml(condition?.label ?? '')}</small></span>
                <span class="rsl-pill">RSL: ${escapeHtml(String(latest?.rsl_hours ?? 'N/A'))} hrs</span></div>`;
            list.appendChild(button);
        });
        if (!trips.length) list.innerHTML = '<div class="empty-box">No trucks have been configured yet.</div>';
        document.getElementById('fleetTruckCount').textContent = trips.length;
        document.getElementById('activeTripCount').textContent = trips.filter(t => t.status === 'in_progress').length;
        document.getElementById('pendingTripCount').textContent = trips.filter(t => t.status === 'pending').length;
        document.getElementById('onlineTruckCount').textContent = trips.filter(t => getBestTruckPosition(t)).length;
        document.getElementById('criticalReadingCount').textContent = trips.filter(t => {
            const age = Date.now() - Date.parse(t.latestTelemetry?.recorded_at);
            return age <= 120000 && age >= -30000 && t.latestTelemetry?.temperature_status?.is_breach;
        }).length;
        filterTrips(document.getElementById('fleetSearch').value);
        setActiveTripButton(selectedTripId);
        syncFleetMarkers();
    }

    function syncFleetMarkers() {
        if (!map || !AdvancedMarkerElementClass) return;
        if (selectedTripId !== null && !trips.some(t => t.id === selectedTripId)) {
            showAllTrips();
            return;
        }
        const visible = trips.filter(t => selectedTripId === null || t.id === selectedTripId);
        const positions = new Map(visible.map(t => [t.id, getBestTruckPosition(t)]));
        truckMarkers.forEach((marker, id) => {
            if (!positions.get(id)) { marker.map = null; truckMarkers.delete(id); }
        });
        visible.forEach(trip => {
            const position = positions.get(trip.id);
            if (!position) return;
            let marker = truckMarkers.get(trip.id);
            if (marker) {
                marker.position = position;
                marker.fleetInfoWindow.setContent(buildTruckInfoWindow(trip));
            } else {
                marker = createAdvancedMarker({ position, type: 'truck', truckNumber: trip.truck.id,
                    title: trip.truck.plate_number, infoContent: buildTruckInfoWindow(trip) });
                marker.addListener('click', () => focusTrip(trip.id));
                truckMarkers.set(trip.id, marker);
                if (selectedTripId !== null) { hideMapNotice(); map.panTo(position); }
            }
        });
        if (!fleetHasPosition && truckMarkers.size && selectedTripId === null) {
            const bounds = new google.maps.LatLngBounds();
            truckMarkers.forEach(marker => bounds.extend(marker.position));
            map.fitBounds(bounds);
            fleetHasPosition = true;
        }
        const selected = trips.find(t => t.id === selectedTripId);
        if (selected) document.getElementById('selectedTripDetails').textContent = buildTripDetailsText(selected);
    }

    function receiveFleetTelemetry(topic, data, packet = {}) {
        if (!data || typeof data !== 'object') return;
        const trip = trips.find(t => t.devices?.some(d => d.code === data.device_code && d.topic === topic));
        if (!trip || (packet.retain && !data.recorded_at)) return;
        const timestamp = data.recorded_at ? Date.parse(data.recorded_at) : Date.now();
        if (!Number.isFinite(timestamp) || Date.now() - timestamp > 120000 || timestamp - Date.now() > 30000) return;
        const live = liveTelemetryByTruck.get(trip.id) ?? {};
        const recordedAt = new Date(timestamp).toISOString();
        const numeric = value => value !== null && value !== undefined && value !== '' && typeof value !== 'boolean' && Number.isFinite(Number(value));
        const gps = {lat: data.latitude, lng: data.longitude, recorded_at: recordedAt, source: 'sensor'};
        const quality = data.gps_valid === true
            && numeric(data.latitude) && numeric(data.longitude) && getLatLng(gps)
            && (data.satellites === undefined || (numeric(data.satellites) && Number(data.satellites) >= 4))
            && (data.hdop === undefined || (numeric(data.hdop) && Number(data.hdop) >= 0 && Number(data.hdop) <= 5));
        if (quality && timestamp >= (Date.parse(live.gps?.recorded_at) || 0)) live.gps = gps;
        if (Object.hasOwn(data, 'temperature') && (data.temperature === null || (numeric(data.temperature) && Math.abs(Number(data.temperature)) <= 100))
            && timestamp >= (Date.parse(live.reading?.recorded_at) || 0)) {
            live.reading = {temperature: data.temperature === null ? null : Number(data.temperature), recorded_at: recordedAt};
        }
        liveTelemetryByTruck.set(trip.id, live);
        renderFleet();
    }

    function subscribeFleetTopics() {
        if (!fleetMqttClient?.connected) return;
        const topics = [...new Set(trips.flatMap(t => t.devices.map(d => d.topic)).filter(Boolean))];
        if (topics.length) fleetMqttClient.subscribe(topics, error => {
            if (error) document.getElementById('fleetConnection').textContent = 'Live connection unavailable. Checking saved readings.';
        });
    }

    async function refreshFleetSnapshot() {
        if (pollingFleet || document.hidden) return;
        pollingFleet = true;
        try {
            const response = await fetch(fleetSnapshotUrl, {headers: {Accept: 'application/json'}, cache: 'no-store'});
            if (!response.ok) throw new Error('Unable to refresh fleet');
            const payload = await response.json();
            const selectedBefore = trips.find(t => t.id === selectedTripId);
            const routeBefore = JSON.stringify([selectedBefore?.trip_id, selectedBefore?.origin, selectedBefore?.destination]);
            trips = payload.data;
            subscribeFleetTopics();
            renderFleet();
            const selectedAfter = trips.find(t => t.id === selectedTripId);
            if (selectedAfter && routeBefore !== JSON.stringify([selectedAfter.trip_id, selectedAfter.origin, selectedAfter.destination])) {
                focusTrip(selectedTripId);
            }
        } catch (error) {
            document.getElementById('fleetConnection').textContent = 'Saved readings could not refresh. Retrying automatically.';
            renderFleet(); // Expire old positions even when the connection drops.
        } finally { pollingFleet = false; }
    }

    renderFleet();
    const fleetRefreshTimer = setInterval(refreshFleetSnapshot, 5000);
    window.addEventListener('pagehide', () => { clearInterval(fleetRefreshTimer); fleetMqttClient?.end(); });
</script>
@if (!empty($mqttBroker) && !empty($mqttUsername) && !empty($mqttPassword))
<script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>
<script>
    if (window.mqtt) {
        fleetMqttClient = mqtt.connect(@json($mqttBroker), {
            username: @json($mqttUsername), password: @json($mqttPassword),
            clean: true, reconnectPeriod: 3000, connectTimeout: 30000,
            clientId: 'coldtrace-admin-map-' + Math.random().toString(16).slice(2, 12),
        });
        fleetMqttClient.on('connect', () => {
            document.getElementById('fleetConnection').textContent = 'Live updates connected. Waiting for truck readings.';
            subscribeFleetTopics();
        });
        fleetMqttClient.on('message', (topic, message, packet) => {
            try { receiveFleetTelemetry(topic, JSON.parse(message.toString()), packet); } catch (error) { /* Ignore malformed readings. */ }
        });
        fleetMqttClient.on('offline', () => {
            document.getElementById('fleetConnection').textContent = 'Reconnecting to live updates. Checking saved readings.';
        });
        fleetMqttClient.on('error', () => {
            document.getElementById('fleetConnection').textContent = 'Live connection unavailable. Checking saved readings.';
        });
    }
</script>
@endif
