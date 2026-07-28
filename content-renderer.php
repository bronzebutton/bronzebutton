<?php
/**
 * Bronze Button content renderer.
 *
 * @package BronzeButton
 * @version 0.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 브론즈버튼 콘텐츠 포스트 타입 슬러그.
 *
 * CPT UI에서 다른 슬러그를 사용했다면
 * 아래 content 값을 변경하면 됩니다.
 */
if ( ! defined( 'BRONZEBUTTON_CONTENT_POST_TYPE' ) ) {
	define( 'BRONZEBUTTON_CONTENT_POST_TYPE', 'content' );
}

/**
 * ACF 필드값을 안전하게 가져옵니다.
 *
 * @param string     $field_name ACF 필드 이름.
 * @param int|string $post_id    게시물 ID.
 * @param mixed      $default    기본값.
 *
 * @return mixed
 */
function bronzebutton_get_field_value( $field_name, $post_id = 0, $default = '' ) {
	if ( ! function_exists( 'get_field' ) ) {
		return $default;
	}

	$post_id = $post_id ? $post_id : get_the_ID();
	$value   = get_field( $field_name, $post_id );

	if ( null === $value || false === $value || '' === $value ) {
		return $default;
	}

	return $value;
}

/**
 * 문자열 ACF 필드값을 가져옵니다.
 *
 * @param string     $field_name ACF 필드 이름.
 * @param int|string $post_id    게시물 ID.
 * @param string     $default    기본값.
 *
 * @return string
 */
function bronzebutton_get_string_field( $field_name, $post_id = 0, $default = '' ) {
	$value = bronzebutton_get_field_value(
		$field_name,
		$post_id,
		$default
	);

	if ( is_array( $value ) || is_object( $value ) ) {
		return $default;
	}

	return trim( (string) $value );
}

/**
 * URL ACF 필드값을 가져옵니다.
 *
 * @param string     $field_name ACF 필드 이름.
 * @param int|string $post_id    게시물 ID.
 *
 * @return string
 */
function bronzebutton_get_url_field( $field_name, $post_id = 0 ) {
	$url = bronzebutton_get_string_field(
		$field_name,
		$post_id
	);

	if ( '' === $url ) {
		return '';
	}

	return esc_url_raw( $url );
}

/**
 * 현재 페이지가 브론즈버튼 콘텐츠 상세페이지인지 확인합니다.
 *
 * @return bool
 */
function bronzebutton_is_content_detail_page() {
	return is_singular( BRONZEBUTTON_CONTENT_POST_TYPE );
}

/**
 * 유튜브 URL에서 영상 ID를 추출합니다.
 *
 * 지원 형식:
 *
 * https://www.youtube.com/watch?v=VIDEO_ID
 * https://youtube.com/watch?v=VIDEO_ID
 * https://youtu.be/VIDEO_ID
 * https://www.youtube.com/shorts/VIDEO_ID
 * https://www.youtube.com/embed/VIDEO_ID
 *
 * @param string $url 유튜브 URL.
 *
 * @return string
 */
function bronzebutton_get_youtube_video_id( $url ) {
	$url = trim( (string) $url );

	if ( '' === $url ) {
		return '';
	}

	$parsed_url = wp_parse_url( $url );

	if (
		! is_array( $parsed_url ) ||
		empty( $parsed_url['host'] )
	) {
		return '';
	}

	$host = strtolower( $parsed_url['host'] );
	$path = isset( $parsed_url['path'] )
		? trim( $parsed_url['path'], '/' )
		: '';

	/**
	 * youtube.com/watch?v=VIDEO_ID
	 */
	if (
		false !== strpos( $host, 'youtube.com' ) &&
		! empty( $parsed_url['query'] )
	) {
		parse_str(
			$parsed_url['query'],
			$query_parameters
		);

		if ( ! empty( $query_parameters['v'] ) ) {
			return bronzebutton_sanitize_youtube_video_id(
				$query_parameters['v']
			);
		}
	}

	/**
	 * youtu.be/VIDEO_ID
	 */
	if (
		false !== strpos( $host, 'youtu.be' ) &&
		'' !== $path
	) {
		$path_parts = explode( '/', $path );

		if ( ! empty( $path_parts[0] ) ) {
			return bronzebutton_sanitize_youtube_video_id(
				$path_parts[0]
			);
		}
	}

	/**
	 * youtube.com/shorts/VIDEO_ID
	 */
	if (
		false !== strpos( $host, 'youtube.com' ) &&
		preg_match(
			'#^shorts/([^/?]+)#',
			$path,
			$matches
		)
	) {
		return bronzebutton_sanitize_youtube_video_id(
			$matches[1]
		);
	}

	/**
	 * youtube.com/embed/VIDEO_ID
	 */
	if (
		false !== strpos( $host, 'youtube.com' ) &&
		preg_match(
			'#^embed/([^/?]+)#',
			$path,
			$matches
		)
	) {
		return bronzebutton_sanitize_youtube_video_id(
			$matches[1]
		);
	}

	return '';
}

