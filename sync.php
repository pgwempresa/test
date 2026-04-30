<?php
$html = file_get_contents('index.html');
$files = [
    'resgatar/index.html',
    'confirmar-saque/index.html',
    'faq/index.html',
    'historico/index.html',
    'back-redirect/index.html',
    'up1/index.html',
    'up2/index.html',
    'up3/index.html',
    'up4/index.html',
    'up5/index.html',
    'upsell-1/index.html',
    'upsell-2/index.html',
    'upsell-3/index.html',
    'upsell-4/index.html',
    'upsell-5/index.html'
];
foreach ($files as $f) {
    if (file_exists($f)) {
        file_put_contents($f, $html);
        echo "Synced $f\n";
    }
}
?>
