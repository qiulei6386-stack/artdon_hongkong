<?php

declare(strict_types=1);

$retailApplicationSlug = preg_replace('/[^a-z0-9-]+/', '-', strtolower(trim((string)($_GET['slug'] ?? '')))) ?: '';
require __DIR__ . '/includes/retail_application_template.php';
