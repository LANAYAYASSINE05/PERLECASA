import { gsap } from 'gsap';
import { SplitText } from 'gsap/SplitText';
import { animate as animeAnimate, stagger as animeStagger, svg, utils } from 'animejs';
import { animate, hover, press } from 'motion';

gsap.registerPlugin(SplitText);

const racine = document.documentElement;
const dejaVus = new WeakSet();
const ressort = { type: 'spring', stiffness: 420, damping: 24 };

function reveler() {
    racine.classList.remove('pc-js-app');
    window.pcAnimationsPretes = true;
}

/* Une seule animation par élément, même si Livewire réinsère le bloc. */
function nouveaux(elements) {
    return [...elements].filter((el) => !dejaVus.has(el) && dejaVus.add(el));
}

/* GSAP : le titre de la page monte ligne par ligne, puis l'en-tête, les onglets et le contenu se posent. */
function entreePage() {
    const titre = document.querySelector('.fi-header-heading');
    const decoupe = titre ? SplitText.create(titre, { type: 'words,lines', mask: 'lines' }) : null;
    const blocs = nouveaux(document.querySelectorAll('.fi-page .fi-tabs, .fi-page .fi-ta-ctn, .fi-page .fi-wi-widget'));

    gsap.set(['.fi-header', ...blocs], { autoAlpha: 1 });

    gsap.timeline({ defaults: { ease: 'power3.out' } })
        .from('.fi-breadcrumbs', { autoAlpha: 0, x: -12, duration: 0.4 })
        .from(decoupe ? decoupe.lines : titre ?? [], { yPercent: 110, duration: 0.7, stagger: 0.08 }, 0.05)
        .from('.fi-header-subheading, .fi-header .fi-ac', { autoAlpha: 0, y: 8, duration: 0.45, stagger: 0.06 }, 0.25)
        .from(blocs, { autoAlpha: 0, y: 18, duration: 0.6, stagger: 0.08, clearProps: 'transform' }, 0.3);
}

/* GSAP : la barre latérale se déroule une fois par session, pas à chaque page. */
function barreLaterale() {
    if (sessionStorage.getItem('pc-barre-vue')) {
        return;
    }

    sessionStorage.setItem('pc-barre-vue', '1');

    gsap.from('.fi-sidebar-nav .fi-sidebar-group', {
        autoAlpha: 0,
        x: -16,
        duration: 0.45,
        stagger: 0.05,
        ease: 'power2.out',
        clearProps: 'all',
    });
}

/* GSAP : les montants des chiffres clés défilent jusqu'à leur valeur, au format « 1 234,56 MAD ». */
function formater(nombre, decimales) {
    const [entier, fraction] = nombre.toFixed(decimales).split('.');
    const groupes = entier.replace(/\B(?=(\d{3})+(?!\d))/g, ' ');

    return fraction ? `${groupes},${fraction}` : groupes;
}

function compteurs(elements) {
    nouveaux(elements).forEach((el) => {
        const correspondance = el.textContent.trim().match(/^(-?[\d\s\u00a0\u202f]+)(?:,(\d+))?(.*)$/);
        if (!correspondance) {
            return;
        }

        const [, entier, fraction = '', suffixe] = correspondance;
        const cible = parseFloat(`${entier.replace(/[\s\u00a0\u202f]/g, '')}.${fraction || 0}`);
        const valeur = { n: 0 };

        gsap.to(valeur, {
            n: cible,
            duration: 1.4,
            ease: 'power2.out',
            onUpdate: () => {
                el.textContent = formater(valeur.n, fraction.length) + suffixe;
            },
        });

        gsap.from(el.closest('.fi-wi-stats-overview-stat'), { autoAlpha: 0, y: 14, duration: 0.5, ease: 'power2.out', clearProps: 'transform' });
    });
}