/**
 * 유튜브 영상 ID를 정리합니다.
 *
 * @param string $video_id 영상 ID.
 *
 * @return string
 */
function bronzebutton_sanitize_youtube_video_id( $video_id ) {
	$video_id = preg_replace(
		'/[^a-zA-Z0-9_-]/',
		'',
		(string) $video_id
	);

	return trim( $video_id );
}

/**
 * 유튜브 썸네일 URL을 반환합니다.
 *
 * @param string $video_id 영상 ID.
 * @param string $quality  썸네일 품질.
 *
 * @return string
 */
function bronzebutton_get_youtube_thumbnail_url(
	$video_id,
	$quality = 'hqdefault'
) {
	$video_id = bronzebutton_sanitize_youtube_video_id(
		$video_id
	);

	if ( '' === $video_id ) {
		return '';
	}

	$allowed_qualities = array(
		'default',
		'mqdefault',
		'hqdefault',
		'sddefault',
		'maxresdefault',
	);

	if ( ! in_array( $quality, $allowed_qualities, true ) ) {
		$quality = 'hqdefault';
	}

	return sprintf(
		'https://i.ytimg.com/vi/%1$s/%2$s.jpg',
		rawurlencode( $video_id ),
		$quality
	);
}

/**
 * 유튜브 개인정보 보호 강화 임베드 URL을 반환합니다.
 *
 * @param string $video_id 영상 ID.
 * @param bool   $autoplay 자동재생 여부.
 *
 * @return string
 */
function bronzebutton_get_youtube_embed_url(
	$video_id,
	$autoplay = false
) {
	$video_id = bronzebutton_sanitize_youtube_video_id(
		$video_id
	);

	if ( '' === $video_id ) {
		return '';
	}

	$query_arguments = array(
		'rel' => 0,
	);

	if ( $autoplay ) {
		$query_arguments['autoplay'] = 1;
	}

	$embed_url = sprintf(
		'https://www.youtube-nocookie.com/embed/%s',
		rawurlencode( $video_id )
	);

	return add_query_arg(
		$query_arguments,
		$embed_url
	);
}

/**
 * 관련 영상 ACF 데이터를 구성합니다.
 *
 * 현재 v0.2에서는 영상 1번부터 5번까지 지원합니다.
 *
 * 필요한 필드 이름:
 *
 * video_1_title
 * video_1_url
 * video_1_description
 *
 * 위 형식으로 video_5_*까지 생성합니다.
 *
 * @param int $post_id 게시물 ID.
 *
 * @return array
 */
function bronzebutton_get_related_videos( $post_id ) {
	$videos = array();

	for ( $index = 1; $index <= 5; $index++ ) {
		$title = bronzebutton_get_string_field(
			'video_' . $index . '_title',
			$post_id
		);

		$url = bronzebutton_get_url_field(
			'video_' . $index . '_url',
			$post_id
		);

		$description = bronzebutton_get_string_field(
			'video_' . $index . '_description',
			$post_id
		);

		/**
		 * 제목, URL, 설명이 모두 없으면
		 * 해당 영상 항목은 출력하지 않습니다.
		 */
		if (
			'' === $title &&
			'' === $url &&
			'' === $description
		) {
			continue;
		}

		$video_id = bronzebutton_get_youtube_video_id(
			$url
		);

		$videos[] = array(
			'index'         => $index,
			'title'         => $title,
			'url'           => $url,
			'description'   => $description,
			'video_id'      => $video_id,
			'thumbnail_url' => bronzebutton_get_youtube_thumbnail_url(
				$video_id
			),
			'embed_url'     => bronzebutton_get_youtube_embed_url(
				$video_id
			),
		);
	}

	return $videos;
}

/**
 * ACF Post Object 또는 Relationship 필드에서
 * 게시물 ID를 추출합니다.
 *
 * @param mixed $value ACF 반환값.
 *
 * @return int
 */
function bronzebutton_get_post_id_from_acf_value( $value ) {
	if ( $value instanceof WP_Post ) {
		return (int) $value->ID;
	}

	if ( is_numeric( $value ) ) {
		return (int) $value;
	}

	if (
		is_array( $value ) &&
		! empty( $value['ID'] )
	) {
		return (int) $value['ID'];
	}

	return 0;
}

