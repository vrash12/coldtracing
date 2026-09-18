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
function environment(page, overrides = {}) {
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
        setInterval(fn){timers.push(fn);return timers.length;}, clearInterval(){}, setTimeout, clearTimeout,
        addEventListener(){}, dispatchEvent(){}, CustomEvent:class {}, navigator:{},
        createColdTraceTruckMarker:()=>({}), mqtt:{connect:()=>({on(){},end(){}})},
        fetch:async()=>({ok:true,json:async()=>({data})})});
    c.window=c;
    const run = code=>vm.runInContext(code,c);
    run(render(scripts('resources/views/components/maps/live-location.blade.php')[0], {"(int) config('coldtrace.gps_timeout_seconds', 30) * 1000":30000}));
    const values={"$routeStops->values()":[], "$expectedDeviceCode":'ESP32-CT-1004',
        "$expectedTopic ?: 'coldtrace/trucks/+/telemetry'":'coldtrace/trucks/CT-1004/telemetry', ...overrides};
    for (const script of scripts(`resources/views/driver/orders/${page}.blade.php`)) run(render(script,values));
    c.Marker=Marker;
    if(page==='index') run('driverOrdersRouteMap={}; AdvancedMarkerElementClass=Marker;');
    else run('coldTraceMap={}; AdvancedMarkerElementClass=Marker;');
    return {run, elements, timers, advance:ms=>now+=ms, setData:value=>data=value, setFetch:fn=>c.fetch=fn,
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

const routeStops = [
    {id:1, order_code:'ORD-1', lat:15.1, lng:121.1, products:[]},
    {id:2, order_code:'ORD-2', lat:15.2, lng:121.2, products:[]},
];
function optimizerEnvironment() {
    const e=environment('index', {"$routeStops->values()":routeStops, "route('driver.orders.optimize')":'/driver/orders/optimize'});
    e.run(deliver);
    e.run(`google={maps:{Polyline:class {constructor(options){this.options=options;}setMap(map){this.options.map=map;}}}};`);
    return e;
}
function routeResponse(source='openai') {
    return {success:true,result:{orderedStops:[...routeStops].reverse(),strategy:'Road routes compared',
        route:{distanceMeters:4000,duration:'600s',polyline:{encodedPolyline:'_p~iF~ps|U_ulLnnqC_mqNvxq`@'}},
        recommendation:{decision_source:source,reason:'Protect cargo <script>alert(1)</script>',risk_level:'warning'},warnings:[]}};
}

test('all-orders button posts only fresh origin and displays the actual AI-selected road route',async()=>{
    const e=optimizerEnvironment(); let request;
    e.setFetch(async(url,options)=>{request={url,...options};return {ok:true,json:async()=>routeResponse()};});
    await e.run('optimizeDriverOrdersRoute(false)');
    assert.equal(request.url,'/driver/orders/optimize');
    const body=JSON.parse(request.body);
    assert.deepEqual(Object.keys(body),['origin']);
    assert.equal(body.origin.lat,14.7);
    assert.ok(body.origin.recorded_at);
    assert.equal(e.run('optimizedStops[0].id'),2);
    assert.equal(e.run('routePolyline.options.path[0].lat'),38.5); // Decoded Google geometry, not a line to the first stop.
    assert.equal(e.elements.get('routeSequenceTitle').innerText,'AI recommended sequence');
    assert.match(e.elements.get('routeMessage').innerHTML,/&lt;script&gt;/);
    assert.equal(e.run('optimizationController'),null);
});

test('Google configuration failure clears previous routes without inventing a replacement path',async()=>{
    const e=optimizerEnvironment();
    e.setFetch(async()=>({ok:true,json:async()=>routeResponse()}));
    await e.run('optimizeDriverOrdersRoute(false)');
    e.run('globalThis.previousPolyline=routePolyline');
    e.setFetch(async()=>({ok:false,status:503,json:async()=>({success:false,code:'routes_disabled',message:'Enable Google Routes API.'})}));
    await e.run('optimizeDriverOrdersRoute(false)');
    assert.equal(e.run('previousPolyline.options.map'),null);
    assert.equal(e.run('routePolyline'),null);
    assert.equal(e.run('routeHasBeenOptimized'),false);
    assert.equal(e.elements.get('routeMessage').innerHTML,'Enable Google Routes API.');
    assert.equal(e.elements.get('optimizedDistance').innerText,'—');
    assert.ok(e.run('currentTruckMarker'));
});

test('a valid road route with unavailable AI is labelled as an automatic recommendation',async()=>{
    const e=optimizerEnvironment();
    e.setFetch(async()=>({ok:true,json:async()=>routeResponse('deterministic_fallback')}));
    await e.run('optimizeDriverOrdersRoute(false)');
    assert.ok(e.run('routePolyline'));
    assert.equal(e.elements.get('routeSequenceTitle').innerText,'Automatic route recommendation');
    assert.equal(e.elements.get('routeStatusBadge').innerText,'Road route ready');
    assert.match(e.elements.get('routeMessage').innerHTML,/AI is unavailable/);
});

test('an in-flight route cannot restore a disconnected truck or an expired route',async()=>{
    const e=optimizerEnvironment(); let resolve, signal;
    e.setFetch((url,options)=>{signal=options.signal;return new Promise(r=>resolve=r);});
    const pending=e.run('optimizeDriverOrdersRoute(false)');
    e.advance(30000); e.timers[0]();
    assert.equal(signal.aborted,true);
    resolve({ok:true,json:async()=>routeResponse()}); await pending;
    assert.equal(e.run('currentTruckMarker'),null);
    assert.equal(e.run('routePolyline'),null);
    assert.equal(e.run('lastRouteResult'),null);
    assert.equal(e.elements.get('routeStatusBadge').innerText,'Waiting for live GPS');
});

test('a newer optimization request supersedes an older recommendation',async()=>{
    const e=optimizerEnvironment(); const pending=[];
    e.setFetch((url,options)=>new Promise(resolve=>pending.push({resolve,signal:options.signal})));
    const first=e.run('optimizeDriverOrdersRoute(false)');
    const second=e.run('optimizeDriverOrdersRoute(false)');
    assert.equal(pending[0].signal.aborted,true);
    pending[1].resolve({ok:true,json:async()=>routeResponse()}); await second;
    pending[0].resolve({ok:true,json:async()=>routeResponse('deterministic_fallback')}); await first;
    assert.equal(e.elements.get('routeSequenceTitle').innerText,'AI recommended sequence');
});

test('long delivery plans open the first stop instead of exceeding Google Maps mobile waypoint limits',()=>{
    const e=optimizerEnvironment();
    const url=new URL(e.run('buildGoogleMapsMultiStopUrl(Array.from({length:12},(_,i)=>({lat:15+i/100,lng:121+i/100})))'));
    assert.equal(url.searchParams.get('destination'),'15,121');
    assert.equal(url.searchParams.has('waypoints'),false);
    const shortUrl=new URL(e.run('buildGoogleMapsMultiStopUrl(deliveryStops)'));
    assert.equal(shortUrl.searchParams.get('waypoints'),'15.1,121.1');
    assert.equal(shortUrl.searchParams.get('destination'),'15.2,121.2');
});
