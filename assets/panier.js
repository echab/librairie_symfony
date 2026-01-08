// @ts-check

import { createSignal, For, Show } from "solid-js";
import html from "solid-js/html";
import { createStorageSignal, formatPrix, toClipboard } from "./util.js";
/** @import { Livre, LivrePanier, EventHandler, HTMLElemEvent, StandardSchemaV1 } from "./types.ts" */

/** @type {StandardSchemaV1<LivrePanier[]>} */
export const validatePanier = {
    '~standard': {
        version: 1,
        vendor: 'ec',
        validate(value) {
            if (value && Array.isArray(value) && value.every(({ ean, titre, auteur, prix }) =>
                typeof ean === 'number'
                && typeof titre === 'string'
                && typeof auteur === 'string'
                && typeof prix === 'number'
            )) {
                return { value };
            } else {
                return { issues: [{ message: 'Panier invalide' }] };
            }
        }
    }
};

const [panier, setPanier] = createStorageSignal('panier', /** @type {LivrePanier[]} */([]), localStorage, validatePanier);

const voirPanier = () => {
    document.querySelector('form.panier')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
};

/** @param {LivrePanier} livre */
const livreQString = (livre) =>
    // `#${rayon ?? ''}-${ean}`
    `?mot=${livre.ean}&rayon=${livre.rayon ?? '0'}`;

// Pour les boutons "Ajouter au panier" de la page coups de cœur
document.addEventListener('click', (e) => {
    const elem = /** @type {HTMLElement | null} */(e.target);
    if (!elem) { return; }

    if (elem.tagName === 'BUTTON' && elem.classList.contains('addPanier')) {
        const { ean, titre, auteur, prix, rayon } = elem.dataset;
        const iean = parseInt(ean || '0', 10);
        if (!panier().find(({ ean }) => ean === iean)) {
            setPanier((p) => [...p, {
                ean: iean,
                titre: titre ?? '',
                auteur: auteur ?? '',
                prix: parseFloat(prix || '0'),
                rayon: parseInt(rayon || '0', 10),
                quantity: 1
            }]);
        }
        // TODO le bouton devient "Voir le panier" si le livre est déja ou arrive dans le panier.
    }
});

/** @param {{ livre: Livre }} props */
export function AddPanier(props) {
    const hasLivre = () => panier().find(({ ean }) => ean === props.livre.i);
    return html`
        <${Show} when=${() => !hasLivre()}
            fallback=${html`
                <button onClick=${voirPanier}>Voir le panier</button>
            `}
        >${() => html`
            <button onClick=${() => {
                if (!hasLivre()) {
                    setPanier((p) => [...p, {
                        ean: props.livre.i,
                        titre: props.livre.t,
                        auteur: props.livre.a ?? '',
                        prix: props.livre.p || 0,
                        rayon: props.livre.r,
                        quantity: 1
                    }]);
                }
            }}
            >Ajouter au panier</button>
        `}
        <//>
    `;
}

/** @param {{ routes: { livres: string, contact: string } }} props */
export function Panier(props) {

    const [feedback, setFeedback] = createSignal(/** @type {string | Node | Node[]} */(''));

    const mail = 'contact@librairie-lespassantes.fr';
    const messageFooter = '\r\n\r\nMon nom: \r\ntéléphone: ';

    const isPageLivres = location.pathname.indexOf(props.routes.livres) !== -1;

    /** @type {EventHandler<HTMLAnchorElement, HTMLElemEvent>} */
    const handleSelect = (e) => {
        const ean = e.target?.dataset.ean;
        const item = /** @type {HTMLElement?} */(document.querySelector(`article.livre[data-ean="${ean}"]`));
        if (item) {
            e.preventDefault();
            item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            item.click();
        }
    }

    const handleMail = () => {
        setFeedback('Ouverture de votre logiciel de messagerie...');
        const t = setTimeout(() => {
            //the browser did not respond
            setFeedback(html`<p>Cliquez sur <button onClick=${handleCopy}>Copier le panier</button> et coller son contenu dans un mail.</p>`);
        }, 3000);
        addEventListener('blur', () => {
            clearTimeout(t);
        });
    }

    /** @type {EventHandler<HTMLButtonElement, HTMLElemEvent>} */
    const handleCopy = (e) => {
        e.preventDefault();
        toClipboard(`A envoyer à ${mail}\n\n${panierText(panier())}${messageFooter}`)
            .then(() => {
                e.target.innerText = "Copié"
            });
    }

    return html`
    <form class="panier" action=${props.routes.contact} method="POST">
        <h3>Mon panier</h3>
        <ul>
            <${For} each=${() => panier()}>${/** @param {LivrePanier} livre */(livre) => html`
                <li>
                    <a href=${() => props.routes.livres + livreQString(livre)}
                        data-ean=${livre.ean}
                        onClick=${handleSelect}
                    >${() => livre.titre}<br/>${() => livre.auteur}</a>
                    <button
                        title="Enlever du panier"
                        class="ghost right"
                        onClick=${() => setPanier((p) => p.filter(({ ean }) => livre.ean !== ean))}
                    ><i class="genericon genericon-close" /></button>
                    <br/>
                    <span>${() => livre.quantity > 1 ? livre.quantity + ' x ' : ''}
                        ${() => formatPrix(livre.prix)}
                    </span>
                </li>
            `}
            <//>
        </ul>
        <${Show} when=${() => panier().length} fallback=${html`
            <p>Panier vide <a href=${props.routes.livres}>${!isPageLivres ? 'visitez nos rayons' : ''}</a></p>
        `}>${() => html`
            <div>
                ${() => panier().length ? ('Total de la commande: ' + formatPrix(panier().reduce(total, 0))) : ''}
                <button title="Vider le panier" class="ghost right" onClick=${() => setPanier([])}><i class="genericon genericon-trash" /></button>
            </div>
            <div style="display: flex; align-items: baseline; gap:1em;">
                <input name="commande" type="hidden" value=${() => panierText(panier())} />
                <button type="submit" title="Commandez en utilisant le formulaire Contact">Réserver en ligne</button>
                <i>ou</i>
                <a href=${() => `mailto:${mail}?subject=Commande%20client&body=${encodeURIComponent(panierText(panier()) + messageFooter)}`}
                    role="button"
                    type="submit"
                    rel="noopener noreferrer"
                    title="Commandez en utilisant votre logiciel de messagerie"
                    onClick=${handleMail}
                >par email</a>
                ${''/*
                <button onClick=${handleCopy}>Copier le panier</button>
                */}
            </div>
            <p>${() => feedback()}</p>
        `}
        <//>
    </form>
    `;
}

/**
 * @param {number} sum 
 * @param {LivrePanier} livre 
 * @returns number
 */
function total(sum, livre) {
    return sum + livre.prix * (livre.quantity ?? 1);
}

/** @param {LivrePanier[]} panier */
function panierText(panier) {
    return 'Mon panier de livres:\r\n\r\n'
        + panier.map(({ ean, titre, prix, quantity }) =>
            `${ean} - ${titre}\r\n${formatPrix(prix)} ${quantity > 1 ? ' x ' + quantity + ' exemplaires' : ''}`
        ).join('\r\n\r\n')
        + `\r\n\r\n--------\r\nTotal de la commande: ${formatPrix(panier.reduce(total, 0))}\r\n`;
}