/**
 * 관련 콘텐츠 ACF 데이터를 구성합니다.
 *
 * 필요한 필드 이름:
 *
 * related_content_1
 * related_content_2
 * related_content_3
 *
 * ACF 필드 유형:
 * Post Object 또는 Relationship
 *
 * @param int $post_id 현재 게시물 ID.
 *
 * @return array
 */
function bronzebutton_get_related_contents( $post_id ) {
	$related_contents = array();

	for ( $index = 1; $index <= 3; $index++ ) {
		$field_value = bronzebutton_get_field_value(
			'related_content_' . $index,
			$post_id,
			null
		);

		/**
		 * Relationship 필드처럼 배열로 반환되는 경우를 지원합니다.
		 */
		if (
			is_array( $field_value ) &&
			isset( $field_value[0] )
		) {
			$field_value = $field_value[0];
		}

		$related_post_id = bronzebutton_get_post_id_from_acf_value(
			$field_value
		);

		if ( ! $related_post_id ) {
			continue;
		}

		if ( $related_post_id === (int) $post_id ) {
			continue;
		}

		if ( 'publish' !== get_post_status( $related_post_id ) ) {
			continue;
		}

		/**
		 * 동일 콘텐츠가 여러 필드에 중복 지정된 경우
		 * 한 번만 출력합니다.
		 */
		if ( isset( $related_contents[ $related_post_id ] ) ) {
			continue;
		}

		$thumbnail_url = get_the_post_thumbnail_url(
			$related_post_id,
			'large'
		);

		$short_description = bronzebutton_get_string_field(
			'short_description',
			$related_post_id
		);

		$related_contents[ $related_post_id ] = array(
			'id'                => $related_post_id,
			'title'             => get_the_title( $related_post_id ),
			'permalink'         => get_permalink( $related_post_id ),
			'thumbnail_url'     => $thumbnail_url
				? $thumbnail_url
				: '',
			'short_description' => $short_description,
		);
	}

	return array_values( $related_contents );
}

/**
 * 참여 채널 ACF 데이터를 구성합니다.
 *
 * 필요한 필드 이름:
 *
 * channel_1_name
 * channel_1_url
 *
 * 위 형식으로 channel_3_*까지 생성합니다.
 *
 * @param int $post_id 게시물 ID.
 *
 * @return array
 */
function bronzebutton_get_participating_channels( $post_id ) {
	$channels = array();

	for ( $index = 1; $index <= 3; $index++ ) {
		$name = bronzebutton_get_string_field(
			'channel_' . $index . '_name',
			$post_id
		);

		$url = bronzebutton_get_url_field(
			'channel_' . $index . '_url',
			$post_id
		);

		if ( '' === $name && '' === $url ) {
			continue;
		}

		if ( '' === $name ) {
			$name = '유튜브 채널';
		}

		$channels[] = array(
			'index' => $index,
			'name'  => $name,
			'url'   => $url,
		);
	}

	return $channels;
}

/**
 * 콘텐츠의 워드프레스 태그를 반환합니다.
 *
 * @param int $post_id 게시물 ID.
 *
 * @return array
 */
function bronzebutton_get_content_tags( $post_id ) {
	$tags = get_the_tags( $post_id );

	if ( ! $tags || is_wp_error( $tags ) ) {
		return array();
	}

	return $tags;
}

/**
 * 콘텐츠 상세페이지 출력에 필요한 데이터를 한 번에 구성합니다.
 *
 * @param int $post_id 게시물 ID.
 *
 * @return array
 */
function bronzebutton_get_content_detail_data( $post_id ) {
	return array(
		'post_id'           => (int) $post_id,
		'short_description' => bronzebutton_get_string_field(
			'short_description',
			$post_id
		),
		'videos'            => bronzebutton_get_related_videos(
			$post_id
		),
		'related_contents'  => bronzebutton_get_related_contents(
			$post_id
		),
		'channels'          => bronzebutton_get_participating_channels(
			$post_id
		),
		'tags'              => bronzebutton_get_content_tags(
			$post_id
		),
	);
}

/**
 * v0.2 — 2-2에서 아래에 이어집니다.
 *
 * 다음 부분:
 *
 * - 관련 영상 HTML 출력
 * - 영상 썸네일 및 재생 버튼
 * - 영상 제목과 설명 출력
 */
 /**
 * 섹션 제목 영역을 출력합니다.
 *
 * @param string $title 섹션 제목.
 * @param int    $count 항목 개수.
 * @param string $unit  개수 단위.
 *
 * @return string
 */
