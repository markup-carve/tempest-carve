<?php

/**
 * @var string|null $content Carve content passed through the content attribute
 */

use MarkupCarve\Carve\CarveConverter;

use function Tempest\Container\get;

$html = get(CarveConverter::class)->convert($content ?? '');
?>

{!! $html !!}
