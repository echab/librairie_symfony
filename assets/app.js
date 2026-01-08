// @ts-check
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';
import './styles/genericons/genericons.css';

/** @param {HTMLElement} controller @param {boolean} [expanded] */
function toggleExpanded(controller, expanded) {
    const itemId = controller.getAttribute('aria-controls');
    if (itemId) {
        const item = document.getElementById(itemId);
        if (item) {
            if (expanded === undefined) {
                expanded = controller.getAttribute('aria-expanded') !== 'true';
            }
            controller.setAttribute('aria-expanded', String(expanded));
            item.setAttribute('aria-expanded', String(expanded));
        }
    }
}

/** @param {HTMLButtonElement} button */
function togglePassword(button) {
    if (button.form) {
        /** @type { NodeListOf<HTMLInputElement>} */
        const passInputs = button.form.querySelectorAll('input[type="password"]');
        if (passInputs.length) {
            passInputs.forEach((p) => {
                p.dataset.type = 'password';
                p.type = 'text';
            });
        } else {
            /** @type { NodeListOf<HTMLInputElement>} */
            const textInputs = button.form.querySelectorAll('input[type="text"][data-type="password"]');
            textInputs.forEach((p) => {
                p.type = 'password';
            });
        }
    }
}

addEventListener('DOMContentLoaded', () => {

    const queries = {
        phone: matchMedia('only screen and (max-width: 600px)'),
        tablet: matchMedia('only screen and (min-width: 600px)'),
        desktop: matchMedia('only screen and (min-width: 992px)'),
    };

    for (const [key, query] of Object.entries(queries)) {
        document.querySelectorAll(`.${key}-collapsed:not([aria-expanded])`).forEach((elem) => {
            elem.setAttribute('aria-expanded', String(!query.matches));
        })
        document.querySelectorAll(`.${key}-expanded:not([aria-expanded])`).forEach((elem) => {
            elem.setAttribute('aria-expanded', String(query.matches));
        });

        query.addEventListener('change', () => {
            document.querySelectorAll(`.${key}-collapsed`).forEach((elem) => {
                elem.setAttribute('aria-expanded', String(!query.matches));
            });
            document.querySelectorAll(`.${key}-expanded`).forEach((elem) => {
                elem.setAttribute('aria-expanded', String(query.matches));
            });
        });
    }

    // const controllers = document.querySelectorAll('.phone[aria-controls][aria-expanded=false]');
    // for (const elem of controllers) {
    //     toggleExpanded(elem, false);
    // }
});

// addEventListener('resize', (e) => {
//     console.log(`resizeHandler`, e);
// });

addEventListener('click', (e) => {
    const elem = /** @type {HTMLElement | null} */(e.target);
    if (!elem) { return; }

    if (elem.tagName === 'BUTTON' && elem.hasAttribute('aria-controls')) {
        toggleExpanded(elem);
    }

    if (elem.tagName === 'BUTTON' && elem.classList.contains('togglePassword')) {
        togglePassword(/** @type {HTMLButtonElement} */(elem));
    }

    // toogle image zoom
    if (elem.tagName === 'IMG' && elem.hasAttribute('data-src-big') && elem.hasAttribute('data-src-small')) {
        const img = /** @type {HTMLImageElement} */(elem);
        const big = img.classList.toggle('big');
        img.src = elem.getAttribute(big ? 'data-src-big' : 'data-src-small') ?? '';
    }

    // toggle item selection
    const elemSel = elem.closest('[aria-selected=false]');
    if (elemSel) {
        elemSel.parentElement?.querySelectorAll(`${elemSel.tagName}[aria-selected=true]`).forEach((e) => {
            e.setAttribute('aria-selected', 'false');
        });
        elemSel.setAttribute('aria-selected', 'true');
    } else {
        const toUnselect = elem.closest('[aria-selected=true][data-unselect]');
        if (toUnselect) {
            toUnselect.setAttribute('aria-selected', 'false');
        }
    }
});
