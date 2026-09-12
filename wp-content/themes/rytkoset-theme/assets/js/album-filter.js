/**
 * Album section filter (#677): "Kaikki / <osio> / Videot" chips above the
 * album body.
 *
 * Sections are identified purely from the rendered DOM, not from any new
 * server-side data model:
 * - Inside .album__content, a top-level <h2> starts a section that runs
 *   until the next <h2> (or the end of the content). Content before the
 *   first <h2> is the album intro and always stays visible.
 * - The legacy ACF image grid (.album__gallery) and the YouTube video list
 *   (.album__videos) are each their own section, labelled from their own
 *   heading.
 *
 * The filter only reveals itself when there are at least two sections with
 * actual media in them, so a normal single-gallery album is unaffected.
 *
 * Must run before assets/js/photoswipe-init.js so a #kuva= deep link can be
 * checked against the resolved section before PhotoSwipe tries to open it;
 * functions.php enforces this via script dependencies.
 */
(function () {
    'use strict';

    var ALL_SLUG = 'kaikki';
    var QUERY_PARAM = 'nayta';
    var HASH_PARAM = 'kuva';
    var MEDIA_SELECTOR = 'img, iframe, video, embed, object';

    var config = window.rytkosetAlbumFilter || {};

    var getHashItemId = function () {
        var hash = window.location.hash || '';
        if (!hash) {
            return '';
        }

        var params = new URLSearchParams(hash.replace(/^#/, ''));
        var itemId = params.get(HASH_PARAM);

        return itemId ? itemId.trim() : '';
    };

    var slugify = function (text, fallback) {
        var base = (text || '').toString().trim().toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');

        return base || fallback;
    };

    var countMedia = function (elements, selector) {
        var seen = [];

        elements.forEach(function (el) {
            var matches = [];

            if (el.matches && el.matches(selector || MEDIA_SELECTOR)) {
                matches.push(el);
            }

            matches = matches.concat(Array.prototype.slice.call(el.querySelectorAll(selector || MEDIA_SELECTOR)));

            matches.forEach(function (match) {
                if (seen.indexOf(match) === -1) {
                    seen.push(match);
                }
            });
        });

        return seen.length;
    };

    /**
     * Splits .album__content's direct children into sections at each <h2>.
     *
     * Content before the first <h2> is intentionally left untagged (always
     * visible); it is not returned as a section.
     */
    var collectContentSections = function (contentRoot) {
        var sections = [];
        var current = null;

        if (!contentRoot) {
            return sections;
        }

        Array.prototype.slice.call(contentRoot.children).forEach(function (child) {
            if (child.tagName === 'H2') {
                current = { label: child.textContent.trim(), elements: [child] };
                sections.push(current);
                return;
            }

            if (current) {
                current.elements.push(child);
            }
        });

        return sections;
    };

    /**
     * Wraps a whole pre-built section container (.album__gallery / .album__videos)
     * as one section, labelled from its own heading.
     */
    var collectWrapperSection = function (root, fallbackLabel, isVideo) {
        if (!root) {
            return null;
        }

        var heading = root.querySelector(':scope > .album__section-title');

        return {
            label: heading ? heading.textContent.trim() : fallbackLabel,
            elements: [root],
            reservedSlug: isVideo ? 'videot' : 'kuvat',
        };
    };

    var buildSections = function (body) {
        var contentRoot = body.querySelector(':scope > .album__content');
        var legacyGalleryRoot = body.querySelector(':scope > .album__gallery');
        var videosRoot = body.querySelector(':scope > .album__videos');

        var sections = collectContentSections(contentRoot);

        var gallerySection = collectWrapperSection(legacyGalleryRoot, config.imagesFallbackLabel || 'Kuvat', false);
        if (gallerySection) {
            sections.push(gallerySection);
        }

        var videoSection = collectWrapperSection(videosRoot, config.videosFallbackLabel || 'Videot', true);
        if (videoSection) {
            sections.push(videoSection);
        }

        // Drop sections without any actual media (e.g. a text-only heading
        // group); they stay visible at all times, same as the intro.
        sections = sections.filter(function (section) {
            section.count = countMedia(section.elements);
            section.imageCount = countMedia(section.elements, 'img');
            section.videoCount = section.count - section.imageCount;
            return section.count > 0;
        });

        if (sections.length < 2) {
            return [];
        }

        var usedSlugs = Object.create(null);
        [ALL_SLUG, 'videot', 'kuvat'].forEach(function (slug) { usedSlugs[slug] = true; });

        sections.forEach(function (section, index) {
            var base = slugify(section.label, 'osio-' + (index + 1));
            var slug = base;
            var suffix = 2;

            while (!section.reservedSlug && usedSlugs[slug]) {
                slug = base + '-' + suffix;
                suffix += 1;
            }

            slug = section.reservedSlug || slug;
            usedSlugs[slug] = true;
            section.slug = slug;

            section.elements.forEach(function (el) {
                el.setAttribute('data-album-section', slug);
            });
        });

        return sections;
    };

    var resetIframes = function (elements) {
        elements.forEach(function (el) {
            var frames = Array.prototype.slice.call(el.querySelectorAll('iframe'));
            if (el.matches('iframe')) { frames.push(el); }
            frames.forEach(function (iframe) {
                var src = iframe.getAttribute('src');
                if (src) {
                    iframe.setAttribute('src', src);
                }
            });
            var videos = Array.prototype.slice.call(el.querySelectorAll('video'));
            if (el.matches('video')) { videos.push(el); }
            videos.forEach(function (video) { video.pause(); });
        });
    };

    var formatMediaCount = function (count, isVideo) {
        if (isVideo) {
            return count === 1 ? '1 video' : count + ' videota';
        }

        return count === 1 ? '1 kuva' : count + ' kuvaa';
    };

    var updateUrl = function (slug) {
        if (!window.history || typeof window.history.replaceState !== 'function') {
            return;
        }

        var url = new URL(window.location.href);

        if (slug === ALL_SLUG) {
            url.searchParams.delete(QUERY_PARAM);
        } else {
            url.searchParams.set(QUERY_PARAM, slug);
        }

        var next = url.pathname + url.search + url.hash;
        var current = window.location.pathname + window.location.search + window.location.hash;

        if (next !== current) {
            window.history.replaceState(window.history.state, '', next);
        }
    };

    var initAlbumFilter = function () {
        var body = document.querySelector('.album__body');
        var container = body ? body.querySelector(':scope > .album-filter') : null;

        if (!body || !container) {
            return;
        }

        var sections = buildSections(body);

        if (sections.length < 2) {
            return;
        }

        var list = container.querySelector('.album-filter__list');
        var status = container.querySelector('.album-filter__status');
        var buttons = Object.create(null);
        var previousSlug = ALL_SLUG;

        var applySlug = function (slug, announce) {
            var target = sections.some(function (section) {
                return section.slug === slug;
            }) ? slug : ALL_SLUG;

            sections.forEach(function (section) {
                var visible = (target === ALL_SLUG) || (section.slug === target);

                if (!visible && (previousSlug === ALL_SLUG || previousSlug === section.slug)) {
                    resetIframes(section.elements);
                }

                section.elements.forEach(function (el) {
                    el.hidden = !visible;
                });

                if (buttons[section.slug]) {
                    buttons[section.slug].setAttribute('aria-pressed', section.slug === target ? 'true' : 'false');
                }
            });

            if (buttons[ALL_SLUG]) {
                buttons[ALL_SLUG].setAttribute('aria-pressed', target === ALL_SLUG ? 'true' : 'false');
            }

            previousSlug = target;
            updateUrl(target);

            if (announce && status) {
                if (target === ALL_SLUG) {
                    status.textContent = config.statusAllText || 'Näytetään kaikki albumin sisältö.';
                } else {
                    var section = sections.filter(function (s) {
                        return s.slug === target;
                    })[0];

                    if (section) {
                        status.textContent = 'Näytetään: ' + section.label + ', ' + [section.imageCount ? formatMediaCount(section.imageCount, false) : '', section.videoCount ? formatMediaCount(section.videoCount, true) : ''].filter(Boolean).join(' ja ') + '.';
                    }
                }
            }

            return target;
        };

        // Build "Kaikki" button.
        var makeButton = function (slug, label, count) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'album-filter__btn';
            button.setAttribute('aria-pressed', 'false');

            if (config.checkIconHtml) {
                var check = document.createElement('span');
                check.className = 'album-filter__check';
                check.innerHTML = config.checkIconHtml;
                button.appendChild(check);
            }

            var text = document.createTextNode(typeof count === 'number' ? label + ' (' + count + ')' : label);
            button.appendChild(text);

            button.addEventListener('click', function () {
                applySlug(slug, true);
            });

            buttons[slug] = button;
            list.appendChild(button);
        };

        makeButton(ALL_SLUG, config.allLabel || 'Kaikki');

        sections.forEach(function (section) {
            makeButton(section.slug, section.label, section.count);
        });

        // Resolve the initial selection: URL query param first, but a #kuva=
        // deep link into a section other than the requested one always wins
        // so the linked image is actually visible.
        var requestedSlug = new URLSearchParams(window.location.search).get(QUERY_PARAM) || ALL_SLUG;
        applySlug(requestedSlug, false);

        // Compare IDs as strings, never interpolate a URL value into a CSS selector.
        // A duplicate in the selected section (or the intro) keeps that selection.
        var itemId = getHashItemId();
        var matches = Array.prototype.slice.call(body.querySelectorAll('[data-pswp-item-id]')).filter(function (el) {
            return el.getAttribute('data-pswp-item-id') === itemId;
        });
        if (matches.length && !matches.some(function (el) { return !el.closest('[hidden]'); })) {
            applySlug(ALL_SLUG, false);
        }
        container.hidden = false;
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAlbumFilter);
    } else {
        initAlbumFilter();
    }
})();
