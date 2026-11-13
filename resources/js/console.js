import { Terminal } from '@xterm/xterm';
import { FitAddon } from '@xterm/addon-fit';
import { WebLinksAddon } from '@xterm/addon-web-links';
import { SearchAddon } from '@xterm/addon-search';
import { SearchBarAddon } from 'xterm-addon-search-bar';
import { WebglAddon } from '@xterm/addon-webgl';

window.Xterm = {
    Terminal,
    WebglAddon,
    FitAddon,
    WebLinksAddon,
    SearchAddon,
    SearchBarAddon,
};

const MAX_SAMPLES = 120;
const MAX_SAMPLE_GAP = 5000;

const config = {
    uuid: null,
    binaryPrefix: false,
    period: 30,
    locale: 'en',
    timezone: null,
    offlineLabel: 'Offline',
    unknownLabel: '',
    statusLabels: {},
};

const samples = [];
let currentState = null;

// Keeps the charts from starting empty on every visit; sessionStorage can be
// unavailable (private mode) or hold junk, and either way we just start empty.
const storageKey = () => `pelican:console-stats:${config.uuid}`;

const restoreSamples = () => {
    if (!config.uuid || samples.length) {
        return;
    }

    try {
        const now = Date.now();
        const horizon = now - MAX_SAMPLES * 1000;
        const stored = JSON.parse(sessionStorage.getItem(storageKey()) ?? '[]');

        // The upper bound also rejects timestamps too large for a Date, which would throw in labelsFor().
        samples.push(...stored.filter((sample) => Number.isFinite(sample?.t) && sample.t >= horizon && sample.t <= now));
    } catch {}
};

const persistSamples = () => {
    if (!config.uuid) {
        return;
    }

    try {
        sessionStorage.setItem(storageKey(), JSON.stringify(samples));
    } catch {}
};

// Mirrors Carbon's diffForHumans(syntax: DIFF_ABSOLUTE, short: true, parts: 2),
// localized through Intl the same way Carbon translates its unit suffixes.
const SHORT_UNITS = [
    ['year', 31536000000],
    ['month', 2592000000],
    ['week', 604800000],
    ['day', 86400000],
    ['hour', 3600000],
    ['minute', 60000],
    ['second', 1000],
];

const formatUnit = (value, unit) => {
    const options = { style: 'unit', unit, unitDisplay: 'narrow' };

    try {
        return new Intl.NumberFormat(config.locale.replace('_', '-'), options).format(value);
    } catch {
        return new Intl.NumberFormat('en', options).format(value);
    }
};

const humanizeShort = (milliseconds, parts = 2) => {
    const found = [];
    let remaining = milliseconds;

    for (const [unit, size] of SHORT_UNITS) {
        if (found.length >= parts) {
            break;
        }

        const value = Math.floor(remaining / size);

        if (value > 0) {
            found.push(formatUnit(value, unit));
            remaining -= value * size;
        }
    }

    return found.length ? found.join(' ') : formatUnit(0, 'second');
};

const number = (value) => {
    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : 0;
};

const round = (value, decimals) => {
    const factor = 10 ** decimals;

    return Math.round(value * factor) / factor;
};

// Mirrors format_number() in app/helpers.php, including its fallback to the default locale.
const formatNumber = (value, decimals, minDecimals = 0) => {
    const options = {
        minimumFractionDigits: minDecimals,
        maximumFractionDigits: decimals,
    };

    try {
        return new Intl.NumberFormat(config.locale.replace('_', '-'), options).format(value);
    } catch {
        return new Intl.NumberFormat('en', options).format(value);
    }
};

// Mirrors convert_bytes_to_readable() in app/helpers.php.
const bytesToReadable = (bytes, decimals = 2) => {
    const suffixes = config.binaryPrefix
        ? ['Bytes', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB']
        : ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];

    if (bytes <= 0) {
        return `0 ${suffixes[0]}`;
    }

    const unit = config.binaryPrefix ? 1024 : 1000;
    const fromBase = Math.log(bytes) / Math.log(unit);
    const base = Math.min(Math.floor(fromBase), suffixes.length - 1);

    return `${formatNumber(unit ** (fromBase - base), decimals, decimals)} ${suffixes[base]}`;
};

const windowed = () => samples.slice(-config.period);

// The panel timezone preference wins over the browser's, like the old PHP labels.
const labelsFor = (window) => {
    try {
        return window.map((sample) =>
            new Date(sample.t).toLocaleTimeString('en-GB', { hour12: false, timeZone: config.timezone ?? undefined }),
        );
    } catch {
        return window.map((sample) => new Date(sample.t).toLocaleTimeString('en-GB', { hour12: false }));
    }
};

