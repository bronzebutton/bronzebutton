<?php
/**
 * Plugin Name: Bronze Button
 * Description: 브론즈버튼 콘텐츠 상세페이지 기능을 관리하는 전용 플러그인입니다.
 * Version: 0.1.0
 * Author: Bronze Button
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 플러그인 기본 경로
 */
define( 'BRONZEBUTTON_VERSION', '0.1.0' );
define( 'BRONZEBUTTON_FILE', __FILE__ );
define( 'BRONZEBUTTON_PATH', plugin_dir_path( __FILE__ ) );
define( 'BRONZEBUTTON_URL', plugin_dir_url( __FILE__ ) );

/**
 * 콘텐츠 출력 기능 불러오기
 */
$renderer_file = BRONZEBUTTON_PATH . 'includes/content-renderer.php';

if ( file_exists( $renderer_file ) ) {
	require_once $renderer_file;
}

/**
 * 콘텐츠 상세페이지에서 CSS와 JavaScript를 불러옵니다.
 */
function bronzebutton_enqueue_assets() {

	if ( ! is_singular( 'content' ) ) {
		return;
	}

	wp_enqueue_style(
		'bronzebutton-content',
		BRONZEBUTTON_URL . 'assets/css/content.css',
		array(),
		BRONZEBUTTON_VERSION
	);

	wp_enqueue_script(
		'bronzebutton-content',
		BRONZEBUTTON_URL . 'assets/js/content.js',
		array(),
		BRONZEBUTTON_VERSION,
		true
	);
}

add_action( 'wp_enqueue_scripts', 'bronzebutton_enqueue_assets' );

/**
 * ACF가 설치되지 않았을 때 관리자 알림을 표시합니다.
 */
function bronzebutton_acf_admin_notice() {

	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	if ( function_exists( 'get_field' ) ) {
		return;
	}

	?>
	<div class="notice notice-warning">
		<p>
			<strong>Bronze Button:</strong>
			콘텐츠 기능을 사용하려면 Advanced Custom Fields 플러그인이 필요합니다.
		</p>
	</div>
	<?php
}

add_action( 'admin_notices', 'bronzebutton_acf_admin_notice' );