function bronzebutton_render_section_heading(
	$title,
	$count = 0,
	$unit = ''
) {
	$title = trim( (string) $title );
	$count = absint( $count );
	$unit  = trim( (string) $unit );

	if ( '' === $title ) {
		return '';
	}

	ob_start();
	?>
	<div class="bb-section-heading">
		<h2 class="bb-section-title">
			<?php echo esc_html( $title ); ?>
		</h2>

		<?php if ( $count > 0 ) : ?>
			<span class="bb-section-count">
				<?php
				echo esc_html(
					sprintf(
						'%1$s%2$s',
						number_format_i18n( $count ),
						$unit
					)
				);
				?>
			</span>
		<?php endif; ?>
	</div>
	<?php

	return ob_get_clean();
}

/**
 * 영상 제목이 없을 때 사용할 기본 제목을 반환합니다.
 *
 * @param array $video 영상 데이터.
 *
 * @return string
 */
function bronzebutton_get_video_accessible_title( $video ) {
	if (
		isset( $video['title'] ) &&
		'' !== trim( (string) $video['title'] )
	) {
		return trim( (string) $video['title'] );
	}

	$index = isset( $video['index'] )
		? absint( $video['index'] )
		: 0;

	if ( $index > 0 ) {
		return sprintf(
			'관련 영상 %s',
			number_format_i18n( $index )
		);
	}

	return '관련 영상';
}

/**
 * 유튜브 영상 썸네일 플레이어를 출력합니다.
 *
 * 실제 iframe은 처음부터 불러오지 않습니다.
 * 사용자가 재생 버튼을 누르면 JavaScript가 iframe으로 교체합니다.
 *
 * @param array $video 영상 데이터.
 *
 * @return string
 */
function bronzebutton_render_video_player( $video ) {
	$video_id = isset( $video['video_id'] )
		? bronzebutton_sanitize_youtube_video_id(
			$video['video_id']
		)
		: '';

	$thumbnail_url = isset( $video['thumbnail_url'] )
		? esc_url_raw( $video['thumbnail_url'] )
		: '';

	$video_url = isset( $video['url'] )
		? esc_url_raw( $video['url'] )
		: '';

	$accessible_title = bronzebutton_get_video_accessible_title(
		$video
	);

	ob_start();

	if ( '' === $video_id ) :
		?>
		<div class="bb-video-player bb-video-player-error">
			<div class="bb-video-error">
				<p class="bb-video-error-message">
					유튜브 영상 주소를 확인해주세요.
				</p>

				<?php if ( '' !== $video_url ) : ?>
					<a
						class="bb-video-original-link"
						href="<?php echo esc_url( $video_url ); ?>"
						target="_blank"
						rel="noopener noreferrer"
					>
						영상 주소 열기
					</a>
				<?php endif; ?>
			</div>
		</div>
		<?php

		return ob_get_clean();
	endif;
	?>
	<div
		class="bb-video-player"
		data-video-id="<?php echo esc_attr( $video_id ); ?>"
	>
		<button
			type="button"
			class="bb-video-thumbnail-button"
			aria-label="<?php echo esc_attr( $accessible_title . ' 재생' ); ?>"
		>
			<?php if ( '' !== $thumbnail_url ) : ?>
				<img
					class="bb-video-thumbnail"
					src="<?php echo esc_url( $thumbnail_url ); ?>"
					alt="<?php echo esc_attr( $accessible_title ); ?>"
					loading="lazy"
					decoding="async"
				>
			<?php endif; ?>

			<span
				class="bb-video-thumbnail-overlay"
				aria-hidden="true"
			></span>

			<span
				class="bb-play-button"
				aria-hidden="true"
			>
				<span class="bb-play-icon"></span>
			</span>
		</button>

		<noscript>
			<div class="bb-video-noscript">
				<a
					href="<?php echo esc_url( $video_url ); ?>"
					target="_blank"
					rel="noopener noreferrer"
				>
					유튜브에서 영상 보기
				</a>
			</div>
		</noscript>
	</div>
	<?php

	return ob_get_clean();
}

/**
 * 영상 카드 본문을 출력합니다.
 *
 * @param array $video 영상 데이터.
 *
 * @return string
 */
function bronzebutton_render_video_body( $video ) {
	$title = isset( $video['title'] )
		? trim( (string) $video['title'] )
		: '';

	$description = isset( $video['description'] )
		? trim( (string) $video['description'] )
		: '';

	$video_url = isset( $video['url'] )
		? esc_url_raw( $video['url'] )
		: '';

	if ( '' === $title && '' === $description ) {
		return '';
	}

	ob_start();
	?>
	<div class="bb-video-body">
		<?php if ( '' !== $title ) : ?>
			<h3 class="bb-video-title">
				<?php if ( '' !== $video_url ) : ?>
					<a
						class="bb-video-title-link"
						href="<?php echo esc_url( $video_url ); ?>"
						target="_blank"
						rel="noopener noreferrer"
					>
						<?php echo esc_html( $title ); ?>
					</a>
				<?php else : ?>
					<?php echo esc_html( $title ); ?>
				<?php endif; ?>
			</h3>
		<?php endif; ?>

		<?php if ( '' !== $description ) : ?>
			<p class="bb-video-description">
				<?php
				echo wp_kses_post(
					nl2br(
						esc_html( $description )
					)
				);
				?>
			</p>
		<?php endif; ?>
	</div>
	<?php

	return ob_get_clean();
}

