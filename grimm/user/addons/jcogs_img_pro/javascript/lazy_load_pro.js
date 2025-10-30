/**
 * JCOGS Image Pro - Lazy Load Javascript
 * Pro-specific version to avoid conflicts with Legacy addon
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 */
function jcogs_pro_lazyload() {
    let lazyloadThrottleTimeout;
    const lazyloadImages = document.querySelectorAll("[data-ji-pro-src]");

    if (lazyloadThrottleTimeout) {
        clearTimeout(lazyloadThrottleTimeout);
    }

    lazyloadThrottleTimeout = setTimeout(() => {
        const scrollTop = window.scrollY;
        const imagesLength = lazyloadImages.length;

        lazyloadImages.forEach(img => {
            if (img.offsetTop < (window.innerHeight + scrollTop)) {
                img.src = img.dataset.jiProSrc;
                img.removeAttribute('data-ji-pro-src');
                if (img.dataset.jiProSrcset !== undefined) {
                    img.srcset = img.dataset.jiProSrcset;
                    img.removeAttribute('data-ji-pro-srcset');
                }
            }
        });

        if (imagesLength === 0) {
            removeProLazyLoadListeners();
        }
    }, 20);
}

function removeProLazyLoadListeners() {
    document.removeEventListener("scroll", jcogs_pro_lazyload);
    window.removeEventListener("resize", jcogs_pro_lazyload);
    window.removeEventListener("orientationChange", jcogs_pro_lazyload);
}

document.addEventListener("DOMContentLoaded", () => {
    // Mark as Pro lazy loaded to avoid duplicate injection
    document.body.classList.add("jcogs_img_pro_lazy_loaded");
    
    // Remove noscript images (Pro version - if JavaScript is running, noscript is not needed)
    document.querySelectorAll("noscript.ji__progenhlazyns").forEach(e => e.parentNode.removeChild(e));

    const lazyloadImages = document.querySelectorAll("[data-ji-pro-src]");

    if ("IntersectionObserver" in window) {
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const image = entry.target;
                    image.src = image.dataset.jiProSrc;
                    image.removeAttribute('data-ji-pro-src');
                    if (image.dataset.jiProSrcset !== undefined) {
                        image.srcset = image.dataset.jiProSrcset;
                        image.removeAttribute('data-ji-pro-srcset');
                    }
                    imageObserver.unobserve(image);
                }
            });
        });

        lazyloadImages.forEach(image => imageObserver.observe(image));
    } else {
        document.addEventListener("scroll", jcogs_pro_lazyload);
        window.addEventListener("resize", jcogs_pro_lazyload);
        window.addEventListener("orientationChange", jcogs_pro_lazyload);
    }
});
