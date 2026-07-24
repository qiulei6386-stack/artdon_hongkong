<?php
declare(strict_types=1);
// V7.1.5.7: old single-item homepage editor is disabled. Use the integrated homepage publish board.
$q = '';
if (isset($_GET['source_id'])) $q = '&q=' . rawurlencode((string)$_GET['source_id']);
header('Location: home_products.php' . ($q !== '' ? ('?' . ltrim($q,'&')) : ''));
exit;
