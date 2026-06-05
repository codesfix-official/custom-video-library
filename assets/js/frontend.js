/**
 * Custom Video Library - Frontend JavaScript
 */

(function() {
	'use strict';

	const CVL = {
		progressTimers: {},

		init: function() {
			this.initHtml5Players();
			this.initYoutubePlayers();
			this.initVimeoPlayers();
			this.initWishlist();
			this.initLibraryFilters();
		},

		initWishlist: function() {
			const buttons = document.querySelectorAll('.cvl-wishlist-toggle');
			if (!buttons.length) {
				return;
			}

			buttons.forEach((button) => {
				button.addEventListener('click', () => {
					if (!window.cvlData?.ajaxUrl || !window.cvlData?.nonce) {
						return;
					}

					const videoId = button.dataset.videoId || '0';
					button.disabled = true;

					const formData = new FormData();
					formData.append('action', 'cvl_toggle_wishlist');
					formData.append('nonce', cvlData.nonce);
					formData.append('video_id', videoId);

					fetch(cvlData.ajaxUrl, {
						method: 'POST',
						credentials: 'same-origin',
						body: formData,
					})
						.then((res) => res.json())
						.then((payload) => {
							if (!payload || !payload.success || !payload.data) {
								return;
							}

							const active = !!payload.data.is_wishlist;
							button.classList.toggle('is-active', active);

							const label = button.querySelector('.cvl-wishlist-label');
							const text = active ? 'Remove from Wishlist' : 'Add to Wishlist';
							if (label) {
								label.textContent = text;
							} else {
								button.textContent = text;
							}
							button.setAttribute('aria-label', text);
						})
						.finally(() => {
							button.disabled = false;
						});
				});
			});
		},

		initHtml5Players: function() {
			const wrappers = document.querySelectorAll('.cvl-player-wrap[data-provider="self"]');
			wrappers.forEach((wrapper) => {
				const media = wrapper.querySelector('.cvl-html5-media');
				if (!media) {
					return;
				}

				if (media.classList.contains('cvl-html5-audio-player')) {
					const fallbackSrc = media.dataset.fallbackSrc || '';
					if (fallbackSrc) {
						media.addEventListener('error', () => {
							if (media.dataset.fallbackTried === '1') {
								return;
							}
							media.dataset.fallbackTried = '1';
							const sourceNode = media.querySelector('source');
							if (sourceNode) {
								sourceNode.src = fallbackSrc;
							}
							media.pause();
							media.currentTime = 0;
							media.src = fallbackSrc;
							media.load();
							media.play().catch(() => {});
						}, { once: true });
					}
				}

				if (media.classList.contains('cvl-html5-audio-player')) {
					const mediaSrc = media.getAttribute('src') || '';
					let isSameOrigin = false;
					try {
						const parsed = new URL(mediaSrc, window.location.href);
						isSameOrigin = parsed.origin === window.location.origin;
					} catch (e) {
						isSameOrigin = false;
					}

					if (isSameOrigin) {
						CVL.initAudioVisualizer(wrapper, media);
					} else {
						wrapper.classList.add('cvl-visualizer-disabled');
					}
				}

				const resumeSeconds = parseInt(wrapper.dataset.resumeSeconds || '0', 10);
				if (resumeSeconds > 0) {
					media.addEventListener('loadedmetadata', () => {
						const duration = Math.floor(media.duration || 0);
						if (duration > 0 && resumeSeconds < duration - 3) {
							media.currentTime = resumeSeconds;
						}
					}, { once: true });
				}

				let lastSent = 0;
				media.addEventListener('timeupdate', () => {
					const current = Math.floor(media.currentTime || 0);
					if (current - lastSent >= 10) {
						lastSent = current;
						CVL.saveProgress(wrapper, current, Math.floor(media.duration || 0));
					}
				});

				media.addEventListener('ended', () => {
					CVL.saveProgress(wrapper, Math.floor(media.duration || 0), Math.floor(media.duration || 0));
				});
			});
		},

		initAudioVisualizer: function(wrapper, media) {
			const canvas = wrapper.querySelector('.cvl-audio-visualizer');
			if (!canvas || typeof canvas.getContext !== 'function') {
				return;
			}

			const AudioContextCtor = window.AudioContext || window.webkitAudioContext;
			if (!AudioContextCtor) {
				wrapper.classList.add('cvl-visualizer-disabled');
				return;
			}

			const ctx = canvas.getContext('2d');
			if (!ctx) {
				wrapper.classList.add('cvl-visualizer-disabled');
				return;
			}

			let audioCtx = null;
			let analyser = null;
			let source = null;
			let dataArray = null;
			let rafId = null;
			let isReady = false;

			const resizeCanvas = () => {
				const rect = canvas.getBoundingClientRect();
				canvas.width = Math.max(1, Math.floor(rect.width));
				canvas.height = Math.max(1, Math.floor(rect.height));
			};

			const drawIdle = () => {
				ctx.clearRect(0, 0, canvas.width, canvas.height);
				ctx.fillStyle = 'rgba(212, 150, 125, 0.25)';
				ctx.fillRect(0, Math.floor(canvas.height * 0.52), canvas.width, 2);
			};

			const setupAudioGraph = () => {
				if (isReady) {
					return true;
				}

				try {
					audioCtx = new AudioContextCtor();
					analyser = audioCtx.createAnalyser();
					analyser.fftSize = 256;
					analyser.smoothingTimeConstant = 0.82;
					source = audioCtx.createMediaElementSource(media);
					source.connect(analyser);
					analyser.connect(audioCtx.destination);
					dataArray = new Uint8Array(analyser.frequencyBinCount);
					isReady = true;
					return true;
				} catch (e) {
					wrapper.classList.add('cvl-visualizer-disabled');
					return false;
				}
			};

			const draw = () => {
				if (!analyser || !dataArray) {
					return;
				}

				analyser.getByteFrequencyData(dataArray);
				ctx.clearRect(0, 0, canvas.width, canvas.height);

				const bars = 44;
				const gap = 3;
				const barWidth = Math.max(2, (canvas.width - gap * (bars - 1)) / bars);
				const maxHeight = canvas.height - 8;
				const step = Math.max(1, Math.floor(dataArray.length / bars));

				for (let i = 0; i < bars; i++) {
					const value = dataArray[Math.min(dataArray.length - 1, i * step)] / 255;
					const h = Math.max(3, value * maxHeight);
					const x = i * (barWidth + gap);
					const y = (canvas.height - h) / 2;
					ctx.fillStyle = 'rgba(212, 150, 125, 0.92)';
					ctx.fillRect(x, y, barWidth, h);
				}

				rafId = requestAnimationFrame(draw);
			};

			const stop = () => {
				wrapper.classList.remove('cvl-audio-visualizer-active');
				if (rafId) {
					cancelAnimationFrame(rafId);
					rafId = null;
				}
				drawIdle();
			};

			resizeCanvas();
			drawIdle();
			window.addEventListener('resize', resizeCanvas);

			media.addEventListener('play', () => {
				if (!setupAudioGraph()) {
					return;
				}

				wrapper.classList.add('cvl-audio-visualizer-active');
				audioCtx.resume().catch(() => {});
				if (!rafId) {
					draw();
				}
			});

			media.addEventListener('pause', stop);
			media.addEventListener('ended', stop);
		},

		initYoutubePlayers: function() {
			const wrappers = document.querySelectorAll('.cvl-player-wrap[data-provider="youtube"]');
			if (!wrappers.length) {
				return;
			}

			const boot = () => {
				wrappers.forEach((wrapper, index) => {
					const iframe = wrapper.querySelector('.cvl-iframe-player');
					if (!iframe) {
						return;
					}

					if (!iframe.id) {
						iframe.id = 'cvl-youtube-' + index;
					}

					const player = new YT.Player(iframe.id, {
						events: {
							onStateChange: (event) => {
								if (event.data === YT.PlayerState.PLAYING) {
									CVL.startTimer(wrapper, () => {
										CVL.saveProgress(wrapper, Math.floor(player.getCurrentTime() || 0), Math.floor(player.getDuration() || 0));
									});
								}
								if (event.data === YT.PlayerState.PAUSED || event.data === YT.PlayerState.ENDED) {
									CVL.stopTimer(wrapper);
								}
								if (event.data === YT.PlayerState.ENDED) {
									CVL.saveProgress(wrapper, Math.floor(player.getDuration() || 0), Math.floor(player.getDuration() || 0));
								}
							},
						},
					});
				});
			};

			if (typeof window.YT !== 'undefined' && typeof window.YT.Player !== 'undefined') {
				boot();
				return;
			}

			window.onYouTubeIframeAPIReady = boot;
			const script = document.createElement('script');
			script.src = 'https://www.youtube.com/iframe_api';
			document.head.appendChild(script);
		},

		initVimeoPlayers: function() {
			const wrappers = document.querySelectorAll('.cvl-player-wrap[data-provider="vimeo"]');
			if (!wrappers.length) {
				return;
			}

			const boot = () => {
				wrappers.forEach((wrapper) => {
					const iframe = wrapper.querySelector('.cvl-iframe-player');
					if (!iframe || typeof window.Vimeo === 'undefined') {
						return;
					}

					const player = new window.Vimeo.Player(iframe);
					let lastSent = 0;

					player.on('timeupdate', (data) => {
						const current = Math.floor(data.seconds || 0);
						const duration = Math.floor(data.duration || 0);
						if (current - lastSent >= 10) {
							lastSent = current;
							CVL.saveProgress(wrapper, current, duration);
						}
					});

					player.on('ended', (data) => {
						const duration = Math.floor((data && data.duration) || 0);
						CVL.saveProgress(wrapper, duration, duration);
					});
				});
			};

			if (typeof window.Vimeo !== 'undefined' && typeof window.Vimeo.Player !== 'undefined') {
				boot();
				return;
			}

			const script = document.createElement('script');
			script.src = 'https://player.vimeo.com/api/player.js';
			script.onload = boot;
			document.head.appendChild(script);
		},

		startTimer: function(wrapper, callback) {
			const key = wrapper.dataset.videoId;
			if (CVL.progressTimers[key]) {
				clearInterval(CVL.progressTimers[key]);
			}
			CVL.progressTimers[key] = setInterval(callback, 10000);
		},

		stopTimer: function(wrapper) {
			const key = wrapper.dataset.videoId;
			if (CVL.progressTimers[key]) {
				clearInterval(CVL.progressTimers[key]);
				delete CVL.progressTimers[key];
			}
		},

		saveProgress: function(wrapper, currentSeconds, durationSeconds) {
			if (!window.cvlData?.ajaxUrl || !window.cvlData?.nonce) {
				return;
			}

			const formData = new FormData();
			formData.append('action', 'cvl_save_progress');
			formData.append('nonce', cvlData.nonce);
			formData.append('video_id', wrapper.dataset.videoId || '0');
			formData.append('current_seconds', String(Math.max(0, currentSeconds || 0)));
			formData.append('duration_seconds', String(Math.max(0, durationSeconds || 0)));

			fetch(cvlData.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: formData,
			});
		},

		initLibraryFilters: function() {
			const chips = document.querySelectorAll('.cvl-chip-row .cvl-chip');

			if (!chips.length) {
				return;
			}

			chips.forEach((chip) => {
				chip.addEventListener('click', (e) => {
					e.preventDefault();

					const url = new URL(chip.href);
					let category = url.searchParams.get('cvl_cat') || '';
					// Fallback: extract from pathname if it's a category URL
					if (!category && url.pathname.includes('video-category/')) {
						const parts = url.pathname.split('/');
						const catIndex = parts.indexOf('video-category');
						if (catIndex !== -1 && parts[catIndex + 1]) {
							category = parts[catIndex + 1];
						}
					}

					
					const libraryType = this._getLibraryType();
					if (!libraryType) {
						return;
					}

					this._loadFilteredLibrary(libraryType, category, 1);
				});
			});

			const pagination = document.querySelectorAll('.cvl-pagination-link');
			pagination.forEach((link) => {
				link.addEventListener('click', (e) => {
					e.preventDefault();
					const page = parseInt(link.dataset.page) || 1;
					const category = this._getCurrentCategory();
					const libraryType = this._getLibraryType();
					if (!libraryType) return;
					this._loadFilteredLibrary(libraryType, category, page);
				});
			});
		},

		_loadFilteredLibrary: function(libraryType, category, page) {

			if (!window.cvlData?.ajaxUrl || !window.cvlData?.nonce) {
				return;
			}

			const formData = new FormData();
			formData.append('action', 'cvl_filter_library');
			formData.append('nonce', cvlData.nonce);
			formData.append('library_type', libraryType);
			formData.append('category', category);
			formData.append('page', String(page));

			const gridContainer = document.getElementById('cvl-library-grid');
			const paginationContainer = document.getElementById('cvl-library-pagination');

			if (!gridContainer || !paginationContainer) {
				return;
			}


			fetch(cvlData.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: formData,
			})
				.then((res) => {
					return res.json();
				})
				.then((response) => {
					if (!response || !response.success || !response.data) {
						return;
					}

					gridContainer.innerHTML = response.data.html;
					paginationContainer.innerHTML = response.data.pagination || '';

					this._updateChipHighlight(category);

					this.initLibraryFilters();

					window.scrollTo({ top: gridContainer.offsetTop - 100, behavior: 'smooth' });
				})
				.catch((error) => {
					console.log('CVL: AJAX error', error);
				});
		},
		
		_getLibraryType: function() {
			const container = document.querySelector('.cvl-page.cvl-archive-page');
			let libraryType = container?.dataset.libraryType || null;

			// Fallback: check URL
			if (!libraryType) {
				const path = window.location.pathname;
				if (path.includes('free')) libraryType = 'free';
				else if (path.includes('paid')) libraryType = 'paid';
				else if (path.includes('audio')) libraryType = 'audio';
				else if (path.includes('video') || path === '/library/' || path === '/library') libraryType = 'all';
			}
			return libraryType;
		},

		_getCurrentCategory: function() {
			const activeChip = document.querySelector('.cvl-chip.is-active');
			if (!activeChip) return '';

			const url = new URL(activeChip.href);
			let category = url.searchParams.get('cvl_cat') || '';

			// Fallback: extract from pathname
			if (!category && url.pathname.includes('video-category/')) {
				const parts = url.pathname.split('/');
				const catIndex = parts.indexOf('video-category');
				if (catIndex !== -1 && parts[catIndex + 1]) {
					category = parts[catIndex + 1];
				}
			}

			return category;
		},
		
		_updateChipHighlight: function(category) {
			document.querySelectorAll('.cvl-chip').forEach((chip) => {
				const url = new URL(chip.href);
				const chipCategory = url.searchParams.get('cvl_cat') || '';
				if (chipCategory === category) {
					chip.classList.add('is-active');
				} else {
					chip.classList.remove('is-active');
				}
			});
		},
	};

	document.addEventListener('DOMContentLoaded', () => CVL.init());
})();
