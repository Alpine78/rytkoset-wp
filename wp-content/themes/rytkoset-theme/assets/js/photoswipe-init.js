(function () {
    var hasPhotoSwipe = typeof PhotoSwipeLightbox !== 'undefined' && typeof PhotoSwipe !== 'undefined';

    var computeDimensions = function (img) {
        if (!img) return null;

        var naturalW = img.naturalWidth || parseInt(img.getAttribute('width'), 10) || 0;
        var naturalH = img.naturalHeight || parseInt(img.getAttribute('height'), 10) || 0;

        var ratio = naturalW && naturalH ? naturalH / naturalW : 0;

        var maxW = naturalW;
        var maxH = naturalH;

        if (img.srcset) {
            var candidates = img.srcset.split(',').map(function (item) {
                var parts = item.trim().split(' ');
                var widthPart = parts.find(function (p) {
                    return p.endsWith('w');
                });
                var width = widthPart ? parseInt(widthPart, 10) : 0;
                return width;
            });
            var candidateMax = Math.max.apply(Math, candidates);
            if (candidateMax && ratio) {
                maxW = candidateMax;
                maxH = Math.round(candidateMax * ratio);
            }
        }

        if (!maxW || !maxH) {
            return null;
        }

        return { width: maxW, height: maxH };
    };

    var setLinkDimensions = function (link, img) {
        var dims = computeDimensions(img);
        if (!dims) return;

        link.dataset.pswpWidth = dims.width;
        link.dataset.pswpHeight = dims.height;

        if (!link.dataset.pswpSrcset && img.srcset) {
            link.dataset.pswpSrcset = img.srcset;
        }

        if (!link.dataset.pswpSizes && img.sizes) {
            link.dataset.pswpSizes = img.sizes;
        }
    };

    var normalizeImageLink = function (img) {
        if (!img) return;

        var figure = img.closest('figure');
        var existingLink = img.closest('a');

        if (!existingLink && figure) {
            existingLink = figure.querySelector('a');
        }

        var link = existingLink;

        if (!link) {
            link = document.createElement('a');
            link.href = img.currentSrc || img.src;
            link.className = 'js-gallery-item';
            setLinkDimensions(link, img);

            if (img.parentNode) {
                img.parentNode.insertBefore(link, img);
                link.appendChild(img);
            }
        }

        if (!link.dataset.pswpWidth || !link.dataset.pswpHeight) {
            setLinkDimensions(link, img);
        }

        if (!link.dataset.pswpType) {
            link.dataset.pswpType = 'image';
        }

        link.classList.add('pswp-link');
        return link;
    };

    var relayoutMasonryGrid = function (grid) {
        if (!grid) return;

        var style = window.getComputedStyle(grid);

        // If layout is flex (horizontal rows), size items by target thumb height and aspect ratio,
        // and justify each row to the container width.
        if (style.display === 'flex') {
            var flexItems = grid.querySelectorAll('.gallery-grid__item, figure.wp-block-image, .blocks-gallery-item');
            var targetHeight = parseFloat(style.getPropertyValue('--thumb-height')) || 200;
            var targetWidthFallback = targetHeight * 1.5;
            var gap = parseFloat(style.getPropertyValue('column-gap') || style.getPropertyValue('gap')) || 0;
            var maxLastRowScale = parseFloat(style.getPropertyValue('--masonry-last-row-max-scale')) || 1.75;
            var minLastRowScale = parseFloat(style.getPropertyValue('--masonry-last-row-min-scale')) || 0.9;
            var paddingLeft = parseFloat(style.paddingLeft) || 0;
            var paddingRight = parseFloat(style.paddingRight) || 0;
            var containerWidth = grid.clientWidth - paddingLeft - paddingRight;

            var currentRow = [];
            var widthSum = 0;

            var flushRow = function (isLast) {
                if (!currentRow.length || containerWidth <= 0 || widthSum <= 0) {
                    return;
                }

                var gapsTotal = gap * Math.max(0, currentRow.length - 1);
                var scale = (containerWidth - gapsTotal) / widthSum;

                if (isLast) {
                    // Keep the last row at the target height (no up/down scaling).
                    scale = 1;
                }

                var rowHeight = targetHeight * scale;

                currentRow.forEach(function (entry) {
                    var width = Math.max(60, entry.width * scale);
                    var item = entry.item;
                    var img = entry.img;

                    item.style.gridRowEnd = '';
                    item.style.height = rowHeight + 'px';
                    item.style.width = Math.round(width) + 'px';
                    item.style.flex = '0 0 auto';

                    if (img) {
                        img.style.height = rowHeight + 'px';
                        img.style.width = 'auto';
                    }
                });
            };

            flexItems.forEach(function (item) {
                var img = item.querySelector('img');
                var ratio = 0;

                // 1) Yritä luonnollisista mitoista (kun kuva on ladattu)
                if (img && img.naturalWidth && img.naturalHeight) {
                    ratio = img.naturalHeight / img.naturalWidth;
                }

                // 2) Jos kuva ei ole vielä ladattu → käytä width/height -attribuutteja
                if (!ratio && img) {
                    var attrW = parseInt(img.getAttribute('width'), 10);
                    var attrH = parseInt(img.getAttribute('height'), 10);
                    if (attrW && attrH) {
                        ratio = attrH / attrW;
                    }
                }

                // 3) Viimeinen fallback: data-* attribuutit (esim. custom-grid)
                if (!ratio) {
                    var dataW = parseInt(item.getAttribute('data-pswp-width'), 10) ||
                                parseInt(item.getAttribute('data-width'), 10);
                    var dataH = parseInt(item.getAttribute('data-pswp-height'), 10) ||
                                parseInt(item.getAttribute('data-height'), 10);
                    if (dataW && dataH) {
                        ratio = dataH / dataW;
                    }
                }

                var width = ratio ? (targetHeight / ratio) : targetWidthFallback;
                var tentativeWidth = widthSum + width;
                var gapsSoFar = gap * Math.max(0, currentRow.length);

                if (currentRow.length && (tentativeWidth + gapsSoFar) > containerWidth) {
                    flushRow(false);
                    currentRow = [];
                    widthSum = 0;
                }

                currentRow.push({ item: item, img: img, width: width });
                widthSum += width;
            });

            flushRow(true);
            return;
        }

        var rowHeight = parseFloat(style.getPropertyValue('--masonry-row-height')) || 2;
        var gap = parseFloat(style.getPropertyValue('row-gap') || style.getPropertyValue('grid-row-gap')) || parseFloat(style.getPropertyValue('gap')) || 0;

        var items = grid.querySelectorAll('.gallery-grid__item, figure.wp-block-image, .blocks-gallery-item');

        items.forEach(function (item) {
            var img = item.querySelector('img');
            var ratio = 0;

            if (img && img.naturalWidth && img.naturalHeight) {
                ratio = img.naturalHeight / img.naturalWidth;
            }

            if (!ratio) {
                var dataW = parseInt(item.getAttribute('data-pswp-width'), 10);
                var dataH = parseInt(item.getAttribute('data-pswp-height'), 10);
                if (dataW && dataH) {
                    ratio = dataH / dataW;
                }
            }

            var width = item.getBoundingClientRect().width;
            if (!width || !ratio) {
                return;
            }

            var height = width * ratio;
            var span = Math.max(1, Math.ceil((height + gap) / (rowHeight + gap)));
            item.style.gridRowEnd = 'span ' + span;
            item.style.height = Math.ceil(height) + 'px';
        });
    };

    var relayoutAllGrids = function () {
        var grids = Array.prototype.slice.call(document.querySelectorAll('.js-gallery-grid, .album .wp-block-gallery.has-nested-images'));
        if (!grids.length) {
            return;
        }
        grids.forEach(relayoutMasonryGrid);
    };

    var initMasonryGrids = function () {
        var grids = Array.prototype.slice.call(document.querySelectorAll('.js-gallery-grid, .album .wp-block-gallery.has-nested-images'));
        if (!grids.length) {
            return;
        }

        grids.forEach(function (grid) {
            if (grid._rytkosetMasonryInit) {
                return;
            }
            grid._rytkosetMasonryInit = true;

            grid.querySelectorAll('img').forEach(function (img) {
                var onLoad = function () {
                    relayoutMasonryGrid(grid);
                };

                if (img.complete) {
                    onLoad();
                } else {
                    img.addEventListener('load', onLoad, { once: true });
                    img.addEventListener('error', onLoad, { once: true });
                }
            });
        });

        window.addEventListener('resize', function () {
            clearTimeout(initMasonryGrids._resizeTimer);
            initMasonryGrids._resizeTimer = setTimeout(relayoutAllGrids, 120);
        });

        window.addEventListener('load', relayoutAllGrids, { once: true });
        window.addEventListener('pageshow', relayoutAllGrids, { once: true });

        relayoutAllGrids();
        setTimeout(relayoutAllGrids, 180);
        setTimeout(relayoutAllGrids, 420);
    };

    var loadDynamicCaptionPlugin = function () {
        if (window.PhotoSwipeDynamicCaption) {
            return Promise.resolve(window.PhotoSwipeDynamicCaption);
        }

        if (loadDynamicCaptionPlugin._promise) {
            return loadDynamicCaptionPlugin._promise;
        }

        if (!document.querySelector('link[data-pswp-dyncap]')) {
            var css = document.createElement('link');
            css.rel = 'stylesheet';
            css.href = '/wp-content/themes/rytkoset-theme/assets/vendor/photoswipe/photoswipe-dynamic-caption-plugin.css';
            css.dataset.pswpDyncap = '1';
            document.head.appendChild(css);
        }

        loadDynamicCaptionPlugin._promise = import('/wp-content/themes/rytkoset-theme/assets/vendor/photoswipe/photoswipe-dynamic-caption-plugin.esm.js')
            .then(function (mod) {
                return (mod && (mod.default || mod.PhotoSwipeDynamicCaption)) ? (mod.default || mod.PhotoSwipeDynamicCaption) : null;
            })
            .catch(function (err) {
                console.warn('PhotoSwipe dynamic caption plugin failed to load', err);
                return null;
            });

        return loadDynamicCaptionPlugin._promise;
    };

    var initLightbox = function () {
        // Normalize all images so they have anchors + width/height data.
        document.querySelectorAll('.wp-block-gallery img, .wp-block-image img, .js-gallery-grid img').forEach(function (img) {
            var normalize = function () {
                normalizeImageLink(img);
            };

            if (img.complete) {
                normalize();
            } else {
                img.addEventListener('load', normalize, { once: true });
                img.addEventListener('error', normalize, { once: true });
            }
        });

        if (!hasPhotoSwipe) {
            initMasonryGrids();
            return;
        }

        // Remove WordPress core lightbox overlays so PhotoSwipe can take over.
        document.querySelectorAll('.wp-lightbox-overlay').forEach(function (overlay) {
            overlay.remove();
        });
        document.querySelectorAll('[data-wp-interactive="core/image"]').forEach(function (node) {
            node.removeAttribute('data-wp-interactive');
        });

        var lightbox = new PhotoSwipeLightbox({
            gallery: 'body',
            children: 'a.pswp-link, .js-gallery-item, .wp-block-gallery figure > a, .wp-block-gallery .blocks-gallery-item > a, .wp-block-image > a',
            showHideAnimationType: 'zoom',
            loop: false, // disable infinite looping; first/last arrows will be disabled
            pswpModule: PhotoSwipe,
        });

        lightbox.addFilter('itemData', function (itemData) {
            var trigger = itemData.element;
            var img = trigger ? trigger.querySelector('img') : null;
            var figure = trigger ? trigger.closest('figure') : null;
            var figcaption = figure ? figure.querySelector('figcaption') : null;

            if (!trigger) {
                return itemData;
            }

            var videoSrc = trigger.getAttribute('data-video-src');

            if (videoSrc) {
                itemData.videoSrc = videoSrc;
                itemData.type = 'video';
                itemData.poster = trigger.getAttribute('data-poster') || '';
                itemData.width = parseInt(trigger.getAttribute('data-pswp-width'), 10) || 1280;
                itemData.height = parseInt(trigger.getAttribute('data-pswp-height'), 10) || 720;
            }

            // Fallbacks for core/gallery (Gutenberg) items.
            if (!itemData.src && trigger.getAttribute('href')) {
                itemData.src = trigger.getAttribute('href');
            }

            if ((!itemData.width || !itemData.height) && img) {
                itemData.width = parseInt(img.getAttribute('width'), 10) || img.naturalWidth || 1280;
                itemData.height = parseInt(img.getAttribute('height'), 10) || img.naturalHeight || 720;
            }

            if (!itemData.alt && img && img.getAttribute('alt')) {
                itemData.alt = img.getAttribute('alt');
            }

            if (!itemData.caption) {
                var captionText = '';
                if (figcaption && figcaption.textContent) {
                    captionText = figcaption.textContent.trim();
                } else if (itemData.alt) {
                    captionText = itemData.alt;
                }
                if (captionText) {
                    itemData.caption = captionText;
                }
            }

            if (!itemData.srcset && img && img.srcset) {
                itemData.srcset = img.srcset;
            }

            if (!itemData.msrc && img && img.currentSrc) {
                itemData.msrc = img.currentSrc;
            }

            return itemData;
        });

        lightbox.on('contentLoad', function (e) {
            var slide = e && e.slide ? e.slide : null;
            if (!slide || !slide.data) {
                return;
            }

            if (slide.data.type !== 'video' || !slide.data.videoSrc) {
                return;
            }

            e.preventDefault();

            var wrapper = document.createElement('div');
            wrapper.className = 'pswp__video';

            var iframe = document.createElement('iframe');
            iframe.src = slide.data.videoSrc;
            iframe.loading = 'lazy';
            iframe.allowFullscreen = true;
            iframe.referrerPolicy = 'strict-origin-when-cross-origin';
            iframe.allow = 'autoplay; encrypted-media; picture-in-picture';

            wrapper.appendChild(iframe);

            e.content.element = wrapper;
            e.content.state = 'loaded';
        });

        loadDynamicCaptionPlugin().then(function (DynamicCaption) {
            if (DynamicCaption) {
                new DynamicCaption(lightbox, {
                    type: 'auto',
                    // Treat landscape mobile like desktop so captions can sit aside.
                    mobileLayoutBreakpoint: function () {
                        return window.innerWidth < 640 && window.innerWidth <= window.innerHeight;
                    },
                    captionContent: function (slide) {
                        if (slide && slide.data && slide.data.caption) {
                            return slide.data.caption;
                        }
                        var el = slide && slide.data && slide.data.element;
                        var fig = el ? el.closest('figure') : null;
                        var figcap = fig ? fig.querySelector('figcaption') : null;
                        if (figcap && figcap.textContent) {
                            return figcap.textContent.trim();
                        }
                        var img = el ? el.querySelector('img') : null;
                        return img && img.getAttribute('alt') ? img.getAttribute('alt') : '';
                    },
                });
            }

            var toggleZoomHide = function () {
                var pswp = lightbox.pswp;
                var slide = pswp && pswp.currSlide;
                var captionEl = slide && slide.dynamicCaption && slide.dynamicCaption.element;
                if (!captionEl) {
                    return;
                }
                var shouldHide = pswp.viewportSize && pswp.viewportSize.x >= 900 && pswp.currZoomLevel > 1.02;
                captionEl.classList.toggle('pswp__dynamic-caption--hidden', !!shouldHide);
            };

            lightbox.on('zoomPanUpdate', toggleZoomHide);
            lightbox.on('change', toggleZoomHide);

            lightbox.init();
        });
        initMasonryGrids();
    };

    // Ajetaan kaikki vasta kun koko sivu (CSS + kuvat) on ladattu,
    // jotta gallerian leveys ja display-tyyli ovat varmasti oikeat.
    var start = function () {
        initLightbox();
        initMasonryGrids();

        // Varmuuden vuoksi muutama ylimääräinen relayout,
        // jos fontit / lazyload-kuvat muuttavat mittoja hieman myöhemmin.
        setTimeout(relayoutAllGrids, 0);
        setTimeout(relayoutAllGrids, 200);
        setTimeout(relayoutAllGrids, 600);
    };

    if (document.readyState === 'complete') {
        // Sivun "hard refresh" voi olla jo complete tässä kohtaa
        start();
    } else {
        window.addEventListener('load', start, { once: true });
    }
})();
