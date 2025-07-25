// Fade-in on scroll for images with class 'fade-in'
function fadeInOnScroll() {
  const images = document.querySelectorAll('img.fade-in');
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries, obs) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          const img = entry.target;
          function show() {
            img.classList.add('visible');
            img.removeEventListener('load', show);
          }
          img.addEventListener('load', show);
          // If already loaded, call immediately
          if (img.complete && img.naturalWidth !== 0) {
            show();
          }
          // Fallback: force visible after 1s if not loaded
          setTimeout(() => {
            if (!img.classList.contains('visible')) {
              img.classList.add('visible');
            }
          }, 1000);
          obs.unobserve(img);
        }
      });
    }, { threshold: 0.1 });
    images.forEach(img => observer.observe(img));
  } else {
    images.forEach(img => img.classList.add('visible'));
  }
}

document.addEventListener('DOMContentLoaded', fadeInOnScroll);
document.addEventListener('mainContentReplaced', fadeInOnScroll);