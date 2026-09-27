(() => {
    'use strict';
    document.querySelectorAll('[data-rnl-carousel]').forEach((section) => {
        const track = section.querySelector('[data-rnl-carousel-track]');
        const controls = section.querySelector('[data-rnl-carousel-controls]');
        if (!track || !controls || section.dataset.rnlCarouselReady) return;
        section.dataset.rnlCarouselReady = '1';
        const previous = controls.querySelector('[data-rnl-carousel-previous]');
        const next = controls.querySelector('[data-rnl-carousel-next]');
        const play = controls.querySelector('[data-rnl-carousel-play]');
        const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
        let paused = motion.matches, hovered = false, timer;
        const move = (direction) => track.scrollBy({left: direction * track.clientWidth,
            behavior: motion.matches ? 'auto' : 'smooth'});
        const schedule = () => {
            clearTimeout(timer);
            play.textContent = paused ? play.dataset.playLabel : play.dataset.pauseLabel;
            if (paused || hovered || document.hidden || section.contains(document.activeElement) || track.scrollWidth <= track.clientWidth + 2) return;
            timer = setTimeout(() => {
                if (track.scrollLeft + track.clientWidth >= track.scrollWidth - 2) track.scrollTo({left: 0, behavior: motion.matches ? 'auto' : 'smooth'});
                else move(1);
                schedule();
            }, 7000);
        };
        const update = () => {
            controls.hidden = track.scrollWidth <= track.clientWidth + 2;
            previous.disabled = track.scrollLeft <= 2;
            next.disabled = track.scrollLeft + track.clientWidth >= track.scrollWidth - 2;
        };
        previous.addEventListener('click', () => { move(-1); schedule(); });
        next.addEventListener('click', () => { move(1); schedule(); });
        play.addEventListener('click', () => { paused = !paused; schedule(); });
        track.addEventListener('keydown', (event) => {
            if (event.target !== track || !['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
            event.preventDefault(); move(event.key === 'ArrowRight' ? 1 : -1);
        });
        section.addEventListener('mouseenter', () => { hovered = true; schedule(); });
        section.addEventListener('mouseleave', () => { hovered = false; schedule(); });
        section.addEventListener('focusin', schedule);
        section.addEventListener('focusout', () => setTimeout(schedule, 0));
        track.addEventListener('touchstart', () => { paused = true; schedule(); }, {passive: true});
        track.addEventListener('scroll', update, {passive: true});
        document.addEventListener('visibilitychange', schedule);
        motion.addEventListener('change', () => { if (motion.matches) paused = true; schedule(); });
        const resize = () => { update(); schedule(); };
        window.addEventListener('resize', resize);
        if (window.ResizeObserver) new ResizeObserver(resize).observe(track);
        resize();
    });
})();
