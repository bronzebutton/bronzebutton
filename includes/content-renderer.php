<?php
/**
 * Bronze Button content detail renderer.
 *
 * Minimal implementation:
 * - Related YouTube videos (up to 5)
 * - Related contents (up to 3)
 * - Participating channels (up to 3)
 * - WordPress post tags
 *
 * @package BronzeButton
 * @version 0.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read an ACF value safely. If ACF is unavailable or returns an empty value,
 * fall back to the raw post meta value.
 *
 * @param string $field_name ACF field name.
 * @param int    $post_id    Post ID.
 * @param mixed  $default    Default value.
 * @return mixed
 */
function bronzebutton_get_field_value( $field_name, $post_id, $default = '' ) {
	$value = null;

	if ( function_exists( 'get_field' ) ) {
		$value = get_field( $field_name, $post_id );
	}

	if ( null === $value || false === $value || '' === $value ) {
		$value = get_post_meta( $post_id, $field_name, true );
	}

	if ( null === $value || false === $value || '' === $value ) {
		return $default;
	}

	return $value;
}

/**
 * Read a text field safely.
 *
 * @param string $field_name ACF field name.
 * @param int    $post_id    Post ID.
 * @return string
 */
function bronzebutton_get_text_field( $field_name, $post_id ) {
	$value = bronzebutton_get_field_value( $field_name, $post_id, '' );

	if ( is_array( $value ) || is_object( $value ) ) {
		return '';
	}

	return trim( (string) $value );
}

/**
 * Read a URL field safely.
 *
 * @param string $field_name ACF field name.
 * @param int    $post_id    Post ID.
 * @return string
 */
function bronzebutton_get_url_field( $field_name, $post_id ) {
	$url = bronzebutton_get_text_field( $field_name, $post_id );

	return '' !== $url ? esc_url_raw( $url ) : '';
}

/**
 * Extract a YouTube video ID from common YouTube URL formats.
 *
 * @param string $url YouTube URL.
 * @return string
 */
function bronzebutton_get_youtube_video_id( $url ) {
	$url = trim( (string) $url );

	if ( '' === $url ) {
		return '';
	}

	$parts = wp_parse_url( $url );

	if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
		return '';
	}

	$host = strtolower( $parts['host'] );
	$path = isset( $parts['path'] ) ? trim( $parts['path'], '/' ) : '';

	if ( false !== strpos( $host, 'youtu.be' ) && '' !== $path ) {
		$path_parts = explode( '/', $path );

		return preg_replace(
			'/[^a-zA-Z0-9_-]/',
			'',
			$path_parts[0]
		);
	}

	if ( false !== strpos( $host, 'youtube.com' ) ) {
		if ( ! empty( $parts['query'] ) ) {
			parse_str( $parts['query'], $query );

			if ( ! empty( $query['v'] ) ) {
				return preg_replace(
					'/[^a-zA-Z0-9_-]/',
					'',
					$query['v']
				);
			}
		}

		if (
			preg_match(
				'#^(?:shorts|embed)/([^/?]+)#',
				$path,
				$matches
			)
		) {
			return preg_replace(
				'/[^a-zA-Z0-9_-]/',
				'',
				$matches[1]
			);
		}
	}

	return '';
}

/**
 * Collect related video data from video_1 through video_5 fields.
 *
 * @param int $post_id Post ID.
 * @return array
 */
function bronzebutton_get_related_videos( $post_id ) {
	$videos = array();

	for ( $index = 1; $index <= 5; $index++ ) {
		$title = bronzebutton_get_text_field(
			'video_' . $index . '_title',
			$post_id
		);

		$url = bronzebutton_get_url_field(
			'video_' . $index . '_url',
			$post_id
		);

		$description = bronzebutton_get_text_field(
			'video_' . $index . '_description',
			$post_id
		);

		$video_id = bronzebutton_get_youtube_video_id( $url );

		if (
			'' === $title &&
			'' === $url &&
			'' === $description
		) {
			continue;
		}

		$videos[] = array(
			'title'       => $title,
			'url'         => $url,
			'description' => $description,
			'video_id'    => $video_id,
		);
	}

	return $videos;
}

/**
 * Convert an ACF Post Object, Relationship, Select,
 * or numeric value to a post ID.
 *
 * @param mixed $value Field value.
 * @return int
 */
function bronzebutton_get_related_post_id( $value ) {
	if ( $value instanceof WP_Post ) {
		return (int) $value->ID;
	}

	if ( is_numeric( $value ) ) {
		return (int) $value;
	}

	if ( is_array( $value ) ) {
		if ( ! empty( $value['ID'] ) ) {
			return (int) $value['ID'];
		}

		if (
			! empty( $value['value'] ) &&
			is_numeric( $value['value'] )
		) {
			return (int) $value['value'];
		}
	}

	return 0;
}

