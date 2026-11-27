// Run with: node --test tests/Browser/NodeClientConnectivity.test.mjs
// Exercise the Alpine component's JavaScript without booting the PHP application.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

const template = readFileSync(new URL('../../resources/views/livewire/node-client-connectivity.blade.php', import.meta.url), 'utf8');

function component({ status = 401, error, websocket = null } = {}) {
    const requests = [];
    const sockets = [];
    const code = template.match(/x-data="([\s\S]*?)"\s+x-init=/)[1]
        .replace(/@if \(\$wsUrl && \$wsToken\)([\s\S]*?)@else([\s\S]*?)@endif/, (_, yes, no) => websocket ? yes : no)
        .replaceAll('{{ $httpUrl }}', 'https://wings.example.test')
        .replaceAll('{{ $wsUrl }}', 'wss://wings.example.test/api/servers/test/ws')
        .replaceAll('{{ $wsToken }}', 'test-token');

    class Socket {
        constructor(url) {
            this.url = url;
            sockets.push(this);
            queueMicrotask(() => {
                if (websocket === 'error') this.onerror();
                else if (websocket === 'suspended') this.onclose({ code: 4409 });
                else this.onopen();
            });
        }

        send(message) {
            this.message = JSON.parse(message);
            queueMicrotask(() => this.onmessage({ data: JSON.stringify({ event: 'auth success' }) }));
        }

        close() {
            this.closed = true;
        }
    }

    const state = runInNewContext(`(${code})`, {
        fetch: async (url, options) => {
            requests.push({ url, options });
            if (error) throw error;
            return { status };
        },
        AbortSignal,
        WebSocket: Socket,
        setTimeout,
        clearTimeout,
    });

    return { state, requests, sockets };
}

for (const status of [200, 401, 403, 404]) {
    test(`HTTP ${status} proves reachability without a WebSocket target`, async () => {
        const { state, requests } = component({ status });
        await state.check();
        assert.equal(state.status, 'online-no-ws');
        assert.equal(requests.length, 1);
        assert.equal(requests[0].url, 'https://wings.example.test');
        assert.equal(requests[0].options.mode, 'cors');
        assert.ok(requests[0].options.signal instanceof AbortSignal);
    });
}

for (const status of [500, 502, 503, 504]) {
    test(`HTTP ${status} is offline and skips the WebSocket probe`, async () => {
        const { state, sockets } = component({ status, websocket: 'success' });
        await state.check();
        assert.equal(state.status, 'offline');
        assert.equal(sockets.length, 0);
    });
}

for (const [name, error] of [
    ['CORS failure', new TypeError('Failed to fetch')],
    ['network failure', new TypeError('Network request failed')],
    ['HTTP timeout', new DOMException('The operation timed out', 'TimeoutError')],
]) {
    test(`${name} is offline and skips the WebSocket probe`, async () => {
        const { state, sockets } = component({ error, websocket: 'success' });
        await state.check();
        assert.equal(state.status, 'offline');
        assert.equal(sockets.length, 0);
    });
}

test('an authenticated WebSocket is online after the expected HTTP 401', async () => {
    const { state, sockets } = component({ websocket: 'success' });
    await state.check();
    assert.equal(state.status, 'online');
    assert.equal(sockets.length, 1);
    assert.equal(sockets[0].url, 'wss://wings.example.test/api/servers/test/ws');
    assert.deepEqual(sockets[0].message, { event: 'auth', args: ['test-token'] });
    assert.equal(sockets[0].closed, true);
});

test('a suspended server close code still proves WebSocket reachability', async () => {
    const { state } = component({ websocket: 'suspended' });
    await state.check();
    assert.equal(state.status, 'online');
});

test('WebSocket failure after a reachable HTTP endpoint is a warning', async () => {
    const { state } = component({ websocket: 'error' });
    await state.check();
    assert.equal(state.status, 'warning');
});
