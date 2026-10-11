<?php
declare(strict_types=1);

/**
 * Isolated contracts for optional video navigation links. No database driver,
 * production bootstrap, real uploads, cache files or network requests are used.
 * The save handler runs unchanged in child PHP processes under a temporary root.
 */
function rvl_check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

final class RVLinkContractStatement extends PDOStatement
{
    private int $position = 0;
    public function __construct(private RVLinkContractPDO $owner, private string $sql, private array $rows = []) {}
    public function execute(?array $params = null): bool
    {
        $this->owner->recordExecute($this->sql, $params ?? []);
        return true;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        return $this->rows[$this->position++] ?? false;
    }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
    public function fetchColumn(int $column = 0): mixed
    {
        $row = $this->rows[0] ?? [];
        return array_values($row)[$column] ?? false;
    }
}

final class RVLinkContractPDO extends PDO
{
    public array $queries = [];
    public array $schema = [];
    public array $executions = [];
    public array $mutations = [];
    public function __construct(private array $fixture = []) {}
    public function exec(string $statement): int|false
    {
        $this->schema[] = $statement;
        if (!empty($this->fixture['duplicate_columns']) && str_starts_with($statement, 'ALTER TABLE')) {
            throw new RuntimeException('Fixture duplicate column');
        }
        return 0;
    }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $this->queries[] = $query;
        if (!empty($this->fixture['catalog_failure'])) throw new RuntimeException('Fixture catalog unavailable');
        if (str_contains($query, 'JOIN web_products p')) {
            $rows = array_filter($this->fixture['variants'] ?? [], static function(array $row) use ($query): bool {
                return (!preg_match('/\bv\.is_published\s*=\s*1\b/', $query) || $row['variant_published'] === 1)
                    && (!preg_match('/\bp\.is_published\s*=\s*1\b/', $query) || $row['parent_published'] === 1)
                    && (!preg_match('/\bv\.slug\s*<>\s*\'\'/', $query) || $row['slug'] !== '');
            });
            return new RVLinkContractStatement($this, $query, array_values($rows));
        }
        if (str_contains($query, 'FROM web_products')) {
            $rows = array_filter($this->fixture['series'] ?? [], static function(array $row) use ($query): bool {
                return (!preg_match('/\bis_published\s*=\s*1\b/', $query) || $row['is_published'] === 1)
                    && (!preg_match('/\bslug\s*<>\s*\'\'/', $query) || $row['slug'] !== '');
            });
            return new RVLinkContractStatement($this, $query, array_values($rows));
        }
        // A populated video table prevents default data seeding in this fixture.
        if (str_contains($query, 'SELECT COUNT(*) FROM web_resource_videos')) return new RVLinkContractStatement($this, $query, [[1]]);
        if (str_contains($query, 'GET_LOCK') || str_contains($query, 'RELEASE_LOCK')) return new RVLinkContractStatement($this, $query, [[1]]);
        throw new RuntimeException('Unexpected fixture query: ' . $query);
    }
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        $rows = str_contains($query, 'SELECT related_url,related_label') ? [$this->fixture['previous'] ?? []] : [];
        return new RVLinkContractStatement($this, $query, $rows);
    }
    public function lastInsertId(?string $name = null): string|false { return '901'; }
    public function recordExecute(string $sql, array $params): void
    {
        $this->executions[] = ['sql'=>$sql, 'params'=>$params];
        if (preg_match('/^UPDATE web_resource_videos SET (.+) WHERE id=\?$/', $sql, $match)) {
            $columns = array_map(static fn(string $assignment): string => trim(explode('=', $assignment, 2)[0]), explode(',', $match[1]));
            rvl_check(count($params) === count($columns) + 1, 'UPDATE parameter count must match its assignments and id');
            $id = array_pop($params);
            $this->mutations[] = ['kind'=>'update', 'id'=>$id, 'values'=>array_combine($columns, $params)];
        } elseif (preg_match('/^INSERT INTO web_resource_videos \(([^)]+)\) VALUES \(([^)]+)\)$/', $sql, $match)) {
            $columns = array_map('trim', explode(',', $match[1]));
            rvl_check(count($params) === count($columns) && substr_count($match[2], '?') === count($params), 'INSERT parameter count must match its columns');
            $this->mutations[] = ['kind'=>'insert', 'id'=>901, 'values'=>array_combine($columns, $params)];
        }
    }
}

