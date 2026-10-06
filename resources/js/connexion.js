import { gsap } from 'gsap';
import { SplitText } from 'gsap/SplitText';
import { animate as animeAnimate, createTimeline, stagger as animeStagger, svg, utils } from 'animejs';
import { animate, hover, press } from 'motion';

gsap.registerPlugin(SplitText);

const racine = document.documentElement;
const mouvementReduit = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function reveler() {
    racine.classList.remove('pc-js');
    window.pcAnimationsPretes = true;
}

/* GSAP : le cartouche se pose, le titre se dévoile, la flèche du nord trouve sa direction. */
function entree() {
    const titre = document.querySelector('.pc-brand-title');
    const decoupe = titre ? SplitText.create(titre, { type: 'words,lines', mask: 'lines' }) : null;

    gsap.set('[data-anim], [data-anim-form]', { autoAlpha: 1 });

    const tl = gsap.timeline({ defaults: { ease: 'power3.out' } });

    tl.from('.pc-carte-bord', { autoAlpha: 0, y: 30, duration: 0.9 })
        .from('.pc-form [data-anim-form]', { autoAlpha: 0, y: 12, duration: 0.5, stagger: 0.07, clearProps: 'transform' }, 0.2)
        .from('.pc-accroche-lieu', { autoAlpha: 0, x: -20, duration: 0.6 }, 0.3)
        .from(decoupe ? decoupe.lines : [], { yPercent: 110, duration: 0.8, stagger: 0.1 }, 0.4)
        .from('.pc-accroche-texte', { autoAlpha: 0, y: 12, duration: 0.6 }, 0.8)
        .from('.pc-nord', { autoAlpha: 0, rotation: -200, svgOrigin: '600 420', duration: 1.6, ease: 'elastic.out(1, 0.5)' }, 2.6);
}

/* GSAP : un viseur doré suit la souris sur le papier ; le plan se décale légèrement. */
function viseur() {
    const page = document.querySelector('.pc-login');
    if (!page || !window.matchMedia('(pointer: fine)').matches) {
        return;
    }

    const ligneY = gsap.quickTo('.pc-viseur-x', 'y', { duration: 0.35, ease: 'power3' });
    const ligneX = gsap.quickTo('.pc-viseur-y', 'x', { duration: 0.35, ease: 'power3' });
    const planX = gsap.quickTo('.pc-plan', 'x', { duration: 1.4, ease: 'power3' });
    const planY = gsap.quickTo('.pc-plan', 'y', { duration: 1.4, ease: 'power3' });

    gsap.set('.pc-viseur-x', { y: window.innerHeight / 2 });
    gsap.set('.pc-viseur-y', { x: window.innerWidth / 3 });

    page.addEventListener('pointermove', (e) => {
        ligneY(e.clientY);
        ligneX(e.clientX);
        planX((e.clientX / window.innerWidth - 0.5) * -12);
        planY((e.clientY / window.innerHeight - 0.5) * -8);
    });
}

/* anime.js : les murs se tracent, puis les cotes, les baies, les portes, le mobilier et les pièces. */
function plan() {
    const murs = svg.createDrawable('.pc-mur');
    const traits = svg.createDrawable('.pc-cote, .pc-porte, .pc-baie path, .pc-mobilier > *');

    utils.set([...murs, ...traits], { draw: '0 0' });
    utils.set('.pc-baie rect, .pc-piece, .pc-cote-texte', { opacity: 0 });

    createTimeline({ delay: 500 })
        .add(murs, { draw: ['0 0', '0 1'], duration: 900, delay: animeStagger(110), ease: 'inOutQuad' })
        .add('.pc-baie rect', { opacity: [0, 1], duration: 300, delay: animeStagger(60) }, '-=200')
        .add(traits, { draw: ['0 0', '0 1'], duration: 700, delay: animeStagger(35), ease: 'outQuad' }, '-=150')
        .add('.pc-cote-texte', { opacity: [0, 1], duration: 400 }, '-=500')
        .add('.pc-piece', { opacity: [0, 1], translateY: [8, 0], duration: 500, delay: animeStagger(90), ease: 'outBack(1.7)' }, '-=300');
}