/* anime.js : la façade du bandeau d'accueil se dessine, des murs aux fenêtres puis aux cotes. */
function facade() {
    const dessin = document.querySelector('.pc-facade');
    if (!dessin || dejaVus.has(dessin)) {
        return;
    }

    dejaVus.add(dessin);

    const murs = svg.createDrawable(dessin.querySelectorAll(':scope > path, :scope > rect:not(.pc-facade-fine)'));
    const fenetres = svg.createDrawable(dessin.querySelectorAll('.pc-facade-fine'));
    const cotes = svg.createDrawable(dessin.querySelectorAll('.pc-facade-cote path'));

    utils.set([...murs, ...fenetres, ...cotes], { draw: '0 0' });
    utils.set(dessin.querySelectorAll('text'), { opacity: 0 });

    animeAnimate(murs, { draw: ['0 0', '0 1'], duration: 900, delay: animeStagger(140), ease: 'inOutQuad' });
    animeAnimate(fenetres, { draw: ['0 0', '0 1'], duration: 380, delay: animeStagger(12, { start: 500, from: 'last' }), ease: 'outQuad' });
    animeAnimate(cotes, { draw: ['0 0', '0 1'], duration: 600, delay: animeStagger(120, { start: 1300 }), ease: 'outQuad' });
    animeAnimate(dessin.querySelectorAll('text'), { opacity: [0, 1], duration: 500, delay: animeStagger(100, { start: 1600 }) });
}

/* GSAP : le titre du bandeau d'accueil se dévoile mot par mot. */
function bandeau() {
    const titre = document.querySelector('.pc-planche h2');
    if (!titre || dejaVus.has(titre)) {
        return;
    }

    dejaVus.add(titre);
    const decoupe = SplitText.create(titre, { type: 'words', mask: 'words' });

    gsap.timeline({ defaults: { ease: 'power3.out' } })
        .from(decoupe.words, { yPercent: 110, duration: 0.7, stagger: 0.06 })
        .from('.pc-planche .pc-bouton-planche', { autoAlpha: 0, y: 10, duration: 0.4, stagger: 0.07, clearProps: 'transform' }, 0.3)
        .from('.pc-planche .pc-cartouche > div', { autoAlpha: 0, x: 14, duration: 0.4, stagger: 0.06 }, 0.4);
}

/* anime.js : les lignes d'un tableau arrivent en cascade (chargement, page suivante, onglet, filtre). */
function lignes(elements) {
    const arrivees = nouveaux(elements);
    if (arrivees.length === 0) {
        return;
    }

    animeAnimate(arrivees, {
        opacity: [0, 1],
        translateY: [10, 0],
        duration: 420,
        delay: animeStagger(28),
        ease: 'outCubic',
        onComplete: (anim) => anim.targets.forEach((ligne) => ligne.style.removeProperty('transform')),
    });
}

/* anime.js : le badge rouge des opérations de caisse non justifiées bat doucement pour attirer l'œil. */
function alerteCaisse() {
    nouveaux(document.querySelectorAll('.fi-sidebar-item .fi-badge.fi-color-danger')).forEach((badge) => {
        animeAnimate(badge, {
            scale: [1, 1.18, 1],
            duration: 900,
            delay: 1200,
            loop: true,
            loopDelay: 2600,
            ease: 'inOutSine',
        });
    });
}

/* Motion : ressort au survol et à l'appui sur les boutons, les cartes et les cases de pagination. */
function microInteractions() {
    press('.fi-main .fi-btn, .fi-main .fi-icon-btn, .fi-main .fi-link, .fi-pagination-item-button', (el) => {
        animate(el, { scale: 0.96 }, ressort);

        return () => animate(el, { scale: 1 }, ressort);
    });

    hover('.fi-wi-stats-overview-stat, .pc-carte, .fi-section:not(.fi-section-not-contained)', (carte) => {
        animate(carte, { y: -3 }, ressort);

        return () => animate(carte, { y: 0 }, ressort);
    });

    hover('.pc-bouton-planche', (bouton) => {
        animate(bouton, { x: -2, y: -2 }, ressort);

        return () => animate(bouton, { x: 0, y: 0 }, ressort);
    });
}

/* Motion : une notification arrive avec un ressort. */
function notification(elements) {
    nouveaux(elements).forEach((el) => {
        animate(el, { opacity: [0, 1], x: [40, 0], scale: [0.96, 1] }, { type: 'spring', stiffness: 380, damping: 26 });
    });
}

/* Les widgets chargés en différé et les mises à jour Livewire passent par ici. */
function surveiller() {
    let enAttente = false;

    new MutationObserver(() => {
        if (enAttente) {
            return;
        }

        enAttente = true;

        requestAnimationFrame(() => {
            enAttente = false;
            compteurs(document.querySelectorAll('.fi-wi-stats-overview-stat-value'));
            lignes(document.querySelectorAll('.fi-ta-row'));
            notification(document.querySelectorAll('.fi-no-notification'));
            facade();
            bandeau();
            alerteCaisse();
        });
    }).observe(document.body, { childList: true, subtree: true });
}

