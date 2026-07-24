<?php

declare(strict_types=1);

function admin_product_center_tabs(string $active, int $seriesId = 0): void
{
    $tabs = [
        'overview' => ['总览','product_center.php'],
        'series' => ['系列管理','products.php'],
        'models' => ['具体产品','product_models.php'],
        'filters' => ['筛选库','product_filters.php'],
        'accessories' => ['共用配件库','products.php?panel=accessories'],
        'home' => ['推荐到首页','home_products.php'],
        'categories' => ['产品分类','product_categories.php'],
        'bulk' => ['导入 / 导出','product_bulk_io.php'],
    ];
    echo '<nav class="product-center-tabs" aria-label="产品与筛选中心">';
    foreach ($tabs as $key => [$label,$href]) {
        echo '<a class="'.($active===$key?'is-active':'').'" href="'.web_e($href).'">'.web_e($label).'</a>';
    }
    if ($seriesId > 0) {
        echo '<span class="product-center-tabs-spacer"></span><a href="product_variants.php?series_id='.$seriesId.'">当前系列产品</a>';
    }
    echo '</nav>';
}
