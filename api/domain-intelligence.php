<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function intelligence_domain(): string {
    $domain = strtolower(trim((string)($_GET['domain'] ?? '')));
    $domain = preg_replace('#^https?://#', '', $domain);
    $domain = trim(explode('/', $domain)[0], '.');
    if (!preg_match('/^(?=.{3,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Enter a valid domain, for example jasper.co.za']);
        exit;
    }
    return $domain;
}

function rdap_event(array $events, string $action): ?string {
    foreach ($events as $event) {
        if (($event['eventAction'] ?? '') === $action) return $event['eventDate'] ?? null;
    }
    return null;
}

function registry_whois(string $server, string $domain): ?array {
    $socket = @fsockopen($server, 43, $errno, $error, 12);
    if (!$socket) return null;
    stream_set_timeout($socket, 12);
    fwrite($socket, $domain . "\r\n");
    $raw = '';
    while (!feof($socket) && strlen($raw) < 200000) $raw .= fgets($socket, 4096);
    fclose($socket);
    if (trim($raw) === '') return null;

    $values = static function (string $pattern) use ($raw): array {
        preg_match_all($pattern, $raw, $matches);
        return array_values(array_unique(array_filter(array_map('trim', $matches[1] ?? []))));
    };
    $first = static function (array $items): ?string { return $items[0] ?? null; };
    $nameservers = $values('/^(?:Name Server|Nameserver)\s*:\s*(.+)$/mi');
    $statuses = $values('/^(?:Domain Status|Status)\s*:\s*(.+)$/mi');
    return [
        'success' => true,
        'domain' => $domain,
        'status' => 'REGISTERED',
        'registrar' => $first($values('/^(?:Registrar|Registrar Name)\s*:\s*(.+)$/mi')) ?? 'Not disclosed',
        'registrationDate' => $first($values('/^(?:Creation Date|Registration Date|Registered On)\s*:\s*(.+)$/mi')),
        'expirationDate' => $first($values('/^(?:Registry Expiry Date|Expiration Date|Expiry Date)\s*:\s*(.+)$/mi')),
        'lastChangedDate' => $first($values('/^(?:Updated Date|Last Updated)\s*:\s*(.+)$/mi')),
        'nameservers' => array_map('strtolower', $nameservers),
        'statuses' => $statuses,
        'source' => 'ZARC WHOIS',
    ];
}

$action = $_GET['action'] ?? '';
$domain = intelligence_domain();

if ($action === 'dns') {
    $types = ['A' => DNS_A, 'AAAA' => DNS_AAAA, 'MX' => DNS_MX, 'NS' => DNS_NS, 'TXT' => DNS_TXT, 'CNAME' => DNS_CNAME, 'SOA' => DNS_SOA];
    $records = [];
    foreach ($types as $label => $constant) {
        $rows = @dns_get_record($domain, $constant) ?: [];
        $records[$label] = array_map(static function (array $row): array {
            unset($row['host'], $row['class'], $row['type']);
            return $row;
        }, $rows);
    }
    echo json_encode(['success' => true, 'domain' => $domain, 'records' => $records, 'checked_at' => gmdate('c')]);
    exit;
}

if ($action === 'whois') {
    if (preg_match('/\.(?:co|org|net|web)\.za$/', $domain)) {
        $whois = registry_whois('coza-whois.registry.net.za', $domain);
        if ($whois) {
            echo json_encode($whois);
            exit;
        }
    }
    if (!function_exists('curl_init')) {
        http_response_code(503);
        echo json_encode(['success' => false, 'error' => 'Registry lookup support is not enabled on this server.']);
        exit;
    }
    $url = 'https://rdap.org/domain/' . rawurlencode($domain);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 20, CURLOPT_USERAGENT => 'HiveNest-Domain-Intelligence/1.0', CURLOPT_HTTPHEADER => ['Accept: application/rdap+json, application/json']]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($body === false || $error !== '' || $code < 200 || $code >= 300) {
        if (preg_match('/\.(?:co|org|net|web)\.za$/', $domain)) {
            $whois = registry_whois('coza-whois.registry.net.za', $domain);
            if ($whois) {
                echo json_encode($whois);
                exit;
            }
        }
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => 'Registry information is currently unavailable. No availability assumption was made.']);
        exit;
    }
    $rdap = json_decode($body, true);
    if (!is_array($rdap)) {
        http_response_code(502);
        echo json_encode(['success' => false, 'error' => 'The registry returned an unreadable response.']);
        exit;
    }
    $registrar = 'Not disclosed';
    foreach (($rdap['entities'] ?? []) as $entity) {
        if (in_array('registrar', $entity['roles'] ?? [], true)) {
            foreach (($entity['vcardArray'][1] ?? []) as $field) {
                if (($field[0] ?? '') === 'fn') $registrar = (string)($field[3] ?? $registrar);
            }
        }
    }
    $nameservers = array_values(array_filter(array_map(static fn($ns) => strtolower($ns['ldhName'] ?? ''), $rdap['nameservers'] ?? [])));
    echo json_encode([
        'success' => true,
        'domain' => $rdap['ldhName'] ?? $domain,
        'status' => 'REGISTERED',
        'registrar' => $registrar,
        'registrationDate' => rdap_event($rdap['events'] ?? [], 'registration'),
        'expirationDate' => rdap_event($rdap['events'] ?? [], 'expiration'),
        'lastChangedDate' => rdap_event($rdap['events'] ?? [], 'last changed'),
        'nameservers' => $nameservers,
        'statuses' => $rdap['status'] ?? [],
        'source' => 'RDAP',
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Unknown action']);
