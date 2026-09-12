<x-filament::widget>
    @assets
    @php
        $userFont = (string) user()?->getCustomization(\App\Enums\CustomizationKey::ConsoleFont);
        $userFontSize = (int) user()?->getCustomization(\App\Enums\CustomizationKey::ConsoleFontSize);
        $userRows = (int) user()?->getCustomization(\App\Enums\CustomizationKey::ConsoleRows);

        $terminalPrelude = str(config('app.name'))->slug()->lower()->toString();

        $componentName = fn (string $class) => app('livewire.finder')->normalizeName($class);
    @endphp
    @if($userFont !== "monospace")
        <link rel="preload" href="{{ asset("storage/fonts/{$userFont}.ttf") }}" as="font" crossorigin>
        <style>
            @font-face {
                font-family: '{{ $userFont }}';
                src: url('{{ asset("storage/fonts/{$userFont}.ttf") }}');
            }
        </style>
    @endif
    @vite(['resources/js/console.js', 'resources/css/console.css'])
    @endassets

    <div id="terminal" wire:ignore></div>

    @if ($this->authorizeSendCommand())
        <div class="flex items-center w-full border-top overflow-hidden dark:bg-gray-900"
             style="border-bottom-right-radius: 10px; border-bottom-left-radius: 10px;">
            <x-filament::icon
                icon="tabler-chevrons-right"
            />
            <input
                id="send-command"
                class="w-full focus:outline-none focus:ring-0 border-none dark:bg-gray-900 p-1"
                type="text"
                :readonly="{{ $this->canSendCommand() ? 'false' : 'true' }}"
                title="{{ $this->canSendCommand() ? '' : trans('server/console.command_blocked_title') }}"
                placeholder="{{ $this->canSendCommand() ? trans('server/console.command') : trans('server/console.command_blocked') }}"
                wire:model="input"
                wire:keydown.enter="enter"
                wire:keydown.up.prevent="up"
                wire:keydown.down="down"
            >
        </div>
    @endif

    @script
    <script>
        let theme = {
            background: 'rgba(19,26,32,0.7)',
            cursor: 'transparent',
            black: '#000000',
            red: '#E54B4B',
            green: '#9ECE58',
            yellow: '#FAED70',
            blue: '#396FE2',
            magenta: '#BB80B3',
            cyan: '#2DDAFD',
            white: '#d0d0d0',
            brightBlack: 'rgba(255, 255, 255, 0.2)',
            brightRed: '#FF5370',
            brightGreen: '#C3E88D',
            brightYellow: '#FFCB6B',
            brightBlue: '#82AAFF',
            brightMagenta: '#C792EA',
            brightCyan: '#89DDFF',
            brightWhite: '#ffffff',
            selection: '#FAF089'
        };

        let options = {
            fontSize: {{ $userFontSize }},
            fontFamily: '{{ $userFont }}, monospace',
            lineHeight: 1.2,
            disableStdin: true,
            cursorStyle: 'underline',
            cursorInactiveStyle: 'underline',
            allowTransparency: true,
            rows: {{ $userRows }},
            theme: theme
        };

        const { Terminal, FitAddon, WebLinksAddon, SearchAddon, SearchBarAddon, WebglAddon } = window.Xterm;

        const terminal = new Terminal(options);
        const fitAddon = new FitAddon();
        const webLinksAddon = new WebLinksAddon();
        const searchAddon = new SearchAddon();
        const searchAddonBar = new SearchBarAddon({ searchAddon });
        const webglAddon = new WebglAddon();
        terminal.loadAddon(fitAddon);
        terminal.loadAddon(webLinksAddon);
        terminal.loadAddon(searchAddon);
        terminal.loadAddon(searchAddonBar);
        terminal.loadAddon(webglAddon);

        terminal.open(document.getElementById('terminal'));

        fitAddon.fit(); // Fixes SPA issues.

        window.addEventListener('load', () => {
            fitAddon.fit();
        });

        window.addEventListener('resize', () => {
            fitAddon.fit();
        });

        terminal.attachCustomKeyEventHandler((event) => {
            if ((event.ctrlKey || event.metaKey) && event.key === 'c') {
                navigator.clipboard.writeText(terminal.getSelection());
                return false;
            } else if ((event.ctrlKey || event.metaKey) && event.key === 'f') {
                event.preventDefault();
                searchAddonBar.show();
                return false;
            } else if (event.key === 'Escape') {
                searchAddonBar.hidden();
            }
            return true;
        });

        const TERMINAL_PRELUDE = '\u001b[1m\u001b[33m{{ $terminalPrelude }}@' + '{{ \Filament\Facades\Filament::getTenant()->name }}' + ' ~ \u001b[0m';

        const handleConsoleOutput = (line, prelude = false) =>
            terminal.writeln((prelude ? TERMINAL_PRELUDE : '') + line.replace(/(?:\r\n|\r|\n)$/im, '') + '\u001b[0m');

        const handleTransferStatus = (status) =>
            status === 'failure' && terminal.writeln(TERMINAL_PRELUDE + 'Transfer has failed.\u001b[0m');

        const handleDaemonErrorOutput = (line) =>
            terminal.writeln(TERMINAL_PRELUDE + '\u001b[1m\u001b[41m' + line.replace(/(?:\r\n|\r|\n)$/im, '') + '\u001b[0m');

        const handlePowerChangeEvent = (state) =>
            terminal.writeln(TERMINAL_PRELUDE + 'Server marked as ' + state + '...\u001b[0m');

        let socket;
        let reconnectAttempts = 0;

        window.ServerStats.configure({
            uuid: @js($this->server->uuid),
            binaryPrefix: @js((bool) config('panel.use_binary_prefix')),
            period: @js((int) user()?->getCustomization(\App\Enums\CustomizationKey::ConsoleGraphPeriod)),
            locale: @js(str_replace('_', '-', user()->language ?? 'en')),
            offlineLabel: @js(\App\Enums\ContainerStatus::Offline->getLabel()),
            statusLabels: @js(collect(\App\Enums\ContainerStatus::cases())->mapWithKeys(fn ($case) => [$case->value => $case->getLabel()])),
        });

        let statusIsLive = @js($this->server->status === null);

        const setStatText = (id, value) => {
            const element = document.getElementById(id);

            if (element) {
                element.textContent = value;
            }
        };

        const pushToWidgets = () => {
            Livewire.dispatchTo(@js($componentName(\App\Filament\Server\Widgets\ServerCpuChart::class)), 'updateChartData', { data: window.ServerStats.cpuData() });
            Livewire.dispatchTo(@js($componentName(\App\Filament\Server\Widgets\ServerMemoryChart::class)), 'updateChartData', { data: window.ServerStats.memoryData() });
            Livewire.dispatchTo(@js($componentName(\App\Filament\Server\Widgets\ServerNetworkChart::class)), 'updateChartData', { data: window.ServerStats.networkData() });

            const latest = window.ServerStats.latest();
            const state = window.ServerStats.state();

            if (latest) {
                setStatText('server-network-heading', `- ↓${window.ServerStats.bytesToReadable(latest.rx)} - ↑${window.ServerStats.bytesToReadable(latest.tx)}`);
                setStatText('server-stat-disk', latest.disk === 0 ? window.ServerStats.unknownLabel() : window.ServerStats.bytesToReadable(latest.disk));
            }

            const statValue = (format) => {
                if (state === 'offline') {
                    return window.ServerStats.offlineLabel();
                }

                return state === null || !latest ? window.ServerStats.unknownLabel() : format(latest);
            };

            setStatText('server-stat-cpu', statValue((sample) => `${window.ServerStats.formatNumber(sample.cpu, 2, 0)} %`));
            setStatText('server-stat-memory', statValue((sample) => window.ServerStats.bytesToReadable(sample.memory)));

            if (statusIsLive) {
                setStatText('server-stat-status', window.ServerStats.statusText());
            }
        };

        const connect = () => {
            socket = new WebSocket("{{ $this->getSocket() }}");

            // A dropped socket would otherwise leave the page frozen at its last
            // values, so reconnect quietly and only raise the banner once that fails.
            socket.onclose = (event) => {
                if (reconnectAttempts >= 5) {
                    $wire.dispatchSelf('websocket-error');

                    return;
                }

                reconnectAttempts++;
                setTimeout(connect, 2000);
            };

            socket.onmessage = function(websocketMessageEvent) {
                let { event, args } = JSON.parse(websocketMessageEvent.data);

                switch (event) {
                    case 'console output':
                    case 'install output':
                        handleConsoleOutput(args[0]);
                        break;
                    case 'install completed':
                        statusIsLive = true;
                        $wire.dispatch('refresh-sidebar');
                        $wire.dispatch('refresh-topbar');
                        $wire.dispatch('removeAlertBanner', { id: 'server_conflict' });
                        break;
                    case 'feature match':
                        Livewire.dispatch('mount-feature', { data: args[0] });
                        break;
                    case 'status':
                        handlePowerChangeEvent(args[0]);
                        window.ServerStats.setState(args[0]);
                        pushToWidgets();
                        $wire.dispatch('console-status', { state: args[0] });
                        break;
                    case 'transfer status':
                        handleTransferStatus(args[0]);
                        break;
                    case 'daemon error':
                        handleDaemonErrorOutput(args[0]);
                        break;
                    case 'stats':
                        window.ServerStats.push(JSON.parse(args[0]));
                        pushToWidgets();
                        break;
                    case 'auth success':
                        reconnectAttempts = 0;
                        socket.send(JSON.stringify({
                            'event': 'send logs',
                            'args': [null]
                        }));
                        break;
                    case 'token expiring':
                    case 'token expired':
                        $wire.dispatchSelf('token-request');
                        break;
                }
            };

            socket.onopen = (event) => {
                $wire.dispatchSelf('token-request');
            };
        };

        connect();

        Livewire.on('setServerState', ({ state, uuid }) => {
            const serverUuid = "{{ $this->server->uuid }}";
            if (uuid !== serverUuid) {
                return;
            }

            socket.send(JSON.stringify({
                'event': 'set state',
                'args': [state]
            }));
        });

        $wire.on('sendAuthRequest', ({ token }) => {
            socket.send(JSON.stringify({
                'event': 'auth',
                'args': [token]
            }));
        });

        $wire.on('sendServerCommand', ({ command }) => {
            socket.send(JSON.stringify({
                'event': 'send command',
                'args': [command]
            }));
        });
    </script>
    @endscript
</x-filament::widget>
