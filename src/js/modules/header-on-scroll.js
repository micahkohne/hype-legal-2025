/* Init header on scroll */
const header = document.querySelector('.header');
const headerHeaight = header.offsetHeight;
const headerToggleContainer = document.querySelector('.header-toggle');
const headerToggle = document.querySelector('.js-menu-toggle');

let isFixed = false;
let lastKnownScrollY = 0;
let ticking = false;

const isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;

/**
 * Show header.
 *
 * @return  {Void}
 */
function showHeader() {
	if (isFixed) {
		header.classList.add('is-visible');
	}
}

/**
 * Hide header.
 *
 * @return  {Void}
 */
function hideHeader() {
	if (isFixed) {
		header.classList.remove('is-visible');
	}
}

/**
 * Set fixed header.
 *
 * @return  {Void}
 */
function setFixedHeader() {
	header.classList.add('no-transition');
	header.classList.add('is-fixed');
	header.classList.remove('is-visible');
	headerToggleContainer.classList.add('is-visible');
	isFixed = true;

	requestAnimationFrame(() => {
		header.classList.remove('no-transition');
	});
}

/**
 * Reset header.
 *
 * @return  {Void}
 */
function resetHeader() {
	header.classList.add('no-transition');
	header.classList.remove('is-fixed', 'is-visible');
	headerToggleContainer.classList.remove('is-visible');
	isFixed = false;

	requestAnimationFrame(() => {
		header.classList.remove('no-transition');
	});
}

/**
 * On scroll.
 *
 * @return  {Void}
 */
function onScroll() {
	lastKnownScrollY = window.scrollY;

	if (!ticking) {
		window.requestAnimationFrame(() => {
			if (lastKnownScrollY > headerHeaight && !isFixed) {
				setFixedHeader();
			} else if (lastKnownScrollY <= headerHeaight && isFixed) {
				resetHeader();
			}
			ticking = false;
		});

		ticking = true;
	}

	if (window.scrollY > 0) {
		header.classList.add('is-scrolled');
	} else {
		header.classList.remove('is-scrolled');
	}
}

/**
 * Enable header events.
 *
 * @param   {NodeElement}  el
 * @return  {Void}
 */
function enableHeaderEvents(el) {
	el.addEventListener('mouseenter', showHeader);
	el.addEventListener('mouseleave', hideHeader);

	if (isTouchDevice) {
		el.addEventListener('touchstart', () => {
			showHeader();
		});
	}
}

window.addEventListener('scroll', onScroll);
[headerToggle, header].forEach(enableHeaderEvents);