/*
 * Chargement des données : barre dorée en haut de l'écran pendant les requêtes Livewire et les changements de page,
 * tableau estompé pendant la recherche, les filtres, le tri et la pagination.
 */
const progression = (() => {
    /* Même barre que celle de partials/chargement, sinon on en crée une. */
    let barre = document.getElementById('pc-chargement-mini');

    if (!barre) {
        barre = document.createElement('div');
        barre.className = 'pc-progression';
        barre.setAttribute('aria-hidden', 'true');
        document.body.append(barre);
    }

    let actives = 0;
    let attente = null;

    const terminer = () => {
        clearTimeout(attente);
        racine.classList.remove('pc-navigation');

        if (barre.classList.contains('pc-progression-active')) {
            barre.classList.replace('pc-progression-active', 'pc-progression-fin');
        }
    };

    return {
        debut() {
            if (++actives > 1) {
                return;
            }

            /* Rien ne s'affiche pour les réponses rapides. */
            attente = setTimeout(() => {
                barre.className = 'pc-progression';
                void barre.offsetWidth;
                barre.classList.add('pc-progression-active');
            }, 120);
        },
        fin() {
            actives = Math.max(0, actives - 1);

            if (actives === 0) {
                terminer();
            }
        },
        annuler() {
            actives = 0;
            terminer();
        },
    };
})();

const DONNEES_TABLEAU = /^(table|paginators|gotoPage|nextPage|previousPage|setPage|sortTable|resetTable|removeTableFilter)/;

function chargementLivewire() {
    window.Livewire.hook('commit', ({ component, commit, respond, fail }) => {
        const appels = commit.calls.map((appel) => appel.method);
        const champs = Object.keys(commit.updates);

        /* Les interrogations périodiques (cloche, widgets) restent silencieuses. */
        if (champs.length === 0 && appels.every((methode) => methode === '$refresh')) {
            return;
        }

        progression.debut();

        const tableau = [...champs, ...appels].some((nom) => DONNEES_TABLEAU.test(nom))
            ? component.el.querySelector('.fi-ta-ctn')
            : null;
        const voile = tableau ? setTimeout(() => tableau.classList.add('pc-ta-maj'), 150) : null;
        let fini = false;

        const terminer = () => {
            if (fini) {
                return;
            }

            fini = true;
            clearTimeout(voile);
            tableau?.classList.remove('pc-ta-maj');
            progression.fin();
        };

        respond(terminer);
        fail(terminer);
    });
}

function chargementPages() {
    document.addEventListener('click', (evenement) => {
        const lien = evenement.target.closest('a[href]');

        if (!lien || evenement.defaultPrevented || evenement.button !== 0
            || evenement.metaKey || evenement.ctrlKey || evenement.shiftKey || evenement.altKey
            || (lien.target && lien.target !== '_self') || lien.hasAttribute('download')) {
            return;
        }

        const url = new URL(lien.href, window.location.href);

        /* Les pièces Excel et justificatives se téléchargent sans quitter la page. */
        if (url.origin !== window.location.origin
            || (url.pathname === window.location.pathname && url.search === window.location.search)
            || /\/(facture|recu|imprimer|piece)$/.test(url.pathname)) {
            return;
        }

        progression.debut();
        racine.classList.add('pc-navigation');
    });

    window.addEventListener('pageshow', (evenement) => {
        if (evenement.persisted) {
            progression.annuler();
        }
    });
}

chargementPages();

if (window.Livewire) {
    chargementLivewire();
} else {
    document.addEventListener('livewire:init', chargementLivewire, { once: true });
}

function demarrer() {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        reveler();

        return;
    }

    nouveaux(document.querySelectorAll('.fi-ta-row'));
    entreePage();
    reveler();
    barreLaterale();
    facade();
    bandeau();
    compteurs(document.querySelectorAll('.fi-wi-stats-overview-stat-value'));
    alerteCaisse();
    microInteractions();
    surveiller();
}

/* Les entrées de page attendent que l'écran de chargement (partials/chargement) se retire. */
function lancer() {
    (window.pcChargementFini ?? Promise.resolve()).then(demarrer);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', lancer);
} else {
    lancer();
}