/**
 * 관련 영상 카드 하나를 출력합니다.
 *
 * @param array $video 영상 데이터.
 *
 * @return string
 */
function bronzebutton_render_video_card( $video ) {
	if ( ! is_array( $video ) || empty( $video ) ) {
		return '';
	}

	$index = isset( $video['index'] )
		? absint( $video['index'] )
		: 0;

	$card_classes = array(
		'bb-video-card',
	);

	if ( $index > 0 ) {
		$card_classes[] = 'bb-video-card-' . $index;
	}

	ob_start();
	?>
	<article
		class="<?php echo esc_attr( implode( ' ', $card_classes ) ); ?>"
	>
		<?php
		echo bronzebutton_render_video_player(
			$video
		);
		?>

		<?php
		echo bronzebutton_render_video_body(
			$video
		);
		?>
	</article>
	<?php

	return ob_get_clean();
}

/**
 * 관련 영상 섹션 전체를 출력합니다.
 *
 * @param array $videos 관련 영상 배열.
 *
 * @return string
 */
function bronzebutton_render_related_videos_section( $videos ) {
	if ( ! is_array( $videos ) || empty( $videos ) ) {
		return '';
	}

	$valid_videos = array();

	foreach ( $videos as $video ) {
		if ( ! is_array( $video ) || empty( $video ) ) {
			continue;
		}

		$valid_videos[] = $video;
	}

	if ( empty( $valid_videos ) ) {
		return '';
	}

	ob_start();
	?>
	<section
		class="bb-section bb-video-section"
		aria-labelledby="bb-related-video-title"
	>
		<div id="bb-related-video-title">
			<?php
			echo bronzebutton_render_section_heading(
				'관련 영상',
				count( $valid_videos ),
				'개의 영상'
			);
			?>
		</div>

		<div class="bb-video-grid">
			<?php foreach ( $valid_videos as $video ) : ?>
				<?php
				echo bronzebutton_render_video_card(
					$video
				);
				?>
			<?php endforeach; ?>
		</div>
	</section>
	<?php

	return ob_get_clean();
}

/**
 * 관련 콘텐츠 설명을 지정된 길이로 정리합니다.
 *
 * @param string $description 설명.
 * @param int    $word_count  최대 단어 수.
 *
 * @return string
 */
function bronzebutton_trim_related_description(
	$description,
	$word_count = 24
) {
	$description = trim(
		wp_strip_all_tags(
			(string) $description
		)
	);

	if ( '' === $description ) {
		return '';
	}

	$word_count = absint( $word_count );

	if ( $word_count < 1 ) {
		$word_count = 24;
	}

	return wp_trim_words(
		$description,
		$word_count,
		'…'
	);
}

/**
 * v0.2 — 2-3에서 아래에 이어집니다.
 *
 * 다음 부분:
 *
 * - 관련 콘텐츠 카드 출력
 * - 참여 채널 출력
 * - 태그 출력
 */
 /**
 * 관련 콘텐츠 카드의 대표 이미지를 출력합니다.
 *
 * @param array $content 관련 콘텐츠 데이터.
 *
 * @return string
 */
function bronzebutton_render_related_content_thumbnail( $content ) {
	$title = isset( $content['title'] )
		? trim( (string) $content['title'] )
		: '';

	$permalink = isset( $content['permalink'] )
		? esc_url_raw( $content['permalink'] )
		: '';

	$thumbnail_url = isset( $content['thumbnail_url'] )
		? esc_url_raw( $content['thumbnail_url'] )
		: '';

	if ( '' === $permalink ) {
		return '';
	}

	ob_start();
	?>
	<a
		class="bb-related-content-thumbnail-link"
		href="<?php echo esc_url( $permalink ); ?>"
		aria-label="<?php echo esc_attr( $title ); ?>"
	>
		<div class="bb-related-content-thumbnail">
			<?php if ( '' !== $thumbnail_url ) : ?>
				<img
					class="bb-related-content-image"
					src="<?php echo esc_url( $thumbnail_url ); ?>"
					alt="<?php echo esc_attr( $title ); ?>"
					loading="lazy"
					decoding="async"
				>
			<?php else : ?>
				<div
					class="bb-related-content-placeholder"
					aria-hidden="true"
				>
					<span class="bb-related-content-placeholder-text">
						Bronze Button
					</span>
				</div>
			<?php endif; ?>
		</div>
	</a>
	<?php

	return ob_get_clean();
}

