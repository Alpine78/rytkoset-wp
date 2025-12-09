(function () {
    if (typeof PhotoSwipeLightbox === 'undefined' || typeof PhotoSwipe === 'undefined') {
        return;
    }

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

    var initMasonryGrids = function () {
        var grids = Array.prototype.slice.call(document.querySelectorAll('.js-gallery-grid, .album .wp-block-gallery.has-nested-images'));
        if (!grids.length) {
            return;
        }

        var relayoutAll = function () {
            grids.forEach(relayoutMasonryGrid);
        };

        grids.forEach(function (grid) {
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
            initMasonryGrids._resizeTimer = setTimeout(relayoutAll, 120);
        });

        relayoutAll();
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

        lightbox.init();
        initMasonryGrids();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLightbox);
    } else {
        initLightbox();
    }
})();
