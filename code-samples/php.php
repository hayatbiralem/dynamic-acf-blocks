<?php

// Get latest posts
// See: https://timber.github.io/docs/reference/timber/
$context['posts'] = Timber::get_posts(['numberposts' => $context['posts_count']]);

// set classes
$context['classes'] = [];
if(isset($block['className']) && !empty($block['className'])) {
    $context['classes'][] = $block['className'];
}
if(isset($block['align']) && !empty($block['align'])) {
    $context['classes'][] = 'align' . $block['align'];
}
if(isset($block['mode']) && !empty($block['mode'])) {
    $context['classes'][] = 'mode' . $block['mode'];
}
$context['classes'] = implode(' ', $context['classes']);