const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const script = fs.readFileSync('resources/views/components/route-speech.blade.php', 'utf8')
    .match(/<script>([\s\S]*?)<\/script>/)[1];

function environment(supported = true) {
    const elements = new Map(), spoken = [], pageEvents = {}, documentEvents = {};
    let cancellations = 0, current = true, throwOnSpeak = false;
    const element = id => {
        if (!elements.has(id)) elements.set(id, { disabled: false, textContent: '', events: {},
            addEventListener(name, fn) { this.events[name] = fn; } });
        return elements.get(id);
    };
    const document = { hidden: false, getElementById: element,
        addEventListener(name, fn) { documentEvents[name] = fn; } };
    const context = vm.createContext({ document,
        addEventListener(name, fn) { pageEvents[name] = fn; },
        ...(supported ? {
            speechSynthesis: { speak(utterance) { if (throwOnSpeak) throw new Error('No audio device'); spoken.push(utterance); },
                cancel() { cancellations++; } },
            SpeechSynthesisUtterance: class { constructor(text) { this.text = text; } },
        } : {}),
    });
    context.window = context;
    vm.runInContext(script, context);
    const read = element('readRecommendationButton');
    const stop = element('stopRecommendationButton');
    const status = element('recommendationSpeechStatus');
    return { spoken, read, stop, status, document,
        set: parts => context.ColdTraceSpeech.setRecommendation(parts, () => current),
        clear: () => context.ColdTraceSpeech.clear(),
        clickRead: () => read.events.click(), clickStop: () => stop.events.click(),
        expire: () => { current = false; }, fail: () => { throwOnSpeak = true; },
        hide: () => { document.hidden = true; documentEvents.visibilitychange(); },
        leave: () => pageEvents.pagehide(), cancellations: () => cancellations,
    };
}

test('speech starts only on demand and reads the explanation and every warning without truncation', () => {
    const e = environment();
    assert.equal(e.read.disabled, true);
    const explanation = 'Choose the route that reaches the first delivery earlier. '.repeat(10).trim();
    const warning = 'Warning: RSL is low. MKT needs attention.';
    e.set(['AI route recommendation.', explanation, warning]);
    assert.equal(e.spoken.length, 0, 'setting a recommendation never starts audio');
    assert.equal(e.read.disabled, false);
    e.clickRead();
    assert.equal(e.stop.disabled, false);
    e.clickRead();
    assert.equal(e.spoken.length, 1, 'repeated clicks cannot overlap playback');
    for (let index = 0; index < e.spoken.length; index++) e.spoken[index].onend();
    assert.equal(e.spoken.map(item => item.text).join(' '),
        `AI route recommendation. ${explanation} Warning: remaining shelf life is low. mean kinetic temperature needs attention.`);
    assert.ok(e.spoken.every(item => item.text.length <= 220));
    assert.equal(e.stop.disabled, true);
    assert.equal(e.read.disabled, false);
    assert.match(e.status.textContent, /Finished reading/);
});

test('Stop cancels all remaining speech and ignores late callbacks from the stopped utterance', () => {
    const e = environment();
    e.set(['Long recommendation. '.repeat(30)]); e.clickRead();
    const old = e.spoken[0];
    e.clickStop(); old.onend(); old.onerror();
    assert.equal(e.cancellations(), 1);
    assert.equal(e.spoken.length, 1);
    assert.equal(e.stop.disabled, true);
    assert.equal(e.status.textContent, 'Reading stopped.');
    e.clickRead();
    assert.equal(e.spoken.length, 2, 'the driver can restart after stopping');
});

test('changed recommendations stop playback while identical refreshes keep it playing', () => {
    const e = environment();
    e.set(['Original route.', 'Warning one.']); e.clickRead();
    const old = e.spoken[0];
    e.set(['Original route.', 'Warning one.']);
    assert.equal(e.cancellations(), 0);
    e.set(['New route.', 'Warning two.']);
    assert.equal(e.cancellations(), 1);
    old.onend();
    assert.equal(e.spoken.length, 1, 'updated advice is not read automatically');
    e.clickRead();
    assert.equal(e.spoken[1].text, 'New route. Warning two.');
});

test('expired routes cannot be read and cleared recommendations cancel current playback', () => {
    const e = environment();
    e.set(['Route advice']); e.expire(); e.clickRead();
    assert.equal(e.spoken.length, 0);
    assert.equal(e.read.disabled, true);
    const playing = environment();
    playing.set(['Route advice']); playing.clickRead(); playing.clear();
    assert.equal(playing.cancellations(), 1);
    assert.equal(playing.read.disabled, true);
});

test('leaving or hiding the page stops audio', () => {
    for (const event of ['hide', 'leave']) {
        const e = environment(); e.set(['Route advice']); e.clickRead(); e[event]();
        assert.equal(e.cancellations(), 1);
        assert.equal(e.stop.disabled, true);
    }
});

test('unsupported browsers keep text available and disable speech controls', () => {
    const e = environment(false);
    e.set(['Route advice']); e.clickRead();
    assert.match(e.status.textContent, /unavailable in this browser/);
    assert.equal(e.read.disabled, true);
    assert.equal(e.stop.disabled, true);
    assert.equal(e.spoken.length, 0);
});

test('speech errors restore usable controls and give a readable error', () => {
    for (const throws of [true, false]) {
        const e = environment(); e.set(['Route advice']);
        if (throws) e.fail();
        e.clickRead();
        if (!throws) e.spoken[0].onerror();
        assert.match(e.status.textContent, /Audio could not play/);
        assert.equal(e.read.disabled, false);
        assert.equal(e.stop.disabled, true);
    }
});
