<?php
/*
Plugin Name: Dynamically Dynamic Sidebar
Version: 0.1
Description: This plugin enables you to create unlimited widget area and use them for posts, pages,
Author: YOUR NAME HERE
Author URI: YOUR SITE HERE
Plugin URI: PLUGIN SITE HERE
Text Domain: dynamically-dynamic-sidebar
Domain Path: /languages
*/

require 'inc/admin-main.php';
require 'inc/admin-post.php';
require 'inc/admin-term.php';
require 'inc/functions.php';

add_action( 'widgets_init', 'dds_widgets_init' );
function dds_widgets_init() {

	$dds_sidebars = get_option( 'dds_sidebars' );
	if( $dds_sidebars && is_array( $dds_sidebars ) ){
		foreach ( $dds_sidebars as $key => $val ) {
			register_sidebar(
				array(
					'name' => $val,
					'id' => $key,
				)
			);
		}
	}

}

add_filter( 'is_active_sidebar', 'dds_switch_sidebar', 10, 2 );
function dds_switch_sidebar( $is_active_sidebar, $index ) {

	// 投稿やタームで指定されたウィジェットエリアを取得
	$switch = dds_get_desired_widget_area();

	if ( false == $switch ) {
		return $is_active_sidebar;
	}

	// スイッチングされるべきウィジェットエリアを取得
	$dds_target = get_option( 'dds_target_widget_area' );

	if ( ! $dds_target ) {
		return $is_active_sidebar;
	}

	// 今 is_active_sidebar されているものが、ターゲットの場合にだけ実行
	if ( $dds_target === $index ) {

		// ウィジェットエリアの表示用のパラメータを元のところから持ってきて、
		// ユーザーが定義したウィジェットエリアにセットする
		global $wp_registered_sidebars;
		if ( isset( $wp_registered_sidebars[$index] ) ) {
			$original_params = $wp_registered_sidebars[$index]; // この中にテーマなどで定義されたウィジェット周辺のHTML情報が入っている
		} else {
			// テーマを切り替えるなどして、ターゲットに指定されていたウィジェットエリアのIDがなくなることがある
			// その場合は何もしない
			return $is_active_sidebar;
		}

		$params = array(
			'before_widget',
			'after_widget',
			'before_title',
			'after_title',
		);
		foreach ( $params as $p ) {
			// 管理画面で作られたウィジェットエリアにもそれを入れちゃう
			$wp_registered_sidebars[$switch][$p] = $original_params[$p];
		}

		// 止めちゃう
		$is_active_sidebar = false;

		// output the dynamic one.
		do_action( 'dynamically_dynamic_sidebar' );

	}

	return $is_active_sidebar;

}

// 出力！
add_action( 'dynamically_dynamic_sidebar', 'dynamically_dynamic_sidebar' );
function dynamically_dynamic_sidebar() {

	// 投稿やタームで指定されたウィジェットエリアを取得
	$switch = dds_get_desired_widget_area();

	if ( false == $switch ) {
		return;
	}

	// ユーザーが作ったやつのリスト
	$registered  = get_option( 'dds_sidebars' );

	// ユーザー作ったやつが存在すればいよいよ実行
	if ( array_key_exists( $switch, $registered ) ) {

		// is_active_sidebar が無限ループしてしまうのを防いだ
		remove_filter( 'is_active_sidebar', 'dds_switch_sidebar' );

		// 中身があれば出力。無ければ何もしない
		if ( is_active_sidebar( $switch ) ) {
			dynamic_sidebar( $switch );
		}

		// 外したのをもう一度入れておく
		add_filter( 'is_active_sidebar', 'dds_switch_sidebar', 10, 2 );

	} else {

		// ユーザーが投稿やタームで指定したウィジェットエリアが何かの拍子に消えてしまっていたらデフォルト
		dynamic_sidebar( 1 );
	}

}

// return widget area id (ie, sidebar-custom)
function dds_get_desired_widget_area() {

	$widget_area = false;

	if ( is_singular() ) {

		global $post;
		$widget_area = get_post_meta( $post->ID, 'dds_widget_area', true );
		if ( $widget_area ) {
			return $widget_area;
		}

		$by_term = dds_get_widget_of_post_by_term( $post );
		if ( $by_term ) {
			return $by_term["area-id"];
		}


	} elseif ( is_category() || is_tag() || is_tax() ) {

		$queried_obj = get_queried_object();
		$term_id     = $queried_obj->term_id;
		return get_term_meta( $term_id, 'dds_widget_area', true );

	}

	return false;

}

