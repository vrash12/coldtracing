const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function scripts(path) { return [...fs.readFileSync(path, 'utf8').matchAll(/<script>([\s\S]*?)<\/script>/g)].map(m => m[1]); }
// Replace Blade JSON expressions while preserving the actual page JavaScript.
function render(script, values = {}) {
    while (script.includes('@json(')) {
        const start = script.indexOf('@json(');
        let end = start + 6, depth = 1;
        while (depth) { const c = script[end++]; if (c === '(') depth++; if (c === ')') depth--; }
        const expression = script.slice(start + 6, end - 1);
        script = script.slice(0, start) + JSON.stringify(values[expression] ?? null) + script.slice(end);
    }
    return script;
}
function environment(page) {
    let now = Date.now(), data = null;
    const elements = new Map(), timers = [], events = {};
    const element = () => ({textContent:'', innerText:'', innerHTML:'', style:{}, dataset:{},
        classList:{add(){},remove(){},toggle(){},contains(){return false;}},
        setAttribute(){}, removeAttribute(){}, addEventListener(){}, querySelector(){return null;}});
    const document = {addEventListener(name, fn){events[name]=fn;}, querySelectorAll:()=>[], querySelector:()=>null,
        createElement:element, getElementById(id){if(!elements.has(id)) elements.set(id,element()); return elements.get(id);}};
    document.getElementById('driverOrderPage').dataset.telemetryUrl='/latest';
    class Marker {constructor(options){Object.assign(this, options);}}
    const c=vm.createContext({document, console, Map, URL, Intl, AbortController,
        Date:class extends Date {static now(){return now;}},
        setInterval(fn){timers.push(fn);return timers.length;}, clearInterval(){},
        addEventListener(){}, dispatchEvent(){}, CustomEvent:class {}, navigator:{},
        createColdTraceTruckMarker:()=>({}), mqtt:{connect:()=>({on(){},end(){}})},
        fetch:async()=>({ok:true,json:async()=>({data})})});
    c.window=c;
    const run = code=>vm.runInContext(code,c);
    run(render(scripts('resources/views/components/maps/live-location.blade.php')[0], {"(int) config('coldtrace.gps_timeout_seconds', 30) * 1000":30000}));
    const values={"$routeStops->values()":[], "$expectedDeviceCode":'ESP32-CT-1004',
        "$expectedTopic ?: 'coldtrace/trucks/+/telemetry'":'coldtrace/trucks/CT-1004/telemetry'};
    for (const script of scripts(`resources/views/driver/orders/${page}.blade.php`)) run(render(script,values));
    c.Marker=Marker;
    if(page==='index') run('driverOrdersRouteMap={}; AdvancedMarkerElementClass=Marker;');
    else run('coldTraceMap={}; AdvancedMarkerElementClass=Marker;');
    return {run, elements, timers, advance:ms=>now+=ms, setData:value=>data=value,
        poll:async()=>{await timers[1]();}};
}
const packet="{device_code:'ESP32-CT-1004', gps_valid:true, latitude:14.7, longitude:121.2, satellites:8, hdop:1}";
const deliver=`handleDriverTelemetryPayload(${packet},'coldtrace/trucks/CT-1004/telemetry')`;

test('driver marker expires without further messages and recovers at the same coordinates',()=>{
    const e=environment('index'); e.run(deliver);
    assert.ok(e.run('currentTruckMarker'));
    e.run('globalThis.oldMarker=currentTruckMarker;');
    e.advance(30000); e.timers[0]();
    assert.equal(e.run('oldMarker.map'),null);
    assert.equal(e.run('currentTruckMarker'),null);
    assert.equal(e.run('latestCurrentPosition'),null);
    e.run(deliver);
    assert.ok(e.run('currentTruckMarker'));
});

test('retained, stale, wrong-topic and out-of-order packets cannot revive or move a driver truck',()=>{
    const e=environment('index');
    e.run(`handleDriverTelemetryPayload(${packet},'coldtrace/trucks/CT-1004/telemetry',{retain:true})`);
    e.run(`handleDriverTelemetryPayload(${packet},'coldtrace/trucks/CT-1003/telemetry')`);
    e.run(`handleDriverTelemetryPayload({...${packet},recorded_at:new Date(Date.now()-31000).toISOString()},'coldtrace/trucks/CT-1004/telemetry')`);
    assert.equal(e.run('currentTruckMarker'),null);
    e.run(deliver); e.advance(1000);
    e.run(`handleDriverTelemetryPayload({...${packet},latitude:10,recorded_at:new Date(Date.now()-2000).toISOString()},'coldtrace/trucks/CT-1004/telemetry')`);
    assert.equal(e.run('latestCurrentPosition.lat'),14.7);
});

test('stopping browser location clears its marker and stale browser fixes are not republished', async()=>{
    const e=environment('index');
    e.run('rememberAccurateDevicePosition({timestamp:Date.now(),coords:{latitude:14.7,longitude:121.2,accuracy:10}})');
    assert.ok(e.run('currentTruckMarker'));
    e.run('stopSoftwareTelemetryFeed()');
    assert.equal(e.run('currentTruckMarker'),null);
    assert.equal(e.run('rememberAccurateDevicePosition({timestamp:Date.now()-31000,coords:{latitude:14.7,longitude:121.2,accuracy:10}})'),false);
});

test('detail polling cannot keep an old fix alive; null data and closed trips clear it; same-position reconnect works',async()=>{
    const e=environment('show');
    await new Promise(resolve=>setImmediate(resolve));
    const reading=()=>({trip_status:'in_progress',latitude:14.7,longitude:121.2,temperature:4,
        recorded_at:e.run('new Date(Date.now()).toISOString()'),gps_recorded_at:e.run('new Date(Date.now()).toISOString()')});
    const initial=reading(); e.setData(initial); await e.poll();
    assert.ok(e.run('currentTruckMarker'));
    e.advance(30000); e.timers[0]();
    assert.equal(e.run('currentTruckMarker'),null);
    await e.poll(); assert.equal(e.run('currentTruckMarker'),null);
    e.setData(reading()); await e.poll(); assert.ok(e.run('currentTruckMarker'));
    e.setData(null); await e.poll(); assert.equal(e.run('currentTruckMarker'),null);
    e.setData(reading()); await e.poll(); assert.ok(e.run('currentTruckMarker'));
    e.setData({...reading(),trip_status:'completed'}); await e.poll(); assert.equal(e.run('currentTruckMarker'),null);
});
