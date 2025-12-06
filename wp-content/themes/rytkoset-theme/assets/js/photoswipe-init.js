(function () {
    if (typeof PhotoSwipeLightbox === 'undefined' || typeof PhotoSwipe === 'undefined') {
        return;
    }

    var galleries = document.querySelectorAll('.js-gallery-grid');

    if (!galleries.length) {
        return;
    }

    var lightbox = new PhotoSwipeLightbox({
        gallery: '.js-gallery-grid',
        children: '.js-gallery-item',
        showHideAnimationType: 'zoom',
        pswpModule: PhotoSwipe,
    });

    lightbox.addFilter('itemData', function (itemData) {
        var trigger = itemData.element;

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

        return itemData;
    });

    lightbox.on('contentLoad', function (e) {
        var slide = e.slide;

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
})();