function rvl_remove_tree(string $directory): void
{
    foreach (scandir($directory) ?: [] as $name) {
        if ($name === '.' || $name === '..') continue;
        $path = $directory . '/' . $name;
        if (is_dir($path)) rvl_remove_tree($path); else unlink($path);
    }
    rmdir($directory);
}

// Child mode executes only our copied handler and mocked services. In particular,
// requiring this test never requires the website's real bootstrap.php.
if (($argv[1] ?? '') === '--save-fixture') {
    $fixtureRoot = (string)($argv[2] ?? '');
    $scenario = json_decode(base64_decode((string)($argv[3] ?? ''), true), true, 512, JSON_THROW_ON_ERROR);
    $GLOBALS['rvl_pdo'] = new RVLinkContractPDO($scenario);
    $GLOBALS['rvl_uploads'] = [];
    $GLOBALS['rvl_cache_clears'] = [];
    $GLOBALS['rvl_logs'] = [];
    $_SESSION = [];
    $_POST = $scenario['post'];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_FILES = empty($scenario['uploads']) ? [] : [
        'cover_upload'=>['error'=>UPLOAD_ERR_OK, 'name'=>'fixture.webp'],
        'video_upload'=>['error'=>UPLOAD_ERR_OK, 'name'=>'fixture.mp4'],
    ];
    register_shutdown_function(static function(): void {
        $pdo = $GLOBALS['rvl_pdo'];
        echo json_encode(['session'=>$_SESSION, 'uploads'=>$GLOBALS['rvl_uploads'], 'cache_clears'=>$GLOBALS['rvl_cache_clears'],
            'logs'=>$GLOBALS['rvl_logs'], 'mutations'=>$pdo->mutations, 'executions'=>$pdo->executions], JSON_THROW_ON_ERROR);
    });
    require $fixtureRoot . '/admin/save_resources_video.php';
    exit;
}

