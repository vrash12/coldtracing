const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function environment() {
    let now = Date.now();
    const elements = new Map();
    function element() {
        return {textContent: '', innerHTML: '', value: '', dataset: {}, style: {}, hidden: true,
            classList: {add() {}, remove() {}}, appendChild() {}, addEventListener() {}};
    }
    const document = {hidden: false, createElement: element, querySelectorAll: () => [], addEventListener() {},
        getElementById(id) { if (!elements.has(id)) elements.set(id, element()); return elements.get(id); }};
    class MapView { fitBounds() {} setCenter() {} setZoom() {} panTo() {} }
    class Marker { constructor(options) { Object.assign(this, options); } addListener() {} }
    class Bounds { extend() {} }
    class InfoWindow { close() {} constructor() {} setContent() {} open() {} }
    const context = vm.createContext({document, console, Map, Date: class extends Date {static now() {return now;}},
        setInterval() {}, clearInterval() {}, fetch: async () => ({ok:true, json:async()=>({data: structuredClone(fleet)})}),
        google: {maps: {LatLngBounds: Bounds, InfoWindow, importLibrary: async name => {
            if (name === 'routes') throw new Error('Routes disabled');
            return {Map:MapView, AdvancedMarkerElement:Marker};
        }}},
        window: {addEventListener() {}, createColdTraceTruckMarker: number => ({number})}});
    const fleet = Array.from({length:6}, (_, i) => ({id:i+1, trip_id:null, status:'idle',
        truck:{id:i+1, plate_number:`CT-${1001+i}`}, devices:[{code:`ESP32-CT-${1001+i}`, topic:`coldtrace/trucks/CT-${1001+i}/telemetry`}],
        product:{}, driver:{name:`Driver ${i+1}`}, origin:{lat:14.6,lng:121.1}, destination:{}, gps:null, latestTelemetry:null}));
    const script = path => fs.readFileSync(path, 'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];
    const main = script('resources/views/maps/index.blade.php')
        .replace('@json($mapTrips ?? [])', JSON.stringify(fleet))
        .replace("@json(route('monitoring.latest'))", "'/monitoring/latest'");
    context.window = context;
    context.addEventListener = () => {};
    context.createColdTraceTruckMarker = number => ({number});
    vm.runInContext(script('resources/views/components/maps/live-location.blade.php').replace(/@json\([^\n]+\)/, '30000'), context);
    vm.runInContext(main, context);
    vm.runInContext(script('resources/views/maps/live-feed.blade.php'), context);
    return {run: code => vm.runInContext(code, context), elements, advance: ms => {now += ms;}};
}

test('idle fleet receives a live marker without trips or the Routes library', async () => {
    const e=environment();
    await e.run('initColdTraceMap()');
    assert.equal(e.run('truckMarkers.size'),0, 'pickup addresses must not become truck positions');
    e.run(`receiveFleetTelemetry('coldtrace/trucks/CT-1004/telemetry', {
        device_code:'ESP32-CT-1004', gps_valid:true, latitude:14.7, longitude:121.2, satellites:8, hdop:1, temperature:0
    })`);
    assert.equal(e.run('truckMarkers.size'),1);
    assert.equal(e.run('truckMarkers.get(4).content.number'),4);
    assert.equal(e.run('truckMarkers.get(4).position.lat'),14.7);
    assert.equal(e.run('trips[3].latestTelemetry.temperature'),0);
    await e.run('refreshFleetSnapshot()');
    assert.equal(e.run('truckMarkers.size'),1, 'empty stored snapshot must not erase live GPS');
    e.advance(30000);
    e.run('renderFleet()');
    assert.equal(e.run('truckMarkers.size'),0, 'old GPS must expire');
});

test('rejects wrong device topic, poor fix and un-timestamped retained readings', async () => {
    const e=environment();
    await e.run('initColdTraceMap()');
    for (const [topic, payload, packet] of [
        ['CT-1003', {device_code:'ESP32-CT-1004', gps_valid:true}, {}],
        ['CT-1004', {device_code:'ESP32-CT-1004', gps_valid:false}, {}],
        ['CT-1004', {device_code:'ESP32-CT-1004', gps_valid:true, satellites:2}, {}],
        ['CT-1004', {device_code:'ESP32-CT-1004', gps_valid:true, hdop:9}, {}],
        ['CT-1004', {device_code:'ESP32-CT-1004', gps_valid:true}, {retain:true}],
    ]) {
        e.run(`receiveFleetTelemetry('coldtrace/trucks/${topic}/telemetry', ${JSON.stringify({latitude:14.7,longitude:121.2, ...payload})}, ${JSON.stringify(packet)})`);
    }
    assert.equal(e.run('truckMarkers.size'),0);
});

test('newer temperature-only readings keep valid GPS and older packets cannot move the truck back', async () => {
    const e=environment();
    await e.run('initColdTraceMap()');
    e.run(`receiveFleetTelemetry('coldtrace/trucks/CT-1001/telemetry', {device_code:'ESP32-CT-1001',gps_valid:true,latitude:0,longitude:121.1,temperature:4})`);
    e.advance(1000);
    e.run(`receiveFleetTelemetry('coldtrace/trucks/CT-1001/telemetry', {device_code:'ESP32-CT-1001',gps_valid:false,temperature:null})`);
    assert.equal(e.run('truckMarkers.get(1).position.lat'),0);
    assert.equal(e.run('trips[0].latestTelemetry.temperature'),null);
    e.run(`receiveFleetTelemetry('coldtrace/trucks/CT-1001/telemetry', {device_code:'ESP32-CT-1001',gps_valid:true,latitude:10,longitude:10,recorded_at:new Date(Date.now()-60000).toISOString()})`);
    assert.equal(e.run('truckMarkers.get(1).position.lat'),0);
});