/**
 * 관련 콘텐츠 카드 하나를 출력합니다.
 *
 * @param array $content 관련 콘텐츠 데이터.
 *
 * @return string
 */
function bronzebutton_render_related_content_card( $content ) {
	if ( ! is_array( $content ) || empty( $content ) ) {
		return '';
	}

	$content_id = isset( $content['id'] )
		? absint( $content['id'] )
		: 0;

	$title = isset( $content['title'] )
		? trim( (string) $content['title'] )
		: '';

	$permalink = isset( $content['permalink'] )
		? esc_url_raw( $content['permalink'] )
		: '';

	$description = isset( $content['short_description'] )
		? bronzebutton_trim_related_description(
			$content['short_description']
		)
		: '';

	if ( ! $content_id || '' === $permalink ) {
		return '';
	}

	if ( '' === $title ) {
		$title = '관련 콘텐츠';
	}

	ob_start();
	?>
	<article
		class="bb-related-content-card"
		data-content-id="<?php echo esc_attr( $content_id ); ?>"
	>
		<?php
		echo bronzebutton_render_related_content_thumbnail(
			$content
		);
		?>

		<div class="bb-related-content-body">
			<h3 class="bb-related-content-title">
				<a
					class="bb-related-content-title-link"
					href="<?php echo esc_url( $permalink ); ?>"
				>
					<?php echo esc_html( $title ); ?>
				</a>
			</h3>

			<?php if ( '' !== $description ) : ?>
				<p class="bb-related-content-description">
					<?php echo esc_html( $description ); ?>
				</p>
			<?php endif; ?>

			<a
				class="bb-related-content-more"
				href="<?php echo esc_url( $permalink ); ?>"
				aria-label="<?php echo esc_attr( $title . ' 자세히 보기' ); ?>"
			>
				<span class="bb-related-content-more-text">
					자세히 보기
				</span>

				<span
					class="bb-related-content-more-icon"
					aria-hidden="true"
				>
					→
				</span>
			</a>
		</div>
	</article>
	<?php

	return ob_get_clean();
}

/**
 * 관련 콘텐츠 섹션 전체를 출력합니다.
 *
 * @param array $related_contents 관련 콘텐츠 배열.
 *
 * @return string
 */
function bronzebutton_render_related_contents_section(
	$related_contents
) {
	if (
		! is_array( $related_contents ) ||
		empty( $related_contents )
	) {
		return '';
	}

	$valid_contents = array();

	foreach ( $related_contents as $content ) {
		if ( ! is_array( $content ) || empty( $content ) ) {
			continue;
		}

		if ( empty( $content['id'] ) ) {
			continue;
		}

		$valid_contents[] = $content;
	}

	if ( empty( $valid_contents ) ) {
		return '';
	}

	ob_start();
	?>
	<section
		class="bb-section bb-related-content-section"
		aria-labelledby="bb-related-content-title"
	>
		<div id="bb-related-content-title">
			<?php
			echo bronzebutton_render_section_heading(
				'관련 콘텐츠',
				count( $valid_contents ),
				'개'
			);
			?>
		</div>

		<div class="bb-related-content-grid">
			<?php foreach ( $valid_contents as $content ) : ?>
				<?php
				echo bronzebutton_render_related_content_card(
					$content
				);
				?>
			<?php endforeach; ?>
		</div>
	</section>
	<?php

	return ob_get_clean();
}

/**
 * 참여 채널 카드 하나를 출력합니다.
 *
 * @param array $channel 참여 채널 데이터.
 *
 * @return string
 */
