/**
 * Image Module Lazy Load Javascript
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img
 */
function jcogs_lazyload() {
    let lazyloadThrottleTimeout;
    const lazyloadImages = document.querySelectorAll("[data-ji-src]");

    if (lazyloadThrottleTimeout) {
        clearTimeout(lazyloadThrottleTimeout);
    }

    lazyloadThrottleTimeout = setTimeout(() => {
        const scrollTop = window.scrollY;
        const imagesLength = lazyloadImages.length;

        lazyloadImages.forEach(img => {
            if (img.offsetTop < (window.innerHeight + scrollTop)) {
                img.src = img.dataset.jiSrc;
                img.removeAttribute('data-ji-src');
                if (img.dataset.jiSrcset !== undefined) {
                    img.srcset = img.dataset.jiSrcset;
                    img.removeAttribute('data-ji-srcset');
                }
            }
        });

        if (imagesLength === 0) {
            removeLazyLoadListeners();
        }
    }, 20);
}

function removeLazyLoadListeners() {
    document.removeEventListener("scroll", jcogs_lazyload);
    window.removeEventListener("resize", jcogs_lazyload);
    window.removeEventListener("orientationChange", jcogs_lazyload);
}

document.addEventListener("DOMContentLoaded", () => {
    // remove noscript images
    document.querySelectorAll("noscript.ji__progenhlazyns").forEach(e => e.parentNode.removeChild(e));

    const lazyloadImages = document.querySelectorAll("[data-ji-src]");

    if ("IntersectionObserver" in window) {
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const image = entry.target;
                    image.src = image.dataset.jiSrc;
                    image.removeAttribute('data-ji-src');
                    if (image.dataset.jiSrcset !== undefined) {
                        image.srcset = image.dataset.jiSrcset;
                        image.removeAttribute('data-ji-srcset');
                    }
                    imageObserver.unobserve(image);
                }
            });
        });

        lazyloadImages.forEach(image => imageObserver.observe(image));
    } else {
        document.addEventListener("scroll", jcogs_lazyload);
        window.addEventListener("resize", jcogs_lazyload);
        window.addEventListener("orientationChange", jcogs_lazyload);
    }
});