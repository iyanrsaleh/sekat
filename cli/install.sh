#!/bin/sh
set -e

repo='iyanrsaleh/sekat'
asset='sekat-linux-x64.tar.gz'
url="https://github.com/$repo/releases/latest/download/$asset"

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

curl -fsSL "$url" -o "$tmp/sekat.tar.gz"
tar -xzf "$tmp/sekat.tar.gz" -C "$tmp"

bin="$(find "$tmp" -type f -name 'sekat' | head -n 1)"
if [ -z "$bin" ]; then
    echo "Arsip rilis tidak berisi biner sekat." >&2
    exit 1
fi

"$bin" install --global