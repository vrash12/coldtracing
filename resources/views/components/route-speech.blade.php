<div class="route-speech-controls" role="group" aria-label="Spoken recommendation">
    <button type="button" class="secondary-button" id="readRecommendationButton" aria-describedby="recommendationSpeechStatus" disabled>
        <i class="bi bi-volume-up" aria-hidden="true"></i>
        Read Recommendation
    </button>
    <button type="button" class="secondary-button" id="stopRecommendationButton" aria-label="Stop reading recommendation" disabled>
        <i class="bi bi-stop-circle" aria-hidden="true"></i>
        Stop
    </button>
    <small id="recommendationSpeechStatus" role="status" aria-live="polite">Calculate a route to enable spoken recommendations.</small>
</div>

@once
@push('styles')
<style>
    .route-speech-controls { display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin:12px 0 16px; }
    .route-speech-controls .secondary-button { min-height:44px; }
    .route-speech-controls button:disabled { opacity:.5; cursor:not-allowed; }
    .route-speech-controls button:focus-visible { outline:3px solid #0e7490; outline-offset:3px; }
    .route-speech-controls small { color:#475569; flex:1 1 230px; font-size:12px; line-height:1.5; }
</style>
@endpush
@push('scripts')
<script>
    (() => {
        const readButton = document.getElementById('readRecommendationButton');
        const stopButton = document.getElementById('stopRecommendationButton');
        const status = document.getElementById('recommendationSpeechStatus');
        if (!readButton || !stopButton || !status) return;

        const synth = window.speechSynthesis;
        const supported = !!synth && typeof synth.speak === 'function'
            && typeof synth.cancel === 'function' && typeof window.SpeechSynthesisUtterance === 'function';
        const unavailable = 'Spoken recommendations are unavailable in this browser. You can read the recommendation on screen.';
        let text = '';
        let isCurrent = () => false;
        let speaking = false;
        let version = 0;
        let activeUtterance = null;

        function update(message) {
            readButton.disabled = !supported || !text || speaking;
            stopButton.disabled = !supported || !speaking;
            status.textContent = supported ? message : unavailable;
        }

        function stop(message = 'Reading stopped.') {
            version++;
            const wasSpeaking = speaking;
            speaking = false;
            activeUtterance = null;
            if (supported && wasSpeaking) synth.cancel();
            update(message);
        }

        function clear(message = 'Calculate a route to enable spoken recommendations.') {
            text = '';
            isCurrent = () => false;
            stop(message);
        }

        function setRecommendation(parts, current) {
            const nextText = [...new Set(parts.filter(part => typeof part === 'string')
                .map(part => part.replace(/\s+/g, ' ').trim()).filter(Boolean))].join(' ')
                .replace(/\bRSL\b/g, 'remaining shelf life').replace(/\bMKT\b/g, 'mean kinetic temperature');
            isCurrent = typeof current === 'function' ? current : () => false;
            if (nextText === text) return;
            const wasSpeaking = speaking;
            text = nextText;
            stop(wasSpeaking ? 'Recommendation updated. Tap Read Recommendation to hear it.' : 'Ready to read the recommendation and warnings.');
        }

        // Short utterances avoid long-text playback failures and keep all warnings.
        function chunks(value) {
            const result = [];
            while (value.length > 220) {
                const boundary = value.lastIndexOf(' ', 220);
                const end = boundary > 0 ? boundary : 220;
                result.push(value.slice(0, end));
                value = value.slice(end).trimStart();
            }
            if (value) result.push(value);
            return result;
        }

        function read() {
            if (!supported || !text || speaking) return;
            if (!isCurrent()) {
                clear('This recommendation is no longer current. Calculate a new route first.');
                return;
            }
            const sentences = chunks(text);
            const requestVersion = ++version;
            speaking = true;
            update('Reading recommendation and warnings…');

            function next() {
                if (version !== requestVersion) return;
                if (!isCurrent()) {
                    clear('This recommendation is no longer current. Calculate a new route first.');
                    return;
                }
                if (!sentences.length) {
                    speaking = false;
                    activeUtterance = null;
                    update('Finished reading. Tap Read Recommendation to listen again.');
                    return;
                }
                try {
                    activeUtterance = new window.SpeechSynthesisUtterance(sentences.shift());
                    activeUtterance.lang = 'en-US';
                    activeUtterance.rate = 1;
                    activeUtterance.onend = next;
                    activeUtterance.onerror = () => {
                        if (version === requestVersion) stop('Audio could not play. Check your device volume or try again.');
                    };
                    synth.speak(activeUtterance);
                } catch (_) {
                    stop('Audio could not play. Try another browser or read the recommendation on screen.');
                }
            }
            next();
        }

        readButton.addEventListener('click', read);
        stopButton.addEventListener('click', () => stop());
        window.addEventListener('pagehide', () => stop());
        document.addEventListener('visibilitychange', () => {
            if (document.hidden && speaking) stop();
        });
        window.ColdTraceSpeech = { setRecommendation, clear };
        update('Calculate a route to enable spoken recommendations.');
    })();
</script>
@endpush
@endonce
