# Node client connectivity tests

Run the Alpine component logic tests with Node.js 22 or newer:

```sh
node --test tests/Browser/NodeClientConnectivity.test.mjs
```

The tests execute the JavaScript from the Blade view with controlled HTTP and
WebSocket responses. They need no npm dependencies, PHP application, database,
Wings instance, or credentials.

Coverage includes readable HTTP responses (including the expected unauthenticated
401), server errors, CORS and network rejections, HTTP timeouts, authenticated
WebSockets, suspended servers, and failed WebSockets. Server errors and HTTP
failures must stop before opening a WebSocket.

These are component logic tests. They do not render Blade through Laravel or
replace a browser check of CORS and Cross-Origin-Resource-Policy headers.