/* Motion : ressort sur le bouton, secousse du cartouche quand la connexion échoue. */
function microInteractions() {
    const ressort = { type: 'spring', stiffness: 400, damping: 22 };

    hover('.pc-form .fi-btn', (bouton) => {
        animate(bouton, { x: -2, y: -2 }, ressort);

        return () => animate(bouton, { x: 0, y: 0 }, ressort);
    });

    press('.pc-form .fi-btn', (bouton) => {
        animate(bouton, { x: 3, y: 3 }, ressort);

        return () => animate(bouton, { x: 0, y: 0 }, ressort);
    });

    const carte = document.querySelector('.pc-carte-bord');
    if (!carte) {
        return;
    }

    let erreurAffichee = false;

    new MutationObserver(() => {
        const erreur = carte.querySelector('.fi-fo-field-wrp-error-message');

        if (erreur && !erreurAffichee) {
            animate(carte, { x: [0, -10, 9, -6, 4, 0] }, { duration: 0.45, ease: 'easeOut' });
        }

        erreurAffichee = Boolean(erreur);
    }).observe(carte, { childList: true, subtree: true });
}

/* Messages de saisie maison à la place des bulles du navigateur, qu'on ne peut pas mettre en forme. */
function validation() {
    const formulaire = document.querySelector('.pc-form form');
    const carte = document.querySelector('.pc-carte-bord');
    if (!formulaire || !carte) {
        return;
    }

    formulaire.noValidate = true;

    const message = (champ) => {
        if (champ.validity.typeMismatch) {
            return "Il manque une partie de l'adresse, par exemple nom@agence.ma.";
        }

        if (champ.type === 'email') {
            return 'Indiquez votre adresse e-mail.';
        }

        return champ.id.includes('password') ? 'Indiquez votre mot de passe.' : 'Ce champ est obligatoire.';
    };

    const retirer = (champ) => {
        const bloc = champ.closest('.fi-fo-field-wrp');
        bloc?.classList.remove('pc-invalide');
        bloc?.querySelector('.pc-bulle')?.remove();
        champ.removeAttribute('aria-invalid');
        champ.removeAttribute('aria-describedby');
    };

    const afficher = (champ) => {
        const bloc = champ.closest('.fi-fo-field-wrp');
        const cadre = champ.closest('.fi-input-wrp');
        if (!bloc || !cadre) {
            return;
        }

        retirer(champ);

        const bulle = document.createElement('p');
        bulle.className = 'pc-bulle';
        bulle.id = `${champ.id}-bulle`.replace('.', '-');
        bulle.setAttribute('role', 'alert');
        bulle.textContent = message(champ);

        bloc.classList.add('pc-invalide');
        cadre.after(bulle);
        champ.setAttribute('aria-invalid', 'true');
        champ.setAttribute('aria-describedby', bulle.id);

        if (!mouvementReduit) {
            animate(bulle, { opacity: [0, 1], y: [-6, 0] }, { type: 'spring', stiffness: 500, damping: 28 });
        }
    };

    carte.addEventListener('submit', (e) => {
        const invalides = [...formulaire.querySelectorAll('input[required]')].filter((champ) => {
            if (champ.checkValidity()) {
                retirer(champ);

                return false;
            }

            afficher(champ);

            return true;
        });

        if (invalides.length === 0) {
            return;
        }

        e.preventDefault();
        e.stopPropagation();
        invalides[0].focus();

        if (!mouvementReduit) {
            animate(carte, { x: [0, -10, 9, -6, 4, 0] }, { duration: 0.45, ease: 'easeOut' });
        }
    }, true);

    formulaire.addEventListener('input', (e) => {
        if (e.target.matches('input') && e.target.checkValidity()) {
            retirer(e.target);
        }
    });
}

function demarrer() {
    validation();

    if (mouvementReduit) {
        reveler();

        return;
    }

    entree();
    plan();
    reveler();
    viseur();
    microInteractions();
}

/* L'entrée attend que l'écran de chargement (partials/chargement) se retire. */
function lancer() {
    (window.pcChargementFini ?? Promise.resolve()).then(demarrer);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', lancer);
} else {
    lancer();
}