function bronzebutton_render_channel_item( $channel ) {
	if ( ! is_array( $channel ) || empty( $channel ) ) {
		return '';
	}

	$name = isset( $channel['name'] )
		? trim( (string) $channel['name'] )
		: '';

	$url = isset( $channel['url'] )
		? esc_url_raw( $channel['url'] )
		: '';

	$index = isset( $channel['index'] )
		? absint( $channel['index'] )
		: 0;

	if ( '' === $name && '' === $url ) {
		return '';
	}

	if ( '' === $name ) {
		$name = '유튜브 채널';
	}

	ob_start();
	?>
	<li
		class="bb-channel-item<?php echo $index ? ' bb-channel-item-' . esc_attr( $index ) : ''; ?>"
	>
		<?php if ( '' !== $url ) : ?>
			<a
				class="bb-channel-link"
				href="<?php echo esc_url( $url ); ?>"
				target="_blank"
				rel="noopener noreferrer"
			>
				<span
					class="bb-channel-icon"
					aria-hidden="true"
				>
					<svg
						viewBox="0 0 24 24"
						focusable="false"
						aria-hidden="true"
					>
						<path
							d="M21.58 7.19a2.99 2.99 0 0 0-2.1-2.12C17.64 4.5 12 4.5 12 4.5s-5.64 0-7.48.57a2.99 2.99 0 0 0-2.1 2.12A31.2 31.2 0 0 0 2 12a31.2 31.2 0 0 0 .42 4.81 2.99 2.99 0 0 0 2.1 2.12c1.84.57 7.48.57 7.48.57s5.64 0 7.48-.57a2.99 2.99 0 0 0 2.1-2.12A31.2 31.2 0 0 0 22 12a31.2 31.2 0 0 0-.42-4.81ZM10 15.5v-7l6 3.5-6 3.5Z"
						/>
					</svg>
				</span>

				<span class="bb-channel-name">
					<?php echo esc_html( $name ); ?>
				</span>

				<span
					class="bb-channel-external-icon"
					aria-hidden="true"
				>
					↗
				</span>
			</a>
		<?php else : ?>
			<div class="bb-channel-link bb-channel-link-disabled">
				<span
					class="bb-channel-icon"
					aria-hidden="true"
				>
					<svg
						viewBox="0 0 24 24"
						focusable="false"
						aria-hidden="true"
					>
						<path
							d="M21.58 7.19a2.99 2.99 0 0 0-2.1-2.12C17.64 4.5 12 4.5 12 4.5s-5.64 0-7.48.57a2.99 2.99 0 0 0-2.1 2.12A31.2 31.2 0 0 0 2 12a31.2 31.2 0 0 0 .42 4.81 2.99 2.99 0 0 0 2.1 2.12c1.84.57 7.48.57 7.48.57s5.64 0 7.48-.57a2.99 2.99 0 0 0 2.1-2.12A31.2 31.2 0 0 0 22 12a31.2 31.2 0 0 0-.42-4.81ZM10 15.5v-7l6 3.5-6 3.5Z"
						/>
					</svg>
				</span>

				<span class="bb-channel-name">
					<?php echo esc_html( $name ); ?>
				</span>
			</div>
		<?php endif; ?>
	</li>
	<?php

	return ob_get_clean();
}

/**
 * 참여 채널 섹션 전체를 출력합니다.
 *
 * @param array $channels 참여 채널 배열.
 *
 * @return string
 */
function bronzebutton_render_channels_section( $channels ) {
	if ( ! is_array( $channels ) || empty( $channels ) ) {
		return '';
	}

	$valid_channels = array();

	foreach ( $channels as $channel ) {
		if ( ! is_array( $channel ) || empty( $channel ) ) {
			continue;
		}

		if (
			empty( $channel['name'] ) &&
			empty( $channel['url'] )
		) {
			continue;
		}

		$valid_channels[] = $channel;
	}

	if ( empty( $valid_channels ) ) {
		return '';
	}

	ob_start();
	?>
	<section
		class="bb-section bb-channel-section"
		aria-labelledby="bb-channel-section-title"
	>
		<div id="bb-channel-section-title">
			<?php
			echo bronzebutton_render_section_heading(
				'참여 채널',
				count( $valid_channels ),
				'개'
			);
			?>
		</div>

		<ul class="bb-channel-list">
			<?php foreach ( $valid_channels as $channel ) : ?>
				<?php
				echo bronzebutton_render_channel_item(
					$channel
				);
				?>
			<?php endforeach; ?>
		</ul>
	</section>
	<?php

	return ob_get_clean();
}

/**
 * 태그 하나를 출력합니다.
 *
 * @param WP_Term $tag 워드프레스 태그 객체.
 *
 * @return string
 */
function bronzebutton_render_tag_item( $tag ) {
	if ( ! $tag instanceof WP_Term ) {
		return '';
	}

	$tag_link = get_term_link( $tag );

	if ( is_wp_error( $tag_link ) ) {
		return '';
	}

	$tag_name = trim( (string) $tag->name );

	if ( '' === $tag_name ) {
		return '';
	}

	ob_start();
	?>
	<li class="bb-tag-item">
		<a
			class="bb-tag-link"
			href="<?php echo esc_url( $tag_link ); ?>"
		>
			<span aria-hidden="true">#</span><?php echo esc_html( $tag_name ); ?>
		</a>
	</li>
	<?php

	return ob_get_clean();
}

/**
 * 태그 섹션 전체를 출력합니다.
 *
 * @param array $tags 워드프레스 태그 배열.
 *
 * @return string
 */
