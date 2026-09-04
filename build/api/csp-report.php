<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit();
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$report = $data['csp-report'] ?? $data;

if (is_array($report)) {
    $line = sprintf(
        "[%s] doc=%s blocked=%s directive=%s ip=%s\n",
        date('Y-m-d H:i:s'),
        $report['document-uri'] ?? $report['documentURL'] ?? '?',
        $report['blocked-uri'] ?? $report['blockedURL'] ?? '?',
        $report['violated-directive'] ?? $report['effectiveDirective'] ?? '?',
        $_SERVER['REMOTE_ADDR'] ?? '?'
    );
    @file_put_contents(__DIR__ . '/../logs/csp-violations.log', $line, FILE_APPEND | LOCK_EX);
}

http_response_code(204);
