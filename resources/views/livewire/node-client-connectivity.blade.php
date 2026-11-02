<div
    x-data="{
        status: 'loading',
        async check() {
            try {
                await fetch('{{ $httpUrl }}', { mode: 'no-cors', signal: AbortSignal.timeout(5000) });
            } catch (e) {
                this.status = 'offline';
                return;
            }

            @if ($wsUrl && $wsToken)
                try {
                    await new Promise((resolve, reject) => {
                        const timeout = setTimeout(() => {
                            ws.close();
                            reject(new Error('timeout'));
                        }, 10000);

                        const ws = new WebSocket('{{ $wsUrl }}');

                        ws.onerror = () => {
                            clearTimeout(timeout);
                            ws.close();
                            reject(new Error('ws_error'));
                        };

                        // Wings closes a suspended server's socket with 4409 before auth,
                        // which still proves the websocket reached Wings
                        ws.onclose = (event) => {
                            if (event.code === 4409) {
                                clearTimeout(timeout);
                                resolve();
                            }
                        };

                        ws.onopen = () => {
                            ws.send(JSON.stringify({ event: 'auth', args: ['{{ $wsToken }}'] }));
                        };

                        ws.onmessage = (event) => {
                            const data = JSON.parse(event.data);
                            if (data.event === 'auth success') {
                                clearTimeout(timeout);
                                ws.close();
                                resolve();
                            }
                        };
                    });
                    this.status = 'online';
                } catch (e) {
                    this.status = 'warning';
                }
            @else
                this.status = 'online-no-ws';
            @endif
        }
    }"
    x-init="check()"
>
    <div x-show="status === 'loading'" x-cloak>
        {!! $loadingIcon !!}
    </div>
    <div x-show="status === 'offline'" x-cloak>
        {!! $offlineIcon !!}
    </div>
    <div x-show="status === 'online'" x-cloak>
        {!! $onlineIcon !!}
    </div>
    <div x-show="status === 'warning'" x-cloak>
        {!! $warningIcon !!}
    </div>
    <div x-show="status === 'online-no-ws'" x-cloak>
        {!! $onlineNoWsIcon !!}
    </div>
</div>