function bronzebutton_render_tags_section( $tags ) {
	if ( ! is_array( $tags ) || empty( $tags ) ) {
		return '';
	}

	$valid_tags = array();

	foreach ( $tags as $tag ) {
		if ( ! $tag instanceof WP_Term ) {
			continue;
		}

		$valid_tags[] = $tag;
	}

	if ( empty( $valid_tags ) ) {
		return '';
	}

	ob_start();
	?>
	<section
		class="bb-section bb-tag-section"
		aria-labelledby="bb-tag-section-title"
	>
		<h2
			id="bb-tag-section-title"
			class="bb-screen-reader-text"
		>
			관련 태그
		</h2>

		<ul class="bb-tag-list">
			<?php foreach ( $valid_tags as $tag ) : ?>
				<?php
				echo bronzebutton_render_tag_item(
					$tag
				);
				?>
			<?php endforeach; ?>
		</ul>
	</section>
	<?php

	return ob_get_clean();
}

/**
 * v0.2 — 2-4에서 아래에 이어집니다.
 *
 * 다음 부분:
 *
 * - 콘텐츠 상세페이지 전체 조합
 * - 본문 자동 삽입
 * - CSS/JavaScript 로드
 * - 최종 훅 등록
 */
 /**
 * 콘텐츠 상세페이지 전체 HTML을 생성합니다.
 *
 * @param int $post_id 게시물 ID.
 *
 * @return string
 */
function bronzebutton_render_content_detail( $post_id ) {

	$data = bronzebutton_get_content_detail_data( $post_id );

	ob_start();
	?>

	<div class="bb-content-wrapper">

		<?php
		if ( ! empty( $data['short_description'] ) ) :
		?>
			<div class="bb-short-description">
				<p>
					<?php echo esc_html( $data['short_description'] ); ?>
				</p>
			</div>
		<?php endif; ?>

		<?php
		echo bronzebutton_render_related_videos_section(
			$data['videos']
		);
		?>

		<?php
		echo bronzebutton_render_related_contents_section(
			$data['related_contents']
		);
		?>

		<?php
		echo bronzebutton_render_channels_section(
			$data['channels']
		);
		?>

		<?php
		echo bronzebutton_render_tags_section(
			$data['tags']
		);
		?>

	</div>

	<?php

	return ob_get_clean();
}

/**
 * 본문 뒤에 브론즈버튼 콘텐츠를 자동 추가합니다.
 */
function bronzebutton_append_content( $content ) {

	if (
		is_admin() ||
		! is_main_query() ||
		! in_the_loop()
	) {
		return $content;
	}

	if ( ! bronzebutton_is_content_detail_page() ) {
		return $content;
	}

	return $content . bronzebutton_render_content_detail(
		get_the_ID()
	);
}

add_filter(
	'the_content',
	'bronzebutton_append_content',
	99
);

/**
 * CSS 등록
 */
function bronzebutton_enqueue_content_css() {

	if ( ! bronzebutton_is_content_detail_page() ) {
		return;
	}

	wp_enqueue_style(
		'bronzebutton-content',
		plugins_url(
			'assets/css/content.css',
			dirname( __FILE__ )
		),
		array(),
		'0.2.0'
	);
}

add_action(
	'wp_enqueue_scripts',
	'bronzebutton_enqueue_content_css'
);

/**
 * JavaScript 등록
 */
function bronzebutton_enqueue_content_js() {

	if ( ! bronzebutton_is_content_detail_page() ) {
		return;
	}

	wp_enqueue_script(
		'bronzebutton-content',
		plugins_url(
			'assets/js/content.js',
			dirname( __FILE__ )
		),
		array(),
		'0.2.0',
		true
	);

	wp_localize_script(
		'bronzebutton-content',
		'BronzeButton',
		array(
			'embedBase' => 'https://www.youtube-nocookie.com/embed/',
		)
	);
}

add_action(
	'wp_enqueue_scripts',
	'bronzebutton_enqueue_content_js'
);

/**
 * Body Class 추가
 */
function bronzebutton_body_class( $classes ) {

	if ( bronzebutton_is_content_detail_page() ) {
		$classes[] = 'bronzebutton-content-page';
	}

	return $classes;
}

add_filter(
	'body_class',
	'bronzebutton_body_class'
);

/**
 * 플러그인 초기화
 */
function bronzebutton_content_renderer_init() {

	/**
	 * 향후 버전에서
	 *
	 * Universe
	 * 식당
	 * SEO
	 * Schema.org
	 * Open Graph
	 *
	 * 등을 여기서 초기화합니다.
	 */

	return true;
}

add_action(
	'init',
	'bronzebutton_content_renderer_init'
);
