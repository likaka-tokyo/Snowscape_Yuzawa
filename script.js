document.addEventListener('DOMContentLoaded', () => {

    const menuIcon = document.querySelector('.menu-icon');
    const fullMenu = document.querySelector('.full-screen-menu');
    const menuLinks = document.querySelectorAll('.menu-list a');
    const overlay = document.querySelector('.overlay');

    // --- 1. 漢堡選單開關邏輯 ---
    if (menuIcon && fullMenu) {
        menuIcon.addEventListener('click', () => {
            menuIcon.classList.toggle('active');
            fullMenu.classList.toggle('is-open');

            // 開啟選單時鎖定背景捲動
            if (fullMenu.classList.contains('is-open')) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = 'auto';
            }
        });
    }

    // --- 2. 平滑捲動連結邏輯 ---
    menuLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            const targetId = link.getAttribute('href');

            // 檢查是否為內部錨點連結
            if (targetId.startsWith('#')) {
                e.preventDefault();
                const targetElement = document.querySelector(targetId);

                if (targetElement) {
                    // 先關閉選單並恢復捲動
                    menuIcon.classList.remove('active');
                    fullMenu.classList.remove('is-open');
                    document.body.style.overflow = 'auto';

                    // 執行平滑捲動
                    const targetOffset = targetElement.offsetTop;
                    window.scrollTo({
                        top: targetOffset,
                        behavior: 'smooth'
                    });
                }
            }
        });
    });

    // --- 3. Hero Section 視差效果與淡出 ---
    window.addEventListener('scroll', () => {
        let value = window.scrollY;
        // 限制在 Hero Section 範圍內執行
        if (overlay && value < window.innerHeight) {
            overlay.style.transform = `translateY(${value * 0.5}px)`;
            overlay.style.opacity = 1 - value / 700;
        }
    });

    // --- 4. ABOUT 區塊觀察器 (觸發百葉簾動畫) ---
    const revealObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-active');
                // 觸發後即停止觀察以節省效能
                revealObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15 });

    const aboutTarget = document.querySelector('.about-section');
    if (aboutTarget) {
        revealObserver.observe(aboutTarget);
    }

    // --- 5. 通用捲動淡入觀察器 (js-fade-in) ---
    const fadeObserver = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
            }
        });
    }, { threshold: 0.2 });

    document.querySelectorAll('.js-fade-in').forEach(el => {
        fadeObserver.observe(el);
    });

    // --- 6. Transition spacer 文字淡化 ---
    const spacerText = document.querySelector('.spacer-text');
    const toquizMedia = document.querySelector('.toquiz-media');

    if (spacerText && toquizMedia) {
        const updateSpacerOpacity = () => {
            const rect = toquizMedia.getBoundingClientRect();
            const start = window.innerHeight * 0.45;
            const end = window.innerHeight * 0.15;
            let opacity = 1;

            if (rect.top <= start) {
                const progress = Math.min(1, Math.max(0, (start - rect.top) / (start - end)));
                opacity = Math.max(0.4, 1 - progress * 0.6);
            }

            spacerText.style.opacity = opacity.toFixed(2);
        };

        updateSpacerOpacity();
        window.addEventListener('scroll', updateSpacerOpacity, { passive: true });
        window.addEventListener('resize', updateSpacerOpacity);
    }
});

// 雪場區塊觀察器
const resortObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('is-visible');
        } else {
            // 如果希望捲動回去時動畫重播，可以保留這行；不重播則移除
            entry.target.classList.remove('is-visible');
        }
    });
}, { threshold: 0.3 }); // 看到 30% 就開始升起文字

document.querySelectorAll('.resort-item').forEach(item => {
    resortObserver.observe(item);
});

