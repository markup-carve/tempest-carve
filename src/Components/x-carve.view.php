<?php

use MarkupCarve\Tempest\CarveRenderer;
use function Tempest\Container\get;

/**
 * @var string|null $content Carve content passed through the content attribute
 */
$content ??= null;
$html = get(CarveRenderer::class)->render($content ?? '');
?>

{!! $html !!}
