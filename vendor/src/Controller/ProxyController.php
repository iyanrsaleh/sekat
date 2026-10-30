<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Proxy\ProxyClient;

/**
 * Proxy: /api/{alias}/{path} diteruskan ke URL tujuan. Alias tidak pernah
 * membocorkan host tujuan. Meniru konsep proxy pada referensi.
 */
final class ProxyController
{
    private const METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD'];

    /**
     * @param array<string, string> $aliases daftar alias yang boleh dipanggil
     * @param array<string, string> $upstreams alias => URL dasar tujuan
     */
    public function __construct(
        private readonly array $aliases,
        private readonly array $upstreams,
        private readonly ProxyClient $client,
    ) {
    }

    /**
     * @param array<string, string> $params
     */
    public function dispatch(array $params, Request $request): Response
    {
        $alias = (string) ($params['alias'] ?? '');
        $path = (string) ($params['path'] ?? '');
        $method = $request->method();

        if ($method === 'OPTIONS') {
            return Response::noContent();
        }

        if (!in_array($method, self::METHODS, true)) {
            return Response::raw(json_encode(['error' => 'method_not_allowed', 'hint' => 'metode tidak didukung']), 405, $this->jsonHeaders());
        }

        if (!in_array($alias, $this->aliases, true)) {
            return Response::raw(json_encode(['error' => 'not_found', 'hint' => 'alias tidak ada']), 404, $this->jsonHeaders());
        }

        if (str_contains($path, '..')) {
            return Response::raw(json_encode(['error' => 'bad_request', 'hint' => 'path tidak sah']), 400, $this->jsonHeaders());
        }

        $upstream = $this->upstreams[$alias] ?? null;

        if (!is_string($upstream) || $upstream === '' || !filter_var($upstream, FILTER_VALIDATE_URL)) {
            return Response::raw(json_encode(['error' => 'driver_unavailable', 'hint' => 'tujuan tidak tersedia']), 503, $this->jsonHeaders());
        }

        $target = rtrim($upstream, '/') . '/' . ltrim($path, '/');
        $query = $request->queryAll();

        if ($query !== []) {
            $target .= (str_contains($target, '?') ? '&' : '?') . http_build_query($query);
        }

        $headers = $this->forwardHeaders($request);

        try {
            $result = $this->client->forward($method, $target, $headers, $request->rawBody());
        } catch (\Throwable) {
            return Response::raw(json_encode(['error' => 'bad_gateway', 'hint' => 'mesin tujuan tidak menjawab']), 502, $this->jsonHeaders());
        }

        $responseHeaders = ['Content-Type' => $result['contentType'] ?? 'application/octet-stream'];

        return Response::raw($result['body'], $result['status'], $responseHeaders);
    }

    /**
     * Meneruskan header yang relevan. Authorization TIDAK diteruskan.
     *
     * @return array<string, string>
     */
    private function forwardHeaders(Request $request): array
    {
        $map = [
            'Accept' => 'Accept',
            'Content-Type' => 'Content-Type',
            'User-Agent' => 'User-Agent',
        ];

        $headers = [];

        foreach ($map as $name => $serverKey) {
            $value = $name === 'Content-Type' ? $request->header('Content-Type') : $request->header($name);

            if (is_string($value) && $value !== '') {
                $headers[$name] = $value;
            }
        }

        return $headers;
    }

    /**
     * @return array<string, string>
     */
    private function jsonHeaders(): array
    {
        return ['Content-Type' => 'application/json; charset=utf-8'];
    }
}