(function () {
    var hasPhotoSwipe = typeof PhotoSwipeLightbox !== 'undefined' && typeof PhotoSwipe !== 'undefined';
    var photoSwipeConfig = window.rytkosetPhotoSwipe || {};
    var gallerySelector = '.js-gallery-grid, .album .wp-block-gallery, .album figure.pswp-standalone-gallery';
    var galleryImageSelector = '.js-gallery-grid img, .album .wp-block-gallery img, .album figure.wp-block-image img';
    var galleryChildrenSelector = 'a.pswp-link, .js-gallery-item, figure > a, .blocks-gallery-item > a';
    var galleryHashParam = 'kuva';
    var urlSyncState = {
        previousHash: ''
    };

    var getGalleryHashItemId = function (hash) {
        var rawHash = typeof hash === 'string' ? hash : window.location.hash;
        if (!rawHash) {
            return '';
        }

        var params = new URLSearchParams(rawHash.replace(/^#/, ''));
        var itemId = params.get(galleryHashParam);

        return itemId ? itemId.trim() : '';
    };

    var isPhotoSwipeHash = function (hash) {
        return '' !== getGalleryHashItemId(hash);
    };

    var replaceUrlHash = function (hash) {
        if (!window.history || typeof window.history.replaceState !== 'function') {
            return;
        }

        var nextUrl = window.location.pathname + window.location.search + (hash || '');
        window.history.replaceState(window.history.state, '', nextUrl);
    };

    var updateUrlForItemId = function (itemId) {
        if (!itemId) {
            return;
        }

        var nextHash = '#' + galleryHashParam + '=' + encodeURIComponent(itemId);
        if (window.location.hash === nextHash) {
            return;
        }

        replaceUrlHash(nextHash);
    };

    var getShareUrlForItemId = function (itemId) {
        if (!itemId) {
            return window.location.href;
        }

        var origin = window.location.origin || (window.location.protocol + '//' + window.location.host);

        return origin + window.location.pathname + window.location.search + '#' + galleryHashParam + '=' + encodeURIComponent(itemId);
    };

    var restorePreviousUrlHash = function () {
        if (window.location.hash === urlSyncState.previousHash) {
            return;
        }

        replaceUrlHash(urlSyncState.previousHash || '');
    };

    var getItemIdFromNode = function (node) {
        if (!node) {
            return '';
        }

        if (node.getAttribute) {
            var directId = node.getAttribute('data-pswp-item-id');
            if (directId) {
                return directId.trim();
            }

            var legacyId = node.getAttribute('data-id');
            if (legacyId) {
                return legacyId.trim();
            }
        }

        var figure = node.closest ? node.closest('figure') : null;
        if (figure && figure !== node) {
            var figureId = getItemIdFromNode(figure);
            if (figureId) {
                return figureId;
            }
        }

        var img = node.tagName === 'IMG' ? node : (node.querySelector ? node.querySelector('img') : null);
        if (img) {
            var imageId = img.getAttribute('data-id');
            if (imageId) {
                return imageId.trim();
            }

            var className = img.getAttribute('class') || '';
            var classMatch = className.match(/\bwp-image-(\d+)\b/);
            if (classMatch && classMatch[1]) {
                return classMatch[1];
            }
        }

        return '';
    };

    var getAllGalleryTriggers = function () {
        return Array.prototype.slice.call(
            document.querySelectorAll('.js-gallery-grid .js-gallery-item, .album .wp-block-gallery a.pswp-link, .album figure.pswp-standalone-gallery > a.pswp-link')
        );
    };

    var findGalleryTriggerByItemId = function (itemId) {
        if (!itemId) {
            return null;
        }

        return getAllGalleryTriggers().find(function (trigger) {
            return getItemIdFromNode(trigger) === itemId;
        }) || null;
    };

    var fallbackCopyToClipboard = function (text) {
        var textarea = document.createElement('textarea');

        textarea.value = text;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.top = '-9999px';
        textarea.style.left = '-9999px';

        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();

        try {
            return document.execCommand('copy');
        } catch (error) {
            return false;
        } finally {
            document.body.removeChild(textarea);
        }
    };

    var copyToClipboard = function (text) {
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function' && window.isSecureContext) {
            return navigator.clipboard.writeText(text);
        }

        return new Promise(function (resolve, reject) {
            if (fallbackCopyToClipboard(text)) {
                resolve();
                return;
            }

            reject(new Error('clipboard_unavailable'));
        });
    };

    var showCopyPrompt = function (text) {
        window.prompt(photoSwipeConfig.copyLinkPrompt || 'Kopioi linkki tähän kuvaan:', text);
    };

    var showCopyToast = function (pswp, message) {
        var toastMessage = message || photoSwipeConfig.copyLinkToast || photoSwipeConfig.copyLinkSuccess || 'Linkki kopioitu';
        var root = pswp && pswp.element;
        var toastEl;

        if (!root) {
            return;
        }

        toastEl = root.querySelector('.pswp__copy-toast');

        if (!toastEl) {
            toastEl = document.createElement('div');
            toastEl.className = 'pswp__copy-toast';
            toastEl.setAttribute('role', 'status');
            toastEl.setAttribute('aria-live', 'polite');
            root.appendChild(toastEl);
        }

        toastEl.textContent = toastMessage;
        toastEl.classList.add('is-visible');

        if (toastEl._hideTimeout) {
            window.clearTimeout(toastEl._hideTimeout);
        }

        toastEl._hideTimeout = window.setTimeout(function () {
            toastEl.classList.remove('is-visible');
        }, 1800);
    };

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

        if (!link.dataset.pswpItemId) {
            var itemId = getItemIdFromNode(link) || getItemIdFromNode(figure) || getItemIdFromNode(img);
            if (itemId) {
                link.dataset.pswpItemId = itemId;
            }
        }

        link.classList.add('pswp-link');
        return link;
    };

    var loadDynamicCaptionPlugin = function () {
        if (window.PhotoSwipeDynamicCaption) {
            return Promise.resolve(window.PhotoSwipeDynamicCaption);
        }

        if (loadDynamicCaptionPlugin._promise) {
            return loadDynamicCaptionPlugin._promise;
        }

        if (photoSwipeConfig.dynamicCaptionCssUrl && !document.querySelector('link[data-pswp-dyncap]')) {
            var css = document.createElement('link');
            css.rel = 'stylesheet';
            css.href = photoSwipeConfig.dynamicCaptionCssUrl;
            css.dataset.pswpDyncap = '1';
            document.head.appendChild(css);
        }

        if (!photoSwipeConfig.dynamicCaptionJsUrl) {
            return Promise.resolve(null);
        }

        loadDynamicCaptionPlugin._promise = import(photoSwipeConfig.dynamicCaptionJsUrl)
            .then(function (mod) {
                return (mod && (mod.default || mod.PhotoSwipeDynamicCaption)) ? (mod.default || mod.PhotoSwipeDynamicCaption) : null;
            })
            .catch(function (err) {
                console.warn('PhotoSwipe dynamic caption plugin failed to load', err);
                return null;
            });

        return loadDynamicCaptionPlugin._promise;
    };

    var getCaptionHTML = function (trigger, itemData) {
        if (!trigger) {
            return '';
        }

        var directCaption = trigger.getAttribute('data-pswp-caption-html');
        if (directCaption) {
            return directCaption.trim();
        }

        var hiddenCaption = trigger.querySelector('.pswp-caption-source');
        if (hiddenCaption && hiddenCaption.innerHTML) {
            return hiddenCaption.innerHTML.trim();
        }

        var figure = trigger.closest('figure');
        var figureCaption = figure ? figure.getAttribute('data-pswp-caption-html') : '';
        if (figureCaption) {
            return figureCaption.trim();
        }

        var figureHiddenCaption = figure ? figure.querySelector('.pswp-caption-source') : null;
        var figcaption = figure ? figure.querySelector('figcaption') : null;
        if (figureHiddenCaption && figureHiddenCaption.innerHTML) {
            return figureHiddenCaption.innerHTML.trim();
        }
        if (figcaption && figcaption.innerHTML) {
            return figcaption.innerHTML.trim();
        }

        if (itemData && itemData.alt) {
            return itemData.alt;
        }

        var img = trigger.querySelector('img');
        return img && img.getAttribute('alt') ? img.getAttribute('alt') : '';
    };

    var initLightbox = function () {
        // Normalize all images so they have anchors + width/height data.
        document.querySelectorAll(galleryImageSelector).forEach(function (img) {
            var normalize = function () {
                var figure = img.closest('figure.wp-block-image');
                if (figure && !figure.closest('.wp-block-gallery')) {
                    figure.classList.add('pswp-standalone-gallery');
                }
                normalizeImageLink(img);
            };

            normalize();

            if (!img.complete) {
                img.addEventListener('load', normalize, { once: true });
                img.addEventListener('error', normalize, { once: true });
            }
        });

        if (!hasPhotoSwipe) {
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
            gallery: gallerySelector,
            children: galleryChildrenSelector,
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
                var captionText = getCaptionHTML(trigger, itemData);
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

            if (!itemData.itemId) {
                itemData.itemId = getItemIdFromNode(trigger) || getItemIdFromNode(img);
            }

            return itemData;
        });

        lightbox.on('uiRegister', function () {
            lightbox.pswp.ui.registerElement({
                name: 'copy-link',
                order: 18,
                isButton: true,
                title: photoSwipeConfig.copyLinkLabel || 'Kopioi linkki tähän kuvaan',
                ariaLabel: photoSwipeConfig.copyLinkLabel || 'Kopioi linkki tähän kuvaan',
                html: '<svg width="32" height="32" viewBox="0 0 32 32" aria-hidden="true" class="pswp__icn"><path d="M13.4 22.7a5 5 0 0 1 0-7.1l3.9-3.9a5 5 0 0 1 7.1 7.1l-1.9 1.9-1.6-1.6 1.9-1.9a2.8 2.8 0 0 0-4-4l-3.9 3.9a2.8 2.8 0 1 0 4 4l1-1 1.6 1.6-1 1a5 5 0 0 1-7.1 0Zm-5.8-2.3a5 5 0 0 1 0-7.1l1-1 1.6 1.6-1 1a2.8 2.8 0 0 0 4 4l3.9-3.9a2.8 2.8 0 1 0-4-4l-1.9 1.9-1.6-1.6 1.9-1.9a5 5 0 0 1 7.1 7.1l-3.9 3.9a5 5 0 0 1-7.1 0Z" fill="currentColor"/></svg>',
                onInit: function (el) {
                    el._copyLinkDefaultHtml = el.innerHTML;
                    el._copyLinkDefaultLabel = photoSwipeConfig.copyLinkLabel || 'Kopioi linkki tähän kuvaan';
                    el._copyLinkSuccessLabel = photoSwipeConfig.copyLinkSuccess || 'Linkki kopioitu';
                },
                onClick: function (event, el, pswp) {
                    var slide = pswp && pswp.currSlide;
                    var itemId = slide && slide.data ? slide.data.itemId : '';
                    var shareUrl = getShareUrlForItemId(itemId);
                    var copiedHtml = '<svg width="32" height="32" viewBox="0 0 32 32" aria-hidden="true" class="pswp__icn"><path d="m13.7 21.7-5.4-5.3 1.6-1.6 3.8 3.8 8.4-8.4 1.6 1.6-10 9.9Z" fill="currentColor"/></svg>';

                    if (!shareUrl) {
                        return;
                    }

                    copyToClipboard(shareUrl).then(function () {
                        if (el._copyLinkResetTimeout) {
                            window.clearTimeout(el._copyLinkResetTimeout);
                        }

                        el.classList.add('is-copied');
                        el.innerHTML = copiedHtml;
                        el.setAttribute('title', el._copyLinkSuccessLabel);
                        el.setAttribute('aria-label', el._copyLinkSuccessLabel);

                        el._copyLinkResetTimeout = window.setTimeout(function () {
                            el.classList.remove('is-copied');
                            el.innerHTML = el._copyLinkDefaultHtml;
                            el.setAttribute('title', el._copyLinkDefaultLabel);
                            el.setAttribute('aria-label', el._copyLinkDefaultLabel);
                        }, 1600);

                        showCopyToast(pswp, photoSwipeConfig.copyLinkToast);
                    }).catch(function () {
                        showCopyPrompt(shareUrl);
                    });
                }
            });
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
                        return getCaptionHTML(el, slide && slide.data ? slide.data : null);
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

            var syncSlideUrl = function () {
                var pswp = lightbox.pswp;
                var slide = pswp && pswp.currSlide;
                var itemId = slide && slide.data ? slide.data.itemId : '';

                if (itemId) {
                    updateUrlForItemId(itemId);
                }
            };

            var openFromHash = function () {
                var itemId = getGalleryHashItemId();
                var trigger;

                if (!itemId) {
                    return;
                }

                trigger = findGalleryTriggerByItemId(itemId);
                if (!trigger) {
                    return;
                }

                trigger.click();
            };

            lightbox.on('beforeOpen', function () {
                urlSyncState.previousHash = isPhotoSwipeHash(window.location.hash) ? '' : window.location.hash;
            });

            lightbox.on('zoomPanUpdate', toggleZoomHide);
            lightbox.on('change', toggleZoomHide);
            lightbox.on('change', syncSlideUrl);
            lightbox.on('destroy', restorePreviousUrlHash);

            lightbox.init();
            openFromHash();
        });
    };

    if (document.readyState === 'complete') {
        initLightbox();
    } else {
        window.addEventListener('load', initLightbox, { once: true });
    }
})();
