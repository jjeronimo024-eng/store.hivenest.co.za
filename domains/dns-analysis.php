<?php
$current_page = 'cyber-scan';
$page_title = 'DNS Analysis | HiveNest Cyber Scan';
$page_description = 'Inspect live DNS records for a domain.';
$page_scripts = <<<'JS'
function escapeDnsValue(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatDnsRecord(type, record) {
    const ttl = record.ttl ? ' <span style="color:rgba(255,255,255,.55)">TTL ' + escapeDnsValue(record.ttl) + '</span>' : '';

    if (type === 'A') {
        return '<li><strong>' + escapeDnsValue(record.ip || record.target || '') + '</strong>' + ttl + '</li>';
    }

    if (type === 'MX') {
        const priority = record.pri !== undefined ? 'Priority ' + escapeDnsValue(record.pri) + ' · ' : '';
        return '<li><strong>' + priority + escapeDnsValue(record.target || '') + '</strong>' + ttl + '</li>';
    }

    if (type === 'NS') {
        return '<li><strong>' + escapeDnsValue(record.target || '') + '</strong>' + ttl + '</li>';
    }

    if (type === 'TXT') {
        const txt = record.txt || (Array.isArray(record.entries) ? record.entries.join(' ') : '');
        return '<li><strong>' + escapeDnsValue(txt) + '</strong>' + ttl + '</li>';
    }

    if (type === 'SOA') {
        const lines = [
            ['Primary nameserver', record.mname],
            ['Responsible email', record.rname],
            ['Serial', record.serial],
            ['Refresh', record.refresh],
            ['Retry', record.retry],
            ['Expire', record.expire],
            ['Minimum TTL', record['minimum-ttl'] || record.minimum_ttl || record.minimum]
        ].filter(([, value]) => value !== undefined && value !== null && value !== '');

        return '<li>' + lines.map(([label, value]) => (
            '<div><span style="color:var(--cyber-neon-cyan)">' + label + ':</span> ' + escapeDnsValue(value) + '</div>'
        )).join('') + ttl + '</li>';
    }

    return '';
}

function renderDnsSection(type, records) {
    if (!Array.isArray(records) || records.length === 0) return '';
    const items = records.map(record => formatDnsRecord(type, record)).filter(Boolean).join('');
    if (!items) return '';

    return '<div class="cyber-card" style="margin:1rem 0">' +
        '<h3 style="color:var(--cyber-neon-green); margin-bottom:.85rem">' + type + ' RECORDS</h3>' +
        '<ul style="list-style:none; padding:0; margin:0; display:grid; gap:.7rem; color:white">' + items + '</ul>' +
    '</div>';
}

document.getElementById('dns-form').addEventListener('submit', async function (event) {
    event.preventDefault();
    const domain = this.domain.value.trim();
    const output = document.getElementById('dns-results');
    output.innerHTML = '<div class="cyber-card">SCANNING DNS MATRIX...</div>';
    try {
        const response = await fetch('/api/domain-intelligence.php?action=dns&domain=' + encodeURIComponent(domain));
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.error || 'DNS lookup failed');
        const allowedTypes = ['A', 'MX', 'NS', 'TXT', 'SOA'];
        let html = '<h2 style="color:var(--cyber-neon-cyan)">DNS RECORDS FOR ' + escapeDnsValue(data.domain.toUpperCase()) + '</h2>';
        html += allowedTypes.map(type => renderDnsSection(type, data.records[type] || [])).join('');
        if (html.indexOf('cyber-card') === -1) {
            html += '<div class="cyber-card" style="margin:1rem 0;color:rgba(255,255,255,.78)">No A, MX, NS, TXT or SOA records were returned for this domain.</div>';
        }
        output.innerHTML = html;
        output.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (error) { output.innerHTML = '<div class="cyber-card" style="color:var(--cyber-neon-pink)">' + error.message + '</div>'; }
});
JS;
?>
<!DOCTYPE html><html lang="en"><head><?php include '../utilities/head.php'; ?></head><body>
<?php include '../utilities/nav.php'; include '../utilities/mobile-menu.php'; ?>
<section class="section" style="padding-top:8rem"><div class="container">
<div class="text-center mb-8"><h1>DNS <span class="cyber-text">ANALYSIS</span></h1><p class="hero-subtitle">Inspect useful A, MX, NS, TXT and SOA records.</p></div>
<form id="dns-form" class="cyber-card" style="max-width:800px;margin:auto;display:flex;gap:1rem;flex-wrap:wrap"><input name="domain" required placeholder="jasper.co.za" style="flex:1;min-width:260px;padding:16px;background:#090909;color:white;border:1px solid var(--cyber-neon-cyan);border-radius:8px"><button class="btn btn-primary">ANALYZE DNS</button></form>
<div id="dns-results" style="max-width:1000px;margin:2rem auto"></div></div></section>
<?php include '../utilities/footer.php'; include '../utilities/scripts.php'; ?></body></html>