/**
 * Collect related content data from related_content_1
 * through related_content_3.
 *
 * @param int $post_id Post ID.
 * @return array
 */
function bronzebutton_get_related_contents( $post_id ) {
	$items = array();

	for ( $index = 1; $index <= 3; $index++ ) {
		$value = bronzebutton_get_field_value(
			'related_content_' . $index,
			$post_id,
			''
		);

		$values = array();

		if ( is_array( $value ) && isset( $value[0] ) ) {
			$values = $value;
		} else {
			$values[] = $value;
		}

		foreach ( $values as $related_value ) {
			$related_post_id =
				bronzebutton_get_related_post_id(
					$related_value
				);

			if (
				! $related_post_id ||
				$related_post_id === (int) $post_id
			) {
				continue;
			}

			if (
				'publish' !==
				get_post_status( $related_post_id )
			) {
				continue;
			}

			if ( isset( $items[ $related_post_id ] ) ) {
				continue;
			}

			$items[ $related_post_id ] = array(
				'id'        => $related_post_id,
				'title'     => get_the_title(
					$related_post_id
				),
				'url'       => get_permalink(
					$related_post_id
				),
				'thumbnail' => get_the_post_thumbnail_url(
					$related_post_id,
					'medium_large'
				),
				'short_description' =>
					bronzebutton_get_text_field(
						'short_description',
						$related_post_id
					),
			);
		}
	}

	return array_values( $items );
}

/**
 * Collect channel data from channel_1 through channel_3 fields.
 *
 * @param int $post_id Post ID.
 * @return array
 */
