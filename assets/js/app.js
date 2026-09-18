document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('input[name="customer_phone"]').forEach((input) => {
        input.addEventListener('input', (event) => {
            let value = event.target.value.replace(/\D/g, '').slice(0, 11);
            if (value.length > 10) {
                value = value.replace(/^(\d{2})(\d{5})(\d{4}).*/, '($1) $2-$3');
            } else if (value.length > 6) {
                value = value.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3');
            } else if (value.length > 2) {
                value = value.replace(/^(\d{2})(\d{0,5}).*/, '($1) $2');
            } else if (value.length) {
                value = value.replace(/^(\d{0,2})/, '($1');
            }
            event.target.value = value;
        });
    });
});


document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-product-carousel]').forEach((carousel) => {
        const viewport = carousel.querySelector('.home-products-viewport');
        const track = carousel.querySelector('.home-products-track');
        const prevButton = document.querySelector('[data-carousel-prev]');
        const nextButton = document.querySelector('[data-carousel-next]');

        if (!viewport || !track) return;

        const getStep = () => {
            const firstSlide = track.querySelector('.home-product-slide');
            if (!firstSlide) return viewport.clientWidth;

            const styles = window.getComputedStyle(track);
            const gap = parseFloat(styles.columnGap || styles.gap || '0') || 0;
            return firstSlide.getBoundingClientRect().width + gap;
        };

        const scrollBySlides = (direction) => {
            const visible = window.innerWidth >= 1200 ? 4 : window.innerWidth >= 992 ? 3 : window.innerWidth >= 768 ? 2 : 1;
            viewport.scrollBy({
                left: direction * getStep() * visible,
                behavior: 'smooth'
            });
        };

        prevButton?.addEventListener('click', () => scrollBySlides(-1));
        nextButton?.addEventListener('click', () => scrollBySlides(1));

        let autoplayTimer = null;

        const stopAutoplay = () => {
            if (autoplayTimer) {
                window.clearInterval(autoplayTimer);
                autoplayTimer = null;
            }
        };

        const startAutoplay = () => {
            stopAutoplay();

            if (track.scrollWidth <= viewport.clientWidth + 5) return;

            autoplayTimer = window.setInterval(() => {
                const maxScroll = viewport.scrollWidth - viewport.clientWidth;

                if (viewport.scrollLeft >= maxScroll - 8) {
                    viewport.scrollTo({ left: 0, behavior: 'smooth' });
                } else {
                    scrollBySlides(1);
                }
            }, 5200);
        };

        carousel.addEventListener('mouseenter', stopAutoplay);
        carousel.addEventListener('mouseleave', startAutoplay);
        carousel.addEventListener('focusin', stopAutoplay);
        carousel.addEventListener('focusout', startAutoplay);
        window.addEventListener('resize', startAutoplay);

        startAutoplay();
    });
});
