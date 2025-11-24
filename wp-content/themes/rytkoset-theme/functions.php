<?php

// Perustiedot ja asetukset
function rytkoset_theme_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption']);
}
add_action('after_setup_theme', 'rytkoset_theme_setup');

// Staattiset tyylit ja skriptit
function rytkoset_theme_assets() {
    wp_enqueue_style('rytkoset-style', get_stylesheet_uri());
}
add_action('wp_enqueue_scripts', 'rytkoset_theme_assets');