function bronzebutton_get_channels( $post_id ) {
	$channels = array();

	for ( $index = 1; $index <= 3; $index++ ) {
		$name = bronzebutton_get_text_field(
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

		$channels[] = array(
			'name' => '' !== $name
				? $name
				: '유튜브 채널',
			'url'  => $url,
		);
	}

	return $channels;
}

/**
 * Collect all data needed by the detail renderer.
 *
 * @param int $post_id Post ID.
 * @return array
 */
function bronzebutton_get_content_data( $post_id ) {
	$tags = wp_get_post_terms(
		$post_id,
		'post_tag'
	);

	if ( is_wp_error( $tags ) ) {
		$tags = array();
	}

	return array(
		'short_description' =>
			bronzebutton_get_text_field(
				'short_description',
				$post_id
			),
		'videos' =>
			bronzebutton_get_related_videos(
				$post_id
			),
		'related_contents' =>
			bronzebutton_get_related_contents(
				$post_id
			),
		'channels' =>
			bronzebutton_get_channels(
				$post_id
			),
		'tags' => $tags,
	);
}

/* 1-1 끝: 다음 1-2 코드를 바로 이어서 붙여넣으세요. */
/**
 * Render a section heading.
 *
 * @param string $title Section title.
 * @return string
 */
function bronzebutton_render_section_title( $title ) {
	return sprintf(
		'<h2 class="bb-section-title">%s</h2>',
		esc_html( $title )
	);
}

/**
 * Render one YouTube video card.
 *
 * @param array $video Video data.
 * @return string
 */
function bronzebutton_render_video_card( $video ) {
	$title = ! empty( $video['title'] )
		? trim( (string) $video['title'] )
		: '';

	$description = ! empty( $video['description'] )
		? trim( (string) $video['description'] )
		: '';

	$url = ! empty( $video['url'] )
		? esc_url_raw( $video['url'] )
		: '';

	$video_id = ! empty( $video['video_id'] )
		? trim( (string) $video['video_id'] )
		: '';

	ob_start();
	?>
	<article class="bb-video-card">

		<div class="bb-video-embed">

			<?php if ( '' !== $video_id ) : ?>

				<iframe
					src="<?php
					echo esc_url(
						'https://www.youtube.com/embed/' .
						rawurlencode( $video_id )
					);
					?>"
					title="<?php
					echo esc_attr(
						'' !== $title
							? $title
							: 'YouTube video'
					);
					?>"
					loading="lazy"
					allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
					allowfullscreen
				></iframe>

			<?php elseif ( '' !== $url ) : ?>

				<a
					class="bb-video-fallback"
					href="<?php echo esc_url( $url ); ?>"
					target="_blank"
					rel="noopener noreferrer"
				>
					유튜브에서 영상 보기
				</a>

			<?php else : ?>

				<div class="bb-video-empty">
					영상 주소가 없습니다.
				</div>

			<?php endif; ?>

		</div>

		<?php if ( '' !== $title ) : ?>

			<h3 class="bb-video-title">

				<?php if ( '' !== $url ) : ?>

					<a
						href="<?php echo esc_url( $url ); ?>"
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
				echo nl2br(
					esc_html( $description )
				);
				?>
			</p>

		<?php endif; ?>

	</article>
	<?php

	return ob_get_clean();
}

/**
 * Render the related videos section.
 *
 * @param array $videos Video data.
 * @return string
 */
function bronzebutton_render_videos_section( $videos ) {
	if ( empty( $videos ) || ! is_array( $videos ) ) {
		return '';
	}

	ob_start();
	?>
	<section class="bb-section bb-videos-section">

		<?php
		echo bronzebutton_render_section_title(
			'관련 영상'
		);
		?>

		<div class="bb-video-grid">

			<?php foreach ( $videos as $video ) : ?>

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
 * Render one related content card.
 *
 * @param array $item Related content data.
 * @return string
 */
function bronzebutton_render_related_content_card( $item ) {
	if (
		empty( $item['title'] ) ||
		empty( $item['url'] )
	) {
		return '';
	}

	$title = trim( (string) $item['title'] );
	$url   = esc_url_raw( $item['url'] );

	$thumbnail = ! empty( $item['thumbnail'] )
		? esc_url_raw( $item['thumbnail'] )
		: '';

	$description = ! empty(
		$item['short_description']
	)
		? trim(
			(string) $item['short_description']
		)
		: '';

	ob_start();
	?>
	<article class="bb-related-card">

		<a
			class="bb-related-thumbnail-link"
			href="<?php echo esc_url( $url ); ?>"
		>

			<div class="bb-related-thumbnail">

				<?php if ( '' !== $thumbnail ) : ?>

					<img
						src="<?php
						echo esc_url( $thumbnail );
						?>"
						alt="<?php
						echo esc_attr( $title );
						?>"
						loading="lazy"
					>

				<?php else : ?>

					<div class="bb-related-placeholder">
						BRONZE BUTTON
					</div>

				<?php endif; ?>

			</div>

		</a>

		<div class="bb-related-body">

			<h3 class="bb-related-title">

				<a href="<?php echo esc_url( $url ); ?>">
					<?php echo esc_html( $title ); ?>
				</a>

			</h3>

			<?php if ( '' !== $description ) : ?>

				<p class="bb-related-description">
					<?php
					echo esc_html(
						wp_trim_words(
							$description,
							24,
							'…'
						)
					);
					?>
				</p>

			<?php endif; ?>

		</div>

	</article>
	<?php

	return ob_get_clean();
}

/**
 * Render related content section.
 *
 * @param array $items Related contents.
 * @return string
 */
function bronzebutton_render_related_contents_section( $items ) {
	if ( empty( $items ) || ! is_array( $items ) ) {
		return '';
	}

	ob_start();
	?>
	<section class="bb-section bb-related-section">

		<?php
		echo bronzebutton_render_section_title(
			'관련 콘텐츠'
		);
		?>

		<div class="bb-related-grid">

			<?php foreach ( $items as $item ) : ?>

				<?php
				echo bronzebutton_render_related_content_card(
					$item
				);
				?>

			<?php endforeach; ?>

		</div>

	</section>
	<?php

	return ob_get_clean();
}

/**
 * Render the channel section.
 *
 * @param array $channels Channel data.
 * @return string
 */
function bronzebutton_render_channels_section( $channels ) {
	if (
		empty( $channels ) ||
		! is_array( $channels )
	) {
		return '';
	}

	ob_start();
	?>
	<section class="bb-section bb-channels-section">

		<?php
		echo bronzebutton_render_section_title(
			'참여한 유튜브 채널'
		);
		?>

		<div class="bb-channel-grid">

			<?php foreach ( $channels as $channel ) : ?>

				<?php
				$name = ! empty( $channel['name'] )
					? trim(
						(string) $channel['name']
					)
					: '유튜브 채널';

				$url = ! empty( $channel['url'] )
					? esc_url_raw(
						$channel['url']
					)
					: '';
				?>

				<article class="bb-channel-card">

					<div class="bb-channel-name">
						<?php echo esc_html( $name ); ?>
					</div>

					<?php if ( '' !== $url ) : ?>

						<a
							class="bb-channel-button"
							href="<?php
							echo esc_url( $url );
							?>"
							target="_blank"
							rel="noopener noreferrer"
						>
							채널 보기
						</a>

					<?php endif; ?>

				</article>

			<?php endforeach; ?>

		</div>

	</section>
	<?php

	return ob_get_clean();
}

/**
 * Render the WordPress tag section.
 *
 * @param array $tags WordPress terms.
 * @return string
 */
function bronzebutton_render_tags_section( $tags ) {
	if ( empty( $tags ) || ! is_array( $tags ) ) {
		return '';
	}

	ob_start();
	?>
	<section class="bb-section bb-tags-section">

		<?php
		echo bronzebutton_render_section_title(
			'브론즈버튼 태그'
		);
		?>

		<div class="bb-tag-list">

			<?php foreach ( $tags as $tag ) : ?>

				<?php
				if ( ! $tag instanceof WP_Term ) {
					continue;
				}

				$tag_url = get_term_link( $tag );

				if ( is_wp_error( $tag_url ) ) {
					continue;
				}
				?>

				<a
					class="bb-tag"
					href="<?php
					echo esc_url( $tag_url );
					?>"
				>
					#<?php echo esc_html( $tag->name ); ?>
				</a>

			<?php endforeach; ?>

		</div>

	</section>
	<?php

	return ob_get_clean();
}

/**
 * Render the complete Bronze Button content detail area.
 *
 * @param int $post_id Post ID.
 * @return string
 */
function bronzebutton_render_content_detail( $post_id ) {
	$data = bronzebutton_get_content_data(
		$post_id
	);

	ob_start();
	?>
	<div class="bb-content-wrapper">

		<?php
		if (
			! empty(
				$data['short_description']
			)
		) :
		?>

			<div class="bb-short-description">

				<p>
					<?php
					echo nl2br(
						esc_html(
							$data[
								'short_description'
							]
						)
					);
					?>
				</p>

			</div>

		<?php endif; ?>

		<?php
		echo bronzebutton_render_videos_section(
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
 * Replace the main content body with the Bronze Button renderer.
 *
 * @param string $content Existing WordPress content.
 * @return string
 */
function bronzebutton_replace_content( $content ) {
	if ( is_admin() ) {
		return $content;
	}

	if ( ! is_singular() ) {
		return $content;
	}

	if ( ! in_the_loop() ) {
		return $content;
	}

	if ( ! is_main_query() ) {
		return $content;
	}

	$post_id = (int) get_the_ID();

	if ( ! $post_id ) {
		return $content;
	}

	$queried_post_id = (int) get_queried_object_id();

	if ( $post_id !== $queried_post_id ) {
		return $content;
	}

	$post_type = get_post_type( $post_id );

	/*
	 * CPT UI에서 생성한 콘텐츠 포스트 타입의 슬러그가
	 * content 또는 contents인 경우를 기본 지원합니다.
	 */
	$allowed_post_types = array(
		'content',
		'contents',
		'bronzebutton_content',
	);

	/*
	 * 현재 게시물에 브론즈버튼 ACF 필드가 존재하면
	 * 포스트 타입 슬러그가 달라도 출력할 수 있도록 합니다.
	 */
	$has_bronzebutton_fields =
		'' !== bronzebutton_get_text_field(
			'short_description',
			$post_id
		) ||
		'' !== bronzebutton_get_url_field(
			'video_1_url',
			$post_id
		) ||
		'' !== bronzebutton_get_text_field(
			'channel_1_name',
			$post_id
		);

	if (
		! in_array(
			$post_type,
			$allowed_post_types,
			true
		) &&
		! $has_bronzebutton_fields
	) {
		return $content;
	}

	return bronzebutton_render_content_detail(
		$post_id
	);
}

add_filter(
	'the_content',
	'bronzebutton_replace_content',
	99
);

/**
 * Add a body class to Bronze Button content pages.
 *
 * @param array $classes Body classes.
 * @return array
 */
function bronzebutton_add_body_class( $classes ) {
	if ( ! is_singular() ) {
		return $classes;
	}

	$post_id = (int) get_queried_object_id();

	if ( ! $post_id ) {
		return $classes;
	}

	$has_bronzebutton_fields =
		'' !== bronzebutton_get_text_field(
			'short_description',
			$post_id
		) ||
		'' !== bronzebutton_get_url_field(
			'video_1_url',
			$post_id
		) ||
		'' !== bronzebutton_get_text_field(
			'channel_1_name',
			$post_id
		);

	if ( $has_bronzebutton_fields ) {
		$classes[] = 'bronzebutton-content-page';
	}

	return $classes;
}

add_filter(
	'body_class',
	'bronzebutton_add_body_class'
);
