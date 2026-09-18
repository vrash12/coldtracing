const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

function pageScript() {
    const source = fs.readFileSync('resources/views/driver/orders/show.blade.php', 'utf8');
    let script = [...source.matchAll(/<script>([\s\S]*?)<\/script>/g)]
        .map(match => match[1]).find(script => script.includes('let coldTraceMap = null;'));
    while (script.includes('@json(')) {
        const start = script.indexOf('@json(');
        let end = start + 6, depth = 1;
        while (depth) {
            const char = script[end++];
            if (char === '(') depth++;
            if (char === ')') depth--;
        }
        script = script.slice(0, start) + 'null' + script.slice(end);
    }
    return script;
}

function environment() {
    const elements = new Map(), requests = [];
    let fresh = true;
    const element = () => ({ innerText: '', innerHTML: '',
        classList: { add() {}, remove() {} }, appendChild() {} });
    const getElement = id => {
        if (!elements.has(id)) elements.set(id, element());
        return elements.get(id);
    };
    const context = vm.createContext({
        console: { error() {} },
        document: { getElementById: getElement, querySelector: getElement,
            addEventListener() {}, createElement: element },
        ColdTraceLocation: { fresh: () => fresh }, detailGpsRecordedAt: Date.now(),
        google: { maps: { LatLngBounds: class { extend() {} } } },
        fetch: async (url, options) => new Promise((resolve, reject) => {
            requests.push({ body: JSON.parse(options.body), reject,
                respond(recommendation) {
                    resolve({ ok: true, json: async () => ({ success: true, recommendation }) });
                } });
        }),
    });
    context.window = context;
    const run = code => vm.runInContext(code, context);
    run(pageScript());
    run(`
        coldTraceMap = { fitBounds() {} };
        latestCurrentPosition = { lat: 14.7, lng: 121.2 };
        latestScoredRoutes = [
            { originalIndex: 9, score: 0.2, durationSeconds: 600, distanceMeters: 5000,
              route: { duration: '600s', distanceMeters: 5000, legs: [] } },
            { originalIndex: 2, score: 0.3, durationSeconds: 900, distanceMeters: 7000,
              route: { duration: '900s', distanceMeters: 7000, legs: [] } }
        ];
        recommendedRouteOriginalIndex = 9;
        selectRouteOption(9);
    `);
    return { run, requests, elements, getElement, expire: () => { fresh = false; } };
}

const recommendation = source => ({ recommended_route_id: 'route_2', decision_source: source,
    risk_level: 'warning', driver_action: 'Take the second route.', reason: 'Protect the cargo.',
    cold_chain_warning: 'Monitor the cargo temperature.' });

test('AI route ids use the sent sorted options and update the route, ETA, and recommendation label', async () => {
    const e = environment();
    const pending = e.run('requestOpenAiRouteRecommendation(latestScoredRoutes)');
    assert.equal(e.requests[0].body.route_options[1].original_index, 2);
    e.requests[0].respond(recommendation('openai'));
    await pending;

    assert.equal(e.run('selectedRouteOriginalIndex'), 2);
    assert.equal(e.run('recommendedRouteOriginalIndex'), 2);
    assert.equal(e.getElement('etaTravelTime').innerText, '15 min');
    assert.equal(e.getElement('aiDistance').innerText, '7.0 km');
    assert.equal(e.getElement('aiRecommendationBadge').innerText, 'AI: WARNING');
    assert.match(e.getElement('alternateRouteList').innerHTML, /Recommended Route 2/);
    assert.equal(e.requests.length, 1, 'applying the chosen route must not request AI again');
});

test('server fallback applies its selected route and is clearly labeled rule-based', async () => {
    const e = environment();
    const pending = e.run('requestOpenAiRouteRecommendation(latestScoredRoutes)');
    e.requests[0].respond(recommendation('deterministic_fallback'));
    await pending;
    assert.equal(e.run('selectedRouteOriginalIndex'), 2);
    assert.equal(e.getElement('aiRecommendationBadge').innerText, 'Rule-based: WARNING');
    assert.match(e.getElement('aiReason').innerText, /AI recommendation is unavailable/);
});

for (const change of ['route recalculation', 'GPS expiry', 'manual route selection']) {
    test(`late AI success is ignored after ${change}`, async () => {
        const e = environment();
        const pending = e.run('requestOpenAiRouteRecommendation(latestScoredRoutes)');
        if (change === 'route recalculation') e.run('routeRequestVersion++');
        if (change === 'GPS expiry') { e.expire(); e.run('clearCurrentGpsMarker()'); }
        if (change === 'manual route selection') e.run('selectRouteOption(9)');
        const selected = e.run('selectedRouteOriginalIndex');
        const badge = e.getElement('aiRecommendationBadge').innerText;
        e.requests[0].respond(recommendation('openai'));
        await pending;
        assert.equal(e.run('selectedRouteOriginalIndex'), selected);
        assert.equal(e.getElement('aiRecommendationBadge').innerText, badge);
    });
}

test('a newer AI request wins even when an older request completes afterward', async () => {
    const e = environment();
    const older = e.run('requestOpenAiRouteRecommendation(latestScoredRoutes)');
    const newer = e.run('requestOpenAiRouteRecommendation(latestScoredRoutes)');
    e.requests[1].respond(recommendation('openai'));
    await newer;
    e.requests[0].respond({ ...recommendation('openai'), recommended_route_id: 'route_1' });
    await older;
    assert.equal(e.run('selectedRouteOriginalIndex'), 2);
});

test('expired GPS prevents new AI requests and late failures cannot replace the cleared panel', async () => {
    const e = environment();
    const pending = e.run('requestOpenAiRouteRecommendation(latestScoredRoutes)');
    e.expire();
    e.run('clearCurrentGpsMarker()');
    const badge = e.getElement('aiRecommendationBadge').innerText;
    e.requests[0].reject(new Error('network unavailable'));
    await pending;
    assert.equal(e.getElement('aiRecommendationBadge').innerText, badge);
    await e.run('requestOpenAiRouteRecommendation(latestScoredRoutes)');
    assert.equal(e.requests.length, 1);
    assert.equal(e.run('selectedRouteOriginalIndex'), null);
});

test('an unknown route id cannot change the selected route or claim an AI recommendation', async () => {
    const e = environment();
    const pending = e.run('requestOpenAiRouteRecommendation(latestScoredRoutes)');
    e.requests[0].respond({ ...recommendation('openai'), recommended_route_id: 'route_99' });
    await pending;
    assert.equal(e.run('selectedRouteOriginalIndex'), 9);
    assert.equal(e.getElement('aiRecommendationBadge').innerText, 'Rule-based route');
});
