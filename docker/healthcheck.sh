#!/bin/ash -e

if [ "${SKIP_CADDY:-false}" != "true" ]; then
    curl -sf http://localhost/up || exit 1
fi

cgi-fcgi -bind -connect 127.0.0.1:9000 || exit 2

exit 0