const areaDataset = (data, backgroundColor = 'rgba(96, 165, 250, 0.3)', label = undefined) => ({
    ...(label === undefined ? {} : { label }),
    data,
    backgroundColor: [backgroundColor],
    tension: '0.3',
    fill: true,
});

// The network and disk I/O counters are cumulative, so each point is the delta
// from the previous sample. Pairs further apart than MAX_SAMPLE_GAP (history
// restored after a revisit) would plot as one spike, as would a pair where either
// side lacks a counter (older Wings, or history saved before disk I/O existed),
// so both are skipped. A counter that went backwards (container restart) is clamped to 0.
const deltaSeries = (window, keys) => {
    const series = Object.fromEntries(keys.map((key) => [key, []]));
    const points = [];

    for (let i = 1; i < window.length; i++) {
        if (
            window[i].t - window[i - 1].t > MAX_SAMPLE_GAP ||
            keys.some((key) => window[i][key] == null || window[i - 1][key] == null)
        ) {
            continue;
        }

        for (const key of keys) {
            series[key].push(Math.max(0, number(window[i][key]) - number(window[i - 1][key])));
        }

        points.push(window[i]);
    }

    return { series, points };
};

window.ServerStats = {
    configure(options) {
        const previousUuid = config.uuid;

        Object.assign(config, options);

        // Livewire.navigate() can remount the console for another server in the
        // same document, so drop the previous server's in-memory history.
        if (config.uuid !== previousUuid) {
            samples.length = 0;
            currentState = null;
        }

        restoreSamples();
    },

    // Takes the raw stats frame from Wings. Malformed or non-object frames are
    // dropped, as the old server-side json_decode/is_object check did.
    push(frame) {
        let stats;

        try {
            stats = typeof frame === 'string' ? JSON.parse(frame) : frame;
        } catch {
            return false;
        }

        if (stats === null || Array.isArray(stats) || typeof stats !== 'object') {
            return false;
        }

        samples.push({
            t: Date.now(),
            cpu: number(stats.cpu_absolute),
            memory: number(stats.memory_bytes),
            disk: number(stats.disk_bytes),
            rx: number(stats.network?.rx_bytes),
            tx: number(stats.network?.tx_bytes),
            // Wings before v1.0.0-beta28 sends no disk_io; null lets the heading say so instead of showing 0.
            read: stats.disk_io ? number(stats.disk_io.read_bytes) : null,
            write: stats.disk_io ? number(stats.disk_io.write_bytes) : null,
            uptime: number(stats.uptime),
        });

        if (samples.length > MAX_SAMPLES) {
            samples.splice(0, samples.length - MAX_SAMPLES);
        }

        persistSamples();

        if (typeof stats.state === 'string') {
            currentState = stats.state;
        }

        return true;
    },

    setState(state) {
        currentState = state;
    },

    latest() {
        return samples.length ? samples[samples.length - 1] : null;
    },

    state() {
        return currentState;
    },

    statusText() {
        if (currentState === null) {
            return config.unknownLabel;
        }

        const label = config.statusLabels[currentState] ?? currentState;
        const uptime = this.latest()?.uptime ?? 0;

        return uptime === 0 ? label : `${label} (${humanizeShort(uptime)})`;
    },

    offlineLabel() {
        return config.offlineLabel;
    },

    unknownLabel() {
        return config.unknownLabel;
    },

    formatNumber,
    bytesToReadable,

    // Mirrors ServerCpuChart::getData().
    cpuData() {
        const window = windowed();

        return {
            datasets: [areaDataset(window.map((sample) => round(sample.cpu, 2)))],
            labels: labelsFor(window),
            locale: config.locale,
        };
    },

    // Mirrors ServerMemoryChart::getData().
    memoryData() {
        const window = windowed();
        const unit = config.binaryPrefix ? 1024 ** 3 : 1000 ** 3;

        return {
            datasets: [areaDataset(window.map((sample) => round(sample.memory / unit, 2)))],
            labels: labelsFor(window),
            locale: config.locale,
        };
    },

    // Mirrors ServerNetworkChart::getData().
    networkData() {
        const { series, points } = deltaSeries(windowed(), ['rx', 'tx']);

        return {
            datasets: [
                areaDataset(series.rx, 'rgba(100, 255, 105, 0.5)', 'Inbound'),
                areaDataset(series.tx, 'rgba(96, 165, 250, 0.3)', 'Outbound'),
            ],
            labels: labelsFor(points),
        };
    },

    // Mirrors ServerDiskIoChart::getData().
    diskIoData() {
        const { series, points } = deltaSeries(windowed(), ['read', 'write']);

        return {
            datasets: [
                areaDataset(series.read, 'rgba(100, 255, 105, 0.5)', 'Read'),
                areaDataset(series.write, 'rgba(96, 165, 250, 0.3)', 'Write'),
            ],
            labels: labelsFor(points),
        };
    },
};
