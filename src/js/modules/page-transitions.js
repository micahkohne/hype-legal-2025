// Page Transitions Script
// Intercepts internal link clicks, animates fade out/in, fetches new content, and updates history
// Only links inside <header> use AJAX transitions into <main>. All other internal links fade out the whole body and then navigate.

(function() {
  const mainSelector = 'main';
  const navSelector = 'header'; // Main nav is <header>
  const fadeDuration = 200; // ms

  function isInternalLink(link) {
    return location.hostname === link.hostname && link.getAttribute('href') && !link.getAttribute('href').startsWith('#') && !link.hasAttribute('target');
  }

  function fadeOut(element) {
    return new Promise(resolve => {
      element.style.transition = `opacity ${fadeDuration}ms`;
      element.style.opacity = 0;
      setTimeout(resolve, fadeDuration);
    });
  }

  function fadeIn(element) {
    return new Promise(resolve => {
      element.style.transition = `opacity ${fadeDuration}ms`;
      element.style.opacity = 1;
      setTimeout(resolve, fadeDuration);
    });
  }

  async function loadPage(url, addToHistory = true) {
    const main = document.querySelector(mainSelector);
    if (!main) return;
    await fadeOut(main);
    try {
      const response = await fetch(url, { headers: { 'X-Requested-With': 'fetch' } });
      if (!response.ok) throw new Error('Page load failed');
      const html = await response.text();
      const temp = document.createElement('div');
      temp.innerHTML = html;
      const newMain = temp.querySelector(mainSelector);
      if (newMain) {
        main.innerHTML = newMain.innerHTML;
        // Force images to reload
        main.querySelectorAll('img').forEach(img => {
          const src = img.src;
          img.src = '';
          img.src = src;
        });
        if (addToHistory) {
          history.pushState(null, '', url);
        }
        document.title = temp.querySelector('title')?.innerText || document.title;
        await fadeIn(main);
        window.scrollTo(0, 0);
      } else {
        window.location.href = url; // fallback
      }
    } catch (e) {
      window.location.href = url; // fallback
    }
  }

  function onLinkClick(e) {
    const link = e.target.closest('a');
    if (!link || !isInternalLink(link)) return;

    // Check if link is inside the main nav (<header>)
    const nav = document.querySelector(navSelector);
    if (nav && nav.contains(link)) {
      e.preventDefault();
      loadPage(link.href);
    } else {
      // For all other internal links, fade out the whole body, then navigate
      e.preventDefault();
      const body = document.body;
      fadeOut(body).then(() => {
        window.location.href = link.href;
      });
    }
  }

  function onPopState() {
    loadPage(location.href, false);
  }

  function init() {
    const main = document.querySelector(mainSelector);
    if (!main) return;
    main.style.opacity = 1;
    document.body.addEventListener('click', onLinkClick);
    window.addEventListener('popstate', onPopState);
  }

  document.addEventListener('DOMContentLoaded', init);
})();