<?php
// Domain Services API Handler

function getDomainPricing() {
    $domain_pricing = [
        [
            'tld' => '.com',
            'price' => 12.99,
            'renewal_price' => 14.99,
            'currency' => 'USD',
            'period' => 'yearly',
            'popular' => true
        ],
        [
            'tld' => '.net',
            'price' => 13.99,
            'renewal_price' => 15.99,
            'currency' => 'USD',
            'period' => 'yearly',
            'popular' => false
        ],
        [
            'tld' => '.org',
            'price' => 13.99,
            'renewal_price' => 15.99,
            'currency' => 'USD',
            'period' => 'yearly',
            'popular' => false
        ],
        [
            'tld' => '.co.za',
            'price' => 8.99,
            'renewal_price' => 8.99,
            'currency' => 'USD',
            'period' => 'yearly',
            'popular' => true
        ],
        [
            'tld' => '.info',
            'price' => 11.99,
            'renewal_price' => 13.99,
            'currency' => 'USD',
            'period' => 'yearly',
            'popular' => false
        ],
        [
            'tld' => '.biz',
            'price' => 12.99,
            'renewal_price' => 14.99,
            'currency' => 'USD',
            'period' => 'yearly',
            'popular' => false
        ],
        [
            'tld' => '.io',
            'price' => 49.99,
            'renewal_price' => 49.99,
            'currency' => 'USD',
            'period' => 'yearly',
            'popular' => true
        ],
        [
            'tld' => '.tech',
            'price' => 39.99,
            'renewal_price' => 39.99,
            'currency' => 'USD',
            'period' => 'yearly',
            'popular' => false
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'data' => $domain_pricing,
        'count' => count($domain_pricing)
    ]);
}

function checkDomainAvailability() {
    http_response_code(410);
    echo json_encode([
        'success' => false,
        'error' => 'Legacy availability simulation is disabled. Use /api/domains_live.php?action=check-availability.'
    ]);
}

function getDomainPriceForTLD($tld) {
    $pricing = [
        'com' => ['price' => 12.99, 'currency' => 'USD'],
        'net' => ['price' => 13.99, 'currency' => 'USD'],
        'org' => ['price' => 13.99, 'currency' => 'USD'],
        'co.za' => ['price' => 8.99, 'currency' => 'USD'],
        'info' => ['price' => 11.99, 'currency' => 'USD'],
        'biz' => ['price' => 12.99, 'currency' => 'USD'],
        'io' => ['price' => 49.99, 'currency' => 'USD'],
        'tech' => ['price' => 39.99, 'currency' => 'USD']
    ];
    
    return isset($pricing[$tld]) ? $pricing[$tld] : ['price' => 15.99, 'currency' => 'USD'];
}
?>
