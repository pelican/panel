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

const config = {
    binaryPrefix: false,
    period: 30,
    locale: 'en',
    offlineLabel: 'Offline',
    unknownLabel: '—',
    statusLabels: {},
};

const samples = [];
let currentState = null;

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

const labelsFor = (window) =>
    window.map((sample) => new Date(sample.t).toLocaleTimeString('en-GB', { hour12: false }));

const areaDataset = (data, backgroundColor = 'rgba(96, 165, 250, 0.3)', label = undefined) => ({
    ...(label === undefined ? {} : { label }),
    data,
    backgroundColor: [backgroundColor],
    tension: '0.3',
    fill: true,
});

window.ServerStats = {
    configure(options) {
        Object.assign(config, options);
    },

    push(stats) {
        samples.push({
            t: Date.now(),
            cpu: number(stats.cpu_absolute),
            memory: number(stats.memory_bytes),
            disk: number(stats.disk_bytes),
            rx: number(stats.network?.rx_bytes),
            tx: number(stats.network?.tx_bytes),
            uptime: number(stats.uptime),
        });

        if (samples.length > MAX_SAMPLES) {
            samples.splice(0, samples.length - MAX_SAMPLES);
        }

        if (typeof stats.state === 'string') {
            currentState = stats.state;
        }
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

    // Mirrors ServerNetworkChart::getData(). The PHP maps the first sample to null
    // and array_column() drops it, so the series is one shorter than the window.
    networkData() {
        const window = windowed();
        const rx = [];
        const tx = [];

        for (let i = 1; i < window.length; i++) {
            rx.push(Math.max(0, window[i].rx - window[i - 1].rx));
            tx.push(Math.max(0, window[i].tx - window[i - 1].tx));
        }

        return {
            datasets: [
                areaDataset(rx, 'rgba(100, 255, 105, 0.5)', 'Inbound'),
                areaDataset(tx, 'rgba(96, 165, 250, 0.3)', 'Outbound'),
            ],
            labels: labelsFor(window.slice(1)),
        };
    },
};
