<?php
declare(strict_types=1);

function page_header(string $title, string $active = ''): void
{
    $nav = [
        'dashboard' => 'Dashboard',
        'locations' => 'Locations',
        'reviews'   => 'Reviews',
        'posts'     => 'Posts',
        'insights'  => 'Insights',
    ];
    $f = flash();
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>' . e($title) . ' - GMB CRM</title>'
       . '<link rel="stylesheet" href="assets/style.css"></head><body>';
    echo '<header class="top"><strong>GMB CRM</strong><nav>';
    foreach ($nav as $key => $label) {
        $cls = $key === $active ? ' class="on"' : '';
        echo '<a' . $cls . ' href="' . $key . '.php">' . e($label) . '</a>';
    }
    echo '</nav><a class="logout" href="logout.php">Log out</a></header><main>';
    if ($f) {
        echo '<div class="flash ' . e($f['type']) . '">' . e($f['msg']) . '</div>';
    }
}

function page_footer(): void
{
    echo '</main></body></html>';
}