$fixtureRoot = sys_get_temp_dir() . '/artdon-video-links-contract-' . bin2hex(random_bytes(8));
rvl_check(mkdir($fixtureRoot . '/includes', 0700, true), 'Create isolated includes root');
rvl_check(mkdir($fixtureRoot . '/admin', 0700), 'Create isolated admin root');
try {
    foreach (['includes/resource_video_links.php', 'includes/resources_video_data.php', 'includes/pretty_urls_v71868.php', 'admin/save_resources_video.php'] as $file) {
        rvl_check(copy(dirname(__DIR__) . '/' . $file, $fixtureRoot . '/' . $file), 'Copy real candidate source: ' . $file);
    }
    $bootstrap = <<<'PHP'
<?php
declare(strict_types=1);
function web_db(?string &$error = null): PDO { return $GLOBALS['rvl_pdo']; }
function web_migrate(PDO $pdo): void {}
function web_require_admin(PDO $pdo): array { return ['id'=>7]; }
function web_verify_csrf(mixed $token): bool { return $token === 'fixture-csrf'; }
function web_upload_file(array $file, string $kind, PDO $pdo, int $userId, string $title, string $alt, string $usage): string
{
    $GLOBALS['rvl_uploads'][] = ['kind'=>$kind, 'user_id'=>$userId, 'usage'=>$usage];
    return $kind === 'image' ? 'uploads/fixture-cover.webp' : 'uploads/fixture-video.mp4';
}
function web_public_cache_clear(string $path): void { $GLOBALS['rvl_cache_clears'][] = $path; }
function web_log(PDO $pdo, int $userId, string $action, string $type, string $id, array $details): void
{
    $GLOBALS['rvl_logs'][] = ['id'=>$id, 'action'=>$action, 'details'=>$details];
}
PHP;
    file_put_contents($fixtureRoot . '/includes/bootstrap.php', $bootstrap);
    file_put_contents($fixtureRoot . '/includes/admin_auth.php', "<?php require_once __DIR__ . '/bootstrap.php';\n");
    file_put_contents($fixtureRoot . '/includes/upload.php', "<?php // Upload stub is in fixture bootstrap.\n");
    file_put_contents($fixtureRoot . '/includes/public_cache.php', "<?php // Cache stub is in fixture bootstrap.\n");
    require $fixtureRoot . '/includes/resources_video_data.php';

    $accepted = [
        ['', ''], ['   ', ''], ['/', '/'],
        [' https://example.com/products?a=1&b=2#details ', 'https://example.com/products?a=1&b=2#details'],
        ['http://example.com:8080/catalog', 'http://example.com:8080/catalog'],
        ['HTTPS://Example.COM/a', 'HTTPS://Example.COM/a'],
        ['http://192.0.2.1/details', 'http://192.0.2.1/details'],
        ['https://[2001:db8::1]/details', 'https://[2001:db8::1]/details'],
        ['/products/track-lights/spectrum/45?finish=white#spec', '/products/track-lights/spectrum/45?finish=white#spec'],
        ['/products.php?q=track%20light&next=https://example.com#list', '/products.php?q=track%20light&next=https://example.com#list'],
        ['product.php?slug=spectrum-45#specifications', '/product.php?slug=spectrum-45#specifications'],
        ['guides/install.php?step=2', '/guides/install.php?step=2'],
        ['products/track-lights?sort=name#models', '/products/track-lights?sort=name#models'],
        ['catalog', '/catalog'],
        ['www.example.com/catalog?from=video#details', 'https://www.example.com/catalog?from=video#details'],
        ['example.com:8443/catalog', 'https://example.com:8443/catalog'],
        ['/' . str_repeat('a', 999), '/' . str_repeat('a', 999)],
        [str_repeat('a', 999), '/' . str_repeat('a', 999)],
        ['guide' . str_repeat('a', 990) . '.php', '/guide' . str_repeat('a', 990) . '.php'],
        ['example.com/' . str_repeat('a', 980), 'https://example.com/' . str_repeat('a', 980)],
    ];
    foreach ($accepted as [$raw, $expected]) rvl_check(artdon_resource_video_link_url($raw) === $expected, 'Allowed URL must normalize correctly: ' . substr($raw, 0, 120));
    $rejected = [null, false, 12, [], ['https://example.com'],
        'javascript:alert(1)', 'JaVaScRiPt:alert(1)', 'java%73cript:alert(1)',
        'data:text/html,<script>alert(1)</script>', 'mailto:sales@example.com', 'tel:+123456', 'ftp://example.com/file', 'vbscript:msgbox(1)',
        '//example.com/path', '///example.com/path', 'http:example.com', 'https://',
        'https://user@example.com/', 'https://user:password@example.com/',
        'https://bad_host.example/', 'https://-bad.example/', 'https://bad..example/', 'https://[not-an-ip]/', 'https://example.com:65536/',
        '/products with spaces', "/products\t.php", "/products\n.php", "/products\x00.php", "/products\x7f.php", '/\\example.com/',
        'https://example.com\\@evil.example/', '/products.php?x=%00', '/products.php?x=%0a', '/products.php?x=%0D%0A', '/products.php?x=%7F', '/%5cevil.example/',
        './products.php', '../products.php', '#details', '?slug=spectrum', '/' . str_repeat('a', 1000),
        str_repeat('a', 1000), 'guide' . str_repeat('a', 991) . '.php', 'example.com/' . str_repeat('a', 981),
    ];
    foreach ($rejected as $raw) rvl_check(artdon_resource_video_link_url($raw) === '', 'Unsafe or invalid URL must be rejected: ' . json_encode($raw));
    foreach (['', '   ', null, false, []] as $label) rvl_check(artdon_resource_video_link_label($label) === 'View Details', 'Missing label must have a readable default');
    rvl_check(artdon_resource_video_link_label('  查看产品  ') === '查看产品', 'Custom Unicode labels must be retained and trimmed');

    $catalogDb = new RVLinkContractPDO([
        'series'=>[
            ['id'=>1,'name'=>' SPECTRUM Series ','slug'=>'spectrum','series_name'=>'Spectrum','model_code'=>'SP','category_slug'=>'track-lights','is_published'=>1],
            ['id'=>2,'name'=>'Hidden Series','slug'=>'hidden','series_name'=>'Hidden','model_code'=>'HI','category_slug'=>'track-lights','is_published'=>0],
            ['id'=>3,'name'=>'No Slug','slug'=>'','series_name'=>'No Slug','model_code'=>'NS','category_slug'=>'track-lights','is_published'=>1],
            ['id'=>4,'name'=>'','slug'=>'legacy-series','series_name'=>'Legacy Series','model_code'=>'LS','category_slug'=>'','is_published'=>1],
        ],
        'variants'=>[
            ['name'=>'SPECTRUM 45','slug'=>'spectrum-45','model_code'=>'SP.45','series_title'=>'SPECTRUM Series','series_slug'=>'spectrum','series_name'=>'Spectrum','category_slug'=>'track-lights','variant_published'=>1,'parent_published'=>1],
            ['name'=>'Hidden Variant','slug'=>'hidden-variant','model_code'=>'HV','series_title'=>'SPECTRUM Series','series_slug'=>'spectrum','series_name'=>'Spectrum','category_slug'=>'track-lights','variant_published'=>0,'parent_published'=>1],
            ['name'=>'Hidden Parent Child','slug'=>'hidden-parent-child','model_code'=>'HC','series_title'=>'Hidden Series','series_slug'=>'hidden','series_name'=>'Hidden','category_slug'=>'track-lights','variant_published'=>1,'parent_published'=>0],
            ['name'=>'No Variant Slug','slug'=>'','model_code'=>'NS','series_title'=>'SPECTRUM Series','series_slug'=>'spectrum','series_name'=>'Spectrum','category_slug'=>'track-lights','variant_published'=>1,'parent_published'=>1],
            ['name'=>'','slug'=>'legacy-model','model_code'=>'LEGACY 1','series_title'=>'Legacy Series','series_slug'=>'legacy-series','series_name'=>'Legacy','category_slug'=>'','variant_published'=>1,'parent_published'=>1],
        ],
    ]);
    $catalog = artdon_resource_video_link_products($catalogDb);
    rvl_check(count($catalogDb->queries) === 2 && count($catalog) === 4, 'Catalog must exclude unpublished series, variants, unpublished parents and empty slugs');
    rvl_check(array_column($catalog, 'title') === ['SPECTRUM Series', 'Legacy Series', 'SPECTRUM 45', 'LEGACY 1'], 'Catalog must provide human titles and series/model fallbacks');
    rvl_check(array_column($catalog, 'url') === ['/products/track-lights/spectrum', '/series.php?slug=legacy-series', '/products/track-lights/spectrum/sp.45', '/product.php?slug=legacy-model'], 'Catalog URLs must use the real public route helpers, including legacy fallbacks');
    rvl_check($catalog[0]['url'] === artdon_pretty_series_url_v71868('track-lights', ['slug'=>'spectrum'])
        && $catalog[2]['url'] === artdon_pretty_product_url_v71868('track-lights', ['slug'=>'spectrum'], ['model_code'=>'SP.45']), 'Catalog must agree with real series/product URL helpers');
    rvl_check(str_contains($catalog[2]['search'], 'SP.45') && str_contains($catalog[2]['search'], 'Spectrum') && str_contains($catalog[2]['search'], 'spectrum-45'), 'Catalog search must include model, series and slug');
    rvl_check(artdon_resource_video_link_products(new RVLinkContractPDO(['catalog_failure'=>true])) === [], 'Catalog outage must leave manual link entry available');

    $migrationDb = new RVLinkContractPDO(['duplicate_columns'=>true]);
    artdon_resource_video_migrate($migrationDb);
    artdon_resource_video_migrate($migrationDb);
    $schema = implode("\n", $migrationDb->schema);
    rvl_check(substr_count($schema, 'CREATE TABLE IF NOT EXISTS') === 2, 'Migration must remain safe to invoke repeatedly');
    rvl_check(substr_count($schema, 'ADD COLUMN related_url VARCHAR(1000)') === 2 && substr_count($schema, 'ADD COLUMN related_label VARCHAR(120)') === 2, 'Existing video tables must receive both related-link columns despite duplicate-column errors');
    rvl_check(str_contains($migrationDb->schema[0], "related_url VARCHAR(1000) NOT NULL DEFAULT ''") && str_contains($migrationDb->schema[0], "related_label VARCHAR(120) NOT NULL DEFAULT ''"), 'New video tables must define empty related-link defaults');
    $mapped = artdon_resource_video_map_row(['id'=>11,'title'=>'Sample','related_url'=>'product.php?slug=spectrum-45','related_label'=>'  See product  ']);
    rvl_check($mapped['related_url'] === '/product.php?slug=spectrum-45' && $mapped['related_label'] === 'See product', 'Read mapping must normalize link and label');
    foreach ([[], ['related_url'=>'javascript:alert(1)', 'related_label'=>''], ['related_url'=>['bad'], 'related_label'=>null]] as $row) {
        $mapped = artdon_resource_video_map_row($row);
        rvl_check($mapped['related_url'] === '' && $mapped['related_label'] === 'View Details', 'Legacy or unsafe stored fields must never become an unsafe public link');
    }

    $basePost = ['csrf'=>'fixture-csrf', 'action'=>'save', 'id'=>0, 'title'=>'Fixture title', 'description'=>'Fixture description',
        'main_category'=>'product', 'product_subcategory'=>'track-lights', 'source_type'=>'file', 'video_url'=>'/videos/original.mp4',
        'related_url'=>'product.php?slug=spectrum-45#details', 'related_label'=>'  Explore product  ',
        'cover_image'=>'assets/fixture-cover.webp', 'cover_alt'=>'Fixture alt', 'duration'=>'2:34', 'publish_date'=>'2026-10-11',
        'seo_title'=>'Fixture SEO', 'seo_description'=>'Fixture meta', 'seo_keywords'=>'fixture,product', 'sort_order'=>27, 'is_active'=>'1', 'is_featured'=>'1'];
    $runSave = static function(array $post, array $extra = []) use ($fixtureRoot): array {
        $scenario = array_merge(['post'=>$post, 'previous'=>['related_url'=>'/products/existing', 'related_label'=>'Existing button']], $extra);
        $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --save-fixture ' . escapeshellarg($fixtureRoot) . ' ' . escapeshellarg(base64_encode(json_encode($scenario, JSON_THROW_ON_ERROR))) . ' 2>&1';
        exec($command, $lines, $exitCode);
        rvl_check($exitCode === 0, 'Real save handler fixture must exit successfully: ' . implode("\n", $lines));
        try { return json_decode(implode("\n", $lines), true, 512, JSON_THROW_ON_ERROR); }
        catch (Throwable $error) { throw new RuntimeException('Save fixture returned non-JSON output: ' . implode("\n", $lines), 0, $error); }
    };
    $expectedValues = ['title'=>'Fixture title','description'=>'Fixture description','main_category'=>'product','sub_category'=>'track-lights',
        'source_type'=>'file','video_url'=>'/videos/original.mp4','related_url'=>'/product.php?slug=spectrum-45#details','related_label'=>'Explore product',
        'cover_image'=>'assets/fixture-cover.webp','cover_alt'=>'Fixture alt','duration'=>'2:34','publish_date'=>'2026-10-11',
        'seo_title'=>'Fixture SEO','seo_description'=>'Fixture meta','seo_keywords'=>'fixture,product','sort_order'=>27,'is_active'=>1,'is_featured'=>1];
    foreach ([0, 31] as $id) {
        $result = $runSave(array_replace($basePost, ['id'=>$id]));
        rvl_check(count($result['mutations']) === 1, 'Saving must make one video mutation');
        $mutation = $result['mutations'][0];
        rvl_check($mutation['kind'] === ($id ? 'update' : 'insert') && $mutation['id'] === ($id ?: 901), 'Saving must retain the correct id and SQL operation');
        rvl_check($mutation['values'] === $expectedValues, 'Real INSERT/UPDATE bound parameters must align with every column, including related fields');
        rvl_check(isset($result['session']['admin_success']) && !isset($result['session']['admin_error']), 'Successful handler must report saved video');
        rvl_check($result['cache_clears'] === [''] && count($result['logs']) === 1, 'Successful handler must retain existing cache/log behavior');
    }
    $oldPost = array_replace($basePost, ['id'=>31]);
    unset($oldPost['related_url'], $oldPost['related_label']);
    $result = $runSave($oldPost);
    rvl_check($result['mutations'][0]['values']['related_url'] === '/products/existing' && $result['mutations'][0]['values']['related_label'] === 'Existing button', 'Old forms omitting both fields must preserve the saved relationship');
    $lookup = array_values(array_filter($result['executions'], static fn(array $execution): bool => str_starts_with($execution['sql'], 'SELECT related_url,related_label')));
    rvl_check(count($lookup) === 1 && $lookup[0]['params'] === [31], 'Old form fallback must read only the video being updated');
    foreach (['related_url', 'related_label'] as $missing) {
        $post = array_replace($basePost, ['id'=>31]);
        unset($post[$missing]);
        $result = $runSave($post);
        $values = $result['mutations'][0]['values'];
        rvl_check($values[$missing] === ($missing === 'related_url' ? '/products/existing' : 'Existing button'), 'Omitting one field must preserve that field');
        $submitted = $missing === 'related_url' ? 'related_label' : 'related_url';
        rvl_check($values[$submitted] === $expectedValues[$submitted], 'Omitting one field must still save the other submitted field');
    }
    $result = $runSave(array_replace($basePost, ['id'=>31, 'related_url'=>'', 'related_label'=>'']));
    rvl_check($result['mutations'][0]['values']['related_url'] === '' && $result['mutations'][0]['values']['related_label'] === '', 'Explicit blank fields must clear an existing relationship');
    $newOldForm = $oldPost;
    $newOldForm['id'] = 0;
    $result = $runSave($newOldForm);
    rvl_check($result['mutations'][0]['values']['related_url'] === '' && $result['mutations'][0]['values']['related_label'] === '', 'New videos from an old form must use empty optional fields');
    $result = $runSave(array_replace($basePost, ['related_url'=>'example.com/details', 'related_label'=>str_repeat('灯', 120)]), ['uploads'=>true]);
    rvl_check($result['mutations'][0]['values']['related_url'] === 'https://example.com/details' && $result['mutations'][0]['values']['related_label'] === str_repeat('灯', 120), 'Save must normalize bare domains and count Unicode label characters');
    rvl_check(count($result['uploads']) === 2 && $result['mutations'][0]['values']['cover_image'] === 'uploads/fixture-cover.webp' && $result['mutations'][0]['values']['video_url'] === 'uploads/fixture-video.mp4', 'Valid related links must retain cover/video upload behavior');
    $invalidPosts = [
        ['related_url'=>'javascript:alert(1)'], ['related_url'=>'//evil.example/'], ['related_url'=>'https://user@evil.example/'],
        ['related_url'=>'/products.php?x=%0a'], ['related_url'=>'/' . str_repeat('a', 1000)], ['related_url'=>['https://example.com']],
        ['related_url'=>'example.com/' . str_repeat('a', 981)], ['related_url'=>'guide' . str_repeat('a', 991) . '.php'],
        ['related_label'=>['bad']], ['related_label'=>str_repeat('灯', 121)],
    ];
    foreach ($invalidPosts as $override) {
        $result = $runSave(array_replace($basePost, $override), ['uploads'=>true]);
        rvl_check(isset($result['session']['admin_error']) && !isset($result['session']['admin_success']), 'Invalid related fields must return a validation error');
        rvl_check($result['mutations'] === [] && $result['uploads'] === [] && $result['cache_clears'] === [] && $result['logs'] === [], 'Invalid related fields must fail before uploads, video writes, cache clearing and success logging');
    }
    echo 'Resource video link URL, label, public catalog, migration, mapping and isolated save contracts passed.' . "\n";
} finally {
    rvl_remove_tree($fixtureRoot);
}
