#!/bin/sh

if [ -z "$USER" ]; then
    echo "USER environment variable must be set to the host UID (e.g. -e USER=\$(id -u))" >&2
    exit 1
fi

usermod -u "$USER" ez-delivery
exec su-exec ez-delivery "$@"
