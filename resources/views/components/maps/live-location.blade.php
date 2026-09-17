<script>
// All maps use the same deadline. Reading a saved fix again never renews it.
window.ColdTraceLocation = {
    timeoutMs: @json((int) config('coldtrace.gps_timeout_seconds', 30) * 1000),
    time(value) {
        return typeof value === 'number' ? value : Date.parse(value);
    },
    fresh(value) {
        const time = this.time(value);
        return Number.isFinite(time) && Date.now() - time < this.timeoutMs && time - Date.now() <= 30000;
    },
    packetTime(data, packet = {}) {
        if (packet.retain && !data.recorded_at) return null;
        const time = data.recorded_at ? this.time(data.recorded_at) : Date.now();
        return this.fresh(time) ? time : null;
    },
    numeric(value) {
        return value !== null && value !== undefined && value !== ''
            && typeof value !== 'boolean' && Number.isFinite(Number(value));
    },
    valid(lat, lng) {
        return this.numeric(lat) && this.numeric(lng)
            && Math.abs(Number(lat)) <= 90 && Math.abs(Number(lng)) <= 180
            && !(Number(lat) === 0 && Number(lng) === 0);
    },
};
// A restored page must reconnect its feeds after pagehide cleanup.
window.addEventListener('pageshow', event => { if (event.persisted) window.location.reload(); });
</script>
