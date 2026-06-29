const hamburger = document.getElementById('hamburger');
const navMenu = document.getElementById('navMenu');
const navLinks = document.querySelectorAll('.nav-link');
const navbar = document.getElementById('navbar');
const contactForm = document.getElementById('contactForm');

hamburger.addEventListener('click', () => {
    const isOpen = navMenu.classList.toggle('active');
    hamburger.classList.toggle('active');
    hamburger.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
});

navLinks.forEach(link => {
    link.addEventListener('click', () => {
        navMenu.classList.remove('active');
        hamburger.classList.remove('active');
        hamburger.setAttribute('aria-expanded', 'false');
    });
});

window.addEventListener('scroll', () => {
    navbar.classList.toggle('scrolled', window.pageYOffset > 80);
});

const revealObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.classList.add('visible');
        }
    });
}, {
    threshold: 0.12,
    rootMargin: '0px 0px -40px 0px'
});

document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));

const contactFormSubmit = document.getElementById('contactFormSubmit');
const contactFormStatus = document.getElementById('contactFormStatus');

function setContactFormStatus(message, type) {
    if (!contactFormStatus) return;
    contactFormStatus.hidden = !message;
    contactFormStatus.textContent = message;
    contactFormStatus.classList.remove('is-success', 'is-error');
    if (type) {
        contactFormStatus.classList.add(type === 'success' ? 'is-success' : 'is-error');
    }
}

contactForm.addEventListener('submit', async (e) => {
    e.preventDefault();

    if (!contactForm.reportValidity()) {
        return;
    }

    setContactFormStatus('', null);
    contactFormSubmit.disabled = true;
    contactFormSubmit.textContent = 'Enviando…';

    try {
        const response = await fetch(contactForm.action, {
            method: 'POST',
            body: new FormData(contactForm),
            headers: { Accept: 'application/json' },
        });
        const data = await response.json();

        if (response.ok && data.success) {
            contactForm.reset();
            setContactFormStatus(
                '¡Gracias por tu mensaje! Nos pondremos en contacto a la brevedad en info@bioenlace.io.',
                'success'
            );
        } else {
            setContactFormStatus(
                data.message || 'No pudimos enviar el mensaje. Probá de nuevo o escribinos a info@bioenlace.io.',
                'error'
            );
        }
    } catch (err) {
        setContactFormStatus(
            'Error de conexión. Probá de nuevo o escribinos a info@bioenlace.io.',
            'error'
        );
    } finally {
        contactFormSubmit.disabled = false;
        contactFormSubmit.textContent = 'Enviar mensaje';
    }
});

document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const href = this.getAttribute('href');
        if (!href || href === '#') return;

        const target = document.querySelector(href);
        if (!target) return;

        e.preventDefault();
        const offsetTop = target.offsetTop - 72;
        window.scrollTo({ top: offsetTop, behavior: 'smooth' });
    });
});

const sections = document.querySelectorAll('section[id]');

function activateNavLink() {
    const scrollY = window.pageYOffset;

    sections.forEach(section => {
        const sectionTop = section.offsetTop - 100;
        const sectionBottom = sectionTop + section.offsetHeight;
        const sectionId = section.getAttribute('id');

        if (scrollY >= sectionTop && scrollY < sectionBottom) {
            navLinks.forEach(link => {
                link.classList.toggle('active', link.getAttribute('href') === `#${sectionId}`);
            });
        }
    });
}

window.addEventListener('scroll', activateNavLink);
