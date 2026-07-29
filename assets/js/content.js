## Hi there/**
 * Bronze Button v0.2
 * content.js
 * Part 4-1
 */

(function () {
	'use strict';

	/**
	 * 브라우저가 DOM API를 지원하는지 확인합니다.
	 *
	 * @returns {boolean}
	 */
	function supportsRequiredFeatures() {
		return (
			typeof document !== 'undefined' &&
			typeof document.querySelectorAll === 'function' &&
			typeof window !== 'undefined'
		);
	}

	/**
	 * 문자열을 안전하게 정리합니다.
	 *
	 * @param {*} value
	 * @returns {string}
	 */
	function toSafeString(value) {
		if (value === null || typeof value === 'undefined') {
			return '';
		}

		return String(value).trim();
	}

	/**
	 * 유튜브 영상 ID를 정리합니다.
	 *
	 * @param {*} value
	 * @returns {string}
	 */
	function sanitizeVideoId(value) {
		return toSafeString(value).replace(/[^a-zA-Z0-9_-]/g, '');
	}

	/**
	 * PHP에서 전달된 embedBase 값을 가져옵니다.
	 *
	 * @returns {string}
	 */
	function getEmbedBase() {
		if (
			typeof window.BronzeButton === 'object' &&
			window.BronzeButton !== null &&
			typeof window.BronzeButton.embedBase === 'string'
		) {
			return window.BronzeButton.embedBase;
		}

		return 'https://www.youtube-nocookie.com/embed/';
	}

	/**
	 * 유튜브 임베드 URL을 생성합니다.
	 *
	 * @param {string} videoId
	 * @param {boolean} autoplay
	 * @returns {string}
	 */
	function buildEmbedUrl(videoId, autoplay) {
		var cleanVideoId = sanitizeVideoId(videoId);
		var embedBase = getEmbedBase();

		if (!cleanVideoId) {
			return '';
		}

		var parameters = [
			'rel=0',
			'modestbranding=1',
			'playsinline=1'
		];

		if (autoplay) {
			parameters.push('autoplay=1');
		}

		return (
			embedBase +
			encodeURIComponent(cleanVideoId) +
			'?' +
			parameters.join('&')
		);
	}

	/**
	 * iframe의 제목을 생성합니다.
	 *
	 * @param {HTMLElement} player
	 * @returns {string}
	 */
	function getIframeTitle(player) {
		var button;
		var label;

		if (!player) {
			return '유튜브 영상';
		}

		button = player.querySelector('.bb-video-thumbnail-button');

		if (button) {
			label = toSafeString(
				button.getAttribute('aria-label')
			);

			if (label) {
				return label.replace(/\s*재생\s*$/, '');
			}
		}

		return '유튜브 영상';
	}

	/**
	 * iframe 요소를 생성합니다.
	 *
	 * @param {HTMLElement} player
	 * @param {string} videoId
	 * @returns {HTMLIFrameElement|null}
	 */
	function createIframe(player, videoId) {
		var iframe;
		var embedUrl;

		embedUrl = buildEmbedUrl(videoId, true);

		if (!embedUrl) {
			return null;
		}

		iframe = document.createElement('iframe');

		iframe.className = 'bb-video-iframe';
		iframe.src = embedUrl;
		iframe.title = getIframeTitle(player);
		iframe.loading = 'lazy';
		iframe.allowFullscreen = true;

		iframe.setAttribute(
			'allow',
			'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share'
		);

		iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
		iframe.setAttribute('frameborder', '0');

		return iframe;
	}

	/**
	 * 플레이어를 오류 상태로 변경합니다.
	 *
	 * @param {HTMLElement} player
	 * @param {string} message
	 */
	function renderPlayerError(player, message) {
		var errorWrapper;
		var errorMessage;

		if (!player) {
			return;
		}

		player.innerHTML = '';
		player.classList.remove('bb-loading');
		player.classList.add('bb-video-player-error');

		errorWrapper = document.createElement('div');
		errorWrapper.className = 'bb-video-error';

		errorMessage = document.createElement('p');
		errorMessage.className = 'bb-video-error-message';
		errorMessage.textContent =
			toSafeString(message) || '영상을 불러오지 못했습니다.';

		errorWrapper.appendChild(errorMessage);
		player.appendChild(errorWrapper);
	}

	/**
	 * 플레이어 내부를 iframe으로 교체합니다.
	 *
	 * @param {HTMLElement} player
	 */
	function activateVideoPlayer(player) {
		var videoId;
		var iframe;

		if (!player) {
			return;
		}

		if (player.getAttribute('data-player-active') === 'true') {
			return;
		}

		videoId = sanitizeVideoId(
			player.getAttribute('data-video-id')
		);

		if (!videoId) {
			renderPlayerError(
				player,
				'유튜브 영상 주소를 확인해주세요.'
			);
			return;
		}

		iframe = createIframe(player, videoId);

		if (!iframe) {
			renderPlayerError(
				player,
				'영상 재생 주소를 생성하지 못했습니다.'
			);
			return;
		}

		player.setAttribute('data-player-active', 'true');
		player.classList.add('bb-loading');

		iframe.addEventListener(
			'load',
			function () {
				player.classList.remove('bb-loading');
			},
			{ once: true }
		);

		iframe.addEventListener(
			'error',
			function () {
				renderPlayerError(
					player,
					'영상을 불러오는 중 오류가 발생했습니다.'
				);
			},
			{ once: true }
		);

		player.innerHTML = '';
		player.appendChild(iframe);
	}

	/**
	 * 영상 재생 버튼 클릭 이벤트를 처리합니다.
	 *
	 * @param {MouseEvent} event
	 */
	function handleVideoClick(event) {
		var button;
		var player;

		button = event.target.closest(
			'.bb-video-thumbnail-button'
		);

		if (!button) {
			return;
		}

		player = button.closest('.bb-video-player');

		if (!player) {
			return;
		}

		event.preventDefault();

		activateVideoPlayer(player);
	}

	/**
	 * 키보드 입력으로 영상 재생을 처리합니다.
	 *
	 * button 요소는 기본적으로 Enter와 Space를 지원하지만,
	 * 일부 테마나 스크립트 충돌 상황을 대비합니다.
	 *
	 * @param {KeyboardEvent} event
	 */
	function handleVideoKeydown(event) {
		var button;
		var player;

		if (event.key !== 'Enter' && event.key !== ' ') {
			return;
		}

		button = event.target.closest(
			'.bb-video-thumbnail-button'
		);

		if (!button) {
			return;
		}

		player = button.closest('.bb-video-player');

		if (!player) {
			return;
		}

		event.preventDefault();

		activateVideoPlayer(player);
	}

	/**
	 * 영상 플레이어 이벤트를 등록합니다.
	 *
	 * 이벤트 위임 방식이므로 여러 영상이 있어도
	 * 문서에 이벤트를 한 번만 등록합니다.
	 */
	function bindVideoPlayerEvents() {
		document.addEventListener(
			'click',
			handleVideoClick
		);

		document.addEventListener(
			'keydown',
			handleVideoKeydown
		);
	}

	/**
	 * 플레이어 초기 상태를 점검합니다.
	 */
	function prepareVideoPlayers() {
		var players = document.querySelectorAll(
			'.bb-video-player[data-video-id]'
		);

		players.forEach(function (player) {
			var videoId = sanitizeVideoId(
				player.getAttribute('data-video-id')
			);

			if (!videoId) {
				player.classList.add(
					'bb-video-player-invalid'
				);
			}

			player.setAttribute(
				'data-player-ready',
				'true'
			);
		});
	}
	/**
	 * IntersectionObserver 애니메이션
	 */
	function initializeFadeAnimation() {

		if (!('IntersectionObserver' in window)) {
			return;
		}

		var elements = document.querySelectorAll('.bb-fade-up');

		if (!elements.length) {
			return;
		}

		var observer = new IntersectionObserver(function (entries) {

			entries.forEach(function (entry) {

				if (!entry.isIntersecting) {
					return;
				}

				entry.target.classList.add('is-visible');

				observer.unobserve(entry.target);

			});

		}, {
			root: null,
			rootMargin: '0px',
			threshold: 0.15
		});

		elements.forEach(function (element) {
			observer.observe(element);
		});

	}

	/**
	 * data-bb-animation 속성이 있는 요소에
	 * 자동으로 애니메이션 클래스를 부여합니다.
	 */
	function prepareAnimations() {

		document
			.querySelectorAll('[data-bb-animation]')
			.forEach(function (element) {

				element.classList.add('bb-fade-up');

			});

	}

	/**
	 * AJAX 등으로 새 콘텐츠가 추가되었을 때
	 * 다시 초기화할 수 있도록 공개 API 제공합니다.
	 */
	function refresh() {

		prepareVideoPlayers();

		prepareAnimations();

		initializeFadeAnimation();

	}

	/**
	 * 이미 초기화되었는지 확인
	 */
	function alreadyInitialized() {

		return document.documentElement.hasAttribute(
			'data-bronzebutton-ready'
		);

	}

	function markInitialized() {

		document.documentElement.setAttribute(
			'data-bronzebutton-ready',
			'true'
		);

	}

	/**
	 * 전체 초기화
	 */
	function initialize() {

		if (alreadyInitialized()) {
			return;
		}

		markInitialized();

		prepareVideoPlayers();

		bindVideoPlayerEvents();

		prepareAnimations();

		initializeFadeAnimation();

	}

	/**
	 * 외부에서 사용할 수 있는 API
	 */
	window.BronzeButton = window.BronzeButton || {};

	window.BronzeButton.refresh = refresh;

	/**
	 * DOM Ready
	 */
	if (document.readyState === 'loading') {

		document.addEventListener(
			'DOMContentLoaded',
			initialize
		);

	} else {

		initialize();

	}

})();
