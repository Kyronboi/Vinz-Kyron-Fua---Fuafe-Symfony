document.addEventListener('DOMContentLoaded', function() {
    // Get all nav links
    const navLinks = document.querySelectorAll('.nav-links a');

    // 1. HOVER EFFECT: Turn orange on mouseenter, back to normal on mouseleave
    navLinks.forEach(link => {
        link.addEventListener('mouseenter', function() {
            this.classList.add('hover-orange');
        });
        link.addEventListener('mouseleave', function() {
            this.classList.remove('hover-orange');
        });
    });

    // 2. CLICK ACTIONS: Scroll or open blank page based on data-target
    navLinks.forEach(link => {
        link.addEventListener('click', function(event) {
            const target = this.getAttribute('data-target');
            const targetElement = target ? document.getElementById(target) : null;

            // Keep the home-page section links working normally from other pages.
            if (!targetElement) return;

            event.preventDefault();
            targetElement.scrollIntoView({ behavior: 'smooth' });
        });
    });
});

document.addEventListener('DOMContentLoaded', function() {
    const track = document.querySelector('.carousel-track');
    const prevBtn = document.getElementById('prevBtn');
    const nextBtn = document.getElementById('nextBtn');
    const cards = document.querySelectorAll('.menu-card');

    if (!track || !prevBtn || !nextBtn) return;

    let currentIndex = 0;
    const totalCards = cards.length; // 4
    const visibleCards = 3; // Only 3 visible at a time

    // Calculate max index (how far we can slide)
    const maxIndex = totalCards - visibleCards; // 1

    function updateCarousel() {
        // Get width of one card and gap
        const cardWidth = cards[0].offsetWidth;
        const gap = 20; // Must match the gap in CSS
        const moveAmount = cardWidth + gap;

        // Apply the slide transformation
        track.style.transform = `translateX(-${currentIndex * moveAmount}px)`;
    }

    // Next Button (Click -> 4th appears, 1st disappears)
    nextBtn.addEventListener('click', () => {
        if (currentIndex >= maxIndex) {
            currentIndex = 0; // Loop back to start
        } else {
            currentIndex++;
        }
        updateCarousel();
    });

    // Previous Button (Click -> 1st appears, 4th disappears)
    prevBtn.addEventListener('click', () => {
        if (currentIndex <= 0) {
            currentIndex = maxIndex; // Loop to end
        } else {
            currentIndex--;
        }
        updateCarousel();
    });

    // Adjust on window resize
    window.addEventListener('resize', updateCarousel);

    // Initial position
    updateCarousel();
});

document.addEventListener('DOMContentLoaded', function () {
    const wrapper = document.querySelector('.testimonials-wrapper');
    const track = wrapper?.querySelector('.testimonial-track');
    const cards = wrapper?.querySelectorAll('.testimonial-card');
    const prevButton = document.getElementById('prevComment');
    const nextButton = document.getElementById('nextComment');

    if (!wrapper || !track || !cards.length || !prevButton || !nextButton) return;

    let currentIndex = 0;

    function updateCarousel() {
        const visibleCards = window.innerWidth <= 768 ? 1 : 3;
        const maxIndex = Math.max(0, cards.length - visibleCards);
        const cardWidth = cards[0].getBoundingClientRect().width;
        const gap = 20;

        currentIndex = Math.max(0, Math.min(currentIndex, maxIndex));
        track.style.transform =
            `translateX(-${currentIndex * (cardWidth + gap)}px)`;
    }

    nextButton.addEventListener('click', function () {
        currentIndex++;
        updateCarousel();
    });

    prevButton.addEventListener('click', function () {
        currentIndex--;
        updateCarousel();
    });

    window.addEventListener('resize', updateCarousel);
    updateCarousel();
});
